<?php

namespace App\Http\Controllers;

use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /** Keys the Settings page may write, with their validation rules. Add a row here to add a setting. */
    private const EDITABLE = [
        'mail_mode' => ['required', 'in:queued,sync'],
    ];

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settings->all(),
            'editable' => array_keys(self::EDITABLE),
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

        foreach ($validated as $key => $value) {
            if ($this->settings->get($key) !== $value) {
                $this->settings->set($key, $value, $request->user());
            }
        }

        return response()->json([
            'message' => 'Settings saved.',
            'settings' => $this->settings->all(),
        ]);
    }
}
