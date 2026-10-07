<?php

namespace Tests\Feature;

use App\Mail\BroadcastMail;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\PlacementCycle;
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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class SurveyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private PlacementCycle $cycle;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);

        // 0-2 enrolled in the cycle (batch 2027); 3 batch 2028 not enrolled.
        for ($i = 0; $i < 4; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i + 1), 'graduating_batch' => $i === 3 ? 2028 : 2027]);
            if ($i < 3) {
                CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            }
            $this->students[] = $s;
        }
    }

    /**
     * A survey with a mandatory Yes/No, an optional text, a static text, a rating and a file question, for the cycle.
     */
    private function makeSurvey(array $settings = [], bool $publish = true): Survey
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/surveys', ['title' => 'Acme || PPO Consent', 'welcome_text' => '<p>Hi</p>'])->assertCreated()->json('survey.id');
        $this->putJson("/api/admin/surveys/{$id}", $settings + [
            'survey_type' => 'ppo_consent',
            'questions' => [
                ['qtype' => 'yes_no', 'question' => 'Do you accept the PPO', 'required' => true],
                ['qtype' => 'static_text', 'question' => 'Section B', 'required' => true],
                ['qtype' => 'text', 'question' => '=HYPERLINK("x")', 'help_text' => 'Optional'],
                ['qtype' => 'rating', 'question' => 'Rate us', 'settings' => ['max' => 5]],
                ['qtype' => 'sequence', 'question' => 'Rank', 'options' => ['A', 'B', 'C']],
                ['qtype' => 'file', 'question' => 'Offer letter'],
            ],
            'audiences' => [['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]],
        ])->assertOk();
        if ($publish) {
            $this->postJson("/api/admin/surveys/{$id}/publish")->assertOk();
        }

        return Survey::findOrFail($id);
    }

    private function q(Survey $survey, string $type): int
    {
        return $survey->questions()->where('qtype', $type)->value('id');
    }

    public function test_create_build_and_publish_is_audited(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/surveys', ['title' => ''])->assertStatus(422)->assertJsonPath('errors.title.0', 'Required field');

        $survey = $this->makeSurvey(publish: false);
        $this->assertSame('draft', $survey->status);
        $this->assertFalse($survey->questions()->where('qtype', 'static_text')->value('required')); // display-only never mandatory
        $this->postJson("/api/admin/surveys/{$survey->id}/publish")->assertOk();
        $this->assertSame('published', $survey->fresh()->status);
        $this->assertNotNull($survey->fresh()->published_at);
        $this->postJson("/api/admin/surveys/{$survey->id}/publish")->assertStatus(422);
        foreach (['survey.create', 'survey.update', 'survey.publish'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
        Mail::assertNotQueued(BroadcastMail::class);
    }

    public function test_publish_needs_a_question_and_an_audience(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/surveys', ['title' => 'Empty'])->json('survey.id');
        $this->postJson("/api/admin/surveys/{$id}/publish")->assertStatus(422);
        $this->putJson("/api/admin/surveys/{$id}", ['questions' => [['qtype' => 'text', 'question' => 'Q']]])->assertOk();
        $this->postJson("/api/admin/surveys/{$id}/publish")->assertStatus(422); // no audience, not public
        $this->putJson("/api/admin/surveys/{$id}", ['questions' => [['qtype' => 'mcq_single', 'question' => 'Q', 'options' => ['Only']]]])->assertStatus(422);
    }

    public function test_only_the_audience_sees_and_answers_others_get_404(): void
    {
        $survey = $this->makeSurvey();

        Sanctum::actingAs($this->students[3]->user);
        $this->assertSame([], $this->getJson('/api/student/surveys')->assertOk()->json('surveys'));
        $this->getJson("/api/student/surveys/{$survey->id}")->assertNotFound();
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$this->q($survey, 'yes_no') => 'yes']])->assertNotFound();

        Sanctum::actingAs($this->students[0]->user);
        $list = $this->getJson('/api/student/surveys')->assertOk()->json('surveys');
        $this->assertSame([$survey->id], array_column($list, 'id'));
        $this->assertArrayNotHasKey('response_count', $list[0]); // students never see counts
        $this->getJson("/api/student/surveys/{$survey->id}")->assertOk()->assertJsonCount(6, 'survey.questions');
    }

    public function test_public_survey_is_open_to_any_signed_in_student_but_not_recruiters(): void
    {
        $survey = $this->makeSurvey(['is_public' => true]);

        Sanctum::actingAs($this->students[3]->user);
        $this->getJson("/api/student/surveys/{$survey->id}")->assertOk();

        Sanctum::actingAs($this->companyUser);
        $this->getJson("/api/student/surveys/{$survey->id}")->assertForbidden();
        $this->getJson('/api/student/surveys')->assertForbidden();

        auth()->forgetGuards();
        $this->app['auth']->guard('sanctum')->forgetUser();
        $this->getJson("/api/student/surveys/{$survey->id}")->assertUnauthorized();
    }

    public function test_mandatory_and_answer_types_are_enforced_on_the_server(): void
    {
        $survey = $this->makeSurvey();
        Sanctum::actingAs($this->students[0]->user);
        $url = "/api/student/surveys/{$survey->id}/responses";
        $yes = $this->q($survey, 'yes_no');

        $this->postJson($url, ['answers' => []])->assertStatus(422)->assertJsonValidationErrors(["answers.{$yes}"]);
        $this->postJson($url, ['answers' => [$yes => 'maybe']])->assertStatus(422);
        $this->postJson($url, ['answers' => [$yes => 'yes', $this->q($survey, 'rating') => 9]])->assertStatus(422);
        $this->postJson($url, ['answers' => [$yes => 'yes', $this->q($survey, 'sequence') => ['A', 'B']]])->assertStatus(422);
        $this->assertSame(0, SurveyResponse::count());

        $this->postJson($url, ['answers' => [$yes => 'no', $this->q($survey, 'sequence') => ['C', 'A', 'B'], $this->q($survey, 'rating') => 4]])->assertCreated();
        $this->assertSame(['C', 'A', 'B'], SurveyResponse::sole()->answers[(string) $this->q($survey, 'sequence')]);

        // One response only, unless multiple submission is allowed.
        $this->postJson($url, ['answers' => [$yes => 'yes']])->assertStatus(422);
    }

    public function test_file_upload_limit_and_private_download(): void
    {
        $survey = $this->makeSurvey();
        $file = $this->q($survey, 'file');
        $yes = $this->q($survey, 'yes_no');
        Sanctum::actingAs($this->students[0]->user);
        $url = "/api/student/surveys/{$survey->id}/responses";

        $this->post($url, ['answers' => json_encode([$yes => 'yes']), 'files' => [$file => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(["answers.{$file}"]);
        $this->post($url, ['answers' => json_encode([$yes => 'yes']), 'files' => [$file => UploadedFile::fake()->create('x.exe', 10, 'application/octet-stream')]], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->post($url, ['answers' => json_encode([$yes => 'yes']), 'files' => [$file => UploadedFile::fake()->create('offer.pdf', 100, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath("response.answers.{$file}.name", 'offer.pdf')->assertJsonMissingPath("response.answers.{$file}.path");

        $response = SurveyResponse::sole();
        Storage::disk('local')->assertExists($response->answers[(string) $file]['path']);
        $this->get("/api/student/surveys/{$survey->id}/responses/{$response->id}/files/{$file}")->assertOk();

        Sanctum::actingAs($this->students[1]->user);
        $this->getJson("/api/student/surveys/{$survey->id}/responses/{$response->id}/files/{$file}")->assertNotFound();
        Sanctum::actingAs($this->admin);
        $this->get("/api/admin/surveys/{$survey->id}/responses/{$response->id}/files/{$file}")->assertOk();
    }

    public function test_edit_before_deadline_only_when_allowed(): void
    {
        $survey = $this->makeSurvey(['allow_edits' => true, 'deadline_at' => now()->addDay()->timezone('Asia/Kolkata')->format('Y-m-d\TH:i')]);
        $yes = $this->q($survey, 'yes_no');
        Sanctum::actingAs($this->students[0]->user);
        $id = $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$yes => 'yes']])->assertCreated()->json('response.id');

        $this->postJson("/api/student/surveys/{$survey->id}/responses/{$id}", ['answers' => [$yes => 'no']])->assertOk();
        $this->assertSame('no', SurveyResponse::find($id)->answers[(string) $yes]);

        Sanctum::actingAs($this->students[1]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses/{$id}", ['answers' => [$yes => 'no']])->assertNotFound();

        $survey->update(['deadline_at' => now()->subMinute()]);
        Sanctum::actingAs($this->students[0]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses/{$id}", ['answers' => [$yes => 'yes']])->assertStatus(422);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$yes => 'yes']])->assertStatus(422);

        $other = $this->makeSurvey(['allow_multiple' => true]);
        Sanctum::actingAs($this->students[0]->user);
        $rid = $this->postJson("/api/student/surveys/{$other->id}/responses", ['answers' => [$this->q($other, 'yes_no') => 'yes']])->assertCreated()->json('response.id');
        $this->postJson("/api/student/surveys/{$other->id}/responses", ['answers' => [$this->q($other, 'yes_no') => 'no']])->assertCreated();
        $this->postJson("/api/student/surveys/{$other->id}/responses/{$rid}", ['answers' => [$this->q($other, 'yes_no') => 'no']])->assertStatus(422); // edits not allowed
    }

    public function test_report_and_export_match_the_responses(): void
    {
        $survey = $this->makeSurvey();
        $yes = $this->q($survey, 'yes_no');
        $text = $this->q($survey, 'text');
        Sanctum::actingAs($this->students[0]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$yes => 'yes', $text => '=1+1']])->assertCreated();
        Sanctum::actingAs($this->students[1]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$yes => 'no']])->assertCreated();

        Sanctum::actingAs($this->admin);
        $report = $this->getJson("/api/admin/surveys/{$survey->id}/report")->assertOk();
        $report->assertJsonPath('counts', ['audience' => 3, 'responses' => 2, 'responders' => 2, 'non_responders' => 1]);
        $this->assertSame([$this->students[2]->roll_no], array_column($report->json('non_responders'), 'roll_no'));
        $this->assertSame('Yes', $report->json("responses.0.answers.{$yes}"));
        $this->assertSame(5, count($report->json('questions'))); // static text is not a question

        $response = $this->get("/api/admin/surveys/{$survey->id}/export")->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'svy').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('Q1: Do you accept the PPO', $sheet->getCell('I1')->getValue());
        $this->assertSame('Q2: =HYPERLINK("x")', $sheet->getCell('J1')->getValue());
        $this->assertSame($this->students[0]->roll_no, $sheet->getCell('B2')->getValue());
        $this->assertSame('Yes', $sheet->getCell('I2')->getValue());
        $this->assertSame('=1+1', $sheet->getCell('J2')->getValue());
        $this->assertFalse($sheet->getCell('J2')->isFormula());
        $this->assertSame('No', $sheet->getCell('I3')->getValue());
        $this->assertNull($sheet->getCell('B4')->getValue());
        @unlink($path);
    }

    public function test_questions_freeze_once_there_are_responses(): void
    {
        $survey = $this->makeSurvey();
        Sanctum::actingAs($this->students[0]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$this->q($survey, 'yes_no') => 'yes']])->assertCreated();
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/surveys/{$survey->id}", ['questions' => [['qtype' => 'text', 'question' => 'New']]])->assertStatus(422);
        $this->putJson("/api/admin/surveys/{$survey->id}", ['allow_edits' => true])->assertOk();
    }

    public function test_clone_copies_questions_and_settings_but_not_responses(): void
    {
        $survey = $this->makeSurvey(['allow_multiple' => true]);
        Sanctum::actingAs($this->students[0]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$this->q($survey, 'yes_no') => 'yes']])->assertCreated();

        Sanctum::actingAs($this->admin);
        $copyId = $this->postJson("/api/admin/surveys/{$survey->id}/clone")->assertCreated()->json('survey.id');
        $copy = Survey::findOrFail($copyId);
        $this->assertSame('draft', $copy->status);
        $this->assertNull($copy->published_at);
        $this->assertTrue($copy->allow_multiple);
        $this->assertSame('Copy of Acme || PPO Consent', $copy->title);
        $this->assertSame(6, $copy->questions()->count());
        $this->assertSame(1, $copy->audiences()->count());
        $this->assertSame(0, $copy->responses()->count());
        $this->assertSame(1, $survey->responses()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'survey.clone']);
    }

    public function test_delete_drafts_but_archive_published_surveys(): void
    {
        $draft = $this->makeSurvey(publish: false);
        $this->deleteJson("/api/admin/surveys/{$draft->id}")->assertOk()->assertJsonPath('archived', false);
        $this->assertDatabaseMissing('surveys', ['id' => $draft->id]);

        $survey = $this->makeSurvey();
        Sanctum::actingAs($this->students[0]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$this->q($survey, 'yes_no') => 'yes']])->assertCreated();

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/admin/surveys/{$survey->id}")->assertOk()->assertJsonPath('archived', true);
        $this->assertSame('archived', $survey->fresh()->status);
        $this->assertSame(1, $survey->responses()->count());
        $this->getJson("/api/admin/surveys/{$survey->id}/report")->assertOk()->assertJsonPath('counts.responses', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'survey.archive']);

        Sanctum::actingAs($this->students[0]->user);
        $this->assertSame([], $this->getJson('/api/student/surveys')->json('surveys'));
        $this->getJson("/api/student/surveys/{$survey->id}")->assertNotFound();
    }

    public function test_announcement_mail_goes_in_bcc_batches_and_is_logged(): void
    {
        config(['mail.bulk_batch_size' => 2]);
        $survey = $this->makeSurvey(publish: false);
        $this->postJson("/api/admin/surveys/{$survey->id}/publish", ['send_email' => true])->assertOk();

        Mail::assertQueued(BroadcastMail::class, 2);
        foreach (array_slice($this->students, 0, 3) as $s) {
            Mail::assertQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($s->user->email) && ! $m->hasTo($s->user->email));
        }
        Mail::assertNotQueued(BroadcastMail::class, fn (BroadcastMail $m) => $m->hasBcc($this->students[3]->user->email));
        $this->assertSame(3, EmailLog::where('kind', 'survey')->count());
    }

    public function test_survey_answers_never_touch_offers_or_blocks(): void
    {
        $survey = $this->makeSurvey();
        Sanctum::actingAs($this->students[0]->user);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$this->q($survey, 'yes_no') => 'no']])->assertCreated();
        $this->assertDatabaseCount('offers', 0);
        $this->assertDatabaseCount('placement_blocks', 0);
    }

    public function test_batch_audience_and_permissions(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/audiences/preview', ['kind' => 'survey', 'audiences' => [['audience_type' => 'batch', 'audience_filter' => ['batches' => [2028]]]]])
            ->assertOk()->assertJsonPath('count', 1);
        $this->postJson('/api/admin/audiences/preview', ['kind' => 'survey', 'audiences' => [['audience_type' => 'round_results', 'audience_filter' => []]]])
            ->assertStatus(422);

        $survey = $this->makeSurvey(publish: false);
        foreach ([$this->companyUser, $this->students[0]->user] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/surveys')->assertForbidden();
            $this->postJson('/api/admin/surveys', ['title' => 'x'])->assertForbidden();
            $this->getJson("/api/admin/surveys/{$survey->id}/report")->assertForbidden();
            $this->getJson("/api/admin/surveys/{$survey->id}/export")->assertForbidden();
            $this->postJson("/api/admin/surveys/{$survey->id}/clone")->assertForbidden();
            $this->deleteJson("/api/admin/surveys/{$survey->id}")->assertForbidden();
        }
        // A draft is a 404 for students even inside the audience.
        Sanctum::actingAs($this->students[0]->user);
        $this->getJson("/api/student/surveys/{$survey->id}")->assertNotFound();
    }
}
