<?php

namespace Tests\Feature\ParityVerify;

use App\Mail\BroadcastMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Notice;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Independent QA probes for Superset parity milestone S7 (Notices, stage emails, Surveys).
 * A failing test here is a finding; the application code is not changed by this file.
 */
class S7VerifyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private Company $company;

    private PlacementCycle $cycle;

    private JobPosting $posting;

    private PostingRound $round;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $this->company->id, 'email' => 'hr@acme.test']);

        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $jnf = Jnf::create([
            'company_id' => $this->company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
            ],
        ]);

        // 0-4 enrolled (0-3 applied); 5 other branch, batch 2028, not enrolled; 6 enrolled + applied but WITHDRAWN.
        for ($i = 0; $i < 7; $i++) {
            $s = StudentProfile::factory()->create([
                'roll_no' => sprintf('22JE%04d', $i + 1),
                'branch' => $i === 5 ? 'Electrical Engineering' : 'Computer Science & Engineering',
                'graduating_batch' => $i === 5 ? 2028 : 2027,
            ]);
            if ($i !== 5) {
                CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            }
            $this->students[] = $s;
        }

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();
        $this->round = $this->posting->rounds()->orderBy('sort_order')->first();

        // 0: published selected, 1: published waitlisted, 2: DRAFT selected, 3: published rejected, 6: published selected but withdrawn.
        foreach ([0 => 'selected', 1 => 'waitlisted', 2 => 'selected', 3 => 'rejected', 6 => 'selected'] as $i => $result) {
            $s = $this->students[$i];
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $application = Application::create([
                'job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id,
                'status' => $i === 6 ? 'withdrawn' : 'applied', 'applied_at' => now(),
            ]);
            ApplicationRoundResult::create([
                'application_id' => $application->id, 'posting_round_id' => $this->round->id, 'result' => $result,
                'published_at' => $i === 2 ? null : now(),
            ]);
        }
    }

    // ---------------------------------------------------------------- helpers

    private function asAdmin(): void
    {
        Sanctum::actingAs($this->admin);
    }

    private function asStudent(int $i): void
    {
        Sanctum::actingAs($this->students[$i]->user);
    }

    private function asGuest(): void
    {
        auth()->forgetGuards();
        $this->app['auth']->guard('sanctum')->forgetUser();
    }

    private function notice(array $audiences, string $body = '<p>Hall <b>A</b></p>', bool $publish = true, bool $email = false): Notice
    {
        $this->asAdmin();
        $id = $this->postJson('/api/admin/notices', ['title' => 'Venue', 'body' => $body, 'audiences' => $audiences])->assertCreated()->json('notice.id');
        if ($publish) {
            $this->postJson("/api/admin/notices/{$id}/publish", ['send_email' => $email])->assertOk();
        }

        return Notice::findOrFail($id);
    }

    private function cycleAudience(): array
    {
        return [['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]];
    }

    private function survey(array $questions, array $settings = [], bool $publish = true, bool $email = false): Survey
    {
        $this->asAdmin();
        $id = $this->postJson('/api/admin/surveys', ['title' => 'Probe survey', 'welcome_text' => '<p>Hi</p>'])->assertCreated()->json('survey.id');
        $this->putJson("/api/admin/surveys/{$id}", $settings + [
            'questions' => $questions,
            'audiences' => $this->cycleAudience(),
        ])->assertOk();
        if ($publish) {
            $this->postJson("/api/admin/surveys/{$id}/publish", ['send_email' => $email])->assertOk();
        }

        return Survey::findOrFail($id);
    }

    private function qid(Survey $survey, string $type): int
    {
        return (int) $survey->questions()->where('qtype', $type)->value('id');
    }

    private function allTypesRequired(): array
    {
        return [
            ['qtype' => 'mcq_single', 'question' => 'Single', 'options' => ['A', 'B'], 'required' => true],
            ['qtype' => 'mcq_multi', 'question' => 'Multi', 'options' => ['A', 'B', 'C'], 'required' => true],
            ['qtype' => 'text', 'question' => 'Text', 'required' => true],
            ['qtype' => 'yes_no', 'question' => 'YN', 'required' => true],
            ['qtype' => 'dropdown', 'question' => 'Drop', 'options' => ['X', 'Y'], 'required' => true],
            ['qtype' => 'date', 'question' => 'Date', 'required' => true],
            ['qtype' => 'rating', 'question' => 'Rate', 'settings' => ['max' => 5], 'required' => true],
            ['qtype' => 'static_text', 'question' => 'Just text', 'required' => true],
            ['qtype' => 'rich_text', 'question' => 'Rich', 'required' => true],
            ['qtype' => 'file', 'question' => 'File', 'required' => true],
            ['qtype' => 'sequence', 'question' => 'Seq', 'options' => ['P', 'Q', 'R'], 'required' => true],
        ];
    }

    private function bccOnly(BroadcastMail $m, StudentProfile $s): bool
    {
        return $m->hasBcc($s->user->email) && ! $m->hasTo($s->user->email) && $m->hasTo((string) config('mail.from.address'));
    }

    // ---------------------------------------------------------------- notices

    public function test_P01_non_audience_student_gets_404_on_notice_read_and_attachment_and_draft_is_hidden(): void
    {
        $notice = $this->notice([['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $this->posting->id]]], publish: false);
        $this->post("/api/admin/notices/{$notice->id}/attachment", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();

        // Draft: even an audience member gets nothing.
        $this->asStudent(0);
        $this->postJson("/api/student/notices/{$notice->id}/read")->assertNotFound();
        $this->getJson("/api/student/notices/{$notice->id}/attachment")->assertNotFound();

        $this->asAdmin();
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertOk();

        $this->asStudent(4); // enrolled, never applied
        $this->postJson("/api/student/notices/{$notice->id}/read")->assertNotFound();
        $this->getJson("/api/student/notices/{$notice->id}/attachment")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/student/notices')->assertOk()->json('notices'));
        $this->assertDatabaseCount('notice_reads', 0);
    }

    public function test_P02_company_and_anonymous_cannot_reach_any_notice_route(): void
    {
        $notice = $this->notice([['audience_type' => 'all']]);

        Sanctum::actingAs($this->companyUser);
        $this->getJson('/api/student/notices')->assertForbidden();
        $this->postJson("/api/student/notices/{$notice->id}/read")->assertForbidden();
        $this->getJson("/api/student/notices/{$notice->id}/attachment")->assertForbidden();
        $this->getJson("/api/admin/notices/{$notice->id}")->assertForbidden();
        $this->getJson("/api/admin/notices/{$notice->id}/attachment")->assertForbidden();
        $this->getJson('/api/company/notices')->assertNotFound();

        $this->asGuest();
        $this->getJson('/api/student/notices')->assertUnauthorized();
        $this->postJson("/api/student/notices/{$notice->id}/read")->assertUnauthorized();
        $this->getJson('/api/admin/notices')->assertUnauthorized();
    }

    public function test_P03_round_audience_excludes_draft_rejected_and_withdrawn(): void
    {
        $notice = $this->notice([['audience_type' => 'round_results', 'audience_filter' => ['posting_round_id' => $this->round->id, 'results' => ['selected', 'waitlisted']]]], email: true);
        $ids = $notice->fresh()->audienceQuery()->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$this->students[0]->id, $this->students[1]->id], $ids);
        foreach ([2, 3, 4, 5, 6] as $i) {
            Mail::assertNotQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($this->students[$i]->user->email));
        }
        // The notice carries the job profile so the Communication Log lists it.
        $this->assertSame($this->posting->id, $notice->fresh()->job_posting_id);
        $this->assertSame(2, EmailLog::where('kind', 'notice')->where('job_posting_id', $this->posting->id)->count());
    }

    public function test_P04_notice_email_is_bcc_only_with_portal_address_in_to_and_plain_text_body(): void
    {
        $body = '<p>Report at <b>9</b></p><script>alert(1)</script><p>&lt;img src=x onerror=alert(2)&gt;</p>';
        $this->notice($this->cycleAudience(), $body, email: true);

        foreach ([0, 1, 2, 3, 4, 6] as $i) {
            Mail::assertQueued(BroadcastMail::class, fn (BroadcastMail $m) => $this->bccOnly($m, $this->students[$i]));
        }
        Mail::assertQueued(BroadcastMail::class, function (BroadcastMail $m) {
            $this->assertSame([], array_values(array_filter($m->to, fn ($r) => str_contains((string) ($r['address'] ?? ''), 'iitism.ac.in'))));
            $html = $m->render();
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringNotContainsString('<img', $html);
            $this->assertStringNotContainsString('<b>9</b>', $html);
            $this->assertStringContainsString('Report at 9', $html);

            return true;
        });
    }

    public function test_P05_student_notice_payload_has_no_counts_and_inactive_students_are_excluded(): void
    {
        $this->students[4]->user->update(['is_active' => false]);
        $notice = $this->notice($this->cycleAudience(), email: true);
        Mail::assertNotQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($this->students[4]->user->email));

        $this->asStudent(0);
        $row = $this->getJson('/api/student/notices')->assertOk()->json('notices.0');
        foreach (['audience_count', 'read_count', 'audiences', 'created_by'] as $key) {
            $this->assertArrayNotHasKey($key, $row, "student notice payload leaks {$key}");
        }
        $this->assertArrayNotHasKey('attachment_path', $row);
        $this->assertSame($notice->id, $row['id']);
    }

    public function test_P06_notice_writes_are_audited_with_before_and_after(): void
    {
        $notice = $this->notice($this->cycleAudience(), publish: false);
        $this->putJson("/api/admin/notices/{$notice->id}", ['title' => 'Venue 2', 'body' => null, 'audiences' => [['audience_type' => 'all']]])->assertOk();
        $this->post("/api/admin/notices/{$notice->id}/attachment", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->deleteJson("/api/admin/notices/{$notice->id}/attachment")->assertOk();
        $this->postJson("/api/admin/notices/{$notice->id}/publish")->assertOk();
        $this->deleteJson("/api/admin/notices/{$notice->id}")->assertOk();

        $update = AuditLog::where('action', 'notice.update')->sole();
        $this->assertSame('Venue', $update->before['title']);
        $this->assertSame('Venue 2', $update->after['title']);
        $this->assertSame(2, AuditLog::where('action', 'notice.attachment')->count());
        foreach (['notice.create', 'notice.publish', 'notice.delete'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->exists(), $action);
        }
    }

    public function test_P07_notice_attachment_rejects_disguised_file_and_is_private(): void
    {
        $notice = $this->notice($this->cycleAudience(), publish: false);
        $this->post("/api/admin/notices/{$notice->id}/attachment", ['file' => UploadedFile::fake()->create('evil.pdf', 10, 'text/html')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/admin/notices/{$notice->id}/attachment", ['file' => UploadedFile::fake()->create('ok.pdf', 5120, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $path = $notice->fresh()->attachment_path;
        Storage::disk('local')->assertExists($path);
        $this->assertStringStartsWith("notices/{$notice->id}/", $path);
        $this->assertArrayNotHasKey('attachment_path', $this->getJson("/api/admin/notices/{$notice->id}")->json('notice'));
    }

    // ---------------------------------------------------------------- stage email

    public function test_P08_stage_email_skips_draft_rejected_withdrawn_and_is_bcc_logged_audited(): void
    {
        config(['mail.bulk_batch_size' => 1]);
        $base = "/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}";
        $this->getJson("{$base}/message-audience")->assertOk()->assertExactJson(['selected' => 1, 'waitlisted' => 1]);

        $this->postJson("{$base}/email", ['subject' => 'Venue', 'message' => '<p>Hall B</p>', 'results' => ['selected', 'waitlisted']])->assertOk();
        Mail::assertQueued(BroadcastMail::class, 2); // one per batch of 1
        Mail::assertQueued(BroadcastMail::class, fn (BroadcastMail $m) => $this->bccOnly($m, $this->students[0]));
        Mail::assertQueued(BroadcastMail::class, fn (BroadcastMail $m) => $this->bccOnly($m, $this->students[1]));
        foreach ([2, 3, 4, 5, 6] as $i) {
            Mail::assertNotQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($this->students[$i]->user->email));
        }
        $this->assertSame(2, EmailLog::where('kind', 'stage_email')->where('job_posting_id', $this->posting->id)->count());
        $audit = AuditLog::where('action', 'stage.email')->sole();
        $this->assertSame(2, $audit->after['recipients']);
    }

    public function test_P09_stage_email_rejects_wrong_stage_and_draft_only_targets(): void
    {
        $other = PostingRound::where('job_posting_id', $this->posting->id)->where('id', '!=', $this->round->id)->first();
        // Stage 2 has no published decisions.
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$other->id}/email", ['subject' => 'x', 'message' => 'Hi', 'results' => ['selected']])->assertStatus(422);
        // Rejected is never a target.
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", ['subject' => 'x', 'message' => 'Hi', 'results' => ['rejected']])->assertStatus(422);
        // Only the draft selected (student 2) would match once the published selected row is unpublished.
        ApplicationRoundResult::where('result', 'selected')->update(['published_at' => null]);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", ['subject' => 'x', 'message' => 'Hi', 'results' => ['selected']])->assertStatus(422);
        Mail::assertNotQueued(BroadcastMail::class);
        // A stage of another job profile through this job profile's URL is 404.
        $this->postJson("/api/admin/postings/999999/rounds/{$this->round->id}/email", ['subject' => 'x', 'message' => 'Hi', 'results' => ['selected']])->assertNotFound();
    }

    public function test_P10_company_and_student_cannot_send_stage_email(): void
    {
        foreach ([$this->companyUser, $this->students[0]->user] as $user) {
            Sanctum::actingAs($user);
            $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", ['subject' => 'x', 'message' => 'Hi', 'results' => ['selected']])->assertForbidden();
            $this->getJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/message-audience")->assertForbidden();
        }
        Mail::assertNotQueued(BroadcastMail::class);
    }

    // ---------------------------------------------------------------- surveys: access

    public function test_P11_non_audience_company_and_anonymous_are_kept_out_of_every_survey_route(): void
    {
        $survey = $this->survey([['qtype' => 'yes_no', 'question' => 'OK?', 'required' => true]]);
        $q = $this->qid($survey, 'yes_no');
        $this->asStudent(0);
        $rid = $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'yes']])->assertCreated()->json('response.id');

        $this->asStudent(5); // not enrolled
        $this->getJson("/api/student/surveys/{$survey->id}")->assertNotFound();
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'yes']])->assertNotFound();
        $this->postJson("/api/student/surveys/{$survey->id}/responses/{$rid}", ['answers' => [$q => 'no']])->assertNotFound();
        $this->getJson("/api/student/surveys/{$survey->id}/responses/{$rid}/files/{$q}")->assertNotFound();

        Sanctum::actingAs($this->companyUser);
        $this->getJson("/api/student/surveys/{$survey->id}")->assertForbidden();
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'yes']])->assertForbidden();
        $this->postJson("/api/student/surveys/{$survey->id}/responses/{$rid}", ['answers' => [$q => 'no']])->assertForbidden();
        $this->getJson("/api/admin/surveys/{$survey->id}/report")->assertForbidden();

        $this->asGuest();
        $this->getJson('/api/student/surveys')->assertUnauthorized();
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'yes']])->assertUnauthorized();
        $this->getJson("/api/admin/surveys/{$survey->id}/export")->assertUnauthorized();

        $this->assertSame(1, SurveyResponse::count());
        $this->assertSame('yes', SurveyResponse::sole()->answers[(string) $q]);
    }

    public function test_P12_public_survey_any_active_student_never_company_or_inactive(): void
    {
        $survey = $this->survey([['qtype' => 'yes_no', 'question' => 'OK?']], ['is_public' => true]);
        $q = $this->qid($survey, 'yes_no');

        $this->asStudent(5); // outside the target audience, but the survey is public
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'yes']])->assertCreated();

        $this->students[4]->user->update(['is_active' => false]);
        $this->asStudent(4);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'yes']])->assertForbidden();

        Sanctum::actingAs($this->companyUser);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'yes']])->assertForbidden();
        $this->assertSame(1, SurveyResponse::count());
    }

    public function test_P13_multiple_audience_groups_union(): void
    {
        $this->asAdmin();
        $id = $this->postJson('/api/admin/surveys', ['title' => 'Union'])->json('survey.id');
        $this->putJson("/api/admin/surveys/{$id}", [
            'questions' => [['qtype' => 'text', 'question' => 'Q']],
            'audiences' => [
                ['audience_type' => 'batch', 'audience_filter' => ['batches' => [2028]]],
                ['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $this->posting->id]],
            ],
        ])->assertOk();
        $this->postJson("/api/admin/surveys/{$id}/publish")->assertOk();
        $ids = Survey::findOrFail($id)->audienceQuery()->pluck('id')->all();
        // batch 2028 = student 5; live applicants = 0,1,2,3 (6 withdrawn)
        $this->assertEqualsCanonicalizing([$this->students[0]->id, $this->students[1]->id, $this->students[2]->id, $this->students[3]->id, $this->students[5]->id], $ids);

        // Offer holders group.
        Offer::create([
            'application_id' => Application::where('student_profile_id', $this->students[3]->id)->value('id'),
            'student_profile_id' => $this->students[3]->id, 'company_id' => $this->company->id, 'job_posting_id' => $this->posting->id,
            'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'fulltime', 'ctc_annual' => 100, 'announced_at' => now(),
        ]);
        $this->postJson('/api/admin/audiences/preview', ['kind' => 'survey', 'audiences' => [['audience_type' => 'offer_holders', 'audience_filter' => ['job_posting_id' => $this->posting->id]]]])
            ->assertOk()->assertJsonPath('count', 1);
        // Stage audience is notices-only; surveys refuse it.
        $this->putJson("/api/admin/surveys/{$id}", ['audiences' => [['audience_type' => 'round_results', 'audience_filter' => ['posting_round_id' => $this->round->id, 'results' => ['selected']]]]])->assertStatus(422);
    }

    // ---------------------------------------------------------------- surveys: answers

    public function test_P14_every_mandatory_type_is_enforced_on_the_server(): void
    {
        $survey = $this->survey($this->allTypesRequired());
        $this->asStudent(0);
        $response = $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [
            $this->qid($survey, 'text') => '   ',
            $this->qid($survey, 'rich_text') => '<p> </p><p><br></p>',
            $this->qid($survey, 'mcq_multi') => [],
        ]])->assertStatus(422);

        $errors = array_keys($response->json('errors'));
        foreach (['mcq_single', 'mcq_multi', 'text', 'yes_no', 'dropdown', 'date', 'rating', 'rich_text', 'file', 'sequence'] as $type) {
            $this->assertContains('answers.'.$this->qid($survey, $type), $errors, "{$type} mandatory not enforced");
        }
        $this->assertNotContains('answers.'.$this->qid($survey, 'static_text'), $errors);
        $this->assertSame(0, SurveyResponse::count());
    }

    public function test_P15_choices_must_be_listed_options_and_values_valid(): void
    {
        $survey = $this->survey([
            ['qtype' => 'mcq_single', 'question' => 'Single', 'options' => ['A', 'B']],
            ['qtype' => 'mcq_multi', 'question' => 'Multi', 'options' => ['A', 'B', 'C']],
            ['qtype' => 'dropdown', 'question' => 'Drop', 'options' => ['X', 'Y']],
            ['qtype' => 'date', 'question' => 'Date'],
            ['qtype' => 'rating', 'question' => 'Rate', 'settings' => ['max' => 3]],
            ['qtype' => 'sequence', 'question' => 'Seq', 'options' => ['P', 'Q', 'R']],
            ['qtype' => 'yes_no', 'question' => 'YN'],
            ['qtype' => 'text', 'question' => 'T'],
        ]);
        $this->asStudent(0);
        $url = "/api/student/surveys/{$survey->id}/responses";
        $bad = [
            ['mcq_single', 'Z'], ['mcq_single', ['A']], ['mcq_multi', ['A', 'Z']], ['mcq_multi', ['A', 'A']], ['dropdown', 'Z'], ['dropdown', ['X']],
            ['date', '2026-02-30'], ['date', '30-01-2026'], ['rating', 0], ['rating', 4], ['rating', 2.5],
            ['sequence', ['P', 'Q']], ['sequence', ['P', 'Q', 'Q']], ['sequence', ['P', 'Q', 'R', 'S']], ['yes_no', true], ['yes_no', 'Yes'],
            ['text', str_repeat('a', 5001)],
        ];
        foreach ($bad as [$type, $value]) {
            $this->postJson($url, ['answers' => [$this->qid($survey, $type) => $value]])
                ->assertStatus(422, "{$type} accepted ".json_encode($value));
        }
        $this->assertSame(0, SurveyResponse::count());

        $this->postJson($url, ['answers' => [
            $this->qid($survey, 'mcq_single') => 'B', $this->qid($survey, 'mcq_multi') => ['C', 'A'], $this->qid($survey, 'dropdown') => 'Y',
            $this->qid($survey, 'date') => '2026-02-28', $this->qid($survey, 'rating') => 3, $this->qid($survey, 'sequence') => ['R', 'P', 'Q'],
            $this->qid($survey, 'yes_no') => 'no', $this->qid($survey, 'text') => '=1+1',
        ]])->assertCreated();
        $answers = SurveyResponse::sole()->answers;
        $this->assertSame(['A', 'C'], $answers[(string) $this->qid($survey, 'mcq_multi')]);
    }

    public function test_P16_file_upload_limits_type_and_private_storage(): void
    {
        $survey = $this->survey([['qtype' => 'file', 'question' => 'Offer letter', 'required' => true]]);
        $q = $this->qid($survey, 'file');
        $this->asStudent(0);
        $url = "/api/student/surveys/{$survey->id}/responses";
        $send = fn (UploadedFile $f) => $this->post($url, ['answers' => json_encode([]), 'files' => [$q => $f]], ['Accept' => 'application/json']);

        $send(UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf'))->assertStatus(422);
        $send(UploadedFile::fake()->create('page.pdf', 10, 'text/html'))->assertStatus(422);       // disguised HTML
        $send(UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'))->assertStatus(422);
        $send(UploadedFile::fake()->create('x.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->assertStatus(422);
        // A forged path in the JSON answers must never be stored for a File Upload question.
        $this->postJson($url, ['answers' => [$q => ['path' => '../../.env', 'name' => 'x']]])->assertStatus(422);
        $this->assertSame(0, SurveyResponse::count());

        $send(UploadedFile::fake()->create('ok.pdf', 5120, 'application/pdf'))->assertCreated()->assertJsonMissingPath("response.answers.{$q}.path");
        $stored = SurveyResponse::sole()->answers[(string) $q];
        $this->assertStringStartsWith("surveys/{$survey->id}/{$this->students[0]->id}/", $stored['path']);
        Storage::disk('local')->assertExists($stored['path']);

        // Students never see storage paths, even in GET show.
        $this->getJson("/api/student/surveys/{$survey->id}")->assertOk()->assertJsonMissingPath("responses.0.answers.{$q}.path");
        // Admin report also never exposes the path.
        $this->asAdmin();
        $report = $this->getJson("/api/admin/surveys/{$survey->id}/report")->assertOk();
        $this->assertStringNotContainsString('surveys/', json_encode($report->json('responses')));
    }

    public function test_P17_edit_after_deadline_and_second_submission_are_refused(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T']], ['allow_edits' => true, 'deadline_at' => now()->addHour()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i')]);
        $q = $this->qid($survey, 'text');
        $this->asStudent(0);
        $rid = $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'first']])->assertCreated()->json('response.id');
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'second']])->assertStatus(422); // allow_multiple off
        $this->postJson("/api/student/surveys/{$survey->id}/responses/{$rid}", ['answers' => [$q => 'edited']])->assertOk();

        $this->travel(2)->hours();
        $this->postJson("/api/student/surveys/{$survey->id}/responses/{$rid}", ['answers' => [$q => 'late']])->assertStatus(422);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'late']])->assertStatus(422);
        $this->getJson("/api/student/surveys/{$survey->id}")->assertOk()->assertJsonPath('survey.can_edit', false)->assertJsonPath('survey.can_submit', false);
        $this->assertSame('edited', SurveyResponse::sole()->answers[(string) $q]);
    }

    public function test_P18_deadline_is_entered_in_ist(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T']], ['deadline_at' => '2026-12-01T18:00'], publish: false);
        // 18:00 IST = 12:30 UTC.
        $this->assertSame('2026-12-01 12:30:00', $survey->fresh()->deadline_at->utc()->format('Y-m-d H:i:s'));
    }

    // ---------------------------------------------------------------- surveys: lifecycle

    public function test_P19_delete_of_published_survey_archives_never_hard_deletes_and_archived_is_hidden_but_reportable(): void
    {
        $survey = $this->survey([['qtype' => 'yes_no', 'question' => 'OK?']]);
        // Published with NO responses: still archive only (B2-7: published = archive).
        $this->deleteJson("/api/admin/surveys/{$survey->id}")->assertOk()->assertJsonPath('archived', true);
        $this->assertDatabaseHas('surveys', ['id' => $survey->id, 'status' => 'archived']);
        $this->deleteJson("/api/admin/surveys/{$survey->id}")->assertStatus(422);
        $this->putJson("/api/admin/surveys/{$survey->id}", ['title' => 'x'])->assertStatus(422);
        $this->postJson("/api/admin/surveys/{$survey->id}/publish")->assertStatus(422);

        $other = $this->survey([['qtype' => 'yes_no', 'question' => 'OK?']]);
        $q = $this->qid($other, 'yes_no');
        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$other->id}/responses", ['answers' => [$q => 'yes']])->assertCreated();
        $this->asAdmin();
        $this->deleteJson("/api/admin/surveys/{$other->id}")->assertOk()->assertJsonPath('archived', true);
        $this->assertDatabaseCount('survey_responses', 1);
        $this->assertDatabaseCount('survey_questions', 2);
        $this->getJson("/api/admin/surveys/{$other->id}/report")->assertOk()->assertJsonPath('counts.responses', 1);
        $this->get("/api/admin/surveys/{$other->id}/export")->assertOk();
        $this->assertTrue(AuditLog::where('action', 'survey.archive')->where('subject_id', $other->id)->exists());

        $this->asStudent(0);
        $this->assertSame([], $this->getJson('/api/student/surveys')->json('surveys'));
        $this->getJson("/api/student/surveys/{$other->id}")->assertNotFound();
        $this->postJson("/api/student/surveys/{$other->id}/responses", ['answers' => [$q => 'no']])->assertNotFound();
    }

    public function test_P20_clone_copies_questions_settings_but_never_responses_or_files(): void
    {
        $survey = $this->survey([['qtype' => 'yes_no', 'question' => 'OK?', 'required' => true, 'help_text' => 'Help'], ['qtype' => 'file', 'question' => 'F']], ['allow_edits' => true, 'is_public' => true, 'survey_type' => 'ppo_consent', 'job_posting_id' => $this->posting->id]);
        $this->asStudent(0);
        $this->post("/api/student/surveys/{$survey->id}/responses", ['answers' => json_encode([$this->qid($survey, 'yes_no') => 'yes']), 'files' => [$this->qid($survey, 'file') => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertCreated();

        $this->asAdmin();
        $copyId = $this->postJson("/api/admin/surveys/{$survey->id}/clone")->assertCreated()->json('survey.id');
        $copy = Survey::findOrFail($copyId);
        $this->assertSame(['draft', null, null, null], [$copy->status, $copy->published_at, $copy->archived_at, $copy->emailed_at]);
        $this->assertTrue($copy->allow_edits);
        $this->assertTrue($copy->is_public);
        $this->assertSame('ppo_consent', $copy->survey_type);
        $this->assertSame(0, $copy->responses()->count());
        $this->assertSame('Help', $copy->questions()->where('qtype', 'yes_no')->value('help_text'));
        $this->assertNotEquals($survey->questions()->pluck('id')->all(), $copy->questions()->pluck('id')->all());
        $this->assertSame(1, SurveyResponse::count());
        $this->getJson("/api/admin/surveys/{$copyId}/report")->assertOk()->assertJsonPath('counts.responses', 0);
    }

    public function test_P21_export_is_formula_safe_with_q_headers_and_audited(): void
    {
        $survey = $this->survey([
            ['qtype' => 'dropdown', 'question' => '=cmd|"/c calc"!A1', 'options' => ['=SUM(1,1)', '@evil']],
            ['qtype' => 'text', 'question' => '+plus header'],
            ['qtype' => 'rich_text', 'question' => 'Rich'],
        ]);
        $this->students[0]->update(['full_name' => '=HYPERLINK("http://x","y")']);
        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [
            $this->qid($survey, 'dropdown') => '=SUM(1,1)',
            $this->qid($survey, 'text') => '-2+3',
            $this->qid($survey, 'rich_text') => '<p>=1+2</p><script>x</script>',
        ]])->assertCreated();

        $this->asAdmin();
        $content = $this->get("/api/admin/surveys/{$survey->id}/export")->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 's7v').'.xlsx';
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertStringStartsWith('Q1: =cmd', (string) $sheet->getCell('I1')->getValue());
        $this->assertStringStartsWith('Q2: +plus', (string) $sheet->getCell('J1')->getValue());
        $this->assertStringStartsWith('Q3: ', (string) $sheet->getCell('K1')->getValue());
        foreach (['C2', 'I2', 'J2', 'K2'] as $cell) {
            $this->assertFalse($sheet->getCell($cell)->isFormula(), "{$cell} became a formula");
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($cell)->getDataType(), "{$cell} not an explicit string");
        }
        $this->assertSame('=SUM(1,1)', $sheet->getCell('I2')->getValue());
        $this->assertSame('-2+3', $sheet->getCell('J2')->getValue());
        $this->assertStringNotContainsString('<', (string) $sheet->getCell('K2')->getValue());
        $this->assertTrue(AuditLog::where('action', 'survey.export')->exists());
    }

    public function test_P22_report_matches_responses_and_counts_never_reach_students(): void
    {
        $survey = $this->survey([['qtype' => 'mcq_single', 'question' => 'Pick', 'options' => ['A', 'B']]], ['allow_multiple' => true]);
        $q = $this->qid($survey, 'mcq_single');
        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'A']])->assertCreated();
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'B']])->assertCreated();
        $show = $this->getJson("/api/student/surveys/{$survey->id}")->assertOk()->json();
        $list = $this->getJson('/api/student/surveys')->assertOk()->json('surveys.0');
        foreach (['response_count', 'audience_count', 'question_count', 'has_responses', 'counts', 'summary'] as $key) {
            $this->assertArrayNotHasKey($key, $show['survey'], "student survey detail leaks {$key}");
            $this->assertArrayNotHasKey($key, $list, "student survey list leaks {$key}");
        }
        $this->assertCount(2, $show['responses']); // own responses only

        $this->asStudent(1);
        $this->assertSame([], $this->getJson("/api/student/surveys/{$survey->id}")->assertOk()->json('responses')); // never another student's answers

        $this->asAdmin();
        $report = $this->getJson("/api/admin/surveys/{$survey->id}/report")->assertOk();
        $report->assertJsonPath('counts.responses', 2)->assertJsonPath('counts.responders', 1)->assertJsonPath('counts.audience', 6)->assertJsonPath('counts.non_responders', 5);
        $this->assertSame([['label' => 'A', 'count' => 1], ['label' => 'B', 'count' => 1]], $report->json('summary.0.counts'));
    }

    public function test_P23_ppo_consent_answers_never_touch_offers_or_blocks(): void
    {
        $application = Application::where('student_profile_id', $this->students[0]->id)->sole();
        $offer = Offer::create([
            'application_id' => $application->id, 'student_profile_id' => $this->students[0]->id, 'company_id' => $this->company->id,
            'job_posting_id' => $this->posting->id, 'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'ppo_offered', 'ctc_annual' => 1500000,
            'announced_at' => now(),
        ]);
        $block = PlacementBlock::create(['student_profile_id' => $this->students[0]->id, 'placement_cycle_id' => $this->cycle->id, 'scope' => 'all', 'reason' => 'manual', 'active' => true]);
        $offerBefore = $offer->fresh()->toArray();
        $blockBefore = $block->fresh()->toArray();
        $appBefore = $application->fresh()->toArray();

        $this->asAdmin();
        $id = $this->postJson('/api/admin/surveys', ['title' => 'Acme || PPO Consent'])->json('survey.id');
        $this->putJson("/api/admin/surveys/{$id}", [
            'survey_type' => 'ppo_consent', 'job_posting_id' => $this->posting->id, 'allow_edits' => true,
            'questions' => [['qtype' => 'yes_no', 'question' => 'Do you accept the PPO', 'required' => true]],
            'audiences' => [['audience_type' => 'offer_holders', 'audience_filter' => ['job_posting_id' => $this->posting->id]]],
        ])->assertOk();
        $this->postJson("/api/admin/surveys/{$id}/publish")->assertOk();
        $survey = Survey::findOrFail($id);
        $q = $this->qid($survey, 'yes_no');

        $this->asStudent(0);
        $rid = $this->postJson("/api/student/surveys/{$id}/responses", ['answers' => [$q => 'no']])->assertCreated()->json('response.id');
        $this->postJson("/api/student/surveys/{$id}/responses/{$rid}", ['answers' => [$q => 'yes']])->assertOk();

        $this->asAdmin();
        $this->deleteJson("/api/admin/surveys/{$id}")->assertOk();

        $this->assertSame($offerBefore, $offer->fresh()->toArray());
        $this->assertSame($blockBefore, $block->fresh()->toArray());
        $this->assertSame($appBefore, $application->fresh()->toArray());
        $this->assertDatabaseCount('offers', 1);
        $this->assertDatabaseCount('placement_blocks', 1);
        $this->assertFalse(AuditLog::where('action', 'like', 'offer.%')->exists());
        $this->assertFalse(AuditLog::where('action', 'like', 'block.%')->exists());
    }

    public function test_P24_survey_announcement_is_bcc_with_portal_to_and_no_reminders(): void
    {
        config(['mail.bulk_batch_size' => 4]);
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T']], ['deadline_at' => now()->addDay()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i')], email: true);
        Mail::assertQueued(BroadcastMail::class, 2); // 6 enrolled active students, batches of 4
        foreach ([0, 1, 2, 3, 4, 6] as $i) {
            Mail::assertQueued(BroadcastMail::class, fn (BroadcastMail $m) => $this->bccOnly($m, $this->students[$i]));
        }
        $this->assertSame(6, EmailLog::where('kind', 'survey')->count());
        $this->assertNotNull($survey->fresh()->emailed_at);

        // Nothing chases non-responders: no scheduled survey command exists, and time passing sends nothing more.
        $commands = array_keys(\Illuminate\Support\Facades\Artisan::all());
        $this->assertSame([], array_values(array_filter($commands, fn ($c) => str_contains(strtolower($c), 'survey') || str_contains(strtolower($c), 'remind'))));
    }

    public function test_P25_survey_writes_are_audited_with_before_and_after(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T']], publish: false);
        $this->putJson("/api/admin/surveys/{$survey->id}", ['allow_multiple' => true])->assertOk();
        $this->postJson("/api/admin/surveys/{$survey->id}/clone")->assertCreated();
        $this->postJson("/api/admin/surveys/{$survey->id}/publish")->assertOk();
        $draft = $this->survey([['qtype' => 'text', 'question' => 'T']], publish: false);
        $this->deleteJson("/api/admin/surveys/{$draft->id}")->assertOk();

        $update = AuditLog::where('action', 'survey.update')->where('subject_id', $survey->id)->latest('id')->first();
        $this->assertFalse($update->before['allow_multiple']);
        $this->assertTrue($update->after['allow_multiple']);
        foreach (['survey.create', 'survey.clone', 'survey.publish', 'survey.delete'] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->exists(), $action);
        }
        $this->assertDatabaseMissing('surveys', ['id' => $draft->id]);
    }

    /**
     * Saving a published survey that has no responses yet (e.g. the Audience tab's Save button, which re-sends the
     * questions) recreates every question row with new ids. A student who opened the form before that save submits
     * answers keyed by the old ids: they must not be silently dropped into an empty response.
     */
    public function test_P26_resaving_a_published_survey_must_not_silently_drop_open_forms_answers(): void
    {
        $questions = [['qtype' => 'text', 'question' => 'Your preferred city']];
        $survey = $this->survey($questions);
        $this->asStudent(0);
        $oldId = $this->getJson("/api/student/surveys/{$survey->id}")->assertOk()->json('survey.questions.0.id');

        // Admin presses Save on the Audience tab (same questions are re-sent).
        $this->asAdmin();
        $this->putJson("/api/admin/surveys/{$survey->id}", ['questions' => $questions, 'allow_edits' => true])->assertOk();

        $this->asStudent(0);
        $response = $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$oldId => 'Dhanbad']]);
        $stored = SurveyResponse::first();
        $this->assertTrue(
            $response->status() === 422 || ($stored && in_array('Dhanbad', $stored->answers, true)),
            'The answer was accepted (HTTP '.$response->status().') but stored as '.json_encode($stored?->answers).' — silently lost.'
        );
    }

    public function test_P27_rich_text_answers_and_texts_reach_the_admin_report_as_plain_text(): void
    {
        $survey = $this->survey([['qtype' => 'rich_text', 'question' => 'Rich']]);
        $q = $this->qid($survey, 'rich_text');
        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => '<p>Hello <img src=x onerror=alert(1)></p><script>bad()</script>']])->assertCreated();
        $this->asAdmin();
        $value = $this->getJson("/api/admin/surveys/{$survey->id}/report")->assertOk()->json("responses.0.answers.{$q}");
        $this->assertStringNotContainsString('<', $value);
        $this->assertStringContainsString('Hello', $value);
    }

    /**
     * The student Notices page loads the newest 300 published notices of ALL audiences and only then filters to the
     * student's own, so once the board holds more than 300 notices a student's older notices vanish.
     */
    public function test_P29_student_sees_every_notice_in_their_audience_even_on_a_busy_board(): void
    {
        $mine = Notice::create(['title' => 'Old but mine', 'published_at' => now()->subYear()]);
        $mine->audiences()->create(['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]);
        for ($i = 0; $i < 300; $i++) {
            $n = Notice::create(['title' => "Other branch {$i}", 'published_at' => now()->subDays(1)->addMinutes($i)]);
            $n->audiences()->create(['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Electrical Engineering']]]]);
        }
        $this->asStudent(0);
        $ids = collect($this->getJson('/api/student/notices')->assertOk()->json('notices'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids, 'A notice addressed to this student disappeared because 300 newer notices exist for other audiences.');
    }

    public function test_P28_admin_file_route_checks_survey_response_and_question_belong_together(): void
    {
        $a = $this->survey([['qtype' => 'file', 'question' => 'F']]);
        $b = $this->survey([['qtype' => 'file', 'question' => 'F']]);
        $this->asStudent(0);
        $this->post("/api/student/surveys/{$a->id}/responses", ['answers' => json_encode([]), 'files' => [$this->qid($a, 'file') => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertCreated();
        $rid = SurveyResponse::sole()->id;
        $this->asAdmin();
        $this->getJson("/api/admin/surveys/{$b->id}/responses/{$rid}/files/{$this->qid($a, 'file')}")->assertNotFound();
        $this->getJson("/api/admin/surveys/{$a->id}/responses/{$rid}/files/{$this->qid($b, 'file')}")->assertNotFound();
        $this->get("/api/admin/surveys/{$a->id}/responses/{$rid}/files/{$this->qid($a, 'file')}")->assertOk()->assertHeader('Cache-Control');
    }
}
