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

/**
 * Superset parity S6: job profile management (schedule for later, visit date, stage venue and types, help text,
 * attached documents, activity, communication log, send applicant list, status flags, list search).
 */
class JobProfileManagementTest extends TestCase
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

    private function newPosting(array $extra = []): JobPosting
    {
        $company = Company::create(['name' => 'Zeta', 'hr_name' => 'HR', 'hr_email' => 'hr@zeta.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'Analyst', 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => [
                'jobTitle' => 'Analyst',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->posting->placement_cycle_id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ] + $extra)->assertCreated()->json('posting.id');

        return JobPosting::findOrFail($id);
    }

    public function test_schedule_for_later_hides_the_job_profile_until_the_scheduler_opens_it_once(): void
    {
        Mail::fake();
        $opensAt = now()->addHours(2);
        $posting = $this->newPosting(['scheduled_open_at' => $opensAt->toIso8601String(), 'visit_date' => now()->addDays(10)->toDateString()]);
        $this->assertTrue($posting->isScheduled());
        $this->assertFalse($posting->acceptsApplications());
        Mail::assertNothingQueued();

        // Deadline must come after the opening time.
        $this->patchJson("/api/admin/postings/{$posting->id}", ['scheduled_open_at' => now()->addDays(5)->toIso8601String()])->assertStatus(422);
        $this->patchJson("/api/admin/postings/{$posting->id}", ['scheduled_open_at' => now()->addHours(3)->toIso8601String()])->assertOk();

        // Hidden from students: board, detail, apply.
        Sanctum::actingAs($this->students[1]->user);
        $this->assertNotContains($posting->id, collect($this->getJson('/api/student/postings')->json('postings'))->pluck('id')->all());
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();

        // Not due yet → nothing happens.
        $this->artisan('placement:open-scheduled')->assertSuccessful();
        $this->assertNotNull($posting->fresh()->scheduled_open_at);

        $this->travel(4)->hours();
        $this->artisan('placement:open-scheduled')->assertSuccessful();
        $this->artisan('placement:open-scheduled')->assertSuccessful(); // a second run opens nothing
        $this->assertNull($posting->fresh()->scheduled_open_at);
        $this->assertSame(1, AuditLog::where('action', 'posting.scheduled_open')->count());
        $this->assertSame(1, AuditLog::where('action', 'posting.notify')->where('subject_id', $posting->id)->count());
        $this->assertTrue(EmailLog::where('job_posting_id', $posting->id)->where('kind', 'opening')->exists());

        Sanctum::actingAs($this->students[1]->user);
        $this->getJson("/api/student/postings/{$posting->id}")->assertOk()->assertJsonPath('posting.visit_date', $posting->fresh()->visit_date->toDateString());
    }

    public function test_open_now_clears_the_schedule_and_mails(): void
    {
        Mail::fake();
        $posting = $this->newPosting(['scheduled_open_at' => now()->addDay()->toIso8601String()]);
        $this->postJson("/api/admin/postings/{$posting->id}/open-now")->assertOk();
        $this->assertNull($posting->fresh()->scheduled_open_at);
        $this->postJson("/api/admin/postings/{$posting->id}/open-now")->assertStatus(422);
        $this->assertTrue(AuditLog::where('action', 'posting.open_now')->exists());
    }

    public function test_stage_venue_new_types_and_question_help_text(): void
    {
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds", ['name' => 'Coding test', 'round_type' => 'online_test', 'venue' => 'Lab 3'])->assertCreated();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds", ['name' => 'Case study', 'round_type' => 'take_home_assignment'])->assertCreated();
        $round = $this->posting->rounds()->where('round_type', 'online_test')->sole();
        $this->assertSame('Lab 3', $round->venue);
        $this->patchJson("/api/admin/postings/{$this->posting->id}/rounds/{$round->id}", ['venue' => 'NLHC 201'])->assertOk();
        $this->assertSame('NLHC 201', $round->fresh()->venue);

        $this->patchJson("/api/admin/postings/{$this->posting->id}", ['questions' => [
            ['question' => 'Preferred location?', 'help_text' => 'Pick the city you would join', 'qtype' => 'text', 'required' => false],
        ]])->assertOk();

        Sanctum::actingAs($this->students[1]->user);
        $detail = $this->getJson("/api/student/postings/{$this->posting->id}")->assertOk();
        $this->assertSame('Pick the city you would join', $detail->json('posting.questions.0.help_text'));
    }

    public function test_attached_documents_are_pdf_only_and_audited(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $pdf = UploadedFile::fake()->create('jd.pdf', 200, 'application/pdf');
        $id = $this->post("/api/admin/postings/{$this->posting->id}/documents", ['title' => 'Job description', 'file' => $pdf], ['Accept' => 'application/json'])
            ->assertCreated()->json('document.id');
        $this->post("/api/admin/postings/{$this->posting->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('a.docx', 10)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/admin/postings/{$this->posting->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);

        $this->getJson("/api/admin/postings/{$this->posting->id}/documents")->assertOk()->assertJsonCount(1, 'documents')->assertJsonMissingPath('documents.0.file_path');
        $this->get("/api/admin/postings/{$this->posting->id}/documents/{$id}")->assertOk();
        $this->assertTrue(AuditLog::where('action', 'posting.document_add')->exists());

        foreach ([$this->students[1]->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/admin/postings/{$this->posting->id}/documents/{$id}")->assertForbidden();
            $this->postJson("/api/admin/postings/{$this->posting->id}/documents", [])->assertForbidden();
        }

        // Students who can see the job profile download it; others get 404.
        Sanctum::actingAs($this->students[1]->user);
        $this->getJson("/api/student/postings/{$this->posting->id}")->assertJsonPath('posting.documents.0.title', 'Job description');
        $this->get("/api/student/postings/{$this->posting->id}/documents/{$id}")->assertOk();
        $outsider = StudentProfile::factory()->create(['roll_no' => '22JE0999']);
        Sanctum::actingAs($outsider->user);
        $this->getJson("/api/student/postings/{$this->posting->id}/documents/{$id}")->assertNotFound();

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/admin/postings/{$this->posting->id}/documents/{$id}")->assertOk();
        $this->assertTrue(AuditLog::where('action', 'posting.document_remove')->exists());
    }

    public function test_activity_and_communication_log_list_this_job_profile_only(): void
    {
        Mail::fake();
        $this->closeApplications();
        $r1 = $this->round(0);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$r1->id}/publish", ['reject_remaining' => true])->assertOk();

        $activity = collect($this->getJson("/api/admin/postings/{$this->posting->id}/activity")->assertOk()->json('activity'));
        $this->assertTrue($activity->contains('label', 'Stage shortlist published'));
        $this->assertTrue($activity->contains('label', 'Closed for applications'));
        $this->assertTrue($activity->contains('label', 'Opened for applications'));

        $messages = collect($this->getJson("/api/admin/postings/{$this->posting->id}/communications")->assertOk()->json('messages'));
        $this->assertTrue($messages->contains('kind', 'stage_result'));
        $this->assertTrue($messages->contains('kind', 'opening'));
        $this->assertSame(6, $messages->where('kind', 'stage_result')->sum('recipients'));
        $this->getJson("/api/admin/postings/{$this->posting->id}/communications?search=zzzz")->assertJsonPath('meta.total', 0);

        $other = $this->newPosting();
        $this->getJson("/api/admin/postings/{$other->id}/communications")->assertOk()->assertJsonMissing(['kind' => 'stage_result']);
    }

    public function test_send_applicant_list_mails_a_signed_company_safe_link(): void
    {
        Mail::fake();
        $this->postJson("/api/admin/postings/{$this->posting->id}/send-applicant-list", ['note' => 'Please confirm the test slots.'])->assertOk();
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo('hr@acme.test') && str_contains((string) $m->actionUrl, '/api/company-exports/'));
        $this->assertTrue(AuditLog::where('action', 'posting.send_applicant_list')->exists());
        $this->assertTrue(EmailLog::where('job_posting_id', $this->posting->id)->where('kind', 'applicant_list')->exists());

        $url = null;
        Mail::assertQueued(PortalNoticeMail::class, function ($m) use (&$url) {
            $url = $m->actionUrl;

            return true;
        });
        $this->app['auth']->forgetGuards();
        $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
        $this->get($path)->assertOk();
        $this->get(parse_url($url, PHP_URL_PATH))->assertForbidden(); // unsigned
    }

    public function test_list_search_and_status_flags(): void
    {
        $postings = collect($this->getJson('/api/admin/postings?search=acme')->assertOk()->json('postings'));
        $this->assertSame([$this->posting->id], $postings->pluck('id')->all());
        $this->assertSame(0, count($this->getJson('/api/admin/postings?search=nomatch')->json('postings')));
        $this->assertFalse($postings->first()['any_stage_published']);
        $this->assertFalse($postings->first()['is_scheduled']);

        foreach ([$this->students[1]->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/admin/postings/{$this->posting->id}/activity")->assertForbidden();
            $this->getJson("/api/admin/postings/{$this->posting->id}/communications")->assertForbidden();
            $this->postJson("/api/admin/postings/{$this->posting->id}/send-applicant-list")->assertForbidden();
            $this->postJson("/api/admin/postings/{$this->posting->id}/open-now")->assertForbidden();
        }
    }
}
