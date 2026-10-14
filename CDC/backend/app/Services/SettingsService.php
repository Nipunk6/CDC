<?php

namespace App\Services;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    public const CACHE_TTL_SECONDS = 60;

    /** Defaults used when a key has never been written. */
    public const DEFAULTS = [
        'mail_mode' => 'queued',
        // P-1.2: most recipients (To + every BCC) portal mail may use per IST day; 0 = no cap. 1800 leaves headroom
        // under a typical 2,000/day mailbox limit; the admin sets the real value for the production mail account.
        'mail_daily_recipient_cap' => 1800,
        // Account (S8.1): the institute display name and the account logo ({path, mime} on the private disk).
        'institute_name' => null,
        'account_logo' => null,

        // ---- Phase 3 (S22). The S22 migration stores these; the values here are the same fallbacks. ----
        // Deadline reminders (N6, OD-5): in-app and email, one a day from N days before the deadline, one every K
        // hours in the last 24 hours, nothing between quiet_start and quiet_end (IST). Sent from P5 onwards.
        'reminders.enabled' => true,
        'reminders.channels' => ['app', 'email'],
        'reminders.daily_from_days' => 3,
        'reminders.final_hours_interval' => 6,
        'reminders.quiet_start' => '23:00',
        'reminders.quiet_end' => '07:00',
        // Sender pool (N8/N9, OD-3): `round_robin` across the sender mailboxes, or `group_list` = one message to a tag
        // group's list address when the audience is exactly that group (round robin otherwise). Used from P9 onwards.
        // The pool's total daily cap is the existing `mail_daily_recipient_cap` (P3-D33): one cap, one key.
        'mail.pool_strategy' => 'round_robin',
        // Rules (OD-13, OD-14), enforced from P3 onwards.
        'rules.one_intern_one_placement' => true,
        'rules.ppo_decline_exclusion' => true,
        // Login log (N4, OD-19): rows older than this are purged daily.
        'login_logs.retention_days' => 180,
        // MIS API (N16, OD-1): the connection is stored but nothing calls it until the institute supplies the API
        // documentation. `mis.api.secret` is kept encrypted and is never part of all().
        'mis.api.enabled' => false,
        'mis.api.base_url' => null,
        'mis.api.auth_type' => 'none',
        'mis.api.field_map' => [],
    ];

    /** Keys stored encrypted (setSecret / getSecret); never returned by all(). */
    public const SECRET_KEYS = ['mis.api.secret'];

    public function __construct(private readonly AuditService $audit)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $default ??= self::DEFAULTS[$key] ?? null;

        return Cache::remember($this->cacheKey($key), self::CACHE_TTL_SECONDS, function () use ($key, $default) {
            $setting = PortalSetting::query()->where('key', $key)->first();

            return $setting ? $setting->value : $default;
        });
    }

    /** `$action` names the audit row (default `setting.update`); the Account tab uses its own `settings.*` names. */
    public function set(string $key, mixed $value, User $admin, string $action = 'setting.update'): void
    {
        $setting = PortalSetting::query()->firstOrNew(['key' => $key]);
        $before = $setting->exists ? $setting->value : null;

        // `value` is NOT NULL: clearing a key removes its row, so it falls back to the default.
        if ($value === null) {
            if ($setting->exists) {
                $setting->delete();
            }
        } else {
            $setting->value = $value;
            $setting->save();
        }

        Cache::forget($this->cacheKey($key));

        $this->audit->logAs(
            actor: $admin,
            ip: request()?->ip(),
            action: $action,
            subject: $setting,
            before: ['key' => $key, 'value' => $before],
            after: ['key' => $key, 'value' => $value],
        );
    }

    /** @return array<string, mixed> every stored key merged over the defaults (secrets left out) */
    public function all(): array
    {
        $stored = PortalSetting::query()->whereNotIn('key', self::SECRET_KEYS)->pluck('value', 'key')->all();

        return array_merge(self::DEFAULTS, $stored);
    }

    /** A secret setting in clear text, for server-side use only. Null when none is stored. */
    public function getSecret(string $key): ?string
    {
        $stored = PortalSetting::query()->where('key', $key)->first()?->value;

        return is_array($stored) && isset($stored['encrypted']) ? Crypt::decryptString($stored['encrypted']) : null;
    }

    /** Store (or, with null, remove) a secret. It is encrypted at rest and the audit row never carries the value. */
    public function setSecret(string $key, ?string $plain, User $admin): void
    {
        $setting = PortalSetting::query()->firstOrNew(['key' => $key]);
        $had = $setting->exists;

        if ($plain === null || $plain === '') {
            if ($had) {
                $setting->delete();
            }
        } else {
            $setting->value = ['encrypted' => Crypt::encryptString($plain)];
            $setting->save();
        }

        $this->audit->logAs(
            actor: $admin,
            ip: request()?->ip(),
            action: 'setting.secret_update',
            subject: $setting,
            before: ['key' => $key, 'value' => $had ? '(set)' : '(none)'],
            after: ['key' => $key, 'value' => $plain === null || $plain === '' ? '(none)' : '(set)'],
        );
    }

    public function hasSecret(string $key): bool
    {
        return PortalSetting::query()->where('key', $key)->exists();
    }

    private function cacheKey(string $key): string
    {
        return "portal_settings.{$key}";
    }
}
