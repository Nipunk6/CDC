<?php

namespace Tests\Feature;

use App\Mail\EventAnnouncedMail;
use App\Models\AuditLog;
use App\Models\CampusEvent;
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
use Tests\TestCase;

class EventsCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_branch_scoped_event_mails_only_that_branch_and_is_audited(): void
    {
        $cse = StudentProfile::factory()->create();
        $mining = StudentProfile::factory()->create(['branch' => 'Mining Engineering']);
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/events', [
            'title' => 'Mining PPT', 'event_type' => 'ppt', 'starts_at' => now()->addDays(2)->toIso8601String(),
            'audience_type' => 'branches',
            'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Mining Engineering']]],
        ])->assertCreated();
        $event = CampusEvent::sole();
        $this->assertNull($event->published_at);

        // Only title, type and start are required; a bad branch is refused.
        $this->postJson('/api/admin/events', ['title' => 'x', 'event_type' => 'other', 'starts_at' => now()->toIso8601String(), 'audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => 'Nope']]]])->assertStatus(422);

        $this->postJson("/api/admin/events/{$event->id}/publish")->assertOk();
        $this->postJson("/api/admin/events/{$event->id}/publish")->assertStatus(422);

        Mail::assertQueued(EventAnnouncedMail::class, 1);
        Mail::assertQueued(EventAnnouncedMail::class, fn ($m) => $m->hasBcc($mining->user->email) && ! $m->hasTo($mining->user->email));
        $this->assertSame(1, AuditLog::where('action', 'event.publish')->count());

        Sanctum::actingAs($mining->user);
        $this->getJson('/api/student/events')->assertJsonCount(1, 'events');
        Sanctum::actingAs($cse->user);
        $this->getJson('/api/student/events')->assertJsonCount(0, 'events');
    }

    public function test_calendar_shows_deadline_to_enrolled_student_only(): void
    {
        $cycle = PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]]]);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => [
            'jobTitle' => 'SDE',
            // Students only see drives open to their branch (owner decision, QA T3.2).
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true]]]],
            'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]],
        ]]);
        $deadline = now('Asia/Kolkata')->addDays(3)->setTime(18, 0);

        $enrolled = StudentProfile::factory()->create();
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $enrolled->id, 'status' => 'active']);
        $outsider = StudentProfile::factory()->create();

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => $deadline->toIso8601String()])->assertCreated();
        $month = $deadline->format('Y-m');

        $this->getJson("/api/admin/calendar?month={$month}")->assertOk()->assertJsonPath('items.0.type', 'deadline');

        Sanctum::actingAs($enrolled->user);
        $items = $this->getJson("/api/student/calendar?month={$month}")->assertOk()->json('items');
        $this->assertSame('deadline', $items[0]['type']);
        $this->assertSame('/student/postings/'.JobPosting::sole()->id, $items[0]['link']);

        Sanctum::actingAs($outsider->user);
        $this->getJson("/api/student/calendar?month={$month}")->assertOk()->assertJsonCount(0, 'items');
    }

    public function test_company_sees_its_own_published_events(): void
    {
        $acme = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $other = Company::create(['name' => 'Other', 'hr_name' => 'HR', 'hr_email' => 'hr@other.test']);
        CampusEvent::create(['title' => 'Acme PPT', 'event_type' => 'ppt', 'company_id' => $acme->id, 'starts_at' => now()->addDay(), 'published_at' => now()]);
        CampusEvent::create(['title' => 'Acme draft', 'event_type' => 'ppt', 'company_id' => $acme->id, 'starts_at' => now()->addDay()]);
        CampusEvent::create(['title' => 'Other PPT', 'event_type' => 'ppt', 'company_id' => $other->id, 'starts_at' => now()->addDay(), 'published_at' => now()]);

        Sanctum::actingAs(User::factory()->create(['role' => 'company', 'company_id' => $acme->id]));
        $this->getJson('/api/company/events')->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.title', 'Acme PPT');
    }

    public function test_student_calendar_hides_rounds_of_cancelled_postings(): void
    {
        $cycle = PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]]]);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => ['jobTitle' => 'SDE', 'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]]]]);
        $student = StudentProfile::factory()->create();
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDays(40)->toIso8601String()])->assertCreated();
        $posting = JobPosting::sole();
        $round = $posting->rounds()->first();
        $when = now('Asia/Kolkata')->addDays(5)->setTime(11, 0);
        $round->update(['scheduled_at' => $when]);
        $resume = $student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'x.pdf', 'file_size' => 1, 'status' => 'approved']);
        \App\Models\Application::create(['job_posting_id' => $posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);

        Sanctum::actingAs($student->user);
        $this->assertCount(1, $this->getJson('/api/student/calendar?month='.$when->format('Y-m'))->json('items'));

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}/cancel")->assertOk();
        Sanctum::actingAs($student->user);
        $this->assertCount(0, $this->getJson('/api/student/calendar?month='.$when->format('Y-m'))->json('items'));
    }
}
