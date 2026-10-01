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
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Owner decision 2 (2026-10-01, QA T3.2): a student never sees a drive whose branch list leaves them out — not on
 * the job board, the detail page, the calendar or the dashboard — unless they applied before the list changed.
 */
#[Group('qa')]
class FixD2BranchVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private JobPosting $posting;

    private StudentProfile $mining;

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
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => [
            'jobTitle' => 'SDE',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]],
        ]]);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDays(3)->toIso8601String()])->assertCreated();
        $this->posting = JobPosting::sole();
        $this->mining = StudentProfile::factory()->create(['branch' => 'Mining Engineering']);
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $this->mining->id, 'status' => 'active']);
    }

    private function seen(): array
    {
        Sanctum::actingAs($this->mining->user);

        return [
            'board' => in_array($this->posting->id, collect($this->getJson('/api/student/postings')->json('postings'))->pluck('id')->all(), true),
            'detail' => $this->getJson("/api/student/postings/{$this->posting->id}")->status() === 200,
            'calendar' => collect($this->getJson('/api/student/calendar?month='.$this->posting->application_deadline->setTimezone('Asia/Kolkata')->format('Y-m'))->json('items'))->contains('type', 'deadline'),
            'dashboard' => collect($this->getJson('/api/student/dashboard')->json('upcoming'))->contains('type', 'deadline'),
        ];
    }

    public function test_a_drive_that_leaves_the_branch_out_is_hidden_everywhere(): void
    {
        $this->assertSame(['board' => false, 'detail' => false, 'calendar' => false, 'dashboard' => false], $this->seen());
    }

    public function test_a_student_who_already_applied_keeps_seeing_it(): void
    {
        $resume = $this->mining->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
        Application::create(['job_posting_id' => $this->posting->id, 'student_profile_id' => $this->mining->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);

        $this->assertSame(['board' => true, 'detail' => true, 'calendar' => true, 'dashboard' => true], $this->seen());
    }
}
