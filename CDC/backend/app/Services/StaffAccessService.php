<?php

namespace App\Services;

use App\Mail\StaffInvitationMail;
use App\Models\StaffRole;
use App\Models\StaffRolePermission;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Support\ProgrammeCatalogue;
use App\Support\StaffPermissions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

/**
 * What an admin may do. Super admins may do everything. Anyone else gets their role's levels, with any per-person
 * override replacing the role's level for that permission. A role that is turned off (or no role) grants nothing.
 *
 * Bound as a scoped singleton (AppServiceProvider): answers are cached for one request only, so a change made by a
 * super admin applies on the person's next request.
 */
class StaffAccessService
{
    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    public function __construct(private readonly MailDispatchService $mail)
    {
    }

    /** Forget cached answers (the middleware calls this at the start of every request). */
    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * @param  string|list<string>  $permission  a list means "any one of these"
     */
    public function can(User $user, string|array $permission, string $level = 'read'): bool
    {
        if ($user->role !== 'admin') {
            return false;
        }
        if ($user->is_super_admin || $permission === StaffPermissions::ANY) {
            return true;
        }
        if ($permission === StaffPermissions::SUPER) {
            return false;
        }

        $levels = $this->effective($user)['levels'];
        foreach ((array) $permission as $key) {
            if (StaffPermissions::rank($levels[$key] ?? 'none') >= StaffPermissions::rank($level)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The person's access, for the API and the Users page.
     *
     * @return array{super: bool, enabled: bool, role: ?array<string, mixed>, levels: array<string, string>, role_levels: array<string, string>, sources: array<string, string>}
     */
    public function effective(User $user): array
    {
        if (isset($this->cache[$user->id])) {
            return $this->cache[$user->id];
        }

        $keys = StaffPermissions::keys();
        if ($user->is_super_admin) {
            $all = array_combine($keys, array_map(fn ($k) => StaffPermissions::maxLevel($k), $keys));

            return $this->cache[$user->id] = [
                'super' => true, 'enabled' => true, 'role' => null, 'departments' => null,
                'levels' => $all, 'configured' => $all, 'role_levels' => $all, 'sources' => array_fill_keys($keys, 'super'),
            ];
        }

        // Read the role and its on/off switch from the database, not from a possibly older copy of the user.
        $state = User::query()->whereKey($user->id)->first(['staff_role_id', 'staff_role_enabled', 'staff_departments']);
        $role = $state?->staff_role_id ? StaffRole::query()->find($state->staff_role_id) : null;
        $roleLevels = $role ? $this->roleLevels($role) : array_fill_keys($keys, 'none');
        $overrides = UserPermissionOverride::query()->where('user_id', $user->id)->pluck('level', 'permission')->all();
        $enabled = $role !== null && $state->staff_role_enabled !== false;

        $levels = [];
        $sources = [];
        foreach ($keys as $key) {
            $fromRole = $roleLevels[$key] ?? 'none';
            if (array_key_exists($key, $overrides) && StaffPermissions::allows($key, $overrides[$key])) {
                $levels[$key] = $overrides[$key];
                $sources[$key] = match (true) {
                    StaffPermissions::rank($overrides[$key]) > StaffPermissions::rank($fromRole) => 'added',
                    StaffPermissions::rank($overrides[$key]) < StaffPermissions::rank($fromRole) => 'removed',
                    default => 'role',
                };
            } else {
                $levels[$key] = $fromRole;
                $sources[$key] = 'role';
            }
        }

        // What the person would have with the role switched on (the Users page edits this).
        $configured = $levels;
        if (! $enabled) {
            $levels = array_fill_keys($keys, 'none');
        }

        return $this->cache[$user->id] = [
            'super' => false,
            'enabled' => $enabled,
            'role' => $role ? $this->roleSummary($role) : null,
            'departments' => $state?->staff_departments ?: null,
            'levels' => $levels,
            'configured' => $configured,
            'role_levels' => $roleLevels,
            'sources' => $sources,
        ];
    }

    /** @return array<string, string> every catalogue key => level */
    public function roleLevels(StaffRole $role): array
    {
        $keys = StaffPermissions::keys();
        if ($role->isFullAccess()) {
            return array_combine($keys, array_map(fn ($k) => StaffPermissions::maxLevel($k), $keys));
        }

        $stored = $role->permissions()->pluck('level', 'permission')->all();
        $levels = [];
        foreach ($keys as $key) {
            $levels[$key] = isset($stored[$key]) && StaffPermissions::allows($key, $stored[$key]) ? $stored[$key] : 'none';
        }

        return $levels;
    }

    /**
     * Replace a role's levels (only levels above "none" are stored). Full Access has no stored levels.
     *
     * @param  array<string, string>  $levels
     */
    public function setRoleLevels(StaffRole $role, array $levels): void
    {
        if ($role->isFullAccess()) {
            return;
        }

        DB::transaction(function () use ($role, $levels) {
            $role->permissions()->delete();
            foreach ($this->clean($levels) as $key => $level) {
                if ($level !== 'none') {
                    StaffRolePermission::create(['staff_role_id' => $role->id, 'permission' => $key, 'level' => $level]);
                }
            }
        });
        $this->cache = [];
    }

    /**
     * Give a person a role (or none). Their per-person ticks and unticks are cleared: they were made against the old
     * role. The role is switched on.
     */
    public function assignRole(User $user, ?StaffRole $role): void
    {
        DB::transaction(function () use ($user, $role) {
            $user->forceFill(['staff_role_id' => $role?->id, 'staff_role_enabled' => true])->save();
            UserPermissionOverride::query()->where('user_id', $user->id)->delete();
        });
        unset($this->cache[$user->id]);
    }

    /**
     * Set the person's final levels. Where a level equals the role's, no override is kept.
     *
     * @param  array<string, string>  $levels  permission => wanted level (keys left out keep their current override)
     */
    public function setPersonLevels(User $user, array $levels, User $actor): void
    {
        $roleLevels = $user->staff_role_id ? $this->roleLevels(StaffRole::query()->findOrFail($user->staff_role_id)) : [];

        DB::transaction(function () use ($user, $levels, $roleLevels, $actor) {
            foreach ($this->clean($levels) as $key => $level) {
                if ($level === ($roleLevels[$key] ?? 'none')) {
                    UserPermissionOverride::query()->where('user_id', $user->id)->where('permission', $key)->delete();

                    continue;
                }
                UserPermissionOverride::query()->updateOrCreate(
                    ['user_id' => $user->id, 'permission' => $key],
                    ['level' => $level, 'set_by' => $actor->id]
                );
            }
        });
        unset($this->cache[$user->id]);
    }

    public function resetPerson(User $user): void
    {
        UserPermissionOverride::query()->where('user_id', $user->id)->delete();
        unset($this->cache[$user->id]);
    }

    public function setEnabled(User $user, bool $enabled): void
    {
        $user->forceFill(['staff_role_enabled' => $enabled])->save();
        unset($this->cache[$user->id]);
    }

    /**
     * Move a person's role to someone else: the other person gets the role (switched on), optionally with the first
     * person's ticks and unticks; the first person is left with no role and no overrides.
     */
    public function transfer(User $from, User $to, bool $copyOverrides, User $actor): void
    {
        DB::transaction(function () use ($from, $to, $copyOverrides, $actor) {
            $overrides = UserPermissionOverride::query()->where('user_id', $from->id)->get(['permission', 'level']);

            $to->forceFill(['staff_role_id' => $from->staff_role_id, 'staff_role_enabled' => true])->save();
            UserPermissionOverride::query()->where('user_id', $to->id)->delete();
            if ($copyOverrides) {
                foreach ($overrides as $override) {
                    UserPermissionOverride::create(['user_id' => $to->id, 'permission' => $override->permission, 'level' => $override->level, 'set_by' => $actor->id]);
                }
            }

            $from->forceFill(['staff_role_id' => null, 'staff_role_enabled' => true])->save();
            UserPermissionOverride::query()->where('user_id', $from->id)->delete();
        });
        $this->cache = [];
    }

    /**
     * Send (or resend) the staff invitation: the role, what it allows in plain words, and a 7-day set-password link
     * (the `invites` broker, as for students). Accepting it = setting the first password (AuthController).
     */
    public function invite(User $user, User $inviter): void
    {
        $role = $user->staff_role_id ? StaffRole::query()->find($user->staff_role_id) : null;
        $levels = $role ? $this->roleLevels($role) : [];
        $words = ['read' => 'Read', 'verify' => 'Read and verify', 'write' => 'Read and write'];

        $groups = [];
        foreach (StaffPermissions::CATALOGUE as $group => $permissions) {
            $items = [];
            foreach ($permissions as $key => [$label]) {
                if (($levels[$key] ?? 'none') !== 'none') {
                    $items[] = ['label' => $label, 'level' => $words[$levels[$key]]];
                }
            }
            if ($items !== []) {
                $groups[] = ['group' => $group, 'items' => $items];
            }
        }

        $now = now();
        $user->forceFill([
            'invited_at' => $user->invited_at ?? $now,
            'last_invited_at' => $now,
            'invite_count' => (int) $user->invite_count + 1,
        ])->save();

        $token = Password::broker('invites')->createToken($user);
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $query = http_build_query(['token' => $token, 'email' => $user->email]);

        $this->mail->send(
            $user,
            new StaffInvitationMail(
                name: $user->name,
                email: $user->email,
                invitedBy: $inviter->name,
                roleName: $role->name ?? 'No role yet',
                roleDescription: $role?->description,
                fullAccess: (bool) $role?->isFullAccess(),
                permissions: $groups,
                setPasswordUrl: "{$frontend}/auth/staff/set-password?{$query}",
                loginUrl: "{$frontend}/auth/login/admin",
            ),
            StaffInvitationMail::SUBJECT,
            'emails.staff-invitation'
        );
    }

    /**
     * The branches this person is limited to, or null for every department. Super admins are never limited.
     *
     * @return list<string>|null
     */
    public function departments(User $user): ?array
    {
        return $user->is_super_admin ? null : $this->effective($user)['departments'];
    }

    /**
     * Limit a student query (or a query on a model with a `studentProfile` relation) to the person's departments.
     */
    public function scopeStudents(Builder $query, ?User $user, ?string $relation = null): Builder
    {
        $departments = $user ? $this->departments($user) : null;
        if ($departments === null) {
            return $query;
        }

        return $relation
            ? $query->whereHas($relation, fn (Builder $q) => $q->whereIn('branch', $departments))
            : $query->whereIn('branch', $departments);
    }

    /** Whether the person may see this student at all (department limit). */
    public function seesStudent(User $user, ?\App\Models\StudentProfile $student): bool
    {
        $departments = $this->departments($user);

        return $departments === null || ($student !== null && in_array($student->branch, $departments, true));
    }

    /**
     * The departments a super admin can pick: every branch name in the catalogue (a branch offered by several
     * programmes is one department).
     *
     * @return list<string>
     */
    public function departmentOptions(): array
    {
        $names = [];
        foreach (ProgrammeCatalogue::all() as $branches) {
            foreach ($branches as $branch) {
                if (! str_starts_with($branch, 'All ')) {
                    $names[$branch] = true;
                }
            }
        }
        $names = array_keys($names);
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    /** @param  list<string>|null  $departments */
    public function setDepartments(User $user, ?array $departments): void
    {
        $user->forceFill(['staff_departments' => $departments ? array_values(array_unique($departments)) : null])->save();
        unset($this->cache[$user->id]);
    }

    /** One row of the Users list. @return array<string, mixed> */
    public function userPayload(User $user): array
    {
        $role = $user->staff_role_id ? StaffRole::query()->find($user->staff_role_id) : null;

        return $user->only(['id', 'name', 'first_name', 'middle_name', 'last_name', 'email', 'designation', 'mobile', 'alias', 'is_super_admin', 'is_active', 'created_at']) + [
            'staff_role' => $role ? $this->roleSummary($role) : null,
            'staff_role_enabled' => $user->staff_role_enabled !== false,
            'override_count' => UserPermissionOverride::query()->where('user_id', $user->id)->count(),
            'invitation_status' => $user->activated_at !== null || $user->is_super_admin ? 'accepted' : 'sent',
            'last_invited_at' => $user->last_invited_at?->toIso8601String(),
            'profile_completed' => $user->profile_completed_at !== null,
            'staff_departments' => $user->staff_departments ?: null,
        ];
    }

    /** @return array<string, mixed> */
    public function roleSummary(StaffRole $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'color' => $role->color,
            'is_system' => $role->is_system,
            'is_full_access' => $role->isFullAccess(),
        ];
    }

    /**
     * Keep known permissions with allowed levels only.
     *
     * @param  array<string, mixed>  $levels
     * @return array<string, string>
     */
    private function clean(array $levels): array
    {
        $clean = [];
        foreach ($levels as $key => $level) {
            if (is_string($key) && is_string($level) && in_array($key, StaffPermissions::keys(), true) && StaffPermissions::allows($key, $level)) {
                $clean[$key] = $level;
            }
        }

        return $clean;
    }
}
