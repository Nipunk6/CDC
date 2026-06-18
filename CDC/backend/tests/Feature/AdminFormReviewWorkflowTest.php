<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\EmailLog;
use App\Models\FormStatusHistory;
use App\Models\Jnf;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminFormReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_jnf_status_with_audit_and_notifications(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => null,
        ]);

        $company = Company::create([
            'name' => 'Test Company',
            'industry' => 'Technology',
            'website' => 'https://test-company.example',
            'hr_name' => 'HR Person',
            'hr_email' => 'hr@test-company.example',
            'hr_phone' => '9999999999',
        ]);

        $companyUser = User::factory()->create([
            'role' => 'company',
            'company_id' => $company->id,
            'email' => $company->hr_email,
        ]);

        $jnf = Jnf::create([
            'company_id' => $company->id,
            'job_title' => 'Software Engineer',
            'job_description' => 'Role details',
            'status' => 'submitted',
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/jnfs/{$jnf->id}/status", [
            'status' => 'accepted',
            'admin_remarks' => 'Looks good.',
        ]);

        $response->assertOk();
        $this->assertSame('accepted', $jnf->fresh()->status);
        $this->assertSame('Looks good.', $jnf->fresh()->admin_remarks);

        $this->assertDatabaseHas('form_status_histories', [
            'form_type' => Jnf::class,
            'form_id' => $jnf->id,
            'old_status' => 'submitted',
            'new_status' => 'accepted',
            'changed_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $companyUser->id,
            'title' => 'JNF Status Updated',
            'type' => 'success',
        ]);

        $this->assertDatabaseHas('email_logs', [
            'user_id' => $companyUser->id,
            'template' => 'form-status-changed',
        ]);
    }
}
