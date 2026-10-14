<?php

use App\Services\SettingsService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The Phase 3 setting groups (S22). Their default values live in SettingsService::DEFAULTS. */
    private const GROUPS = ['reminders.', 'mail.pool_strategy', 'rules.', 'login_logs.', 'mis.api.'];

    /**
     * Phase 3 S22: store the default of every new setting that has one, so the Settings page and the scheduled
     * commands read real rows. A key an admin has already set is left alone.
     */
    public function up(): void
    {
        $now = now();
        foreach ($this->defaults() as $key => $value) {
            DB::table('portal_settings')->insertOrIgnore([
                'key' => $key,
                'value' => json_encode($value),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('portal_settings')->whereIn('key', array_keys($this->defaults()))->delete();
        DB::table('portal_settings')->whereIn('key', SettingsService::SECRET_KEYS)->delete();
    }

    /** @return array<string, mixed> key => default, without the keys whose default is "not set" (null) */
    private function defaults(): array
    {
        return array_filter(
            SettingsService::DEFAULTS,
            fn (mixed $value, string $key) => $value !== null
                && collect(self::GROUPS)->contains(fn (string $group) => str_starts_with($key, $group)),
            ARRAY_FILTER_USE_BOTH
        );
    }
};
