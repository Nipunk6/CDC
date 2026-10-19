<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Notice;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner decision 2026-10-18: every job profile opened for applications is announced on EVERY student's Notices page,
 * whatever their programme, branch, batch or cycle — one notice per job profile, never emailed.
 */
class JobProfileNoticeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlacementCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT 2027', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
    }

    private function jnf(): Jnf
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test']);

        return Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => [
                'jobTitle' => 'SDE', 'jobLocation' => 'Pune', 'graduatingBatch' => '2027', 'currency' => 'INR',
                'programmeSalaries' => [['programme' => StudentProfileFactory::BTECH, 'ctcAnnual' => '1800000', 'enabled' => true]],
                // Only CSE is eligible.
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            ],
        ]);
    }

    private function open(array $extra = []): JobPosting
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $this->jnf()->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ] + $extra)->assertCreated();

        return JobPosting::latest('id')->first();
    }

    /** Titles on this student's Notices page. */
    private function noticesOf(StudentProfile $student): array
    {
        Sanctum::actingAs($student->user);

        return collect($this->getJson('/api/student/notices')->assertOk()->json('notices'))->pluck('title')->all();
    }

    public function test_an_opened_job_profile_is_on_every_students_notices_page(): void
    {
        $posting = $this->open();

        $notice = Notice::where('source', 'job_profile')->where('job_posting_id', $posting->id)->sole();
        $this->assertSame("Acme's SDE is open for applications", $notice->title);
        $this->assertStringContainsString("Applications are now being accepted for Acme's Job Profile: SDE.", $notice->body);
        $this->assertStringContainsString('1. B.Tech - Computer Science & Engineering', $notice->body);
        $this->assertStringContainsString('CGPA 6 and above', $notice->body);
        $this->assertStringContainsString('Hiring Process - Stages', $notice->body);
        $this->assertNull($notice->emailed_at, 'the notice itself is never emailed');

        // Eligible or not, enrolled in the cycle or not, any branch and any batch.
        $eligible = StudentProfile::factory()->create(['branch' => 'Computer Science & Engineering']);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $eligible->id, 'status' => 'active']);
        $otherBranch = StudentProfile::factory()->create(['branch' => 'Mining Engineering', 'graduating_batch' => 2029]);
        foreach ([$eligible, $otherBranch] as $student) {
            $this->assertContains("Acme's SDE is open for applications", $this->noticesOf($student));
        }

        // The page also gets the card's content as data (laid out with headings and numbered lists, no HTML).
        $details = collect($this->getJson('/api/student/notices')->json('notices'))->firstWhere('title', "Acme's SDE is open for applications")['job_profile'];
        $this->assertSame(['B.Tech - Computer Science & Engineering'], $details['courses']);
        $this->assertSame([['label' => 'B.Tech', 'points' => ['CGPA 6 and above', 'Backlogs allowed']]], $details['eligibility']);
        $this->assertNotEmpty($details['stages']);
        $this->assertStringEndsWith('IST', $details['deadline']);
    }

    public function test_a_scheduled_job_profile_is_announced_only_when_it_opens(): void
    {
        $posting = $this->open(['scheduled_open_at' => now()->addHour()->toIso8601String()]);
        $this->assertNull(Notice::where('job_posting_id', $posting->id)->first());

        $this->travel(2)->hours();
        $this->artisan('placement:open-scheduled')->assertSuccessful();
        $this->assertNotNull(Notice::where('source', 'job_profile')->where('job_posting_id', $posting->id)->sole()->published_at);
    }

    public function test_the_notice_follows_close_reopen_edits_and_cancel_and_stays_single(): void
    {
        $posting = $this->open();
        $notice = fn () => Notice::where('source', 'job_profile')->where('job_posting_id', $posting->id)->sole();

        // Closing and reopening applications keeps the announcement up.
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        $this->assertNotNull($notice()->published_at);
        $this->patchJson("/api/admin/postings/{$posting->id}/reopen")->assertOk();
        $this->assertNotNull($notice()->published_at);

        // An edit refreshes the details.
        $deadline = now()->addDays(10)->setTime(18, 0);
        $this->patchJson("/api/admin/postings/{$posting->id}", ['application_deadline' => $deadline->toIso8601String()])->assertOk();
        $this->assertStringContainsString('Deadline for applications is '.$deadline->timezone('Asia/Kolkata')->format('F j, Y, h:i A'), $notice()->body);

        // Cancelled (final): taken off the Notices page.
        $this->patchJson("/api/admin/postings/{$posting->id}/cancel")->assertOk();
        $this->assertNull($notice()->published_at);
        $other = StudentProfile::factory()->create();
        $this->assertNotContains("Acme's SDE is open for applications", $this->noticesOf($other));

        $this->assertSame(1, Notice::where('job_posting_id', $posting->id)->count());
    }
}
