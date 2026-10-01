<?php

namespace Tests\Feature;

use App\Mail\PortalNoticeMail;
use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private JobPosting $posting;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);

        $cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);

        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [
                    ['type' => 'aptitude_test', 'enabled' => true],
                    ['type' => 'technical_interview', 'enabled' => true],
                    ['type' => 'hr_interview', 'enabled' => true],
                ],
            ],
        ]);

        for ($i = 0; $i < 6; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i + 1), 'phone' => '9000000000']);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $this->students[] = $s;
        }

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        foreach ($this->students as $s) {
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => $s->roll_no === '22JE0001' ? 'pending' : 'approved']);
            Application::create([
                'job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id,
                'status' => 'applied', 'used_unverified_resume' => $resume->status !== 'approved', 'applied_at' => now(),
            ]);
        }
    }

    private function round(int $index)
    {
        return $this->posting->rounds()->get()[$index];
    }

    private function closeApplications(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    public function test_results_are_refused_while_applications_are_open(): void
    {
        $r1 = $this->round(0);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])
            ->assertStatus(422);
    }

    public function test_company_proposal_to_publish_flow_with_mails_and_visibility(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);

        // Company proposes; an unknown roll is refused outright.
        Sanctum::actingAs($this->companyUser);
        $this->postJson("/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals", [
            'kind' => 'shortlist', 'entries' => [['roll_no' => '22JE0001'], ['roll_no' => '99XX0000']],
        ])->assertStatus(422)->assertJsonPath('errors.0.roll_no', '99XX0000');

        $this->postJson("/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals", [
            'kind' => 'shortlist', 'entries' => [['roll_no' => '22je0001'], ['roll_no' => '22JE0002'], ['roll_no' => '22JE0003']],
        ])->assertCreated();
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->admin->email));

        // Admin approves → drafts only.
        Sanctum::actingAs($this->admin);
        $proposal = ShortlistProposal::sole();
        $this->getJson('/api/admin/proposals?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
        $this->patchJson("/api/admin/proposals/{$proposal->id}", ['status' => 'approved'])->assertOk();
        $this->assertSame(3, ApplicationRoundResult::whereNull('published_at')->count());
        $this->patchJson("/api/admin/proposals/{$proposal->id}", ['status' => 'approved'])->assertStatus(422);

        // Students and the company see nothing yet.
        Sanctum::actingAs($this->students[0]->user);
        $trail = $this->getJson('/api/student/applications')->json('applications.0.trail');
        $this->assertFalse($trail[0]['published']);

        Sanctum::actingAs($this->companyUser);
        $this->assertEmpty($this->getJson("/api/company/postings/{$this->posting->id}/applicants")->json('applicants.0.rounds'));

        // Publish, rejecting everyone else in the round.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/publish", ['reject_remaining' => true])
            ->assertOk()
            ->assertJsonPath('counts.selected', 3)
            ->assertJsonPath('counts.rejected', 3);

        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'selected' && $m->hasBcc($this->students[0]->user->email));
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'rejected' && $m->hasBcc($this->students[5]->user->email));
        $this->assertSame('completed', $r1->fresh()->status);
        $this->assertSame('ongoing', $this->round(1)->status);
        $this->assertSame(1, AuditLog::where('action', 'round.publish')->count());

        Sanctum::actingAs($this->students[0]->user);
        $trail = $this->getJson('/api/student/applications')->json('applications.0.trail');
        $this->assertTrue($trail[0]['published']);
        $this->assertSame('selected', $trail[0]['result']);

        Sanctum::actingAs($this->companyUser);
        $applicants = $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertOk();
        $this->assertSame('selected', $applicants->json("applicants.0.rounds.{$r1->id}.result"));
        $this->assertStringNotContainsString('used_unverified_resume', $applicants->getContent());
        $this->assertStringNotContainsString('9000000000', $applicants->getContent());
    }

    public function test_admin_upload_reports_unknowns_and_warns_about_previous_round(): void
    {
        $this->closeApplications();
        $r2 = $this->round(1);
        // An old sheet with a third "rank" column still imports; the column is ignored (D90).
        $csv = "roll_no,result,waitlist_rank\n22JE0001,selected,\n22JE0002,waitlisted,2\n22JE0003,waitlisted,1\nNOPE1,selected,";

        $response = $this->post("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/results", [
            'file' => UploadedFile::fake()->createWithContent('r.csv', $csv),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(3, $response->json('written'));
        $this->assertSame('NOPE1', $response->json('errors.0.roll_no'));
        $this->assertCount(3, $response->json('warnings'));

        $waitlisted = ApplicationRoundResult::where('result', 'waitlisted')->get()->map(fn ($r) => $r->application->studentProfile->roll_no)->all();
        $this->assertEqualsCanonicalizing(['22JE0002', '22JE0003'], $waitlisted);

        // Waitlists have no order: there is no reorder endpoint and no rank anywhere in the payload.
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/waitlist/reorder", ['ordered_application_ids' => [1]])->assertStatus(405);
        $this->assertStringNotContainsString('rank', $this->getJson("/api/admin/postings/{$this->posting->id}/pipeline")->getContent());
    }

    public function test_waitlist_is_unranked_and_any_waitlisted_candidate_can_move_on(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = "/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}";

        $this->postJson("$url/results", ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'],
            ['roll_no' => '22JE0002', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0003', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0004', 'result' => 'waitlisted'],
        ]])->assertOk();

        // Publishing never pushes anyone forward by itself (no suggested promotions, D90).
        $counts = $this->postJson("$url/publish")->assertOk()->json('counts');
        $this->assertArrayNotHasKey('suggested_promotions', $counts);
        $r2 = $this->round(1);
        $this->assertSame(0, ApplicationRoundResult::where('posting_round_id', $r2->id)->count());

        // The admin moves the LAST waitlisted student on: promoted in round 1 (QA F-002), round 2 untouched.
        $a4 = Application::whereHas('studentProfile', fn ($q) => $q->where('roll_no', '22JE0004'))->sole();
        $this->postJson("$url/waitlist/{$a4->id}/promote")->assertOk();
        $this->assertSame('selected', ApplicationRoundResult::where('application_id', $a4->id)->where('posting_round_id', $r1->id)->value('result'));
        $this->assertSame(0, ApplicationRoundResult::where('posting_round_id', $r2->id)->count());

        // Now in round 2's pool: entering their round-2 result raises no warning; someone never selected does.
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/results", ['entries' => [
            ['roll_no' => '22JE0004', 'result' => 'selected'],
        ]])->assertOk()->assertJsonPath('written', 1)->assertJsonCount(0, 'warnings');
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/results", ['entries' => [
            ['roll_no' => '22JE0005', 'result' => 'selected'],
        ]])->assertOk()->assertJsonCount(1, 'warnings');

        // The waitlist mail is one BCC message without any position.
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'waitlisted' && count($m->bcc) === 3 && ! str_contains($m->render(), 'position'));

        Sanctum::actingAs($this->students[3]->user);
        $this->assertStringNotContainsString('rank', $this->getJson('/api/student/applications')->getContent());
    }

    public function test_readd_protocol_requires_confirmation_and_notifies_company(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = "/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}";
        $this->postJson("$url/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("$url/publish", ['reject_remaining' => true])->assertOk();

        $rejected = Application::whereHas('studentProfile', fn ($q) => $q->where('roll_no', '22JE0005'))->sole();

        // A normal upload cannot overwrite a published rejection.
        $this->postJson("$url/results", ['roll_nos' => ['22JE0005'], 'result' => 'selected'])->assertOk()->assertJsonPath('written', 0);
        $this->postJson("$url/addendum", ['roll_nos' => ['22JE0005']])->assertOk()->assertJsonPath('errors.0.roll_no', '22JE0005');

        $this->postJson("$url/readd/{$rejected->id}", ['remark' => 'Mistake'])->assertStatus(422);
        $this->postJson("$url/readd/{$rejected->id}", ['confirm' => true, 'remark' => 'Scored wrongly'])->assertOk();

        $row = ApplicationRoundResult::where('application_id', $rejected->id)->where('posting_round_id', $r1->id)->sole();
        $this->assertSame('selected', $row->result);
        $this->assertTrue($row->is_addendum);
        $this->assertNull($row->published_at);
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo('hr@acme.test') && str_contains($m->headline, '22JE0005'));
        $this->assertSame(1, AuditLog::where('action', 'round.readd')->count());

        // Addendum for a not-yet-decided student lands as a flagged draft; publishing informs them.
        $this->postJson("$url/publish")->assertOk()->assertJsonPath('counts.selected', 1);
    }

    public function test_final_round_publish_goes_through_results_page(): void
    {
        $this->closeApplications();
        $final = $this->round(2);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$final->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$final->id}/publish")->assertStatus(422);
    }

    public function test_attendance_and_company_isolation(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/attendance", [
            'roll_nos_present' => ['22JE0001', '22JE0002'],
            'roll_nos_absent' => ['22JE0003'],
        ])->assertOk();
        $this->assertSame(2, ApplicationRoundResult::where('attendance', 'yes')->count());
        $this->assertSame(1, ApplicationRoundResult::where('attendance', 'no')->count());

        $otherCompany = Company::create(['name' => 'Other', 'hr_name' => 'HR', 'hr_email' => 'hr@other.test']);
        $other = User::factory()->create(['role' => 'company', 'company_id' => $otherCompany->id]);
        Sanctum::actingAs($other);
        $this->getJson('/api/company/postings')->assertOk()->assertJsonCount(0, 'postings');
        $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertNotFound();

        Sanctum::actingAs($this->companyUser);
        $this->getJson('/api/company/postings')->assertOk()->assertJsonPath('postings.0.applicant_count', 6);

        // Contact details appear only when the admin turns sharing on.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$this->posting->id}", ['share_contact_details' => true])->assertOk();
        Sanctum::actingAs($this->companyUser);
        $this->assertSame('9000000000', $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->json('applicants.0.phone'));
    }

    public function test_attendance_rows_still_get_regret_on_reject_remaining(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = "/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}";
        $this->postJson("$url/attendance", ['roll_nos_present' => ['22JE0001', '22JE0002', '22JE0003', '22JE0004', '22JE0005', '22JE0006']])->assertOk();
        $this->postJson("$url/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();

        $this->postJson("$url/publish", ['reject_remaining' => true])->assertOk()->assertJsonPath('counts.rejected', 5);
        $this->assertSame(0, ApplicationRoundResult::where('result', 'pending')->count());
        // Two messages, not six: one to the selected student, one BCC regret to the other five (D89).
        Mail::assertQueued(RoundResultMail::class, 2);
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'rejected' && count($m->bcc) === 5 && $m->name === null);
        $this->assertSame(6, EmailLog::where('template', 'emails.round-result')->count());
        $this->assertSame(6, ApplicationRoundResult::where('attendance', 'yes')->count(), 'attendance is kept');

        // Nothing new → 422.
        $this->postJson("$url/publish", ['reject_remaining' => true])->assertStatus(422);
    }

    public function test_rounds_publish_in_order_and_structure_freezes_after_publish(): void
    {
        $this->closeApplications();
        [$r1, $r2] = [$this->round(0), $this->round(1)];
        $base = "/api/admin/postings/{$this->posting->id}";

        $this->postJson("$base/rounds/{$r2->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("$base/rounds/{$r2->id}/publish")->assertStatus(422);

        $this->postJson("$base/rounds/{$r1->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("$base/rounds/{$r1->id}/publish", ['reject_remaining' => true])->assertOk();
        $this->postJson("$base/rounds/{$r2->id}/publish")->assertOk();

        $this->postJson("$base/rounds/reorder", ['ordered_round_ids' => $this->posting->rounds()->pluck('id')->reverse()->values()->all()])->assertStatus(422);
        $this->patchJson("$base/reopen")->assertStatus(422);
    }

    public function test_waitlist_removal_and_draft_removal(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = "/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}";
        $this->postJson("$url/results", ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'],
            ['roll_no' => '22JE0002', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0003', 'result' => 'waitlisted'],
        ]])->assertOk();
        $this->postJson("$url/publish")->assertOk();

        // A draft promotion of a waitlisted student can be taken back before it is published.
        $r2 = $this->round(1);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/results", ['entries' => [['roll_no' => '22JE0002', 'result' => 'selected']]])->assertOk();
        $draft = ApplicationRoundResult::where('posting_round_id', $r2->id)->sole();
        $this->deleteJson("/api/admin/postings/{$this->posting->id}/rounds/{$r2->id}/results/{$draft->application_id}")->assertOk();
        $this->assertSame(0, ApplicationRoundResult::where('posting_round_id', $r2->id)->count());

        // Remove 22JE0003 from the published waitlist → published "not selected" + regret mail.
        $a3 = Application::whereHas('studentProfile', fn ($q) => $q->where('roll_no', '22JE0003'))->sole();
        $this->deleteJson("$url/waitlist/{$a3->id}")->assertOk();
        $row = ApplicationRoundResult::where('application_id', $a3->id)->where('posting_round_id', $r1->id)->sole();
        $this->assertSame('rejected', $row->result);
        $this->assertNotNull($row->published_at);
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'rejected' && $m->hasBcc($this->students[2]->user->email));

        // An addendum publish of round 1 does not create anything in round 2.
        $this->postJson("$url/addendum", ['roll_nos' => ['22JE0004']])->assertOk();
        $this->postJson("$url/publish")->assertOk();
        $this->assertSame(0, ApplicationRoundResult::where('posting_round_id', $r2->id)->count());
        $this->assertTrue(ApplicationRoundResult::whereHas('application.studentProfile', fn ($q) => $q->where('roll_no', '22JE0004'))->sole()->is_addendum);
    }

    public function test_proposal_guards_dedupe_and_withdrawn_students_are_not_published(): void
    {
        $r1 = $this->round(0);

        Sanctum::actingAs($this->companyUser);
        $this->postJson("/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals", ['kind' => 'shortlist', 'entries' => [['roll_no' => '22JE0001']]])
            ->assertStatus(422);

        $this->closeApplications();
        Sanctum::actingAs($this->companyUser);
        $this->postJson("/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals", ['kind' => 'shortlist', 'entries' => [['roll_no' => '22JE0001'], ['roll_no' => '22je0001'], ['roll_no' => '22JE0002']]])
            ->assertCreated();
        $this->assertCount(2, ShortlistProposal::sole()->payload);

        // A round with a pending proposal cannot be deleted.
        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}")->assertStatus(422);

        $this->patchJson('/api/admin/proposals/'.ShortlistProposal::sole()->id, ['status' => 'approved'])->assertOk();

        // 22JE0002 withdraws after the draft was written (data fix-up scenario) → not published, not mailed.
        Application::whereHas('studentProfile', fn ($q) => $q->where('roll_no', '22JE0002'))->update(['status' => 'withdrawn']);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/publish")->assertOk()->assertJsonPath('counts.selected', 1);
        Mail::assertNotQueued(RoundResultMail::class, fn ($m) => $m->hasTo($this->students[1]->user->email) || $m->hasBcc($this->students[1]->user->email));
    }
}
