<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PortalNotification;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Owner request 2026-10-19: the notification center is personal. Eligible students hear about a new job profile
 * (once, with its details), selected branches hear about an announcement, and deadline / close / cancel changes reach
 * the students they concern.
 */
class StudentNotificationCenterTest extends TestCase
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
        $this->eligible = StudentProfile::factory()->create(['branch' => self::CSE, 'graduating_batch' => 2027]);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $this->eligible->id, 'status' => 'active']);
        $this->mining = StudentProfile::factory()->create(['branch' => 'Mining Engineering', 'graduating_batch' => 2027]);
    }

    private function open(array $announcement): JobPosting
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
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
            'announcement' => $announcement,
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    /** @return list<array<string, mixed>> */
    private function notificationsOf(StudentProfile $student): array
    {
        Sanctum::actingAs($student->user);
        $rows = $this->getJson('/api/auth/notifications')->assertOk()->json('notifications');
        Sanctum::actingAs($this->admin);

        return $rows;
    }

    public function test_eligible_students_get_the_new_job_once_with_its_details_even_without_email(): void
    {
        $posting = $this->open([
            'notice' => ['enabled' => false, 'audience' => ['type' => 'all']],
            'email' => ['enabled' => false, 'audience' => ['type' => 'eligible']],
        ]);

        $mine = $this->notificationsOf($this->eligible);
        $this->assertCount(1, $mine);
        $this->assertSame('new_job', $mine[0]['category']);
        $this->assertSame("/student/postings/{$posting->id}", $mine[0]['link']);
        $this->assertSame(['B.Tech - '.self::CSE], $mine[0]['details']['courses']);
        $this->assertSame([], $this->notificationsOf($this->mining));

        // Announcing again never repeats it.
        $this->postJson("/api/admin/postings/{$posting->id}/announce", ['announcement' => [
            'notice' => ['enabled' => true, 'audience' => ['type' => 'eligible']],
            'email' => ['enabled' => false, 'audience' => ['type' => 'eligible']],
        ]])->assertOk();
        $this->assertCount(1, $this->notificationsOf($this->eligible));
    }

    public function test_selected_branches_get_the_announcement_and_changes_reach_the_right_students(): void
    {
        $posting = $this->open([
            'notice' => ['enabled' => true, 'audience' => ['type' => 'branches', 'branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Mining Engineering']]], 'message' => 'Open to Mining too.'],
            'email' => ['enabled' => false, 'audience' => ['type' => 'eligible']],
        ]);
        $mining = $this->notificationsOf($this->mining);
        $this->assertSame(['announcement'], array_column($mining, 'category'));
        $this->assertSame('Open to Mining too.', $mining[0]['message']);

        // A new deadline: eligible students hear about it.
        $this->patchJson("/api/admin/postings/{$posting->id}", ['application_deadline' => now()->addDays(6)->toIso8601String()])->assertOk();
        $this->assertContains('deadline', array_column($this->notificationsOf($this->eligible), 'category'));

        // Closing and cancelling reach applicants only.
        $resume = $this->eligible->resumes()->create(['slot' => 1, 'label' => 'Main', 'file_path' => 'resumes/x.pdf', 'file_size' => 10, 'status' => 'approved']);
        \App\Models\Application::create(['job_posting_id' => $posting->id, 'student_profile_id' => $this->eligible->id, 'resume_id' => $resume->id, 'status' => 'applied']);
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        $this->patchJson("/api/admin/postings/{$posting->id}/cancel")->assertOk();
        $titles = array_column($this->notificationsOf($this->eligible), 'title');
        $this->assertContains('Applications closed: Acme — SDE', $titles);
        $this->assertContains('Cancelled: Acme — SDE', $titles);
        $this->assertSame(0, PortalNotification::where('user_id', $this->mining->user_id)->where('category', 'status')->count());
    }
}
