<?php

namespace Tests\Feature;

use App\Mail\BroadcastMail;
use App\Mail\PostingFloatedMail;
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
 * Owner request 2026-10-19: opening a job profile offers a notice and an email, each with its own target audience
 * (eligible students, all students, branches, courses) and text. Students see "<company>'s <role> is open for
 * applications" and the courses, eligibility and stages when they open the notice.
 */
class JobProfileAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private const CSE = 'Computer Science & Engineering';

    private User $admin;

    private PlacementCycle $cycle;

    private StudentProfile $eligible;

    private StudentProfile $mining;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT 2027', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertOk();

        $this->eligible = StudentProfile::factory()->create(['branch' => self::CSE, 'graduating_batch' => 2027]);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $this->eligible->id, 'status' => 'active']);
        $this->mining = StudentProfile::factory()->create(['branch' => 'Mining Engineering', 'graduating_batch' => 2027]);
    }

    private function open(?array $announcement, array $extra = []): JobPosting
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid().'@acme.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => [
                'jobTitle' => 'SDE', 'jobLocation' => 'Pune', 'graduatingBatch' => '2027', 'currency' => 'INR',
                'programmeSalaries' => [['programme' => StudentProfileFactory::BTECH, 'ctcAnnual' => '1800000', 'enabled' => true]],
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => self::CSE, 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            ],
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', array_filter([
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
            'announcement' => $announcement,
        ]) + $extra)->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function noticesOf(StudentProfile $student): array
    {
        Sanctum::actingAs($student->user);

        return $this->getJson('/api/student/notices')->assertOk()->json('notices');
    }

    public function test_notice_for_eligible_students_and_email_to_a_branch_with_custom_text(): void
    {
        $this->open([
            'notice' => ['enabled' => true, 'audience' => ['type' => 'eligible'], 'message' => 'Pre-placement talk on Monday.'],
            'email' => ['enabled' => true, 'audience' => ['type' => 'branches', 'branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Mining Engineering']]], 'subject' => 'Acme is hiring'],
        ]);

        $notices = $this->noticesOf($this->eligible);
        $this->assertSame(["Acme's SDE is open for applications"], array_column($notices, 'title'));
        $this->assertSame('Pre-placement talk on Monday.', $notices[0]['job_profile']['message']);
        $this->assertSame(['B.Tech - '.self::CSE], $notices[0]['job_profile']['courses']);
        $this->assertSame([], $this->noticesOf($this->mining), 'not eligible, so no notice');

        Mail::assertNotSent(PostingFloatedMail::class);
        Mail::assertSent(BroadcastMail::class, fn (BroadcastMail $m) => $m->subjectLine === 'Acme is hiring'
            && collect($m->bcc)->pluck('address')->all() === [$this->mining->user->email]);
    }

    public function test_nothing_is_posted_or_sent_when_both_are_off(): void
    {
        $posting = $this->open([
            'notice' => ['enabled' => false, 'audience' => ['type' => 'all']],
            'email' => ['enabled' => false, 'audience' => ['type' => 'eligible']],
        ]);

        $this->assertNull(Notice::where('job_posting_id', $posting->id)->first());
        Mail::assertNothingSent();
    }

    public function test_notify_students_later_reaches_a_wider_audience_and_never_emails_twice(): void
    {
        $posting = $this->open([
            'notice' => ['enabled' => false, 'audience' => ['type' => 'all']],
            'email' => ['enabled' => true, 'audience' => ['type' => 'eligible']],
        ]);
        Mail::assertSent(PostingFloatedMail::class, 1);

        $this->postJson("/api/admin/postings/{$posting->id}/announce", ['announcement' => [
            'notice' => ['enabled' => true, 'audience' => ['type' => 'programmes', 'programmes' => [StudentProfileFactory::BTECH]], 'title' => 'Acme hiring SDEs'],
            'email' => ['enabled' => true, 'audience' => ['type' => 'all']],
        ]])->assertOk()->assertJsonPath('message', 'Notice posted for 2 student(s); email on its way to 1 student(s).');

        foreach ([$this->eligible, $this->mining] as $student) {
            $this->assertSame(['Acme hiring SDEs'], array_column($this->noticesOf($student), 'title'));
        }
        // The eligible student already had the opening email: only the other student gets this one.
        Mail::assertSent(BroadcastMail::class, fn (BroadcastMail $m) => collect($m->bcc)->pluck('address')->all() === [$this->mining->user->email]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'posting.announce', 'subject_id' => $posting->id]);
    }

    public function test_a_scheduled_job_profile_uses_the_saved_choice_when_it_opens(): void
    {
        $posting = $this->open([
            'notice' => ['enabled' => true, 'audience' => ['type' => 'all']],
            'email' => ['enabled' => false, 'audience' => ['type' => 'eligible']],
        ], ['scheduled_open_at' => now()->addHour()->toIso8601String()]);

        $this->travel(2)->hours();
        $this->artisan('placement:open-scheduled')->assertSuccessful();

        $this->assertNotNull(Notice::where('job_posting_id', $posting->id)->sole()->published_at);
        $this->assertCount(1, $this->noticesOf($this->mining));
        Mail::assertNothingSent();
    }

    public function test_bad_audiences_are_refused(): void
    {
        $posting = $this->open(null);

        $this->postJson("/api/admin/postings/{$posting->id}/announce", ['announcement' => [
            'notice' => ['enabled' => true, 'audience' => ['type' => 'branches', 'branches' => []]],
            'email' => ['enabled' => false, 'audience' => ['type' => 'eligible']],
        ]])->assertStatus(422)->assertJsonValidationErrors('announcement.notice.audience');

        $this->postJson("/api/admin/postings/{$posting->id}/announce", ['announcement' => [
            'notice' => ['enabled' => true, 'audience' => ['type' => 'programmes', 'programmes' => ['Not a course']]],
            'email' => ['enabled' => false, 'audience' => ['type' => 'eligible']],
        ]])->assertStatus(422);
    }
}
