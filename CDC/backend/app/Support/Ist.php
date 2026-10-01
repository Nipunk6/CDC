<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The CDC works in India Standard Time (owner decision, QA F-005): a date-time without an offset is IST wall-clock
 * time, one with an offset keeps its instant. Either way it is returned in the app timezone (UTC) — the zone
 * Eloquent writes to the database.
 */
final class Ist
{
    public const TZ = 'Asia/Kolkata';

    public static function parse(string $value): Carbon
    {
        return Carbon::parse($value, self::TZ)->setTimezone(config('app.timezone'));
    }

    public static function parseOrNull(?string $value): ?Carbon
    {
        return $value === null || $value === '' ? null : self::parse($value);
    }
}
