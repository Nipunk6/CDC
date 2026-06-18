<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyRegistrationNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_registration_notifies_admins(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
        ]);

        $payload = [
            'company_name' => 'Acme Corp',
            'industry' => 'Technology',
            'website' => 'https://acme.example',
            'hr_name' => 'Jane HR',
            'hr_email' => 'jane.hr@gmail.com',
            'hr_phone' => '9876543210',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
        ];

        $response = $this->postJson('/api/auth/company/register', $payload);

        $response->assertCreated();

        $company = Company::where('name', 'Acme Corp')->first();
        $this->assertNotNull($company);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'New Company Registration',
            'type' => 'info',
        ]);

        $this->assertDatabaseHas('email_logs', [
            'user_id' => $admin->id,
            'template' => 'new-company-registration',
        ]);
    }
}
