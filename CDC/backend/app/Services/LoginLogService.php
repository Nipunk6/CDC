<?php

namespace App\Services;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Throwable;

/**
 * The login log (Phase 3 N4, OD-19): one row for every login attempt of every role.
 *
 * The address is `$request->ip()`, which is the client address forwarded by the Next.js server when its signature is
 * valid (SEC-008, TrustSignedClientIp) and the connection's own address otherwise; `ip_source` says which, so a
 * forwarded address that failed the signature check is flagged, never trusted. The submitted password is never
 * stored. When no account matches, only a hash of the typed identifier is kept.
 */
class LoginLogService
{
    public const REASON_UNKNOWN_ACCOUNT = 'unknown_account';

    public const REASON_WRONG_PASSWORD = 'wrong_password';

    public const REASON_SUSPENDED = 'suspended';

    public const REASON_THROTTLED = 'throttled';

    /** Header in which the Next.js server passes on the browser's User-Agent (it makes the login call itself). */
    public const HEADER_USER_AGENT = 'X-CDC-Client-User-Agent';

    public function record(Request $request, ?User $user, string $identifier, bool $success, ?string $reason = null): void
    {
        try {
            $source = (string) $request->attributes->get('client_ip_source', 'direct');
            $typed = mb_strtolower(trim($identifier));

            LoginLog::create([
                'user_id' => $user?->id,
                'identifier' => $user
                    ? mb_substr(str_contains($typed, '@') ? $typed : mb_strtoupper($typed), 0, 255)
                    : LoginLog::HASH_PREFIX.hash('sha256', $typed),
                'role' => $user?->role,
                'ip' => mb_substr((string) $request->ip(), 0, 45),
                'ip_source' => in_array($source, ['signed', 'direct', 'invalid_signature'], true) ? $source : 'direct',
                // The forwarded browser name is believed only together with the signed address.
                'user_agent' => mb_substr((string) ($source === 'signed'
                    ? ($request->headers->get(self::HEADER_USER_AGENT) ?? $request->userAgent())
                    : $request->userAgent()), 0, 255) ?: null,
                'success' => $success,
                'failure_reason' => $success ? null : $reason,
            ]);
        } catch (Throwable $exception) {
            // A logging fault must never stop someone signing in.
            report($exception);
        }
    }

    /**
     * The two most recent successful logins of a user: [latest, the one before], each {at, ip} or null.
     *
     * @return array{0: ?array{at: string, ip: string}, 1: ?array{at: string, ip: string}}
     */
    public function lastTwo(User $user): array
    {
        $rows = LoginLog::query()
            ->where('user_id', $user->id)
            ->where('success', true)
            ->latest('id')
            ->limit(2)
            ->get(['created_at', 'ip'])
            ->map(fn (LoginLog $row) => ['at' => $row->created_at->toIso8601String(), 'ip' => $row->ip])
            ->all();

        return [$rows[0] ?? null, $rows[1] ?? null];
    }
}
