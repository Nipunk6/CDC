<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_cannot_access_admin_routes(): void
    {
        $company = Company::create([
            'name' => 'Demo Co',
            'industry' => 'Technology',
            'website' => 'https://demo.example',
            'hr_name' => 'Demo HR',
            'hr_email' => 'demo.hr@example.com',
            'hr_phone' => '1234567890',
        ]);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'company_id' => $company->id,
        ]);

        Sanctum::actingAs($companyUser);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_user_cannot_access_company_routes(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/company/dashboard');

        $response->assertForbidden();
    }
}
