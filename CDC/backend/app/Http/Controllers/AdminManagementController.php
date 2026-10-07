<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminManagementController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $admins = User::where('role', 'admin')->get();

        return response()->json([
            'admins' => $admins,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->is_super_admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
        ]);

        $password = Str::random(16);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $password,
            'role' => 'admin',
            'is_super_admin' => false,
        ]);

        Password::sendResetLink([
            'email' => $user->email,
        ]);
        $this->audit->log($request, 'admin.create', $user, null, $user->only(['name', 'email', 'role']));

        return response()->json([
            'message' => 'Admin created successfully. An invitation has been sent to their email.',
            'user' => $user,
        ], 201);
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
            return response()->json(['message' => 'Nothing changed.', 'user' => $user]);
        }

        $changed = array_keys($user->getDirty());
        $user->save();
        $this->audit->log($request, 'admin.update', $user, array_intersect_key($before, array_flip($changed)), $user->only($changed));

        return response()->json([
            'message' => 'User updated.',
            'user' => $user->fresh(),
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
