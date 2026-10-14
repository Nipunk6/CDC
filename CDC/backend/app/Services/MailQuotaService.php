<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * P-1.2 daily mail-recipient cap (Settings → Mail, key `mail_daily_recipient_cap`; 0 = no cap).
 *
 * Every address on a message counts as one recipient: each BCC student and the portal's own To address. A message
 * reserves its recipients on the first IST day that still has room, so nothing is ever dropped: when today is full
 * the mail is queued for 00:05 IST on the next day with room. Rows are locked while reserving, so concurrent workers
 * cannot oversubscribe a day.
 */
class MailQuotaService
{
    public const TIMEZONE = 'Asia/Kolkata';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function cap(): int
    {
        return max(0, (int) $this->settings->get('mail_daily_recipient_cap'));
    }

    /**
     * Reserve `$recipients` and say when the message may go: null = now, otherwise the UTC time it is due.
     */
    public function reserve(int $recipients): ?Carbon
    {
        $recipients = max(1, $recipients);
        $cap = $this->cap();
        $today = now(self::TIMEZONE)->startOfDay();

        return DB::transaction(function () use ($recipients, $cap, $today): ?Carbon {
            for ($offset = 0; $offset <= 366; $offset++) {
                $day = $today->copy()->addDays($offset);
                $used = $this->lockedUsage($day->toDateString());

                // A message larger than the cap still goes, alone, on an empty day.
                if ($cap === 0 || $used + $recipients <= $cap || $used === 0) {
                    DB::table('mail_daily_usage')
                        ->where('usage_date', $day->toDateString())
                        ->update(['recipients' => $used + $recipients, 'updated_at' => now()]);

                    return $offset === 0 ? null : $day->copy()->setTime(0, 5)->utc();
                }
            }

            return $today->copy()->addDays(367)->setTime(0, 5)->utc();
        });
    }

    /** @return array{date: string, cap: int, used: int, remaining: int|null, deferred: int} */
    public function usage(): array
    {
        $today = now(self::TIMEZONE)->toDateString();
        $cap = $this->cap();
        $used = (int) DB::table('mail_daily_usage')->where('usage_date', $today)->value('recipients');

        return [
            'date' => $today,
            'cap' => $cap,
            'used' => $used,
            'remaining' => $cap === 0 ? null : max(0, $cap - $used),
            'deferred' => (int) DB::table('mail_daily_usage')->where('usage_date', '>', $today)->sum('recipients'),
        ];
    }

    private function lockedUsage(string $date): int
    {
        DB::table('mail_daily_usage')->insertOrIgnore([
            'usage_date' => $date,
            'recipients' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('mail_daily_usage')->where('usage_date', $date)->lockForUpdate()->value('recipients');
    }
}
