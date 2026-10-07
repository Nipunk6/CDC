<?php

namespace App\Support;

use App\Services\SettingsService;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The Account tab's institute display name and account logo (Superset parity S8.1), read by the public
 * branding routes, the admin Settings API and the email layout. The logo file lives on the private disk and
 * is only ever served by `GET /api/branding/logo`.
 */
class Branding
{
    /** Raster types only: an SVG can carry script, so it is never accepted (D59). */
    public const LOGO_MIMES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public const LOGO_MAX_KB = 1024;

    public static function displayName(): ?string
    {
        $name = app(SettingsService::class)->get('institute_name');

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    /** @return array{path: string, mime: string}|null the stored logo, only when its file still exists */
    public static function logo(): ?array
    {
        $logo = app(SettingsService::class)->get('account_logo');
        if (! is_array($logo) || ! is_string($logo['path'] ?? null) || ! isset(self::LOGO_MIMES[$logo['mime'] ?? ''])) {
            return null;
        }

        return Storage::disk('local')->exists($logo['path']) ? ['path' => $logo['path'], 'mime' => $logo['mime']] : null;
    }

    /** @return array{display_name: string|null, has_logo: bool} */
    public static function payload(): array
    {
        return [
            'display_name' => self::displayName(),
            'has_logo' => self::logo() !== null,
        ];
    }

    /**
     * Absolute URL of the public logo route for emails, or null when no logo is set. The path name keeps the
     * URL stable for mail clients that cache images; a new upload gets a new file name, hence a new `v`.
     */
    public static function emailLogoUrl(): ?string
    {
        try {
            $logo = self::logo();
        } catch (Throwable) {
            return null; // a mail must never fail because the settings table is unreachable
        }

        return $logo ? url('/api/branding/logo').'?v='.substr(sha1($logo['path']), 0, 12) : null;
    }

    public static function emailDisplayName(): ?string
    {
        try {
            return self::displayName();
        } catch (Throwable) {
            return null;
        }
    }
}
