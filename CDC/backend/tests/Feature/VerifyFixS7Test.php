<?php

namespace Tests\Feature;

use App\Jobs\DeleteBroadcastAttachment;
use App\Mail\BroadcastMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Notice;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\SettingsService;
use App\Services\SurveyAnswerService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Regression tests for the S7 verification fixes (surveys, notices, stage emails): M1 survey questions saved by id and
 * stale answers refused, M3 notices/surveys filtered and paginated in SQL, L21 duplicate first submissions, L22
 * non-responders sheet, L23 attachments kept until queued batches are sent, L24 real file type, L28 literal search.
 */
class VerifyFixS7Test extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";

    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private User $admin;

    private User $companyUser;

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
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
            ],
        ]);

        // 0-3 enrolled + applied (0 published selected, 1 published waitlisted, 2 draft selected, 3 published rejected);
        // 4 enrolled only; 5 Electrical Engineering, batch 2028, not enrolled.
        for ($i = 0; $i < 6; $i++) {
            $s = StudentProfile::factory()->create([
                'roll_no' => sprintf('22JE%04d', $i + 1),
                'full_name' => $i === 2 ? '=HYPERLINK("http://x","y")' : 'Student '.($i + 1),
                'branch' => $i === 5 ? 'Electrical Engineering' : 'Computer Science & Engineering',
                'graduating_batch' => $i === 5 ? 2028 : 2027,
            ]);
            if ($i < 5) {
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

        foreach ([0 => 'selected', 1 => 'waitlisted', 2 => 'selected', 3 => 'rejected'] as $i => $result) {
            $s = $this->students[$i];
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $application = Application::create([
                'job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now(),
            ]);
            ApplicationRoundResult::create([
                'application_id' => $application->id, 'posting_round_id' => $this->round->id, 'result' => $result, 'published_at' => $i === 2 ? null : now(),
            ]);
        }
    }

    // ---------------------------------------------------------------- helpers

    private function asStudent(int $i): void
    {
        Sanctum::actingAs($this->students[$i]->user);
    }

    private function cycleAudience(): array
    {
        return [['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]];
    }

    private function survey(array $questions, array $settings = [], bool $publish = true): Survey
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/surveys', ['title' => 'Fix survey'])->assertCreated()->json('survey.id');
        $this->putJson("/api/admin/surveys/{$id}", $settings + ['questions' => $questions, 'audiences' => $this->cycleAudience()])->assertOk();
        if ($publish) {
            $this->postJson("/api/admin/surveys/{$id}/publish")->assertOk();
        }

        return Survey::findOrFail($id);
    }

    private function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 's7fix');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function publishedNotice(array $audiences, string $title = 'Mine', $publishedAt = null): Notice
    {
        $notice = Notice::create(['title' => $title, 'published_at' => $publishedAt ?? now()]);
        foreach ($audiences as $group) {
            $notice->audiences()->create($group);
        }

        return $notice;
    }

    // ---------------------------------------------------------------- M1: questions by id, stale answers refused

    public function test_saving_the_template_updates_questions_by_id_and_deletes_only_removed_ones(): void
    {
        $survey = $this->survey([
            ['qtype' => 'text', 'question' => 'City'],
            ['qtype' => 'yes_no', 'question' => 'Relocate?'],
            ['qtype' => 'mcq_single', 'question' => 'Shift', 'options' => ['Day', 'Night']],
        ], publish: false);
        [$city, $relocate, $shift] = $survey->questions()->pluck('id')->all();

        $other = $this->survey([['qtype' => 'text', 'question' => 'Other survey question']], publish: false);
        $foreign = $other->questions()->value('id');

        $this->putJson("/api/admin/surveys/{$survey->id}", ['questions' => [
            ['id' => $relocate, 'qtype' => 'yes_no', 'question' => 'Willing to relocate?', 'required' => true],
            ['id' => $city, 'qtype' => 'text', 'question' => 'City'],
            ['qtype' => 'date', 'question' => 'Joining date'],
            ['id' => $foreign, 'qtype' => 'text', 'question' => 'Copied id'],
        ]])->assertOk();

        $rows = SurveyQuestion::query()->where('survey_id', $survey->id)->orderBy('sort_order')->get();
        $this->assertSame([$relocate, $city], $rows->take(2)->pluck('id')->all(), 'kept questions keep their ids, in the new order');
        $this->assertSame('Willing to relocate?', $rows[0]->question);
        $this->assertTrue($rows[0]->required);
        $this->assertSame(['date', 'text'], [$rows[2]->qtype, $rows[3]->qtype]);
        $this->assertNotContains($rows[3]->id, [$foreign], 'another survey\'s question id is a new question here');
        $this->assertDatabaseMissing('survey_questions', ['id' => $shift]);
        $this->assertSame('Other survey question', SurveyQuestion::find($foreign)->question);
        $this->assertSame(1, $other->questions()->count());

        // The Audience tab saves settings only: question ids never change.
        $before = SurveyQuestion::query()->where('survey_id', $survey->id)->orderBy('id')->pluck('id')->all();
        $this->putJson("/api/admin/surveys/{$survey->id}", ['allow_edits' => true, 'is_public' => false, 'audiences' => $this->cycleAudience()])->assertOk();
        $this->assertSame($before, SurveyQuestion::query()->where('survey_id', $survey->id)->orderBy('id')->pluck('id')->all());

        // Re-saving the same template with ids is a no-op for the ids too.
        $same = collect($this->getJson("/api/admin/surveys/{$survey->id}")->json('survey.questions'))->map(fn ($q) => collect($q)->except('sort_order')->all())->all();
        $this->putJson("/api/admin/surveys/{$survey->id}", ['questions' => $same])->assertOk();
        $this->assertSame($before, SurveyQuestion::query()->where('survey_id', $survey->id)->orderBy('id')->pluck('id')->all());
    }

    public function test_an_answer_for_a_deleted_question_is_refused_with_422_not_an_empty_201(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'City'], ['qtype' => 'file', 'question' => 'Letter']]);
        $old = $survey->questions()->where('qtype', 'text')->value('id');
        $file = $survey->questions()->where('qtype', 'file')->value('id');

        // Admin replaces the City question (not sent with its id) while the form is open.
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/surveys/{$survey->id}", ['questions' => [['qtype' => 'text', 'question' => 'Town'], ['id' => $file, 'qtype' => 'file', 'question' => 'Letter']]])->assertOk();

        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$old => 'Dhanbad']])
            ->assertStatus(422)
            ->assertJsonPath('message', SurveyAnswerService::SURVEY_CHANGED)
            ->assertJsonPath('errors.survey.0', SurveyAnswerService::SURVEY_CHANGED);
        // A file keyed by an unknown question is refused the same way (multipart).
        $this->post("/api/student/surveys/{$survey->id}/responses", ['answers' => json_encode([]), 'files' => [$old => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', SurveyAnswerService::SURVEY_CHANGED);
        $this->assertSame(0, SurveyResponse::count());
        $this->assertSame([], Storage::disk('local')->allFiles("surveys/{$survey->id}"));

        // The reloaded form works.
        $new = $survey->questions()->where('qtype', 'text')->value('id');
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$new => 'Dhanbad']])->assertCreated();
        $this->assertSame('Dhanbad', SurveyResponse::sole()->answers[(string) $new]);
    }

    public function test_a_missing_mandatory_answer_is_reported_against_its_question(): void
    {
        $survey = $this->survey([['qtype' => 'yes_no', 'question' => 'OK?', 'required' => true], ['qtype' => 'text', 'question' => 'Why']]);
        $yes = $survey->questions()->where('qtype', 'yes_no')->value('id');
        $why = $survey->questions()->where('qtype', 'text')->value('id');

        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$why => 'x']])
            ->assertStatus(422)->assertJsonValidationErrors(["answers.{$yes}" => 'This question is mandatory.'])->assertJsonMissingValidationErrors(["answers.{$why}"]);
    }

    // ---------------------------------------------------------------- M3: notices and surveys filtered in SQL

    public function test_an_old_notice_is_listed_and_counted_unread_behind_350_notices_for_other_audiences(): void
    {
        $mine = $this->publishedNotice($this->cycleAudience(), 'Old but mine', now()->subYear());
        for ($i = 0; $i < 350; $i++) {
            $this->publishedNotice([['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Electrical Engineering']]]]], "Other {$i}", now()->subDay()->addMinutes($i));
        }

        $this->asStudent(0);
        $response = $this->getJson('/api/student/notices')->assertOk();
        $this->assertSame([$mine->id], collect($response->json('notices'))->pluck('id')->all());
        $this->assertSame(1, $response->json('unread_count'));
        $this->assertFalse($response->json('notices.0.is_read'));
        $response->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1]);

        // Student 5 (Electrical Engineering) sees the 350, paginated.
        $this->asStudent(5);
        $page = $this->getJson('/api/student/notices?page=18')->assertOk();
        $page->assertJsonPath('meta.total', 350)->assertJsonPath('meta.last_page', 18)->assertJsonPath('unread_count', 350);
        $this->assertCount(10, $page->json('notices'));
    }

    public function test_notices_paginate_20_per_page_with_unread_count_over_all_pages_and_no_per_item_queries(): void
    {
        $ids = [];
        for ($i = 0; $i < 25; $i++) {
            $ids[] = $this->publishedNotice([['audience_type' => 'all']], "N{$i}", now()->subMinutes(100 - $i))->id;
        }
        $this->publishedNotice([['audience_type' => 'all']], 'Draft')->update(['published_at' => null]);

        $this->asStudent(4);
        $first = $this->getJson('/api/student/notices')->assertOk();
        $this->assertCount(20, $first->json('notices'));
        $this->assertSame(array_slice(array_reverse($ids), 0, 20), collect($first->json('notices'))->pluck('id')->all(), 'newest first');
        $first->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2, 'per_page' => 20, 'total' => 25])->assertJsonPath('unread_count', 25);

        $this->postJson("/api/student/notices/{$ids[0]}/read")->assertOk();
        $second = $this->getJson('/api/student/notices?page=2')->assertOk();
        $this->assertCount(5, $second->json('notices'));
        $second->assertJsonPath('unread_count', 24);
        $this->assertTrue(collect($second->json('notices'))->firstWhere('id', $ids[0])['is_read']);

        // The query count does not grow with the number of notices on the page.
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson('/api/student/notices')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $with25 = $count();
        for ($i = 0; $i < 30; $i++) {
            $this->publishedNotice([['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]]], "More {$i}");
        }
        $this->assertSame($with25, $count());
        $this->assertLessThan(20, $with25);
    }

    public function test_student_surveys_are_filtered_in_sql_and_paginated(): void
    {
        Sanctum::actingAs($this->admin);
        $mine = Survey::create(['title' => 'Old but mine', 'status' => 'published', 'published_at' => now()->subYear()]);
        $mine->audiences()->create($this->cycleAudience()[0]);
        for ($i = 0; $i < 210; $i++) {
            $s = Survey::create(['title' => "Other {$i}", 'status' => 'published', 'published_at' => now()->subDay()->addMinutes($i)]);
            $s->audiences()->create(['audience_type' => 'batch', 'audience_filter' => ['batches' => [2028]]]);
        }
        $public = Survey::create(['title' => 'Public', 'status' => 'published', 'is_public' => true, 'published_at' => now()->subMonth()]);
        Survey::create(['title' => 'Draft', 'status' => 'draft', 'is_public' => true]);

        $this->asStudent(0);
        $response = $this->getJson('/api/student/surveys')->assertOk();
        $this->assertSame([$public->id, $mine->id], collect($response->json('surveys'))->pluck('id')->all());
        $response->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 2]);

        $this->asStudent(5);
        $this->getJson('/api/student/surveys?page=11')->assertOk()->assertJsonPath('meta.total', 211)->assertJsonCount(11, 'surveys');
    }

    public function test_sql_audience_filter_matches_the_audience_rule_for_every_group_type(): void
    {
        Offer::create([
            'application_id' => Application::where('student_profile_id', $this->students[1]->id)->value('id'),
            'student_profile_id' => $this->students[1]->id, 'company_id' => $this->posting->company()->id, 'job_posting_id' => $this->posting->id,
            'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'fulltime', 'ctc_annual' => 100, 'announced_at' => now(),
        ]);
        $groups = [
            ['audience_type' => 'all', 'audience_filter' => null],
            ['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => 'Electrical Engineering']]]],
            ['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => StudentProfileFactory::BTECH, 'branch' => null]]]],
            ['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => 'M.Tech', 'branch' => null], ['programme' => StudentProfileFactory::BTECH, 'branch' => 'Computer Science & Engineering']]]],
            ['audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => 'M.Tech', 'branch' => 'Computer Science & Engineering']]]],
            ['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => $this->cycle->id]],
            ['audience_type' => 'cycle', 'audience_filter' => ['placement_cycle_id' => 999]],
            ['audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $this->posting->id]],
            ['audience_type' => 'offer_holders', 'audience_filter' => ['job_posting_id' => $this->posting->id]],
            ['audience_type' => 'batch', 'audience_filter' => ['batches' => [2028, 2030]]],
            ['audience_type' => 'round_results', 'audience_filter' => ['job_posting_id' => $this->posting->id, 'posting_round_id' => $this->round->id, 'results' => ['selected']]],
            ['audience_type' => 'round_results', 'audience_filter' => ['job_posting_id' => $this->posting->id, 'posting_round_id' => $this->round->id, 'results' => ['waitlisted', 'selected']]],
        ];
        $notices = collect($groups)->map(fn ($g) => $this->publishedNotice([$g]));
        $surveys = collect($groups)->map(function ($g) {
            $s = Survey::create(['title' => 'S', 'status' => 'published', 'published_at' => now()]);
            $s->audiences()->create($g);

            return $s->load('audiences');
        });

        foreach ($this->students as $student) {
            $this->assertEqualsCanonicalizing(
                $notices->filter(fn (Notice $n) => $n->load('audiences')->isVisibleTo($student))->pluck('id')->all(),
                Notice::query()->visibleTo($student)->pluck('id')->all(),
                "notices of student {$student->roll_no}"
            );
            $this->assertEqualsCanonicalizing(
                $surveys->filter(fn (Survey $s) => $s->isVisibleTo($student))->pluck('id')->all(),
                Survey::query()->visibleTo($student)->pluck('id')->all(),
                "surveys of student {$student->roll_no}"
            );
        }
        // Spot checks: student 2's draft decision and student 3's rejection never match a stage group.
        $this->assertNotContains($notices[10]->id, Notice::query()->visibleTo($this->students[2])->pluck('id')->all());
        $this->assertNotContains($notices[11]->id, Notice::query()->visibleTo($this->students[3])->pluck('id')->all());
        $this->assertContains($notices[11]->id, Notice::query()->visibleTo($this->students[1])->pluck('id')->all());
    }

    // ---------------------------------------------------------------- L21: duplicate first submissions

    public function test_single_submission_responses_carry_a_unique_key_and_a_concurrent_duplicate_gets_409(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T'], ['qtype' => 'file', 'question' => 'F']]);
        $text = $survey->questions()->where('qtype', 'text')->value('id');
        $file = $survey->questions()->where('qtype', 'file')->value('id');

        // Another request stores the first response between this request's check and its insert.
        SurveyResponse::creating(function (SurveyResponse $response): void {
            if ($response->single_key) {
                DB::table('survey_responses')->insert([
                    'survey_id' => $response->survey_id, 'student_profile_id' => $response->student_profile_id, 'single_key' => $response->single_key,
                    'answers' => '{}', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $this->asStudent(0);
        $this->post("/api/student/surveys/{$survey->id}/responses", ['answers' => json_encode([$text => 'a']), 'files' => [$file => UploadedFile::fake()->create('a.pdf', 5, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertStatus(409)->assertJsonPath('message', 'You have already responded to this survey.');
        $this->assertSame([], Storage::disk('local')->allFiles("surveys/{$survey->id}"), 'the refused duplicate leaves no file behind');
        SurveyResponse::flushEventListeners();

        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$text => 'a']])->assertCreated();
        $this->assertSame("{$survey->id}:{$this->students[0]->id}", SurveyResponse::sole()->single_key);
        $this->assertArrayNotHasKey('single_key', SurveyResponse::sole()->toArray());

        $this->expectException(UniqueConstraintViolationException::class);
        SurveyResponse::create(['survey_id' => $survey->id, 'student_profile_id' => $this->students[0]->id, 'single_key' => "{$survey->id}:{$this->students[0]->id}", 'answers' => [], 'submitted_at' => now()]);
    }

    public function test_multiple_submission_surveys_store_no_key(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T']], ['allow_multiple' => true]);
        $q = $survey->questions()->value('id');
        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'a']])->assertCreated();
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$q => 'b']])->assertCreated();
        $this->assertSame([null, null], SurveyResponse::query()->orderBy('id')->pluck('single_key')->all());
    }

    public function test_single_key_migration_backfills_existing_rows_cleanly(): void
    {
        $migration = require database_path('migrations/2026_10_07_000036_add_single_key_to_survey_responses.php');
        $migration->down();

        $single = Survey::create(['title' => 'One', 'status' => 'published', 'allow_multiple' => false]);
        $multi = Survey::create(['title' => 'Many', 'status' => 'published', 'allow_multiple' => true]);
        $row = fn (Survey $s, int $student) => DB::table('survey_responses')->insertGetId([
            'survey_id' => $s->id, 'student_profile_id' => $this->students[$student]->id, 'answers' => '{}', 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $first = $row($single, 0);
        $duplicate = $row($single, 0); // an old duplicate from before the guard
        $other = $row($single, 1);
        $many = [$row($multi, 0), $row($multi, 0)];

        $migration->up();

        $keys = DB::table('survey_responses')->pluck('single_key', 'id');
        $this->assertSame("{$single->id}:{$this->students[0]->id}", $keys[$first]);
        $this->assertNull($keys[$duplicate]);
        $this->assertSame("{$single->id}:{$this->students[1]->id}", $keys[$other]);
        $this->assertNull($keys[$many[0]]);
        $this->assertNull($keys[$many[1]]);
    }

    // ---------------------------------------------------------------- L22: non-responders sheet

    public function test_export_adds_a_formula_safe_non_responders_sheet_and_keeps_the_responses_sheet(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T']]);
        $this->asStudent(0);
        $this->postJson("/api/student/surveys/{$survey->id}/responses", ['answers' => [$survey->questions()->value('id') => 'x']])->assertCreated();

        Sanctum::actingAs($this->admin);
        $path = tempnam(sys_get_temp_dir(), 's7x').'.xlsx';
        file_put_contents($path, $this->get("/api/admin/surveys/{$survey->id}/export")->assertOk()->streamedContent());
        $book = IOFactory::load($path);
        @unlink($path);

        $this->assertSame(['Responses', 'Non-responders'], $book->getSheetNames());
        $this->assertSame('Responses', $book->getActiveSheet()->getTitle());
        $this->assertSame($this->students[0]->roll_no, $book->getSheet(0)->getCell('B2')->getValue());

        $sheet = $book->getSheetByName('Non-responders');
        $this->assertSame(['Roll Number', 'Name', 'Branch'], [$sheet->getCell('A1')->getValue(), $sheet->getCell('B1')->getValue(), $sheet->getCell('C1')->getValue()]);
        $rolls = [];
        for ($row = 2; $sheet->getCell("A{$row}")->getValue() !== null; $row++) {
            $rolls[] = $sheet->getCell("A{$row}")->getValue();
        }
        $this->assertSame(['22JE0002', '22JE0003', '22JE0004', '22JE0005'], $rolls); // enrolled 1-4, by roll number; never 22JE0001 (responded) or 22JE0006
        $this->assertSame('=HYPERLINK("http://x","y")', $sheet->getCell('B3')->getValue());
        $this->assertFalse($sheet->getCell('B3')->isFormula());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B3')->getDataType());
        $this->assertSame('Computer Science & Engineering', $sheet->getCell('C2')->getValue());
    }

    // ---------------------------------------------------------------- L23: attachments vs queued batches

    public function test_notice_mail_attaches_a_copy_that_outlives_the_notice_until_the_batches_are_sent(): void
    {
        Queue::fake([DeleteBroadcastAttachment::class]);
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/notices', ['title' => 'Venue', 'audiences' => $this->cycleAudience()])->assertCreated()->json('notice.id');
        $this->post("/api/admin/notices/{$id}/attachment", ['file' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $original = Notice::find($id)->attachment_path;
        $this->postJson("/api/admin/notices/{$id}/publish", ['send_email' => true])->assertOk();

        $copy = null;
        Mail::assertQueued(BroadcastMail::class, function (BroadcastMail $m) use (&$copy) {
            $copy = $m->attachmentPath;

            return $m->attachmentName === 'plan.pdf';
        });
        $this->assertNotSame($original, $copy);
        $this->assertStringStartsWith("broadcast-attachments/notice-{$id}/", $copy);

        // Replacing the attachment and deleting the notice never touch the copy the queued batches use.
        $this->post("/api/admin/notices/{$id}/attachment", ['file' => UploadedFile::fake()->create('plan2.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $this->deleteJson("/api/admin/notices/{$id}")->assertOk();
        Storage::disk('local')->assertMissing($original);
        Storage::disk('local')->assertExists($copy);

        $job = null;
        Queue::assertPushed(DeleteBroadcastAttachment::class, function (DeleteBroadcastAttachment $j) use (&$job, $copy) {
            $job = $j;

            return $j->path === $copy;
        });
        $job->handle();
        Storage::disk('local')->assertExists($copy); // batches still queued
        EmailLog::query()->where('kind', 'notice')->update(['status' => 'sent', 'sent_at' => now()]);
        $job->handle();
        Storage::disk('local')->assertMissing($copy);
    }

    public function test_stage_email_attachment_is_cleaned_up_after_the_send_completes(): void
    {
        Queue::fake([DeleteBroadcastAttachment::class]);
        Sanctum::actingAs($this->admin);
        $this->post("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", [
            'subject' => 'Venue', 'message' => '<p>Hall B</p>', 'results' => ['selected', 'waitlisted'],
            'attachment' => UploadedFile::fake()->create('venue.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        $files = Storage::disk('local')->allFiles("stage-emails/{$this->round->id}");
        $this->assertCount(1, $files);
        $job = null;
        Queue::assertPushed(DeleteBroadcastAttachment::class, function (DeleteBroadcastAttachment $j) use (&$job, $files) {
            $job = $j;

            return $j->path === $files[0] && $j->subject === 'Venue';
        });
        $this->assertTrue($job->stillSending());
        EmailLog::query()->where('kind', 'stage_email')->update(['status' => 'failed']);
        $job->handle();
        $this->assertSame([], Storage::disk('local')->allFiles("stage-emails/{$this->round->id}"));
    }

    public function test_in_sync_mail_mode_the_send_file_is_deleted_at_once(): void
    {
        Queue::fake([DeleteBroadcastAttachment::class]);
        app(SettingsService::class)->set('mail_mode', 'sync', $this->admin);
        Sanctum::actingAs($this->admin);
        $this->post("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", [
            'subject' => 'Venue', 'message' => 'Hall B', 'results' => ['selected'],
            'attachment' => UploadedFile::fake()->create('venue.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk();

        Mail::assertSent(BroadcastMail::class);
        $this->assertSame([], Storage::disk('local')->allFiles("stage-emails/{$this->round->id}"));
        Queue::assertNotPushed(DeleteBroadcastAttachment::class);
    }

    // ---------------------------------------------------------------- L24: real type must match the extension

    public function test_uploads_whose_content_does_not_match_the_extension_are_refused(): void
    {
        $png = base64_decode(self::PNG_BASE64);

        // Survey File Upload answers.
        $survey = $this->survey([['qtype' => 'file', 'question' => 'Letter']], ['allow_multiple' => true]);
        $q = $survey->questions()->value('id');
        $url = "/api/student/surveys/{$survey->id}/responses";
        $send = fn (UploadedFile $f) => $this->post($url, ['answers' => json_encode([]), 'files' => [$q => $f]], ['Accept' => 'application/json']);
        $this->asStudent(0);
        $send($this->realFile('letter.png', self::PDF))->assertStatus(422)->assertJsonValidationErrors(["answers.{$q}"]);
        $send(UploadedFile::fake()->create('letter.png', 5, 'application/pdf'))->assertStatus(422);
        $send($this->realFile('photo.pdf', $png))->assertStatus(422);
        $this->assertSame(0, SurveyResponse::count());
        $send($this->realFile('letter.pdf', self::PDF))->assertCreated();
        $send($this->realFile('photo.png', $png))->assertCreated();

        // Stage email attachments.
        Sanctum::actingAs($this->admin);
        $stage = fn (UploadedFile $f) => $this->post("/api/admin/postings/{$this->posting->id}/rounds/{$this->round->id}/email", [
            'subject' => 'Venue', 'message' => 'Hall B', 'results' => ['selected'], 'attachment' => $f,
        ], ['Accept' => 'application/json']);
        $stage($this->realFile('venue.png', self::PDF))->assertStatus(422)->assertJsonValidationErrors(['attachment']);
        $stage(UploadedFile::fake()->create('venue.jpg', 5, 'image/png'))->assertStatus(422);
        Mail::assertNotQueued(BroadcastMail::class);
        $stage($this->realFile('venue.pdf', self::PDF))->assertOk();

        // Notice attachments.
        $id = $this->postJson('/api/admin/notices', ['title' => 'Venue', 'audiences' => $this->cycleAudience()])->json('notice.id');
        $attach = fn (UploadedFile $f) => $this->post("/api/admin/notices/{$id}/attachment", ['file' => $f], ['Accept' => 'application/json']);
        $attach($this->realFile('plan.pdf', '<html><body>not a pdf</body></html>'))->assertStatus(422);
        $attach($this->realFile('plan.pdf', $png))->assertStatus(422);
        $this->assertNull(Notice::find($id)->attachment_path);
        $attach($this->realFile('plan.pdf', self::PDF))->assertOk();
    }

    // ---------------------------------------------------------------- L28: literal % and _ in searches

    public function test_admin_survey_and_notice_searches_treat_percent_and_underscore_literally(): void
    {
        Sanctum::actingAs($this->admin);
        foreach (['100% Feedback', '1000 Feedback', 'PPO_2026', 'PPOx2026'] as $title) {
            Survey::create(['title' => $title, 'status' => 'draft']);
            Notice::create(['title' => $title]);
        }

        $titles = fn (string $path, string $key) => collect($this->getJson($path)->assertOk()->json($key))->pluck('title')->all();
        $this->assertSame(['100% Feedback'], $titles('/api/admin/surveys?search='.urlencode('100%'), 'surveys'));
        $this->assertSame(['PPO_2026'], $titles('/api/admin/surveys?search='.urlencode('PPO_'), 'surveys'));
        $this->assertSame(['100% Feedback'], $titles('/api/admin/notices?search='.urlencode('100%'), 'notices'));
        $this->assertSame(['PPO_2026'], $titles('/api/admin/notices?search='.urlencode('PPO_'), 'notices'));
    }

    // ---------------------------------------------------------------- permissions

    public function test_students_and_companies_cannot_reach_the_admin_routes_touched_here(): void
    {
        $survey = $this->survey([['qtype' => 'text', 'question' => 'T']]);
        foreach ([$this->companyUser, $this->students[0]->user] as $user) {
            Sanctum::actingAs($user);
            $this->putJson("/api/admin/surveys/{$survey->id}", ['allow_edits' => true])->assertForbidden();
            $this->getJson("/api/admin/surveys/{$survey->id}/export")->assertForbidden();
            $this->getJson('/api/admin/notices?search=x')->assertForbidden();
        }
        Sanctum::actingAs($this->companyUser);
        $this->getJson('/api/student/notices')->assertForbidden();
        $this->getJson('/api/student/surveys')->assertForbidden();
    }
}
