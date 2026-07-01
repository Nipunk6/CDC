<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL')],
            [
                'name' => env('ADMIN_NAME', 'CDC Admin'),
                'password' => env('ADMIN_PASSWORD'),
                'role' => 'admin',
                'is_super_admin' => true,
                'company_id' => null,
            ]
        );
    }
}
