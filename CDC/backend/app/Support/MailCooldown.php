<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * SEC-010: at most one mail per address per purpose within a cooldown window, for endpoints anyone can call without
 * signing in (recruiter verification link, alumni outreach confirmation). `Cache::add` is atomic, so two simultaneous
 * requests cannot both pass. Keys hold a hash of the address, never the address itself.
 */
final class MailCooldown
{
    public const DEFAULT_SECONDS = 600;

    /** True when a mail may be sent now (and starts the cooldown); false while the address is cooling down. */
    public static function attempt(string $purpose, string $email, int $seconds = self::DEFAULT_SECONDS): bool
    {
        return Cache::add(self::key($purpose, $email), now()->getTimestamp() + $seconds, $seconds);
    }

    /** Seconds left before the next mail to this address is allowed (0 when none is pending). */
    public static function remaining(string $purpose, string $email): int
    {
        $until = (int) Cache::get(self::key($purpose, $email), 0);

        return max(0, $until - now()->getTimestamp());
    }

    /** Lift the cooldown, e.g. when the send failed and the user should be able to try again. */
    public static function release(string $purpose, string $email): void
    {
        Cache::forget(self::key($purpose, $email));
    }

    private static function key(string $purpose, string $email): string
    {
        return 'mail-cooldown:'.$purpose.':'.hash('sha256', strtolower(trim($email)));
    }
}
