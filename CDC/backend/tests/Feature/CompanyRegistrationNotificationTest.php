<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

        // Verify the recruiter email in the database
        DB::table('recruiter_email_verifications')->insert([
            'email' => 'jane.hr@gmail.com',
            'token_hash' => hash('sha256', 'dummy-token'),
            'verified_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $payload = [
            'company_name' => 'Acme Corp',
            'website' => 'https://acme.example',
            'sector' => 'Technology',
            'company_logo' => UploadedFile::fake()->create('logo.png', 100, 'image/png'),
            'recruiter_name' => 'Jane HR',
            'recruiter_designation' => 'HR Manager',
            'hr_email' => 'jane.hr@gmail.com',
            'hr_phone' => '9876543210',
            'head_name' => 'Head Recruiter',
            'head_designation' => 'VP HR',
            'head_email' => 'head@acme.example',
            'head_mobile' => '9876543211',
            'poc1_name' => 'POC One',
            'poc1_designation' => 'Coordinator',
            'poc1_email' => 'poc1@acme.example',
            'poc1_mobile' => '9876543212',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
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
