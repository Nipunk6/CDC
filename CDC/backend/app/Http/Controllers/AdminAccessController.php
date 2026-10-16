<?php

namespace App\Http\Controllers;

use App\Models\StaffRole;
use App\Models\User;
use App\Models\UserPermissionOverride;
use App\Services\AuditService;
use App\Services\StaffAccessService;
use App\Support\StaffPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Staff roles and per-person access (Users & Roles page). Everything except `me` is super-admin only: the
 * `staff` middleware enforces that through the `super` rules for roles* and manage-admins*. Every change is audited.
 */
class AdminAccessController extends Controller
{
    private const COLORS = ['primary', 'secondary', 'success', 'info', 'warning', 'error', 'default'];

    public function __construct(
        private readonly StaffAccessService $access,
        private readonly AuditService $audit
    ) {
    }

    /** The signed-in admin's own access, and the catalogue, for the sidebar and page guards. */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'access' => $this->access->effective($user) + ['profile_completed' => $user->is_super_admin || $user->profile_completed_at !== null],
            'catalogue' => $this->catalogue(),
        ]);
    }

    /** The signed-in admin's own details (the "Complete your profile" form after an invitation). */
    public function profile(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->access->userPayload($request->user())]);
    }

    /**
     * An invited staff member fills in their own details; until then every other admin route is refused
     * (EnsureStaffPermission). Also usable later to keep the details up to date. The email stays read-only.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'designation' => ['required', 'string', 'max:150'],
            // "+<country code> <number>", e.g. "+91 9876543210".
            'mobile' => ['required', 'string', 'max:25', 'regex:/^\+\d{1,4} \d{4,14}$/'],
            'alias' => ['nullable', 'string', 'max:100'],
        ], [
            'mobile.regex' => 'Enter the mobile number as digits after the country code.',
        ]);

        $user = $request->user();
        $fields = array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, $validated);
        $fields['name'] = implode(' ', array_filter([$fields['first_name'], $fields['middle_name'] ?? null, $fields['last_name'] ?? null]));

        $tracked = ['name', 'first_name', 'middle_name', 'last_name', 'designation', 'mobile', 'alias'];
        $before = $user->only($tracked);
        $firstTime = $user->profile_completed_at === null;
        $user->fill($fields);
        if ($firstTime) {
            $user->profile_completed_at = now();
        }
        $changed = array_values(array_diff(array_keys($user->getDirty()), ['profile_completed_at']));
        $user->save();

        if ($changed !== [] || $firstTime) {
            $this->audit->log($request, $firstTime ? 'admin.profile_complete' : 'admin.profile_update', $user, array_intersect_key($before, array_flip($changed)), $user->only($changed));
        }

        return response()->json(['message' => $firstTime ? 'Thank you. Your profile is complete.' : 'Profile updated.', 'user' => $this->access->userPayload($user->fresh())]);
    }

    public function roles(): JsonResponse
    {
        $roles = StaffRole::query()->with(['users' => fn ($q) => $q->where('role', 'admin')->orderBy('name')])
            ->orderByDesc('is_system')->orderBy('name')->get();

        return response()->json([
            'roles' => $roles->map(fn (StaffRole $role) => $this->rolePayload($role))->values(),
            'catalogue' => $this->catalogue(),
            'departments' => $this->access->departmentOptions(),
        ]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $validated = $this->validateRole($request);
        $role = StaffRole::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? 'primary',
            'created_by' => $request->user()->id,
        ]);
        $this->access->setRoleLevels($role, $validated['levels'] ?? []);

        $this->audit->log($request, 'role.create', $role, null, ['name' => $role->name, 'levels' => $this->storedLevels($role)]);

        return response()->json(['message' => 'Role created.', 'role' => $this->rolePayload($role->fresh())], 201);
    }

    public function updateRole(Request $request, StaffRole $staffRole): JsonResponse
    {
        $validated = $this->validateRole($request, $staffRole);
        $before = ['name' => $staffRole->name, 'description' => $staffRole->description, 'color' => $staffRole->color, 'levels' => $this->storedLevels($staffRole)];

        // The built-in Full Access role keeps its name and always grants everything; only its description and colour change.
        $staffRole->fill(array_intersect_key($validated, array_flip($staffRole->is_system ? ['description', 'color'] : ['name', 'description', 'color'])))->save();
        if (array_key_exists('levels', $validated) && ! $staffRole->isFullAccess()) {
            $this->access->setRoleLevels($staffRole, $validated['levels'] ?? []);
        }

        $after = ['name' => $staffRole->name, 'description' => $staffRole->description, 'color' => $staffRole->color, 'levels' => $this->storedLevels($staffRole)];
        $this->audit->log($request, 'role.update', $staffRole, $before, $after);

        return response()->json([
            'message' => sprintf('Role saved. %d person(s) with this role get the change on their next click.', $staffRole->users()->count()),
            'role' => $this->rolePayload($staffRole->fresh()),
        ]);
    }

    public function destroyRole(Request $request, StaffRole $staffRole): JsonResponse
    {
        if ($staffRole->is_system) {
            return response()->json(['message' => 'The built-in role cannot be deleted.'], 422);
        }
        if (($count = $staffRole->users()->count()) > 0) {
            return response()->json(['message' => sprintf('%d person(s) still have this role. Give them another role first.', $count)], 422);
        }

        $before = ['name' => $staffRole->name, 'levels' => $this->storedLevels($staffRole)];
        $staffRole->delete();
        $this->audit->log($request, 'role.delete', $staffRole, $before, null);

        return response()->json(['message' => 'Role deleted.']);
    }

    public function duplicateRole(Request $request, StaffRole $staffRole): JsonResponse
    {
        $name = $this->uniqueName($staffRole->name.' (copy)');
        $copy = StaffRole::create([
            'name' => $name,
            'description' => $staffRole->description,
            'color' => $staffRole->color,
            'created_by' => $request->user()->id,
        ]);
        $this->access->setRoleLevels($copy, $this->access->roleLevels($staffRole));

        $this->audit->log($request, 'role.duplicate', $copy, null, ['from' => $staffRole->name, 'name' => $copy->name, 'levels' => $this->storedLevels($copy)]);

        return response()->json(['message' => 'Role duplicated.', 'role' => $this->rolePayload($copy->fresh())], 201);
    }

    public function userAccess(User $user): JsonResponse
    {
        abort_unless($user->role === 'admin', 404);

        return response()->json(['access' => $this->access->effective($user), 'catalogue' => $this->catalogue(), 'departments' => $this->access->departmentOptions()]);
    }

    /**
     * Tick or untick single permissions for one person: `levels` holds the wanted final level per permission;
     * where it matches the role, the person's override is removed. `reset: true` removes all their overrides.
     */
    public function updateUserAccess(Request $request, User $user): JsonResponse
    {
        if ($error = $this->guardTarget($request, $user)) {
            return $error;
        }
        if (! $user->staff_role_id) {
            return response()->json(['message' => 'Give this person a role first.'], 422);
        }

        $validated = $request->validate([
            'reset' => ['sometimes', 'boolean'],
            'levels' => ['required_without:reset', 'array'],
            'levels.*' => ['string', Rule::in(array_keys(StaffPermissions::LEVELS))],
        ]);
        foreach (array_keys($validated['levels'] ?? []) as $key) {
            if (! in_array($key, StaffPermissions::keys(), true) || ! StaffPermissions::allows($key, $validated['levels'][$key])) {
                return response()->json(['message' => 'Unknown permission or level: '.$key, 'errors' => ['levels' => ['Unknown permission or level: '.$key]]], 422);
            }
        }

        $before = $this->overrides($user);
        if (! empty($validated['reset'])) {
            $this->access->resetPerson($user);
        } else {
            $this->access->setPersonLevels($user, $validated['levels'], $request->user());
        }
        $after = $this->overrides($user);

        if ($before !== $after) {
            $this->audit->log($request, 'admin.access_update', $user, ['overrides' => $before], ['overrides' => $after]);
        }

        return response()->json([
            'message' => ! empty($validated['reset']) ? 'Access reset to the role.' : 'Access saved. It applies on their next click.',
            'access' => $this->access->effective($user->fresh()),
        ]);
    }

    public function assignRole(Request $request, User $user): JsonResponse
    {
        if ($error = $this->guardTarget($request, $user)) {
            return $error;
        }
        $validated = $request->validate(['staff_role_id' => ['present', 'nullable', 'integer', Rule::exists('staff_roles', 'id')]]);

        $before = ['role' => $user->staffRole?->name, 'overrides' => $this->overrides($user)];
        $role = $validated['staff_role_id'] ? StaffRole::query()->find($validated['staff_role_id']) : null;
        $this->access->assignRole($user, $role);
        $this->audit->log($request, 'admin.role_assign', $user, $before, ['role' => $role?->name, 'overrides' => []]);

        return response()->json([
            'message' => $role ? sprintf('%s now has the role %s.', $user->name, $role->name) : sprintf('%s no longer has a role and cannot open any admin page.', $user->name),
            'access' => $this->access->effective($user->fresh()),
            'user' => $this->access->userPayload($user->fresh()),
        ]);
    }

    public function roleStatus(Request $request, User $user): JsonResponse
    {
        if ($error = $this->guardTarget($request, $user)) {
            return $error;
        }
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        if (! $user->staff_role_id) {
            return response()->json(['message' => 'This person has no role to turn on or off.'], 422);
        }

        $before = (bool) $user->staff_role_enabled;
        $this->access->setEnabled($user, (bool) $validated['enabled']);
        if ($before !== (bool) $validated['enabled']) {
            $this->audit->log($request, 'admin.role_status', $user, ['enabled' => $before], ['enabled' => (bool) $validated['enabled']]);
        }

        return response()->json([
            'message' => $validated['enabled'] ? 'Role turned on.' : 'Role turned off. They can sign in but cannot open any admin page until you turn it on.',
            'access' => $this->access->effective($user->fresh()),
            'user' => $this->access->userPayload($user->fresh()),
        ]);
    }

    /** Limit a person to some departments (branch names), or clear the limit with null / []. */
    public function updateDepartments(Request $request, User $user): JsonResponse
    {
        if ($error = $this->guardTarget($request, $user)) {
            return $error;
        }
        $validated = $request->validate([
            'departments' => ['present', 'nullable', 'array', 'max:60'],
            'departments.*' => ['string', Rule::in($this->access->departmentOptions())],
        ]);

        $before = $user->staff_departments ?: null;
        $this->access->setDepartments($user, $validated['departments'] ?: null);
        $after = $user->fresh()->staff_departments ?: null;
        if ($before !== $after) {
            $this->audit->log($request, 'admin.departments_update', $user, ['departments' => $before], ['departments' => $after]);
        }

        return response()->json([
            'message' => $after ? sprintf('Limited to %d department(s).', count($after)) : 'Sees every department.',
            'access' => $this->access->effective($user->fresh()),
            'user' => $this->access->userPayload($user->fresh()),
        ]);
    }

    public function transferRole(Request $request, User $user): JsonResponse
    {
        if ($error = $this->guardTarget($request, $user)) {
            return $error;
        }
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'copy_overrides' => ['sometimes', 'boolean'],
        ]);
        if (! $user->staff_role_id) {
            return response()->json(['message' => 'This person has no role to move.'], 422);
        }

        $to = User::query()->findOrFail($validated['to_user_id']);
        if ($to->id === $user->id) {
            return response()->json(['message' => 'Choose a different person.', 'errors' => ['to_user_id' => ['Choose a different person.']]], 422);
        }
        if ($error = $this->guardTarget($request, $to)) {
            return $error;
        }

        $role = $user->staffRole;
        $before = ['from' => $user->email, 'to' => $to->email, 'to_role_before' => $to->staffRole?->name, 'role' => $role?->name];
        $this->access->transfer($user, $to, (bool) ($validated['copy_overrides'] ?? false), $request->user());
        $this->audit->log($request, 'admin.role_transfer', $user, $before, ['role' => $role?->name, 'now_with' => $to->email, 'copied_overrides' => (bool) ($validated['copy_overrides'] ?? false)]);

        return response()->json([
            'message' => sprintf('%s moved to %s. %s no longer has a role.', $role?->name, $to->name, $user->name),
            'user' => $this->access->userPayload($user->fresh()),
            'to_user' => $this->access->userPayload($to->fresh()),
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function catalogue(): array
    {
        $groups = [];
        foreach (StaffPermissions::CATALOGUE as $group => $permissions) {
            $groups[] = [
                'group' => $group,
                'permissions' => collect($permissions)->map(fn ($p, $key) => ['key' => $key, 'label' => $p[0], 'levels' => $p[1], 'description' => $p[2]])->values()->all(),
            ];
        }

        return $groups;
    }

    private function guardTarget(Request $request, User $user): ?JsonResponse
    {
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Only admin users have roles.'], 422);
        }
        if ($user->is_super_admin) {
            return response()->json(['message' => 'Super admins have every permission and no role.'], 422);
        }
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change your own access.'], 403);
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function validateRole(Request $request, ?StaffRole $role = null): array
    {
        $required = $role ? 'sometimes' : 'required';

        $validated = $request->validate([
            'name' => [$required, 'string', 'max:80', Rule::unique('staff_roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', Rule::in(self::COLORS)],
            'levels' => ['sometimes', 'array'],
            'levels.*' => ['string', Rule::in(array_keys(StaffPermissions::LEVELS))],
        ]);
        foreach (array_keys($validated['levels'] ?? []) as $key) {
            if (! in_array($key, StaffPermissions::keys(), true) || ! StaffPermissions::allows($key, $validated['levels'][$key])) {
                abort(response()->json(['message' => 'Unknown permission or level: '.$key, 'errors' => ['levels' => ['Unknown permission or level: '.$key]]], 422));
            }
        }
        if (isset($validated['name'])) {
            $validated['name'] = trim($validated['name']);
        }

        return $validated;
    }

    /** @return array<string, mixed> */
    private function rolePayload(StaffRole $role): array
    {
        $members = $role->relationLoaded('users') ? $role->users : $role->users()->where('role', 'admin')->orderBy('name')->get();

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->description,
            'color' => $role->color,
            'is_system' => $role->is_system,
            'is_full_access' => $role->isFullAccess(),
            'levels' => $this->access->roleLevels($role),
            'members' => $members->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'enabled' => (bool) $u->staff_role_enabled])->values(),
            'created_at' => $role->created_at,
        ];
    }

    /** @return array<string, string> */
    private function storedLevels(StaffRole $role): array
    {
        return $role->isFullAccess() ? ['*' => 'all'] : $role->permissions()->orderBy('permission')->pluck('level', 'permission')->all();
    }

    /** @return array<string, string> */
    private function overrides(User $user): array
    {
        return UserPermissionOverride::query()->where('user_id', $user->id)->orderBy('permission')->pluck('level', 'permission')->all();
    }

    private function uniqueName(string $name): string
    {
        $candidate = mb_substr($name, 0, 80);
        for ($i = 2; StaffRole::query()->where('name', $candidate)->exists(); $i++) {
            $candidate = mb_substr($name, 0, 74).' '.$i;
        }

        return $candidate;
    }
}
