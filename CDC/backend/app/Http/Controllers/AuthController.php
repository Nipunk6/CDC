<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\LoginThrottleService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private readonly LoginThrottleService $throttle)
    {
    }

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

        // Per-account backoff (SEC-008): checked before the password, so guesses during a backoff are never tried.
        $identifier = (string) ($validated['roll_no'] ?? $validated['email']);
        $wait = $this->throttle->retryAfter($identifier, (string) $request->ip());
        if ($wait !== null) {
            return response()->json([
                'message' => sprintf('Too many attempts. Try again in %d second%s.', $wait, $wait === 1 ? '' : 's'),
            ], 429)->header('Retry-After', (string) $wait);
        }

        $user = $this->resolveLoginUser($validated);

        // SEC-003: an unknown account costs one password check too, so the response time does not say whether the
        // email or roll number exists.
        if (! $user) {
            Hash::check($validated['password'], $this->dummyPasswordHash());
        }

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            $this->throttle->recordFailure($identifier, (string) $request->ip());

            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $this->throttle->recordSuccess($identifier, (string) $request->ip());

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

        // A revoked invitation (S5.4) must not be side-stepped through forgot-password: same response, no link.
        $revoked = $email !== null && User::query()
            ->where('email', $email)
            ->where('role', 'student')
            ->whereNull('activated_at')
            ->whereNotNull('invite_revoked_at')
            ->exists();

        if ($email === null || $revoked) {
            // Unknown roll number: same response as an unknown email so nothing can be enumerated.
            return response()->json([
                'message' => 'If the account exists, a password reset link has been sent.',
            ]);
        }

        // SEC-003: whatever happens next (sent, throttled by the broker, a mail fault), the answer is the same as for
        // an unknown account, so the response never says whether the account exists. Faults go to the log.
        try {
            $status = Password::sendResetLink([
                'email' => $email,
            ]);

            if (! in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER, Password::RESET_THROTTLED], true)) {
                Log::warning('Password reset link was not sent.', ['status' => $status]);
            }
        } catch (Throwable $exception) {
            Log::error('Password reset email failed.', [
                'transport' => $exception instanceof TransportExceptionInterface,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'If the account exists, a password reset link has been sent.',
        ]);
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
            ]);
            // S5: the first password a student sets accepts the invitation (status Accepted for good).
            if ($user->role === 'student' && $user->activated_at === null) {
                $user->forceFill(['activated_at' => now(), 'invite_revoked_at' => null]);
            }
            $user->save();

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
    /**
     * A real hash (made once at the current cost, then cached) of a value nobody knows, for the unknown-account path.
     */
    private function dummyPasswordHash(): string
    {
        $rounds = (int) config('hashing.bcrypt.rounds', 12);

        return Cache::rememberForever("auth.dummy_password_hash.{$rounds}", fn () => Hash::make(Str::random(40)));
    }

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
