<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AdminManagementController extends Controller
{
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

        return response()->json([
            'message' => 'Admin created successfully. An invitation has been sent to their email.',
            'user' => $user,
        ], 201);
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

        return response()->json([
            'message' => 'Admin deleted successfully.',
        ]);
    }
}
