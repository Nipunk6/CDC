<?php

namespace Tests\Feature\ParityVerify;

use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Notice;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\SurveyAnswerService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Independent re-check (2026-10-07) of the verification fixes H1 and M1–M5: adversarial probes for edge cases the
 * fixer's own tests do not cover. A failing probe is a finding.
 */
class RecheckHighMediumTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private Company $company;

    private PlacementCycle $cycle;

    private JobPosting $posting;

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

        // 0-5 Computer Science, batch 2027, enrolled; 6 Electrical, batch 2028, not enrolled.
        for ($i = 0; $i < 7; $i++) {
            $s = StudentProfile::factory()->create([
                'roll_no' => sprintf('22JE%04d', $i + 1),
                'phone' => '9000000000',
                'branch' => $i === 6 ? 'Electrical Engineering' : 'Computer Science & Engineering',
                'graduating_batch' => $i === 6 ? 2028 : 2027,
            ]);
            if ($i < 6) {
                CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            }
            $this->students[] = $s;
        }

        $this->posting = $this->floatJnf('SDE');

        foreach (array_slice($this->students, 0, 6) as $s) {
            $this->apply($s, $this->posting);
        }
    }

    // ------------------------------------------------------------------ helpers

    private function jnf(string $title, array $extra = []): Jnf
    {
        return Jnf::create([
            'company_id' => $this->company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => $title,
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [
                    ['type' => 'aptitude_test', 'enabled' => true],
                    ['type' => 'technical_interview', 'enabled' => true],
                    ['type' => 'hr_interview', 'enabled' => true],
                ],
            ] + $extra,
        ]);
    }

    private function floatJnf(string $title, array $extraForm = [], array $extraRequest = []): JobPosting
    {
        $jnf = $this->jnf($title, $extraForm);
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ] + $extraRequest)->assertCreated()->json('posting.id');

        return JobPosting::findOrFail($id);
    }

    private function apply(StudentProfile $s, JobPosting $posting): Application
    {
        $resume = $s->resumes()->firstOrCreate(['slot' => 1], ['label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);

        return Application::create([
            'job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id,
            'status' => 'applied', 'applied_at' => now(),
        ]);
    }

    private function app_(int $i): Application
    {
        return Application::where('job_posting_id', $this->posting->id)->where('student_profile_id', $this->students[$i]->id)->firstOrFail();
    }

    private function round(int $index)
    {
        return $this->posting->rounds()->orderBy('sort_order')->get()[$index];
    }

    private function base(int $roundIndex = 0): string
    {
        return "/api/admin/postings/{$this->posting->id}/rounds/{$this->round($roundIndex)->id}/reconcile";
    }

    private function close(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    private function block(int $i, string $reason, string $scope, ?string $remark = null): void
    {
        PlacementBlock::create([
            'student_profile_id' => $this->students[$i]->id, 'placement_cycle_id' => $this->cycle->id,
            'scope' => $scope, 'reason' => $reason, 'remark' => $remark, 'active' => true, 'blocked_by' => $this->admin->id,
        ]);
    }

    /** @return list<string> every student address a queued RoundResultMail reaches (BCC batches: To is the sender) */
    private function roundMailRecipients(): array
    {
        $out = [];
        foreach (Mail::queued(RoundResultMail::class) as $mail) {
            foreach ($mail->bcc as $r) {
                $out[] = strtolower($r['address']);
            }
        }

        return $out;
    }

    private function sheetOf($response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'rchk');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    // ------------------------------------------------------------------ H1 Reconcile

    public function test_h1_guest_is_401_and_a_round_of_another_job_profile_is_404(): void
    {
        $other = $this->floatJnf('Other');
        $foreignRound = $other->rounds()->first();
        $this->close();

        $url = "/api/admin/postings/{$this->posting->id}/rounds/{$foreignRound->id}/reconcile";
        $this->getJson($url)->assertNotFound();
        $this->getJson($url.'/export')->assertNotFound();
        $this->postJson($url, ['application_ids' => [$this->app_(1)->id], 'confirm' => true])->assertNotFound();

        $this->app['auth']->forgetGuards();
        $this->getJson($this->base())->assertUnauthorized();
        $this->getJson($this->base().'/export')->assertUnauthorized();
        $this->postJson($this->base(), ['application_ids' => [1], 'confirm' => true])->assertUnauthorized();
        $this->assertSame(0, ApplicationRoundResult::count());
    }

    public function test_h1_debarment_and_suspended_enrolment_are_listed_but_a_non_applicable_internship_block_is_not(): void
    {
        $this->close();
        $this->block(2, 'debarred', 'all', 'Misconduct');
        CycleEnrollment::where('student_profile_id', $this->students[3]->id)->update(['status' => 'suspended']);
        $this->block(4, 'manual', 'internships_only', 'Interns only'); // full-time job profile: does not apply

        Sanctum::actingAs($this->admin);
        $rows = collect($this->getJson($this->base())->assertOk()->json('students'));
        $this->assertSame(['22JE0003', '22JE0004'], $rows->pluck('student.roll_no')->all());

        $service = app(EligibilityService::class);
        foreach ($rows as $row) {
            $student = StudentProfile::where('roll_no', $row['student']['roll_no'])->first();
            $this->assertSame($service->check($student->fresh(), $this->posting->fresh())['reasons'], $row['reasons']);
        }
        $this->assertContains('You are debarred from this placement. (Misconduct)', $rows[0]['reasons']);

        $this->postJson($this->base(), ['application_ids' => $rows->pluck('application_id')->all(), 'confirm' => 'yes'])->assertOk()->assertJsonPath('rejected', 2);
        foreach ($rows as $row) {
            $result = ApplicationRoundResult::where('application_id', $row['application_id'])->sole();
            $this->assertSame('No longer eligible: '.implode(' ', $row['reasons']), $result->remark);
            $this->assertSame('rejected', $result->result);
            $this->assertNotNull($result->published_at);
        }
        $this->assertNull(ApplicationRoundResult::where('application_id', $this->app_(4)->id)->first());
    }

    public function test_h1_withdrawn_eligible_and_ineligible_mixed_only_the_ineligible_is_rejected_and_mailed_once(): void
    {
        $this->close();
        $this->students[1]->update(['current_cgpa' => 5.0]);
        $this->students[2]->update(['current_cgpa' => 5.0]);
        $withdrawn = $this->app_(2);
        $withdrawn->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);

        Sanctum::actingAs($this->admin);
        $listed = collect($this->getJson($this->base())->json('students'))->pluck('student.roll_no')->all();
        $this->assertSame(['22JE0002'], $listed, 'a withdrawn application is not listed');

        $response = $this->postJson($this->base(), ['application_ids' => [$withdrawn->id, $this->app_(1)->id, $this->app_(0)->id, 999999], 'confirm' => true])->assertOk();
        $response->assertJsonPath('rejected', 1);
        $this->assertCount(3, $response->json('skipped'));

        $this->assertSame([$this->app_(1)->id], ApplicationRoundResult::pluck('application_id')->all());
        Mail::assertQueued(RoundResultMail::class, 1);
        $this->assertSame([strtolower($this->students[1]->user->email)], $this->roundMailRecipients());

        $logs = EmailLog::where('kind', 'reconcile_regret')->get();
        $this->assertCount(1, $logs);
        $this->assertSame($this->posting->id, (int) $logs[0]->job_posting_id);
        $this->assertSame(strtolower($this->students[1]->user->email), strtolower($logs[0]->recipient_email));
    }

    public function test_h1_a_draft_row_is_overwritten_and_a_later_stage_publish_does_not_mail_the_student_again(): void
    {
        $this->close();
        $this->students[1]->update(['current_cgpa' => 5.0]);
        ApplicationRoundResult::create(['application_id' => $this->app_(1)->id, 'posting_round_id' => $this->round(0)->id, 'result' => 'selected']);
        ApplicationRoundResult::create(['application_id' => $this->app_(0)->id, 'posting_round_id' => $this->round(0)->id, 'result' => 'selected']);

        Sanctum::actingAs($this->admin);
        $this->assertSame(['22JE0002'], collect($this->getJson($this->base())->json('students'))->pluck('student.roll_no')->all(), 'a draft decision is still reconcilable');
        $this->postJson($this->base(), ['application_ids' => [$this->app_(1)->id], 'confirm' => true])->assertOk();
        $row = ApplicationRoundResult::where('application_id', $this->app_(1)->id)->sole();
        $this->assertSame('rejected', $row->result);
        $this->assertNotNull($row->published_at);

        // The CDC then publishes the stage (rejecting the rest): the reconciled student is not mailed a second time.
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}/publish", ['reject_remaining' => true])->assertOk();
        $mine = array_filter($this->roundMailRecipients(), fn ($a) => $a === strtolower($this->students[1]->user->email));
        $this->assertCount(1, $mine, 'the reconciled student received more than one stage mail');
        $this->assertSame('rejected', $row->fresh()->result);
    }

    public function test_h1_later_stage_pool_is_reconciled_there_and_not_in_the_published_earlier_stage(): void
    {
        $this->close();
        foreach ([0, 1] as $i) {
            ApplicationRoundResult::create(['application_id' => $this->app_($i)->id, 'posting_round_id' => $this->round(0)->id, 'result' => 'selected']);
        }
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}/publish", ['reject_remaining' => true])->assertOk();
        Mail::fake();

        $this->block(1, 'manual', 'all', 'Policy breach');
        $this->getJson($this->base(0))->assertOk()->assertJsonCount(0, 'students');
        $stage2 = $this->getJson($this->base(1))->assertOk();
        $stage2->assertJsonPath('pool_count', 2);
        $this->assertSame(['22JE0002'], collect($stage2->json('students'))->pluck('student.roll_no')->all());
        $this->assertSame(['Blocked by the CDC: Policy breach'], $stage2->json('students.0.reasons'));

        $this->postJson($this->base(1), ['application_ids' => [$this->app_(1)->id], 'confirm' => true])->assertOk();
        $this->assertSame('rejected', ApplicationRoundResult::where('application_id', $this->app_(1)->id)->where('posting_round_id', $this->round(1)->id)->value('result'));
        $this->assertSame('selected', ApplicationRoundResult::where('application_id', $this->app_(1)->id)->where('posting_round_id', $this->round(0)->id)->value('result'), 'the earlier published decision is untouched');
        Mail::assertQueued(RoundResultMail::class, 1);
    }

    public function test_h1_company_never_sees_the_reconcile_remark_or_block_reason(): void
    {
        $this->close();
        $this->block(1, 'manual', 'all', 'SecretInternalNote');
        Sanctum::actingAs($this->admin);
        $this->postJson($this->base(), ['application_ids' => [$this->app_(1)->id], 'confirm' => true])->assertOk();

        Sanctum::actingAs($this->companyUser);
        foreach (["/api/company/postings/{$this->posting->id}", "/api/company/postings/{$this->posting->id}/applicants", '/api/company/postings'] as $url) {
            $body = $this->getJson($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('No longer eligible', $body, $url);
            $this->assertStringNotContainsString('SecretInternalNote', $body, $url);
        }
    }

    public function test_h1_cancelled_job_profile_refuses_and_report_has_ist_footer_string_reasons_and_audit(): void
    {
        $this->close();
        $this->block(1, 'manual', 'all', '=cmd|calc');
        $this->students[2]->update(['current_cgpa' => 5.0, 'full_name' => '+SUM(A1)']);

        Sanctum::actingAs($this->admin);
        $sheet = $this->sheetOf($this->get($this->base().'/export')->assertOk());
        $values = collect($sheet->toArray())->flatten()->filter()->values();
        $this->assertTrue($values->contains(fn ($v) => str_starts_with((string) $v, 'Downloaded on ') && str_ends_with((string) $v, 'IST')), 'IST footer');
        for ($r = 4; $r <= 5; $r++) {
            foreach (['B', 'C', 'F'] as $col) {
                $this->assertSame(DataType::TYPE_STRING, $sheet->getCell($col.$r)->getDataType(), "{$col}{$r} is not an explicit string");
            }
        }
        $audit = AuditLog::where('action', 'stage.reconcile_report')->sole();
        $this->assertSame(2, $audit->after['ineligible']);
        $this->assertSame($this->admin->id, (int) $audit->user_id);

        $this->patchJson("/api/admin/postings/{$this->posting->id}/cancel")->assertOk();
        $this->postJson($this->base(), ['application_ids' => [$this->app_(1)->id], 'confirm' => true])->assertStatus(422);
        $this->assertSame(0, ApplicationRoundResult::count());
        Mail::assertNotQueued(RoundResultMail::class);
    }

    public function test_h1_audit_records_ids_and_reasons_before_and_after(): void
    {
        $this->close();
        $this->students[1]->update(['current_cgpa' => 5.0]);
        Sanctum::actingAs($this->admin);
        $reasons = $this->getJson($this->base())->json('students.0.reasons');
        $this->postJson($this->base(), ['application_ids' => [$this->app_(1)->id], 'confirm' => true])->assertOk();

        $audit = AuditLog::where('action', 'stage.reconcile')->sole();
        $this->assertSame([$this->app_(1)->id], $audit->before['application_ids']);
        $this->assertSame($reasons, $audit->after['rejected'][0]['reasons']);
        $this->assertSame($this->posting->id, $audit->after['posting_id']);
        // Shown in the job profile's Activity.
        $activity = $this->getJson("/api/admin/postings/{$this->posting->id}/activity")->assertOk()->getContent();
        $this->assertStringContainsString('stage.reconcile', $activity);
    }

    // ------------------------------------------------------------------ M1 survey edits

    private function survey(array $questions, bool $publish = true): Survey
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/surveys', ['title' => 'Recheck survey'])->assertCreated()->json('survey.id');
        $this->putJson("/api/admin/surveys/{$id}", ['questions' => $questions, 'audiences' => [['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]]])->assertOk();
        if ($publish) {
            $this->postJson("/api/admin/surveys/{$id}/publish")->assertOk();
        }

        return Survey::findOrFail($id);
    }

    public function test_m1_mixed_new_and_existing_questions_keep_the_request_order(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'Q1'], ['qtype' => 'text', 'question' => 'Q2']], false);
        [$q1, $q2] = $survey->questions()->orderBy('sort_order')->pluck('id')->all();

        $response = $this->putJson("/api/admin/surveys/{$survey->id}", ['questions' => [
            ['qtype' => 'text', 'question' => 'New A'],
            ['id' => $q2, 'qtype' => 'text', 'question' => 'Q2'],
            ['qtype' => 'text', 'question' => 'New B'],
            ['id' => $q1, 'qtype' => 'text', 'question' => 'Q1 edited'],
        ]])->assertOk();

        $rows = SurveyQuestion::where('survey_id', $survey->id)->orderBy('sort_order')->get();
        $this->assertSame(['New A', 'Q2', 'New B', 'Q1 edited'], $rows->pluck('question')->all());
        $this->assertSame($q2, $rows[1]->id);
        $this->assertSame($q1, $rows[3]->id);
        // The response lists the questions in the same order, so the builder can map new ids by index.
        $this->assertSame($rows->pluck('id')->all(), collect($response->json('survey.questions'))->pluck('id')->all());
    }

    public function test_m1_unknown_foreign_and_non_numeric_answer_keys_are_422_and_store_nothing(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'City'], ['qtype' => 'static_text', 'question' => 'Read me']]);
        $other = $this->survey([['qtype' => 'text', 'question' => 'Elsewhere']]);
        $city = $survey->questions()->where('qtype', 'text')->value('id');
        $static = $survey->questions()->where('qtype', 'static_text')->value('id');
        $foreign = $other->questions()->value('id');

        Sanctum::actingAs($this->students[0]->user);
        $url = "/api/student/surveys/{$survey->id}/responses";
        foreach ([[$city => 'Dhanbad', $foreign => 'x'], ['abc' => 'x', $city => 'Dhanbad'], [$city => 'Dhanbad', '0' => 'x']] as $answers) {
            $this->postJson($url, ['answers' => $answers])->assertStatus(422)->assertJsonPath('errors.survey.0', SurveyAnswerService::SURVEY_CHANGED);
        }
        $this->assertSame(0, SurveyResponse::count());

        // A Static Text id is accepted and ignored.
        $this->postJson($url, ['answers' => [$city => 'Dhanbad', $static => 'ignored']])->assertCreated();
        $this->assertSame([(string) $city => 'Dhanbad'], SurveyResponse::sole()->answers);
    }

    public function test_m1_question_edit_is_refused_once_answered_and_audience_save_keeps_ids(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'City']]);
        $ids = $survey->questions()->pluck('id')->all();
        Sanctum::actingAs($this->students[0]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$ids[0] => 'Dhanbad']])->assertCreated();

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/surveys/{$survey->id}", ['questions' => [['id' => $ids[0], 'qtype' => 'text', 'question' => 'Town']]])->assertStatus(422);
        $this->putJson("/api/admin/surveys/{$survey->id}", ['allow_edits' => true, 'audiences' => [['audience_type' => 'all']]])->assertOk();
        $this->assertSame($ids, $survey->questions()->pluck('id')->all());
        $this->assertSame('City', SurveyQuestion::find($ids[0])->question);
    }

    // ------------------------------------------------------------------ M2 Allowed Student Categories

    /**
     * Before the fix, opening a job profile built the snapshot as `snapshot(form) + [admin categories]`, so a
     * company-written form value won, and the `posting.float` audit recorded `categoryIds($posting->eligibility_snapshot)`
     * (AdminPostingController.php:239, unchanged by the fix) — i.e. the COMPANY's value. The clean-up migration trusts
     * that audit value as "set by an admin", so it keeps exactly the company-injected values it is meant to remove.
     */
    public function test_m2_cleanup_drops_a_company_value_even_when_the_pre_fix_float_audit_echoed_it(): void
    {
        $jnf = $this->jnf('Injector', ['allowedStudentCategories' => [7]]);
        $posting = JobPosting::create([
            'postable_type' => Jnf::class, 'postable_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id, 'application_deadline' => now()->addDay(),
            'status' => 'open', 'offer_type' => 'fulltime', 'floated_at' => now(),
            'eligibility_snapshot' => ['genderFilter' => 'all', 'allowedStudentCategories' => [7]], // pre-fix: form value won
        ]);
        // What the pre-fix float wrote: the category ids read back from the (company-tainted) snapshot.
        AuditLog::create(['action' => 'posting.float', 'subject_type' => JobPosting::class, 'subject_id' => $posting->id, 'after' => ['allowed_student_categories' => [7]]]);

        (require database_path('migrations/2026_10_07_000037_strip_company_supplied_student_categories.php'))->up();

        $this->assertArrayNotHasKey('allowedStudentCategories', $posting->fresh()->eligibility_snapshot, 'the company-supplied category restriction survived the clean-up');
    }

    public function test_m2_cleanup_drops_a_company_value_carried_into_a_later_eligibility_update_audit(): void
    {
        $jnf = $this->jnf('Injector2', ['allowedStudentCategories' => [9]]);
        $posting = JobPosting::create([
            'postable_type' => Jnf::class, 'postable_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id, 'application_deadline' => now()->addDay(),
            'status' => 'open', 'offer_type' => 'fulltime', 'floated_at' => now(),
            'eligibility_snapshot' => ['genderFilter' => 'all', 'globalCgpa' => '7', 'allowedStudentCategories' => [9]],
        ]);
        // The CDC later changed only the CGPA; PostingEligibilityService::proposed() merges over current(), so the
        // audit's after.criteria carries the company's category list along.
        AuditLog::create(['action' => 'posting.eligibility_update', 'subject_type' => JobPosting::class, 'subject_id' => $posting->id,
            'after' => ['criteria' => ['globalCgpa' => '7', 'genderFilter' => 'all', 'allowedStudentCategories' => [9]]]]);

        (require database_path('migrations/2026_10_07_000037_strip_company_supplied_student_categories.php'))->up();

        $this->assertArrayNotHasKey('allowedStudentCategories', $posting->fresh()->eligibility_snapshot, 'the company-supplied category restriction survived the clean-up');
    }

    public function test_m2_company_inf_store_and_update_strip_the_key(): void
    {
        Sanctum::actingAs($this->companyUser);
        $payload = fn () => [
            'internship_title' => 'Intern', 'internship_description' => 'Learn.', 'status' => 'draft',
            'form_data' => json_encode(['internshipTitle' => 'Intern', 'allowedStudentCategories' => [3]]),
        ];
        $id = $this->postJson('/api/company/infs', $payload())->assertCreated()->json('inf.id');
        $this->assertArrayNotHasKey('allowedStudentCategories', Inf::find($id)->form_data);
        $this->putJson("/api/company/infs/{$id}", $payload())->assertOk();
        $this->assertArrayNotHasKey('allowedStudentCategories', Inf::find($id)->form_data);
    }

    public function test_m2_rules_fallback_and_eligibility_edit_never_take_categories_from_the_form(): void
    {
        Sanctum::actingAs($this->admin);
        $cat = $this->postJson('/api/admin/student-categories', ['title' => 'Minor X'])->assertCreated()->json('category.id');

        // Fallback: a posting without a snapshot never reads the key from form_data.
        $jnf = $this->jnf('Fallback');
        $jnf->forceFill(['form_data' => $jnf->form_data + ['allowedStudentCategories' => [$cat]]])->saveQuietly();
        $posting = JobPosting::create([
            'postable_type' => Jnf::class, 'postable_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id, 'application_deadline' => now()->addDay(),
            'status' => 'open', 'offer_type' => 'fulltime', 'floated_at' => now(), 'eligibility_snapshot' => null,
        ]);
        $this->assertSame([], EligibilityService::categoryIds($posting->eligibilityRules()));
        $this->assertTrue(app(EligibilityService::class)->check($this->students[0]->fresh(), $posting)['eligible']);

        // Edit eligibility of another key keeps the CDC's categories and never writes them into form_data.
        $chosen = $this->floatJnf('Chosen', [], ['allowed_student_categories' => [$cat]]);
        $this->patchJson("/api/admin/postings/{$chosen->id}/eligibility", ['genderFilter' => 'female', 'notify_newly_eligible' => false])->assertOk();
        $this->assertSame([$cat], EligibilityService::categoryIds($chosen->fresh()->eligibility_snapshot));
        $this->assertArrayNotHasKey('allowedStudentCategories', $chosen->fresh()->postable->form_data);
    }

    // ------------------------------------------------------------------ M3 notices / surveys

    private function notice(array $groups, string $title): Notice
    {
        $n = Notice::create(['title' => $title, 'published_at' => now()]);
        foreach ($groups as $g) {
            $n->audiences()->create($g);
        }

        return $n;
    }

    public function test_m3_list_and_detail_agree_for_every_notice_group_type_and_student(): void
    {
        $this->close();
        // Student 1 withdrawn; student 2 published shortlisted; student 3 draft shortlisted; student 4 published on hold;
        // student 5 enrolment suspended.
        $this->app_(1)->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $r = $this->round(0);
        ApplicationRoundResult::create(['application_id' => $this->app_(2)->id, 'posting_round_id' => $r->id, 'result' => 'selected', 'published_at' => now()]);
        ApplicationRoundResult::create(['application_id' => $this->app_(3)->id, 'posting_round_id' => $r->id, 'result' => 'selected']);
        ApplicationRoundResult::create(['application_id' => $this->app_(4)->id, 'posting_round_id' => $r->id, 'result' => 'waitlisted', 'published_at' => now()]);
        CycleEnrollment::where('student_profile_id', $this->students[5]->id)->update(['status' => 'suspended']);

        $notices = [
            $this->notice([['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]], 'cycle'),
            $this->notice([['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $this->posting->id]]], 'applicants'),
            $this->notice([['audience_type' => 'round_results', 'audience_filter' => ['job_posting_id' => $this->posting->id, 'posting_round_id' => $r->id, 'results' => ['selected']]]], 'shortlisted'),
            $this->notice([['audience_type' => 'round_results', 'audience_filter' => ['job_posting_id' => $this->posting->id, 'posting_round_id' => $r->id, 'results' => ['waitlisted']]]], 'on hold'),
            $this->notice([['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => null]]]]], 'whole programme'),
            $this->notice([['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Electrical Engineering']]]]], 'EE only'),
            $this->notice([['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => 999]], ['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $this->posting->id]]], 'two groups'),
        ];

        foreach ($this->students as $i => $student) {
            Sanctum::actingAs($student->user);
            $listed = collect($this->getJson('/api/student/notices')->assertOk()->json('notices'))->pluck('id')->sort()->values()->all();
            $openable = collect($notices)->filter(fn (Notice $n) => $this->postJson("/api/student/notices/{$n->id}/read")->status() === 200)->pluck('id')->sort()->values()->all();
            $this->assertSame($openable, $listed, "student {$i}: list and detail disagree");
        }
    }

    public function test_m3_student_survey_list_query_count_does_not_grow_with_surveys(): void
    {
        $count = function (): int {
            Sanctum::actingAs($this->students[0]->user);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/student/surveys')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $make = function (int $n): void {
            for ($i = 0; $i < $n; $i++) {
                $s = Survey::create(['title' => "S{$i}", 'status' => 'published', 'published_at' => now()->subMinutes($i), 'deadline_at' => $i % 2 ? now()->addDay() : null]);
                $s->audiences()->create($i % 2
                    ? ['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]
                    : ['audience_type' => 'batch', 'audience_filter' => ['batches' => [2027]]]);
            }
        };
        $make(3);
        $count(); // warm up: the first call also loads the user's student profile relation
        $few = $count();
        $make(40);
        $many = $count();
        $this->assertSame($few, $many, 'query count grows with the number of surveys');
        $this->getJson('/api/student/surveys?page=3')->assertOk()->assertJsonPath('meta.total', 43)->assertJsonCount(3, 'surveys');
        $this->getJson('/api/student/surveys?page=0')->assertStatus(422);
        $this->getJson('/api/student/notices?page=99')->assertOk()->assertJsonCount(0, 'notices');
    }

    // ------------------------------------------------------------------ M4 scheduled flag

    public function test_m4_is_scheduled_is_false_once_a_scheduled_job_profile_is_closed_or_cancelled(): void
    {
        $a = $this->floatJnf('Sched A', [], ['scheduled_open_at' => now()->addHours(2)->toIso8601String(), 'application_deadline' => now()->addDays(3)->toIso8601String()]);
        $b = $this->floatJnf('Sched B', [], ['scheduled_open_at' => now()->addHours(2)->toIso8601String(), 'application_deadline' => now()->addDays(3)->toIso8601String()]);
        $this->getJson("/api/admin/postings/{$a->id}")->assertOk()->assertJsonPath('posting.is_scheduled', true);

        $this->patchJson("/api/admin/postings/{$a->id}/close")->assertOk();
        $this->patchJson("/api/admin/postings/{$b->id}/cancel")->assertOk();
        $this->getJson("/api/admin/postings/{$a->id}")->assertJsonPath('posting.is_scheduled', false);
        $this->getJson("/api/admin/postings/{$b->id}")->assertJsonPath('posting.is_scheduled', false);
        $this->postJson("/api/admin/postings/{$b->id}/open-now")->assertStatus(422);

        $listed = collect($this->getJson('/api/admin/postings')->json('postings'))->keyBy('id');
        $this->assertFalse((bool) ($listed[$a->id]['is_scheduled'] ?? false));
        $this->assertFalse((bool) ($listed[$b->id]['is_scheduled'] ?? false));
    }

    // ------------------------------------------------------------------ M5 filtered enrolled download

    public function test_m5_export_honours_status_search_and_batch_filters_like_the_list(): void
    {
        CycleEnrollment::where('student_profile_id', $this->students[5]->id)->update(['status' => 'suspended']);
        $this->students[4]->update(['full_name' => 'Ravi 100%_Sure']);
        $this->students[3]->update(['full_name' => 'Ravi 100xxSure']);
        Sanctum::actingAs($this->admin);
        $base = "/api/admin/placement-cycles/{$this->cycle->id}";

        foreach (['status=suspended', 'search='.rawurlencode('100%_'), 'batches[]=2027&status=active', 'cgpa_min=9.99'] as $query) {
            $list = collect($this->getJson("{$base}/enrollments?{$query}")->assertOk()->json('enrollments'))->pluck('student_profile.roll_no')->sort()->values()->all();
            $sheet = $this->sheetOf($this->get("{$base}/students/export?{$query}")->assertOk());
            $exported = collect($sheet->toArray())->flatten()->filter(fn ($v) => is_string($v) && preg_match('/^22JE\d{4}$/', $v))->unique()->sort()->values()->all();
            $this->assertSame($list, $exported, "list and export disagree for {$query}");
        }
        $audits = AuditLog::where('action', 'cycle.export')->get();
        $this->assertCount(4, $audits);
        $this->assertSame('suspended', $audits[0]->after['filters']['status']);
        $this->assertSame(1, $audits[0]->after['count']);
    }
}
