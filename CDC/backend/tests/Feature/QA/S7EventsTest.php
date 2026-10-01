<?php

namespace Tests\Feature\QA;

use App\Mail\EventAnnouncedMail;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\CampusEvent;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
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

/**
 * QA Section 7 — Events (spec B7 "Events", C14, M8.1; D76, D89).
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S7EventsTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const MTECH = 'M.Tech (2 Year) - GATE';

    private User $admin;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin A']);
    }

    // ------------------------------------------------------------------ helpers

    private function student(array $attributes = [], bool $active = true): StudentProfile
    {
        $this->seq++;
        $roll = sprintf('26EV%04d', $this->seq);
        $email = strtolower($roll).'@students.qa.test';
        $student = StudentProfile::factory()->create(array_merge([
            'roll_no' => $roll,
            'institute_email' => $email,
            'user_id' => User::factory()->state(['role' => 'student', 'email' => $email, 'is_active' => $active]),
        ], $attributes));

        return $student->fresh('user');
    }

    private function cycle(): PlacementCycle
    {
        return PlacementCycle::create([
            'name' => 'FT '.uniqid(), 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    private function floatPosting(PlacementCycle $cycle, string $title = 'SDE'): JobPosting
    {
        $company = Company::create(['name' => $title.' Co', 'hr_name' => 'HR', 'hr_email' => uniqid().'@co.qa.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => ['jobTitle' => $title, 'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]]],
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function apply(StudentProfile $student, JobPosting $posting, string $status = 'applied'): Application
    {
        $resume = $student->resumes()->firstOrCreate(['slot' => 1], ['label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);

        return Application::create([
            'job_posting_id' => $posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id,
            'status' => $status, 'applied_at' => now(), 'withdrawn_at' => $status === 'withdrawn' ? now() : null,
        ]);
    }

    /** Create + publish an event as the admin; returns the event. */
    private function publishEvent(array $payload): CampusEvent
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/events', $payload + [
            'title' => 'Event '.uniqid(), 'event_type' => 'ppt', 'starts_at' => now()->addDays(2)->toIso8601String(),
        ])->assertCreated()->json('event.id');
        $this->postJson("/api/admin/events/{$id}/publish")->assertOk();

        return CampusEvent::findOrFail($id);
    }

    /** @return list<string> every BCC address across all queued/sent E6 mails, sorted */
    private function e6BccRecipients(): array
    {
        $mails = Mail::queued(EventAnnouncedMail::class)->merge(Mail::sent(EventAnnouncedMail::class));

        return $mails->flatMap(fn ($m) => array_column($m->bcc, 'address'))->sort()->values()->all();
    }

    /** @return list<string> */
    private function e6LoggedRecipients(): array
    {
        return EmailLog::where('template', 'emails.event-announced')->pluck('recipient_email')->sort()->values()->all();
    }

    /** @param list<StudentProfile> $students @return list<string> */
    private function emails(array $students): array
    {
        return collect($students)->map(fn (StudentProfile $s) => $s->user->email)->sort()->values()->all();
    }

    // ------------------------------------------------------------------ T7.1

    public function test_T7_1_only_title_starts_at_and_event_type_are_required_full_create_and_edit(): void
    {
        Sanctum::actingAs($this->admin);

        // Each of the three required fields is really required.
        $min = ['title' => 'Minimal talk', 'event_type' => 'webinar', 'starts_at' => now()->addDays(3)->toIso8601String()];
        foreach (array_keys($min) as $field) {
            $payload = $min;
            unset($payload[$field]);
            $this->postJson('/api/admin/events', $payload)->assertStatus(422)->assertJsonValidationErrors([$field]);
        }

        // Minimum payload is enough (nothing else is required) → draft, audience defaults to all.
        $minimal = $this->postJson('/api/admin/events', $min)->assertCreated();
        $minimalEvent = CampusEvent::findOrFail($minimal->json('event.id'));
        $this->assertSame('Minimal talk', $minimalEvent->title);
        $this->assertSame('webinar', $minimalEvent->event_type);
        $this->assertSame('all', $minimalEvent->audience_type);
        $this->assertNull($minimalEvent->published_at, 'events start as drafts');
        $this->assertNull($minimalEvent->company_id);
        $this->assertNull($minimalEvent->venue);

        // All fields.
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.qa.test']);
        $full = $this->postJson('/api/admin/events', [
            'title' => 'Acme PPT',
            'event_type' => 'ppt',
            'starts_at' => '2027-01-15T09:30:00Z',
            'company_id' => $company->id,
            'venue' => 'NLHC 101',
            'meeting_link' => 'https://meet.example.test/acme',
            'description' => '<p>Join us for <strong>Acme</strong>.</p><ul><li>Pizza</li></ul>',
            'audience_type' => 'branches',
            'audience_filter' => ['branches' => [['programme' => self::BTECH, 'branch' => 'Computer Science & Engineering']]],
        ])->assertCreated();
        $event = CampusEvent::findOrFail($full->json('event.id'));
        $this->assertSame($company->id, $event->company_id);
        $this->assertSame('NLHC 101', $event->venue);
        $this->assertSame('https://meet.example.test/acme', $event->meeting_link);
        $this->assertStringContainsString('<strong>Acme</strong>', $event->description, 'rich text is stored as authored');
        $this->assertSame('branches', $event->audience_type);
        $this->assertSame([['programme' => self::BTECH, 'branch' => 'Computer Science & Engineering']], $event->audience_filter['branches']);
        $this->assertTrue($event->starts_at->equalTo(\Carbon\Carbon::parse('2027-01-15T09:30:00Z')));
        $this->assertSame(2, AuditLog::where('action', 'event.create')->where('user_id', $this->admin->id)->count());

        // Edit later. NOTE: the implemented edit verb is PUT (full payload), not PATCH — spec M8.1 only says "CRUD".
        $this->putJson("/api/admin/events/{$event->id}", [
            'title' => 'Acme PPT (moved)', 'event_type' => 'ppt', 'starts_at' => '2027-01-16T09:30:00Z',
            'company_id' => $company->id, 'venue' => 'Penman Auditorium', 'meeting_link' => null,
            'description' => '<p>Moved.</p>', 'audience_type' => 'all',
        ])->assertOk()->assertJsonPath('event.venue', 'Penman Auditorium');
        $event->refresh();
        $this->assertSame('Acme PPT (moved)', $event->title);
        $this->assertNull($event->meeting_link);
        $this->assertSame('all', $event->audience_type);
        $this->assertNull($event->audience_filter);

        $log = AuditLog::where('action', 'event.update')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('NLHC 101', $log->before['venue']);
        $this->assertSame('Penman Auditorium', $log->after['venue']);

        // Edits after publishing are still possible (not re-mailed, D76).
        $this->postJson("/api/admin/events/{$event->id}/publish")->assertOk();
        $this->putJson("/api/admin/events/{$event->id}", [
            'title' => 'Acme PPT (final)', 'event_type' => 'ppt', 'starts_at' => '2027-01-16T09:30:00Z',
        ])->assertOk();
        $this->assertSame('Acme PPT (final)', $event->fresh()->title);
        $this->assertNotNull($event->fresh()->published_at, 'editing does not unpublish');
    }

    /**
     * Extra (incidental) check: a starts_at sent with an explicit +05:30 offset must be stored as the same instant.
     * Laravel stores a Carbon's wall-clock time without converting it to the app timezone (UTC).
     */
    public function test_T7_1x_starts_at_with_ist_offset_is_stored_as_the_same_instant(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/events', [
            'title' => 'Offset', 'event_type' => 'other', 'starts_at' => '2027-02-01T01:30:00+05:30',
        ])->assertCreated()->json('event.id');

        $stored = CampusEvent::findOrFail($id)->starts_at;
        $this->assertTrue(
            $stored->equalTo(\Carbon\Carbon::parse('2027-01-31T20:00:00Z')),
            'starts_at sent as 2027-02-01T01:30:00+05:30 was stored as '.$stored->toIso8601String().' (expected 2027-01-31T20:00:00+00:00)'
        );
    }

    // ------------------------------------------------------------------ T7.2

    public function test_T7_2a_audience_all_reaches_every_active_student_only(): void
    {
        $cycle = $this->cycle();
        $a = $this->student();
        $b = $this->student(['branch' => 'Mining Engineering']);
        $noCycle = $this->student(); // never enrolled anywhere
        $suspendedAccount = $this->student([], active: false);
        $suspendedEnrolment = $this->student();
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $a->id, 'status' => 'active']);
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $b->id, 'status' => 'active']);
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $suspendedAccount->id, 'status' => 'active']);
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $suspendedEnrolment->id, 'status' => 'suspended']);
        // Non-students must never be in a student audience.
        User::factory()->create(['role' => 'company', 'email' => 'hr@company.qa.test']);

        $event = $this->publishEvent(['audience_type' => 'all']);

        // Implemented interpretation (D76): "all" = every student whose ACCOUNT is active; cycle enrolment is not
        // considered, so a student with a suspended enrolment (and one in no cycle) is included. Recorded, not judged.
        $expected = $this->emails([$a, $b, $noCycle, $suspendedEnrolment]);
        $this->assertSame($expected, $this->e6BccRecipients(), 'BCC recipients of E6 for audience=all');
        $this->assertSame($expected, $this->e6LoggedRecipients(), 'email_logs rows (one per student, D89)');
        $this->assertNotContains($suspendedAccount->user->email, $this->e6BccRecipients(), 'suspended accounts are excluded');
        $this->assertNotContains($this->admin->email, $this->e6BccRecipients());

        // Never addressed To/CC to a student (BCC batches, D89).
        foreach (Mail::queued(EventAnnouncedMail::class) as $mail) {
            $this->assertTrue($mail->hasTo(config('mail.from.address')));
            $this->assertSame([], $mail->cc);
        }

        $this->assertSame(4, AuditLog::where('action', 'event.publish')->sole()->after['audience']);

        // The student feed agrees with the mail audience.
        foreach ([$a, $b, $noCycle, $suspendedEnrolment] as $s) {
            Sanctum::actingAs($s->user);
            $this->getJson('/api/student/events')->assertOk()->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', $event->id);
        }
    }

    public function test_T7_2b_audience_branches_reaches_only_those_programme_branch_students(): void
    {
        $btechCse = $this->student();
        $btechMining = $this->student(['branch' => 'Mining Engineering']);
        $btechMiningSuspended = $this->student(['branch' => 'Mining Engineering'], active: false);
        $mtechCse = $this->student(['programme' => self::MTECH, 'branch' => 'Computer Science and Engineering']);
        $mtechMining = $this->student(['programme' => self::MTECH, 'branch' => 'Mining Engineering']);

        $this->publishEvent([
            'audience_type' => 'branches',
            'audience_filter' => ['branches' => [
                ['programme' => self::BTECH, 'branch' => 'Mining Engineering'], // one branch of B.Tech
                ['programme' => self::MTECH, 'branch' => null],                  // the whole M.Tech programme
            ]],
        ]);

        $expected = $this->emails([$btechMining, $mtechCse, $mtechMining]);
        $this->assertSame($expected, $this->e6BccRecipients());
        $this->assertSame($expected, $this->e6LoggedRecipients());
        $this->assertNotContains($btechCse->user->email, $this->e6BccRecipients(), 'B.Tech CSE is not in the audience');
        $this->assertNotContains($btechMiningSuspended->user->email, $this->e6BccRecipients(), 'suspended account excluded');

        Sanctum::actingAs($btechCse->user);
        $this->getJson('/api/student/events')->assertOk()->assertJsonCount(0, 'events');
        Sanctum::actingAs($mtechMining->user);
        $this->getJson('/api/student/events')->assertOk()->assertJsonCount(1, 'events');
    }

    public function test_T7_2c_audience_posting_applicants_reaches_only_non_withdrawn_applicants(): void
    {
        $cycle = $this->cycle();
        $posting = $this->floatPosting($cycle, 'Target');
        $other = $this->floatPosting($cycle, 'Other');

        $applied = $this->student();
        $withdrawn = $this->student();
        $otherPostingOnly = $this->student();
        $appliedSuspended = $this->student([], active: false);
        $notApplied = $this->student();
        foreach ([$applied, $withdrawn, $otherPostingOnly, $appliedSuspended, $notApplied] as $s) {
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
        }
        $this->apply($applied, $posting);
        $this->apply($withdrawn, $posting, 'withdrawn');
        $this->apply($otherPostingOnly, $other);
        $this->apply($appliedSuspended, $posting);

        Mail::fake(); // forget the float (E2) mails
        $this->publishEvent(['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $posting->id]]);

        $this->assertSame($this->emails([$applied]), $this->e6BccRecipients());
        $this->assertSame($this->emails([$applied]), $this->e6LoggedRecipients());

        Sanctum::actingAs($withdrawn->user);
        $this->getJson('/api/student/events')->assertOk()->assertJsonCount(0, 'events');
        Sanctum::actingAs($applied->user);
        $this->getJson('/api/student/events')->assertOk()->assertJsonCount(1, 'events');
    }

    // ------------------------------------------------------------------ T7.3

    public function test_T7_3_announce_only_no_rsvp_publish_sends_e6_and_drafts_are_invisible(): void
    {
        // No RSVP route anywhere (spec B7: "Announce-only, no RSVP").
        $rsvp = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($r) => str_contains(strtolower($r->uri()), 'rsvp') || str_contains(strtolower((string) $r->getActionName()), 'rsvp'));
        $this->assertCount(0, $rsvp, 'unexpected RSVP route(s): '.$rsvp->map->uri()->implode(', '));

        $student = $this->student();
        Sanctum::actingAs($this->admin);
        $startsAt = now('Asia/Kolkata')->addDays(3)->setTime(15, 0);
        $id = $this->postJson('/api/admin/events', [
            'title' => 'Draft talk', 'event_type' => 'ppt', 'starts_at' => $startsAt->clone()->utc()->toIso8601String(),
            'description' => '<p>Hello <b>there</b></p><script>alert(1)</script>',
        ])->assertCreated()->json('event.id');
        Mail::assertNothingQueued();
        Mail::assertNothingSent();

        // Draft: invisible to students in the feed and in the calendar.
        $month = $startsAt->format('Y-m');
        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/events')->assertOk()->assertJsonCount(0, 'events');
        $this->assertSame([], collect($this->getJson("/api/student/calendar?month={$month}")->assertOk()->json('items'))->where('type', 'event')->values()->all());
        $this->postJson("/api/student/events/{$id}/rsvp")->assertStatus(404);
        $this->getJson('/api/student/dashboard')->assertOk()->assertJsonCount(0, 'upcoming');

        // Admin calendar shows the draft, marked as draft.
        Sanctum::actingAs($this->admin);
        $adminItem = collect($this->getJson("/api/admin/calendar?month={$month}")->json('items'))->firstWhere('type', 'event');
        $this->assertNotNull($adminItem);
        $this->assertTrue($adminItem['draft']);

        // Publish → E6 (one BCC message) and now visible.
        $this->postJson("/api/admin/events/{$id}/publish")->assertOk();
        Mail::assertQueued(EventAnnouncedMail::class, 1);
        Mail::assertQueued(EventAnnouncedMail::class, fn ($m) => $m->hasBcc($student->user->email));
        $html = Mail::queued(EventAnnouncedMail::class)->first()->render();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'event HTML must not reach the mail as markup');
        $this->assertStringContainsString('Draft talk', $html);

        Sanctum::actingAs($student->user);
        $this->getJson('/api/student/events')->assertOk()->assertJsonCount(1, 'events');
        $this->assertCount(1, collect($this->getJson("/api/student/calendar?month={$month}")->json('items'))->where('type', 'event'));
    }

    // ------------------------------------------------------------------ T7.4

    public function test_T7_4_company_linked_event_shows_for_that_company_only(): void
    {
        $companyA = Company::create(['name' => 'Alpha', 'hr_name' => 'HR', 'hr_email' => 'hr@alpha.qa.test']);
        $companyB = Company::create(['name' => 'Beta', 'hr_name' => 'HR', 'hr_email' => 'hr@beta.qa.test']);
        $userA = User::factory()->create(['role' => 'company', 'company_id' => $companyA->id]);
        $userB = User::factory()->create(['role' => 'company', 'company_id' => $companyB->id]);

        $published = $this->publishEvent(['title' => 'Alpha PPT', 'company_id' => $companyA->id]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/events', ['title' => 'Alpha draft', 'event_type' => 'ppt', 'starts_at' => now()->addDay()->toIso8601String(), 'company_id' => $companyA->id])->assertCreated();
        $this->publishEvent(['title' => 'Unlinked']);

        Sanctum::actingAs($userA);
        $this->getJson('/api/company/events')->assertOk()
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.id', $published->id)
            ->assertJsonPath('events.0.title', 'Alpha PPT');

        Sanctum::actingAs($userB);
        $response = $this->getJson('/api/company/events')->assertOk()->assertJsonCount(0, 'events');
        $this->assertStringNotContainsString('Alpha', $response->getContent());
    }
}
