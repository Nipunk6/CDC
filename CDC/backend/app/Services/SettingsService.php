<?php

namespace App\Services;

use App\Models\PortalSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public const CACHE_TTL_SECONDS = 60;

    /** Defaults used when a key has never been written. */
    public const DEFAULTS = [
        'mail_mode' => 'queued',
        // Account (S8.1): the institute display name and the account logo ({path, mime} on the private disk).
        'institute_name' => null,
        'account_logo' => null,
    ];

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

    /** @return array<string, mixed> every stored key merged over the defaults */
    public function all(): array
    {
        $stored = PortalSetting::query()->pluck('value', 'key')->all();

        return array_merge(self::DEFAULTS, $stored);
    }

    private function cacheKey(string $key): string
    {
        return "portal_settings.{$key}";
    }
}
