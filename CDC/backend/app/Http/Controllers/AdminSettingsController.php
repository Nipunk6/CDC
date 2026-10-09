<?php

namespace App\Http\Controllers;

use App\Services\MailQuotaService;
use App\Services\SettingsService;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminSettingsController extends Controller
{
    /** Keys the Settings page may write, with their validation rules. Add a row here to add a setting. */
    private const EDITABLE = [
        'mail_mode' => ['required', 'in:queued,sync'],
        // P-1.2: recipients (To + BCC) portal mail may use per IST day; 0 = no cap.
        'mail_daily_recipient_cap' => ['required', 'integer', 'min:0', 'max:100000'],
        // Account tab (S8.1): blank clears it and the shells fall back to their built-in text.
        'institute_name' => ['nullable', 'string', 'max:150'],
    ];

    /** Audit action per key; keys not listed use `setting.update`. */
    private const AUDIT_ACTIONS = [
        'institute_name' => 'settings.institute_name_update',
    ];

    /** Stored keys the generic settings map leaves out (the logo is exposed only as `branding.has_logo`). */
    private const HIDDEN = ['account_logo'];

    public function __construct(
        private readonly SettingsService $settings,
        private readonly MailQuotaService $quota
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => $this->publicSettings(),
            'editable' => array_keys(self::EDITABLE),
            'branding' => Branding::payload(),
            'mail_quota' => $this->quota->usage(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate(array_map(
            fn (array $rules) => array_merge(['sometimes'], $rules),
            self::EDITABLE
        ));

        if ($validated === []) {
            return response()->json(['message' => 'Nothing to update.'], 422);
        }

        if (array_key_exists('institute_name', $validated)) {
            $validated['institute_name'] = is_string($validated['institute_name']) && trim($validated['institute_name']) !== ''
                ? trim($validated['institute_name'])
                : null;
        }

        foreach ($validated as $key => $value) {
            if ($this->settings->get($key) !== $value) {
                $this->settings->set($key, $value, $request->user(), self::AUDIT_ACTIONS[$key] ?? 'setting.update');
            }
        }

        return response()->json([
            'message' => 'Settings saved.',
            'settings' => $this->publicSettings(),
            'branding' => Branding::payload(),
            'mail_quota' => $this->quota->usage(),
        ]);
    }

    /**
     * Account logo upload (S8.1). PNG, JPEG or WebP up to 1 MB, checked by content (finfo), never SVG (D59).
     * The file goes on the private disk; only `GET /api/branding/logo` serves it.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => [
                'required',
                'file',
                'max:'.Branding::LOGO_MAX_KB,
                'mimes:png,jpg,jpeg,webp',
                'mimetypes:'.implode(',', array_keys(Branding::LOGO_MIMES)),
            ],
        ], [
            'logo.max' => 'The logo must be 1 MB or smaller.',
            'logo.mimes' => 'The logo must be a PNG, JPG or WebP image.',
            'logo.mimetypes' => 'The logo must be a PNG, JPG or WebP image.',
        ]);

        $file = $request->file('logo');
        $mime = (string) $file->getMimeType();
        // A real raster image, not just a matching magic number.
        if (! isset(Branding::LOGO_MIMES[$mime]) || @getimagesize($file->getRealPath()) === false) {
            return response()->json(['message' => 'The logo must be a PNG, JPG or WebP image.', 'errors' => ['logo' => ['The logo must be a PNG, JPG or WebP image.']]], 422);
        }

        $previous = Branding::logo();
        $path = $file->storeAs('branding', 'account-logo-'.Str::random(16).'.'.Branding::LOGO_MIMES[$mime], 'local');

        $this->settings->set('account_logo', ['path' => $path, 'mime' => $mime], $request->user(), 'settings.logo_update');

        if ($previous && $previous['path'] !== $path) {
            Storage::disk('local')->delete($previous['path']);
        }

        return response()->json([
            'message' => 'Account logo updated.',
            'branding' => Branding::payload(),
        ]);
    }

    /** Remove the account logo; the shells and emails go back to the built-in badge. */
    public function deleteLogo(Request $request): JsonResponse
    {
        $previous = Branding::logo();
        if (! $previous) {
            return response()->json(['message' => 'No account logo has been uploaded.'], 422);
        }

        $this->settings->set('account_logo', null, $request->user(), 'settings.logo_update');
        Storage::disk('local')->delete($previous['path']);

        return response()->json([
            'message' => 'Account logo removed.',
            'branding' => Branding::payload(),
        ]);
    }

    /** @return array<string, mixed> */
    private function publicSettings(): array
    {
        return array_diff_key($this->settings->all(), array_flip(self::HIDDEN));
    }
}
