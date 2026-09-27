<?php

namespace Database\Seeders;

use App\Models\PortalSetting;
use Illuminate\Database\Seeder;

class PortalSettingSeeder extends Seeder
{
    public function run(): void
    {
        PortalSetting::query()->firstOrCreate(
            ['key' => 'mail_mode'],
            ['value' => 'queued'],
        );
    }
}
