<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:roll_no', 'nullable', 'email'],
            'roll_no' => ['required_without:email', 'nullable', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ], [
            'email.required_without' => 'Enter your email address.',
            'roll_no.required_without' => 'Enter your roll number.',
        ]);

        $user = $this->resolveLoginUser($validated);

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        if ($user->is_active === false) {
            return response()->json([
                'message' => EnsureUserIsActive::SUSPENDED_MESSAGE,
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()->load('company'),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required_without:roll_no', 'nullable', 'email'],
            'roll_no' => ['required_without:email', 'nullable', 'string', 'max:30'],
        ], [
            'email.required_without' => 'Enter your email address.',
            'roll_no.required_without' => 'Enter your roll number.',
        ]);

        $email = $this->resolveResetEmail($validated);

        if ($email === null) {
            // Unknown roll number: same response as an unknown email so nothing can be enumerated.
            return response()->json([
                'message' => 'If the account exists, a password reset link has been sent.',
            ]);
        }

        try {
            $status = Password::sendResetLink([
                'email' => $email,
            ]);
        } catch (TransportExceptionInterface $exception) {
            Log::error('Password reset email transport failure.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Mail server is not configured correctly. Please contact support.',
            ], 503);
        } catch (Throwable $exception) {
            Log::error('Password reset email failed unexpectedly.', [
                'email' => $email,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to send reset link right now. Please try again.',
            ], 500);
        }

        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return response()->json([
                'message' => 'If the account exists, a password reset link has been sent.',
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Please wait before requesting another reset link.',
            ], 429);
        }

        return response()->json([
            'message' => 'Unable to send reset link right now. Please try again.',
        ], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->letters()->mixedCase()->numbers()],
        ]);

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
            'password_confirmation' => $request->input('password_confirmation'),
            'token' => $validated['token'],
        ];
        $setPassword = function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();
            // Whichever link was used, an outstanding invitation link stops working too.
            Password::broker('invites')->deleteToken($user);

            event(new PasswordReset($user));
        };

        $status = Password::reset($credentials, $setPassword);
        if ($status === Password::INVALID_TOKEN) {
            // Invitation links come from their own 7-day broker (QA F-011).
            $status = Password::broker('invites')->reset($credentials, $setPassword);
        }

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successful. You can now sign in with your new password.',
            ]);
        }

        return response()->json([
            'message' => 'The password reset link is invalid or has expired.',
        ], 422);
    }

    /**
     * Students sign in with their roll number; admins and companies with email.
     */
    private function resolveLoginUser(array $validated): ?User
    {
        if (! empty($validated['roll_no'])) {
            $rollNo = strtoupper(trim((string) $validated['roll_no']));

            return StudentProfile::query()
                ->where('roll_no', $rollNo)
                ->first()
                ?->user()
                ->with(['company', 'studentProfile'])
                ->first();
        }

        return User::with(['company', 'studentProfile'])->where('email', $validated['email'])->first();
    }

    /**
     * Resolve the address a reset link should go to. Returns null for an unknown roll number.
     */
    private function resolveResetEmail(array $validated): ?string
    {
        if (! empty($validated['roll_no'])) {
            $rollNo = strtoupper(trim((string) $validated['roll_no']));

            return StudentProfile::query()
                ->where('roll_no', $rollNo)
                ->value('institute_email');
        }

        return $validated['email'];
    }
}
