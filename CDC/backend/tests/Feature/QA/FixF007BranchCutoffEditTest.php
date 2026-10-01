<?php

namespace Tests\Feature\QA;

use App\Models\Company;
use App\Models\FormStatusHistory;
use App\Models\Jnf;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-007: an admin edit that touches ONLY a selected branch's cut-offs (CGPA, backlogs allowed,
 * backlog caps) must be saved and reported, not answered with "No changes detected".
 */
#[Group('qa')]
class FixF007BranchCutoffEditTest extends TestCase
{
    use RefreshDatabase;

    private Jnf $jnf;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'submitted', 'form_data' => [
            'jobTitle' => 'SDE',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [
                ['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => true, 'maxOngoingBacklogs' => '0', 'maxTotalBacklogs' => '1'],
                ['branch' => 'Mining Engineering', 'selected' => false, 'cgpa' => '', 'backlogsAllowed' => false],
            ]]],
        ]]);
    }

    private function edit(callable $change)
    {
        $data = $this->jnf->fresh()->form_data;
        $change($data);

        return $this->patchJson("/api/admin/jnfs/{$this->jnf->id}/form-data", ['form_data' => $data])->assertOk();
    }

    public function test_backlog_cap_only_edit_is_saved_and_reported(): void
    {
        $this->edit(fn (array &$d) => $d['eligibility'][0]['branches'][0]['maxTotalBacklogs'] = '2');

        $this->assertSame('2', $this->jnf->fresh()->form_data['eligibility'][0]['branches'][0]['maxTotalBacklogs']);
        $this->assertStringContainsString('Branch Cut-offs', FormStatusHistory::latest('id')->value('remarks'));
    }

    public function test_backlogs_allowed_toggle_and_cgpa_edit_are_saved(): void
    {
        $this->edit(function (array &$d): void {
            $d['eligibility'][0]['branches'][0]['backlogsAllowed'] = false;
            $d['eligibility'][0]['branches'][0]['cgpa'] = '8.0';
        });

        $branch = $this->jnf->fresh()->form_data['eligibility'][0]['branches'][0];
        $this->assertFalse($branch['backlogsAllowed']);
        $this->assertSame('8.0', $branch['cgpa']);
    }

    public function test_an_unselected_branch_or_an_identical_save_is_still_no_change(): void
    {
        $this->edit(fn (array &$d) => $d['eligibility'][0]['branches'][1]['cgpa'] = '9.0')->assertJsonPath('message', 'No changes detected.');
        $this->edit(fn (array &$d) => null)->assertJsonPath('message', 'No changes detected.');
    }
}
