<?php

namespace App\Http\Controllers;

use App\Models\StaffRole;
use App\Models\User;
use App\Services\AuditService;
use App\Services\StaffAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminManagementController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly StaffAccessService $access
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $admins = User::where('role', 'admin')->orderByDesc('is_super_admin')->orderBy('name')->get();

        return response()->json([
            'admins' => $admins->map(fn (User $u) => $this->access->userPayload($u))->values(),
        ]);
    }

    /**
     * Add User (staff roles): the super admin enters only the name, email and role. The person gets an invitation
     * mail describing the role, sets a password from it and then fills in their own details (profile_completed_at).
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'staff_role_id' => ['required', 'integer', Rule::exists('staff_roles', 'id')],
        ], [
            'staff_role_id.required' => 'Choose a role for this person.',
        ]);

        $name = trim($validated['name']);
        $words = preg_split('/\s+/', $name) ?: [$name];
        $role = StaffRole::query()->findOrFail($validated['staff_role_id']);

        $user = User::create([
            'name' => $name,
            'first_name' => $words[0],
            'last_name' => count($words) > 1 ? implode(' ', array_slice($words, 1)) : null,
            'email' => strtolower($validated['email']),
            'password' => Str::random(40),
            'role' => 'admin',
            'is_super_admin' => false,
            'staff_role_id' => $role->id,
            'staff_role_enabled' => true,
        ]);

        $this->access->invite($user, $request->user());
        $this->audit->log($request, 'admin.create', $user, null, $user->only(['name', 'email', 'role']) + ['staff_role' => $role->name]);

        return response()->json([
            'message' => sprintf('%s added with the role %s. An invitation describing the role has been sent to %s.', $user->name, $role->name, $user->email),
            'user' => $this->access->userPayload($user->fresh()),
        ], 201);
    }

    /** Resend the staff invitation (a new set-password link) while the person has not set a password yet. */
    public function resendInvitation(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($user->role !== 'admin' || $user->is_super_admin) {
            return response()->json(['message' => 'Only invited staff can be sent an invitation.'], 422);
        }
        if ($user->activated_at !== null) {
            return response()->json(['message' => 'This person has already set a password.'], 422);
        }

        $this->access->invite($user, $request->user());
        $this->audit->log($request, 'admin.invite_resend', $user, null, ['email' => $user->email, 'invite_count' => (int) $user->fresh()->invite_count]);

        return response()->json(['message' => 'Invitation sent again.', 'user' => $this->access->userPayload($user->fresh())]);
    }

    /**
     * Edit User (S8.2): name parts, designation, mobile with country code and alias. The Email ID is the login
     * and stays read-only (an `email` in the request is ignored). `name` is rebuilt from the parts so every
     * screen that shows it stays in step.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Only admin users can be edited here.'], 422);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:150'],
            // "+<country code> <number>", e.g. "+91 9876543210".
            'mobile' => ['nullable', 'string', 'max:25', 'regex:/^\+\d{1,4} \d{4,14}$/'],
            'alias' => ['nullable', 'string', 'max:100'],
        ], [
            'mobile.regex' => 'Enter the mobile number as digits after the country code.',
        ]);

        // Only the keys sent change; blank strings clear a field.
        $fields = array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, $validated);
        $parts = $fields + $user->only(['first_name', 'middle_name', 'last_name']);
        $fields['name'] = implode(' ', array_filter([$parts['first_name'], $parts['middle_name'], $parts['last_name']]));

        $tracked = ['name', 'first_name', 'middle_name', 'last_name', 'designation', 'mobile', 'alias'];
        $before = $user->only($tracked);
        $user->fill($fields);

        if (! $user->isDirty()) {
            return response()->json(['message' => 'Nothing changed.', 'user' => $this->access->userPayload($user)]);
        }

        $changed = array_keys($user->getDirty());
        $user->save();
        $this->audit->log($request, 'admin.update', $user, array_intersect_key($before, array_flip($changed)), $user->only($changed));

        return response()->json([
            'message' => 'User updated.',
            'user' => $this->access->userPayload($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Cannot delete non-admin user.'], 422);
        }

        if ($user->is_super_admin) {
            return response()->json(['message' => 'Cannot delete a super admin.'], 403);
        }

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delete yourself.'], 403);
        }

        $user->delete();
        $this->audit->log($request, 'admin.delete', $user, $user->only(['name', 'email', 'role']), null);

        return response()->json([
            'message' => 'Admin deleted successfully.',
        ]);
    }
}
