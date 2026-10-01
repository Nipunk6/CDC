<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-009 (owner decision 6): the admin sees, per posting, every eligible student and whether they
 * applied — "Eligible – Applied / Not applied".
 */
#[Group('qa')]
class FixF009EligibleNotAppliedTest extends TestCase
{
    use RefreshDatabase;

    private JobPosting $posting;

    private User $companyUser;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id]);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => [
            'jobTitle' => 'SDE',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => true]]]],
            'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
        ]]);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDay()->toIso8601String()])->assertCreated();
        $this->posting = JobPosting::sole();

        foreach (['22JE0001' => 8.0, '22JE0002' => 8.0, '22JE0003' => 8.0, '22JE0004' => 6.0] as $roll => $cgpa) {
            $s = StudentProfile::factory()->create(['roll_no' => $roll, 'full_name' => "Student {$roll}", 'current_cgpa' => $cgpa]);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            if ($roll === '22JE0001') {
                Application::create(['job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
            }
            if ($roll === '22JE0002') {
                Application::create(['job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'withdrawn', 'applied_at' => now()]);
            }
        }
    }

    private function list(string $query = ''): TestResponse
    {
        return $this->getJson("/api/admin/postings/{$this->posting->id}/eligible{$query}")->assertOk();
    }

    public function test_lists_eligible_students_with_applied_flag_and_counts(): void
    {
        $response = $this->list();
        // 22JE0004 (CGPA 6.0 < 7.0) is not eligible and not listed.
        $this->assertSame(['22JE0001' => true, '22JE0002' => false, '22JE0003' => false], collect($response->json('students'))->pluck('applied', 'roll_no')->all());
        $this->assertSame(['eligible' => 3, 'applied' => 1, 'not_applied' => 2], $response->json('counts'));

        $this->assertSame(['22JE0002', '22JE0003'], collect($this->list('?status=not_applied')->json('students'))->pluck('roll_no')->all(), 'a withdrawn application counts as not applied');
        $this->assertSame(['22JE0001'], collect($this->list('?status=applied')->json('students'))->pluck('roll_no')->all());
        $this->assertSame(['22JE0003'], collect($this->list('?status=not_applied&search=0003')->json('students'))->pluck('roll_no')->all());
    }

    public function test_only_admins_can_see_it(): void
    {
        Sanctum::actingAs($this->companyUser);
        $this->getJson("/api/admin/postings/{$this->posting->id}/eligible")->assertForbidden();
    }
}
