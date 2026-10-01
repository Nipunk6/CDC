<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Jnf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * M4: numeric backlog caps + 10th/12th cutoffs are stored in form_data and surface in the admin CSV.
 */
class EligibilityFormFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function company(): array
    {
        $company = Company::create([
            'name' => 'Acme',
            'hr_name' => 'HR',
            'hr_email' => 'hr@acme.example',
        ]);
        $user = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.example']);

        return [$company, $user];
    }

    private function formData(): array
    {
        return [
            'jobTitle' => 'SDE',
            'globalCgpa' => '7.0',
            'globalBacklogs' => true,
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'minTenthPercent' => '75',
            'minTwelfthPercent' => '70.5',
            'eligibility' => [[
                'programme' => 'B.Tech (4 Year) / B.Tech Double Major (5 Year) / B.Tech-M.Tech Dual Degree (5 Year)',
                'expanded' => false,
                'branches' => [
                    ['branch' => 'Mining Engineering', 'selected' => true, 'cgpa' => '6.5', 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '1', 'maxTotalBacklogs' => '2'],
                    ['branch' => 'Civil Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false],
                ],
            ]],
        ];
    }

    public function test_company_draft_keeps_new_keys_and_admin_csv_shows_them(): void
    {
        [, $companyUser] = $this->company();
        Sanctum::actingAs($companyUser);

        $response = $this->postJson('/api/company/jnfs', [
            'job_title' => 'SDE',
            'job_description' => 'Build things',
            'status' => 'draft',
            'form_data' => json_encode($this->formData()),
        ]);
        $response->assertCreated();

        $jnf = Jnf::sole();
        $this->assertSame('75', $jnf->form_data['minTenthPercent']);
        $this->assertSame('70.5', $jnf->form_data['minTwelfthPercent']);
        $this->assertSame('1', $jnf->form_data['eligibility'][0]['branches'][0]['maxOngoingBacklogs']);

        $jnf->update(['status' => 'accepted']);
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $csv = $this->get("/api/admin/jnfs/{$jnf->id}/csv")->assertOk()->streamedContent();

        $this->assertStringContainsString('min_tenth_percent', $csv);
        $this->assertStringContainsString('min_twelfth_percent', $csv);
        $this->assertStringContainsString('70.5', $csv);
        $this->assertStringContainsString('ongoing ≤ 1, total ≤ 2', $csv);
    }

    public function test_legacy_form_without_new_keys_still_exports(): void
    {
        [$company] = $this->company();
        $data = $this->formData();
        unset($data['minTenthPercent'], $data['minTwelfthPercent'], $data['eligibility'][0]['branches'][0]['maxOngoingBacklogs'], $data['eligibility'][0]['branches'][0]['maxTotalBacklogs']);

        $jnf = Jnf::create([
            'company_id' => $company->id,
            'job_title' => 'SDE',
            'job_description' => 'x',
            'status' => 'accepted',
            'form_data' => $data,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->get("/api/admin/jnfs/{$jnf->id}/csv")->assertOk();
        $this->getJson("/api/admin/jnfs/{$jnf->id}")->assertOk();
    }
}
