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

    public function set(string $key, mixed $value, User $admin): void
    {
        $setting = PortalSetting::query()->firstOrNew(['key' => $key]);
        $before = $setting->exists ? $setting->value : null;

        $setting->value = $value;
        $setting->save();

        Cache::forget($this->cacheKey($key));

        $this->audit->logAs(
            actor: $admin,
            ip: request()?->ip(),
            action: 'setting.update',
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
