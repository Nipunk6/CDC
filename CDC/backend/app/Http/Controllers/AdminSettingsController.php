<?php

namespace App\Http\Controllers;

use App\Services\MailQuotaService;
use App\Services\SettingsService;
use App\Support\Branding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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

        // ---- Phase 3 (S22). Dotted keys arrive nested: {"reminders": {"enabled": true}}. ----
        'reminders.enabled' => ['required', 'boolean'],
        'reminders.channels' => ['present', 'array', 'max:2'],
        'reminders.daily_from_days' => ['required', 'integer', 'min:0', 'max:30'],
        'reminders.final_hours_interval' => ['required', 'integer', 'min:1', 'max:24'],
        'reminders.quiet_start' => ['required', 'date_format:H:i'],
        'reminders.quiet_end' => ['required', 'date_format:H:i'],
        'mail.pool_strategy' => ['required', 'in:round_robin,group_list'],
        'rules.one_intern_one_placement' => ['required', 'boolean'],
        'rules.ppo_decline_exclusion' => ['required', 'boolean'],
        'login_logs.retention_days' => ['required', 'integer', 'min:30', 'max:3650'],
        'mis.api.enabled' => ['required', 'boolean'],
        'mis.api.base_url' => ['nullable', 'string', 'max:255', 'url:https'],
        'mis.api.auth_type' => ['required', 'in:none,bearer,basic'],
        'mis.api.field_map' => ['present', 'array', 'max:100'],
    ];

    /** Rules for the items of the list settings above. */
    private const ITEM_RULES = [
        'reminders.channels.*' => ['string', 'distinct', 'in:app,email'],
    ];

    /** Keys only a super admin may change: they decide where student data and the API secret are sent. */
    private const SUPER_ONLY_PREFIX = 'mis.api.';

    private const MIS_SECRET = 'mis.api.secret';

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
        return response()->json($this->payload() + ['editable' => array_keys(self::EDITABLE)]);
    }

    /** @return array<string, mixed> what both the read and the save answer with */
    private function payload(): array
    {
        return [
            'settings' => $this->publicSettings(),
            'branding' => Branding::payload(),
            'mail_quota' => $this->quota->usage(),
            // The secret itself is write-only; the page only learns whether one is stored.
            'mis_api_secret_set' => $this->settings->hasSecret(self::MIS_SECRET),
        ];
    }

    public function update(Request $request): JsonResponse
    {
        $rules = array_map(fn (array $rules) => array_merge(['sometimes'], $rules), self::EDITABLE)
            + self::ITEM_RULES
            + [self::MIS_SECRET => ['sometimes', 'nullable', 'string', 'max:2000']];
        $validated = $request->validate($rules);

        /** @var array<string, mixed> $changes setting key => new value, for the keys that were sent */
        $changes = [];
        foreach (array_keys(self::EDITABLE) as $key) {
            if (Arr::has($validated, $key)) {
                $changes[$key] = $this->normalise($key, Arr::get($validated, $key));
            }
        }
        $secretSent = Arr::has($validated, self::MIS_SECRET);

        if ($changes === [] && ! $secretSent) {
            return response()->json(['message' => 'Nothing to update.'], 422);
        }

        $touchesMisApi = $secretSent || collect($changes)->keys()->contains(fn (string $key) => str_starts_with($key, self::SUPER_ONLY_PREFIX));
        if ($touchesMisApi && ! $request->user()->is_super_admin) {
            return response()->json(['message' => 'Only a super admin can change the MIS API connection.'], 403);
        }

        if (array_key_exists('mis.api.field_map', $changes) && ($problem = $this->fieldMapProblem($changes['mis.api.field_map']))) {
            return response()->json(['message' => $problem, 'errors' => ['mis.api.field_map' => [$problem]]], 422);
        }

        foreach ($changes as $key => $value) {
            $current = $this->settings->get($key);
            // Maps are compared without regard to key order (MySQL's JSON type returns keys in its own order).
            $same = is_array($value) && is_array($current) && ! array_is_list($value) ? $current == $value : $current === $value;
            if (! $same) {
                $this->settings->set($key, $value, $request->user(), self::AUDIT_ACTIONS[$key] ?? 'setting.update');
            }
        }
        if ($secretSent) {
            $this->settings->setSecret(self::MIS_SECRET, Arr::get($validated, self::MIS_SECRET), $request->user());
        }

        return response()->json(['message' => 'Settings saved.'] + $this->payload());
    }

    /** Booleans and whole numbers are stored as such whatever form they were sent in; a blank name clears it. */
    private function normalise(string $key, mixed $value): mixed
    {
        $rules = self::EDITABLE[$key];

        return match (true) {
            $key === 'institute_name' => is_string($value) && trim($value) !== '' ? trim($value) : null,
            in_array('boolean', $rules, true) => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            in_array('integer', $rules, true) => (int) $value,
            $key === 'reminders.channels' => array_values($value),
            default => $value,
        };
    }

    /** The MIS field map is {"MIS field name": "portal field name"}; names are short plain strings. */
    private function fieldMapProblem(array $map): ?string
    {
        foreach ($map as $misField => $portalField) {
            if (! is_string($portalField) || trim($portalField) === '' || mb_strlen($portalField) > 100 || mb_strlen((string) $misField) > 100) {
                return 'The field map must pair each MIS field name with a portal field name (up to 100 characters each).';
            }
        }

        return null;
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
