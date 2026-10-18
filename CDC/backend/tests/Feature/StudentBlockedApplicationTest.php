<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A student blocked from a job profile they applied to (another offer accepted, debarred, blocked by the CDC) sees
 * that application as "Rejected" with the block's reason — not the one that produced the offer, and not one already
 * decided as not selected in a published stage.
 */
class StudentBlockedApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlacementCycle $cycle;

    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $this->student = StudentProfile::factory()->create(['roll_no' => '22JE0001']);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $this->student->id, 'status' => 'active']);
    }

    private function postingWithApplication(string $company): Application
    {
        $c = Company::create(['name' => $company, 'hr_name' => 'HR', 'hr_email' => strtolower($company).'@co.test']);
        $jnf = Jnf::create([
            'company_id' => $c->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated();
        $posting = JobPosting::latest('id')->first();
        $resume = $this->student->resumes()->firstOrCreate(['slot' => 1], ['label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);

        return Application::create(['job_posting_id' => $posting->id, 'student_profile_id' => $this->student->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
    }

    /** @return array<int, ?string> application id => blocked_reason, from the student's My Applications */
    private function reasons(): array
    {
        Sanctum::actingAs($this->student->user);

        return collect($this->getJson('/api/student/applications')->assertOk()->json('applications'))->pluck('blocked_reason', 'id')->all();
    }

    public function test_other_live_applications_show_rejected_with_the_block_reason(): void
    {
        $won = $this->postingWithApplication('Acme');
        $other = $this->postingWithApplication('Globex');
        $alreadyOut = $this->postingWithApplication('Initech');
        ApplicationRoundResult::create([
            'application_id' => $alreadyOut->id, 'posting_round_id' => $alreadyOut->jobPosting->rounds()->first()->id,
            'result' => 'rejected', 'published_at' => now(), 'decided_by' => $this->admin->id,
        ]);
        $this->assertEquals([$won->id => null, $other->id => null, $alreadyOut->id => null], $this->reasons(), 'no block, nothing rejected by a block');

        $offer = Offer::create([
            'application_id' => $won->id, 'student_profile_id' => $this->student->id, 'company_id' => $won->jobPosting->company()->id,
            'job_posting_id' => $won->job_posting_id, 'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'fulltime',
            'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);
        PlacementBlock::create([
            'student_profile_id' => $this->student->id, 'placement_cycle_id' => $this->cycle->id, 'scope' => 'all',
            'reason' => 'offer', 'offer_id' => $offer->id, 'active' => true, 'blocked_by' => $this->admin->id,
        ]);

        $reasons = $this->reasons();
        $this->assertSame('Blocked: accepted a Full-Time offer.', $reasons[$other->id]);
        $this->assertNull($reasons[$won->id], 'the application that produced the offer shows the offer');
        $this->assertNull($reasons[$alreadyOut->id], 'an earlier published decision is shown as it is');

        // The job board and the job page say the same.
        $this->assertSame('Blocked: accepted a Full-Time offer.', $this->getJson("/api/student/postings/{$other->job_posting_id}")->assertOk()->json('posting.application.blocked_reason'));

        // Lifting the block brings the application back.
        PlacementBlock::query()->update(['active' => false]);
        $this->assertNull($this->reasons()[$other->id]);
    }
}
