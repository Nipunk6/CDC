<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            // CompanySeeder::class,
            // FormSeeder::class,
            PolicyDocumentSeeder::class,
            PortalSettingSeeder::class,
            // Phase2DemoSeeder::class, // demo cycles, 120 students, drives, offers — see its docblock for demo logins
        ]);
    }
}
