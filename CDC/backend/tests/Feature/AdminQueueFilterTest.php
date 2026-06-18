<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminQueueFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_jnf_queue_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
        ]);

        $company = Company::create([
            'name' => 'Filter Co',
            'industry' => 'Technology',
            'website' => 'https://filter.example',
            'hr_name' => 'Filter HR',
            'hr_email' => 'filter.hr@example.com',
            'hr_phone' => '1234567890',
        ]);

        Jnf::create([
            'company_id' => $company->id,
            'job_title' => 'Accepted Job',
            'job_description' => 'Desc',
            'status' => 'accepted',
        ]);

        Jnf::create([
            'company_id' => $company->id,
            'job_title' => 'Rejected Job',
            'job_description' => 'Desc',
            'status' => 'rejected',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/jnfs?status=accepted');

        $response->assertOk();
        $response->assertJsonCount(1, 'jnfs');
        $response->assertJsonPath('jnfs.0.status', 'accepted');
    }

    public function test_admin_inf_queue_can_be_filtered_by_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
        ]);

        $company = Company::create([
            'name' => 'Filter Co 2',
            'industry' => 'Technology',
            'website' => 'https://filter2.example',
            'hr_name' => 'Filter HR 2',
            'hr_email' => 'filter2.hr@example.com',
            'hr_phone' => '1234567890',
        ]);

        Inf::create([
            'company_id' => $company->id,
            'internship_title' => 'Under Review Internship',
            'internship_description' => 'Desc',
            'status' => 'under_review',
        ]);

        Inf::create([
            'company_id' => $company->id,
            'internship_title' => 'Draft Internship',
            'internship_description' => 'Desc',
            'status' => 'draft',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/infs?status=under_review');

        $response->assertOk();
        $response->assertJsonCount(1, 'infs');
        $response->assertJsonPath('infs.0.status', 'under_review');
    }
}
