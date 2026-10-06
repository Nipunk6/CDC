<?php

namespace Tests\Feature\QA;

use App\Mail\PortalNoticeMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use Carbon\Carbon;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * QA acceptance — Phase 2 Part B2/B3 (eligibility, floating, applying). Every assertion encodes the
 * REQUIREMENT (spec PHASE2_IMPLEMENTATION_SPEC.md B2/B3/M5), not the current behaviour.
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S3EligibilityApplyTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const CSE = 'Computer Science & Engineering';

    private const ECE = 'Electronics & Communication Engineering';

    private const MECH = 'Mechanical Engineering';

    /**
     * Oracle: actor => [eligible for the JNF, reason patterns (each must match exactly one reason; reason count must equal pattern count)].
     */
    private const ORACLE = [
        'ELIGIBLE' => [true, []],
        'LOWCGPA' => [false, ['/^CGPA below cutoff \(6\.20? < 7\.00?\)/']],
        'BACKLOG' => [false, ['/ongoing backlog.*1.*0/i', '/total backlog.*2.*1/i']],
        'TOTALBACKLOG_ONLY' => [true, []],
        'FEMALE' => [true, []],
        'OTHERBRANCH' => [false, ['/branch is not eligible/i']],
        'WRONGBATCH' => [false, ['/2027.*batch|batch.*2027/i']],
        'LOW10TH' => [false, ['/10th.*55(\.0+)?\D.*60/']],
        'LOW12TH' => [false, ['/12th.*55(\.0+)?\D.*60/']],
        'NOTENROLLED' => [false, ['/not enrolled/i']],
        'SUSPENDED' => [false, ['/suspended/i']],
        'DEBARRED' => [false, ['/debarred/i']],
    ];

    private User $admin;

    private PlacementCycle $ft;

    private PlacementCycle $intern;

    private Company $companyA;

    private Company $companyB;

    private User $companyAUser;

    private User $companyBUser;

    /** @var array<string, StudentProfile> */
    private array $actors = [];

    private JobPosting $jnfPosting;

    private JobPosting $infPosting;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $base = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]];
        $this->ft = PlacementCycle::create(['name' => 'FT 2026-27', 'type' => 'fulltime'] + $base);
        $this->intern = PlacementCycle::create(['name' => 'Intern 2026-27', 'type' => 'internship'] + $base);

        $this->companyA = Company::create(['name' => 'Company A', 'hr_name' => 'HR A', 'hr_email' => 'hr@company-a.test']);
        $this->companyB = Company::create(['name' => 'Company B', 'hr_name' => 'HR B', 'hr_email' => 'hr@company-b.test']);
        $this->companyAUser = User::factory()->create(['role' => 'company', 'company_id' => $this->companyA->id, 'email' => 'recruiter@company-a.test']);
        $this->companyBUser = User::factory()->create(['role' => 'company', 'company_id' => $this->companyB->id, 'email' => 'recruiter@company-b.test']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ============================================================================================
    // Fixture builders
    // ============================================================================================

    private function branchRow(string $branch, string $cgpa = '7.0', array $extra = []): array
    {
        return array_merge([
            'branch' => $branch, 'selected' => true, 'cgpa' => $cgpa,
            'backlogsAllowed' => true, 'maxOngoingBacklogs' => '0', 'maxTotalBacklogs' => '1',
        ], $extra);
    }

    private function formData(array $overrides = [], ?array $branches = null): array
    {
        return array_replace([
            'jobTitle' => 'Graduate Engineer',
            'internshipTitle' => 'Summer Intern',
            'companyProfile' => ['name' => 'Company A', 'postalAddress' => 'Secret lane 7', 'website' => 'https://company-a.test'],
            'signatory' => ['name' => 'Signatory Person', 'designation' => 'HR'],
            'eligibility' => [[
                'programme' => self::BTECH,
                'branches' => $branches ?? [
                    $this->branchRow(self::CSE),
                    $this->branchRow(self::ECE),
                    ['branch' => self::MECH, 'selected' => false, 'cgpa' => '', 'backlogsAllowed' => false],
                ],
            ]],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'minTenthPercent' => '60',
            'minTwelfthPercent' => '60',
            'currency' => 'INR',
            'programmeSalaries' => [['programme' => self::BTECH, 'ctcAnnual' => '1800000', 'enabled' => true]],
            'programmeStipends' => [['programme' => self::BTECH, 'total' => '80000', 'enabled' => true]],
            'selectionRounds' => [
                ['id' => '1', 'type' => 'aptitude_test', 'enabled' => true],
                ['id' => '2', 'type' => 'group_discussion', 'enabled' => true],
                ['id' => '3', 'type' => 'technical_interview', 'enabled' => true],
                ['id' => '4', 'type' => 'hr_interview', 'enabled' => false],
            ],
        ], $overrides);
    }

    private function jnf(?Company $company = null, ?array $data = null, string $status = 'accepted', string $title = 'Graduate Engineer'): Jnf
    {
        $company ??= $this->companyA;
        $data = array_replace($data ?? $this->formData(), ['jobTitle' => $title]);

        return Jnf::create(['company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => $status, 'vacancies' => 5, 'form_data' => $data]);
    }

    private function inf(?Company $company = null, ?array $data = null, string $status = 'accepted', string $title = 'Summer Intern'): Inf
    {
        $company ??= $this->companyB;
        $data = array_replace($data ?? $this->formData(), ['internshipTitle' => $title]);

        return Inf::create(['company_id' => $company->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => $status, 'vacancies' => 5, 'form_data' => $data]);
    }

    private function float(Jnf|Inf $form, ?PlacementCycle $cycle = null, array $overrides = []): TestResponse
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson('/api/admin/postings', array_merge([
            'form_type' => $form instanceof Inf ? 'inf' : 'jnf',
            'form_id' => $form->id,
            'placement_cycle_id' => ($cycle ?? ($form instanceof Inf ? $this->intern : $this->ft))->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
            'questions' => [['question' => 'Why do you want to join?', 'qtype' => 'text', 'required' => true]],
        ], $overrides));
    }

    private function floatOk(Jnf|Inf $form, ?PlacementCycle $cycle = null, array $overrides = []): JobPosting
    {
        $response = $this->float($form, $cycle, $overrides);
        $response->assertCreated();

        return JobPosting::query()->findOrFail($response->json('posting.id'));
    }

    /**
     * @param  list<PlacementCycle>|null  $cycles  null = the FT cycle; [] = not enrolled anywhere
     */
    private function student(array $attributes = [], ?array $cycles = null): StudentProfile
    {
        $student = StudentProfile::factory()->create(array_merge([
            'programme' => self::BTECH,
            'branch' => self::CSE,
            'graduating_batch' => 2027,
            'tenth_percent' => 90,
            'twelfth_percent' => 88,
            'current_cgpa' => 8.5,
            'ongoing_backlogs' => 0,
            'total_backlogs' => 0,
            'gender' => 'male',
        ], $attributes));

        foreach ($cycles ?? [$this->ft] as $cycle) {
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        }

        $student->resumes()->create(['slot' => 1, 'label' => 'Main CV', 'file_path' => "resumes/{$student->roll_no}/1.pdf", 'file_size' => 100, 'status' => 'approved']);

        return $student;
    }

    private function userOf(StudentProfile $student): User
    {
        return User::query()->findOrFail($student->user_id);
    }

    private function as(StudentProfile|User $who): void
    {
        Sanctum::actingAs($who instanceof StudentProfile ? $this->userOf($who) : $who->fresh());
    }

    /** Build every oracle actor, then float the oracle JNF (FT) and the female-only INF (internship). */
    private function oracle(): void
    {
        $both = [$this->ft, $this->intern];

        $this->actors = [
            'ELIGIBLE' => $this->student([], $both),
            'LOWCGPA' => $this->student(['current_cgpa' => 6.2]),
            'BACKLOG' => $this->student(['ongoing_backlogs' => 1, 'total_backlogs' => 2]),
            'TOTALBACKLOG_ONLY' => $this->student(['ongoing_backlogs' => 0, 'total_backlogs' => 1]),
            'FEMALE' => $this->student(['gender' => 'female'], $both),
            'OTHERBRANCH' => $this->student(['branch' => self::MECH]),
            'WRONGBATCH' => $this->student(['graduating_batch' => 2028]),
            'LOW10TH' => $this->student(['tenth_percent' => 55]),
            'LOW12TH' => $this->student(['twelfth_percent' => 55]),
            'NOTENROLLED' => $this->student([], []),
            'SUSPENDED' => $this->student(),
            'DEBARRED' => $this->student(),
        ];

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/students/{$this->actors['SUSPENDED']->id}/suspend")->assertOk();
        $this->postJson('/api/admin/blocks', [
            'student_profile_id' => $this->actors['DEBARRED']->id,
            'placement_cycle_id' => $this->ft->id,
            'scope' => 'all',
            'reason' => 'debarred',
            'remark' => 'Skipped a scheduled interview',
        ])->assertCreated();

        $this->jnfPosting = $this->floatOk($this->jnf($this->companyA));
        $this->infPosting = $this->floatOk($this->inf($this->companyB, $this->formData(['genderFilter' => 'female'])));
    }

    /** @return list<array{question_id: int, answer: mixed}> */
    private function validAnswers(JobPosting $posting): array
    {
        return $posting->questions()->get()->map(fn ($q) => [
            'question_id' => $q->id,
            'answer' => match ($q->qtype) {
                'text' => 'Because the work is interesting.',
                'mcq_single' => $q->options[0],
                default => [$q->options[0]],
            },
        ])->all();
    }

    private function apply(StudentProfile $student, JobPosting $posting, ?array $answers = null, ?int $resumeId = null): TestResponse
    {
        $this->as($student);

        return $this->postJson("/api/student/postings/{$posting->id}/apply", [
            'resume_id' => $resumeId ?? $student->resumes()->orderBy('slot')->first()->id,
            'answers' => $answers ?? $this->validAnswers($posting),
        ]);
    }

    private function eligibility(): EligibilityService
    {
        // A fresh instance every time: the service memoises blocks per instance.
        return new EligibilityService();
    }

    private function inQuery(StudentProfile $student, JobPosting $posting): bool
    {
        return $this->eligibility()->eligibleStudentsQuery($posting->fresh())->whereKey($student->id)->exists();
    }

    private function assertReasons(array $patterns, array $reasons, string $label): void
    {
        $this->assertCount(count($patterns), $reasons, "{$label}: expected exactly ".count($patterns).' specific reason(s), got '.json_encode($reasons));
        foreach ($patterns as $pattern) {
            $hits = array_filter($reasons, fn ($r) => is_string($r) && preg_match($pattern, $r));
            $this->assertCount(1, $hits, "{$label}: no reason matches {$pattern}; got ".json_encode($reasons));
        }
        foreach ($reasons as $reason) {
            $this->assertIsString($reason);
            $this->assertMatchesRegularExpression('/^[A-Z0-9].{8,}/u', $reason, "{$label}: reason is not a human-readable sentence: {$reason}");
        }
    }

    /**
     * (a) list badge, (b) detail, (c) direct API apply, (d) eligibleStudentsQuery membership == check().
     */
    private function assertOracle(string $actor, JobPosting $posting, bool $eligible, array $patterns): void
    {
        $student = $this->actors[$actor]->fresh();
        $label = "[{$actor} / posting #{$posting->id}]";

        $check = $this->eligibility()->check($student, $posting->fresh());
        $this->assertSame($eligible, $check['eligible'], "{$label} check() eligible; reasons=".json_encode($check['reasons']));
        $this->assertReasons($patterns, $check['reasons'], "{$label} check()");

        // (d)
        $this->assertSame($check['eligible'], $this->inQuery($student, $posting), "{$label} eligibleStudentsQuery membership must equal check()");

        $applicationsBefore = Application::query()->where('job_posting_id', $posting->id)->where('student_profile_id', $student->id)->count();

        if ($actor === 'SUSPENDED') {
            $this->as($student);
            $this->getJson('/api/student/postings')->assertStatus(403)->assertJsonPath('message', 'Account suspended. Contact CDC.');
            $this->getJson("/api/student/postings/{$posting->id}")->assertStatus(403);
            $this->apply($student, $posting)->assertStatus(403);
            $this->assertSame($applicationsBefore, Application::query()->where('job_posting_id', $posting->id)->where('student_profile_id', $student->id)->count());

            return;
        }

        // Not enrolled, or a drive that leaves the student's branch out (owner decision 2026-10-01): hidden entirely.
        if (in_array($actor, ['NOTENROLLED', 'OTHERBRANCH'], true)) {
            $this->as($student);
            $list = $this->getJson('/api/student/postings')->assertOk();
            $this->assertNull(collect($list->json('postings'))->firstWhere('id', $posting->id), "{$label} must not see the posting");
            $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();
            $apply = $this->apply($student, $posting);
            $this->assertTrue($apply->status() >= 400 && $apply->status() < 500, "{$label} apply must be refused with a 4xx, got {$apply->status()}");
            $this->assertSame(0, Application::query()->where('job_posting_id', $posting->id)->where('student_profile_id', $student->id)->count());

            return;
        }

        // (a) list badge
        $this->as($student);
        $list = $this->getJson('/api/student/postings')->assertOk();
        $item = collect($list->json('postings'))->firstWhere('id', $posting->id);
        $this->assertNotNull($item, "{$label} the posting must be listed for an enrolled student (Q3.2)");
        $this->assertSame($check['eligible'], $item['eligibility']['eligible'], "{$label} list badge");
        $this->assertSame($check['reasons'], $item['eligibility']['reasons'], "{$label} list reasons must equal check()");

        // (b) detail
        $detail = $this->getJson("/api/student/postings/{$posting->id}")->assertOk();
        $this->assertSame($check['eligible'], $detail->json('posting.eligibility.eligible'), "{$label} detail badge");
        $this->assertSame($check['reasons'], $detail->json('posting.eligibility.reasons'), "{$label} detail reasons must equal check()");

        // (c) direct API apply re-check
        $apply = $this->apply($student, $posting);
        if ($eligible) {
            $this->assertContains($apply->status(), [200, 201], "{$label} eligible apply: ".$apply->getContent());
            $this->assertSame('applied', Application::query()->where('job_posting_id', $posting->id)->where('student_profile_id', $student->id)->value('status'));
        } else {
            $apply->assertStatus(422);
            $this->assertSame($check['reasons'], $apply->json('reasons'), "{$label} apply 422 must carry the same reasons");
            $this->assertSame(0, Application::query()->where('job_posting_id', $posting->id)->where('student_profile_id', $student->id)->count(), "{$label} no application row");
        }
    }

    private function oracleFor(string $actor): void
    {
        $this->oracle();
        [$eligible, $patterns] = self::ORACLE[$actor];
        $this->assertOracle($actor, $this->jnfPosting, $eligible, $patterns);
    }

    // ============================================================================================
    // T3.1a — separate float step
    // ============================================================================================

    public function test_T3_1a_separate_float_step_and_guards(): void
    {
        $student = $this->student();
        $jnf = $this->jnf();

        // Accepted but not floated → invisible.
        $this->as($student);
        $this->getJson('/api/student/postings')->assertOk()->assertJsonPath('meta.total', 0)->assertJsonPath('postings', []);
        $this->assertSame([], $this->getJson('/api/student/dashboard')->assertOk()->json('nudges'));

        // Float requires a cycle and a future deadline.
        $this->float($jnf, null, ['placement_cycle_id' => null])->assertStatus(422)->assertJsonValidationErrors('placement_cycle_id');
        $this->float($jnf, null, ['placement_cycle_id' => 99999])->assertStatus(422);
        $this->float($jnf, null, ['application_deadline' => null])->assertStatus(422)->assertJsonValidationErrors('application_deadline');
        $this->float($jnf, null, ['application_deadline' => now()->subMinute()->toIso8601String()])->assertStatus(422)->assertJsonValidationErrors('application_deadline');

        // Non-accepted forms.
        foreach (['draft', 'submitted', 'under_review', 'rejected'] as $status) {
            $this->float($this->jnf(null, null, $status, "Form {$status}"))->assertStatus(422)
                ->assertJsonPath('message', 'Only accepted forms can be floated to students.');
        }

        // Closed cycle.
        $closed = PlacementCycle::create(['name' => 'Closed FT', 'type' => 'fulltime', 'starts_on' => '2025-07-01', 'ends_on' => '2026-06-30', 'status' => 'closed', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2026]]]]);
        $this->float($jnf, $closed)->assertStatus(422);

        // Type mismatch (spec M5.3: JNF→fulltime, INF→internship).
        $this->float($jnf, $this->intern)->assertStatus(422);
        $this->float($this->inf(), $this->ft)->assertStatus(422);

        $this->assertSame(0, JobPosting::count(), 'no guard may leave a posting behind');
        $this->as($student);
        $this->getJson('/api/student/postings')->assertJsonPath('meta.total', 0);

        // The explicit float makes it visible.
        $posting = $this->floatOk($jnf);
        $this->as($student);
        $this->getJson('/api/student/postings')->assertJsonPath('meta.total', 1)->assertJsonPath('postings.0.id', $posting->id);
        $this->getJson("/api/student/postings/{$posting->id}")->assertOk();

        // Floating twice.
        $this->float($jnf)->assertStatus(422)->assertJsonPath('message', 'This form has already been floated.');
        $this->assertSame(1, JobPosting::count());
    }

    // ============================================================================================
    // T3.1b — snapshot follows an admin eligibility edit (owner decision 2026-10-06, D103; replaces "frozen at float")
    // ============================================================================================

    public function test_T3_1b_admin_cutoff_edit_after_float_updates_the_snapshot_and_keeps_existing_applications(): void
    {
        $jnf = $this->jnf();
        $posting = $this->floatOk($jnf);
        $applied = $this->student(['current_cgpa' => 8.0]);
        $between = $this->student(['current_cgpa' => 8.0]); // 7.0 (float-time) <= 8.0 < 9.0 (edited)
        $this->apply($applied, $posting)->assertCreated();

        // Admin raises the per-branch cutoff 7.0 → 9.0 through the form editor.
        $data = $jnf->fresh()->form_data;
        $data['eligibility'][0]['branches'][0]['cgpa'] = '9.0';
        $data['eligibility'][0]['branches'][1]['cgpa'] = '9.0';
        $data['globalCgpa'] = '9.0';
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/jnfs/{$jnf->id}/form-data", ['form_data' => $data])->assertOk();
        $this->assertSame('9.0', $jnf->fresh()->form_data['eligibility'][0]['branches'][0]['cgpa']);
        $this->assertSame('9.0', $posting->fresh()->eligibility_snapshot['eligibility'][0]['branches'][0]['cgpa'], 'snapshot follows the edit');

        // The new rule applies everywhere, and check() and the SQL audience agree.
        $this->assertFalse($this->eligibility()->check($between->fresh(), $posting->fresh())['eligible']);
        $this->assertFalse($this->inQuery($between, $posting));

        $this->as($between);
        $item = collect($this->getJson('/api/student/postings')->json('postings'))->firstWhere('id', $posting->id);
        $this->assertFalse($item['eligibility']['eligible']);
        $detail = $this->getJson("/api/student/postings/{$posting->id}")->assertOk();
        $this->assertSame('9.0', $detail->json('posting.form_data.eligibility.0.branches.0.cgpa'), 'criteria shown must equal criteria enforced (D66)');
        $this->apply($between, $posting)->assertStatus(422);

        // The student who applied before the change keeps the application (D103).
        $this->assertSame(1, Application::where('job_posting_id', $posting->id)->where('student_profile_id', $applied->id)->where('status', 'applied')->count());
    }

    public function test_T3_1b_admin_per_branch_cgpa_edit_is_actually_saved(): void
    {
        // Side check discovered while building T3.1b: an admin editing ONLY a branch's CGPA cutoff.
        $jnf = $this->jnf();
        $data = $jnf->form_data;
        $data['eligibility'][0]['branches'][0]['cgpa'] = '9.0';

        Sanctum::actingAs($this->admin);
        $response = $this->patchJson("/api/admin/jnfs/{$jnf->id}/form-data", ['form_data' => $data])->assertOk();

        $this->assertSame('9.0', $jnf->fresh()->form_data['eligibility'][0]['branches'][0]['cgpa'],
            'Admin per-branch CGPA edit was not persisted; API answered: '.$response->json('message'));
    }

    // ============================================================================================
    // T3.2 — visibility rule
    // ============================================================================================

    public function test_T3_2_visibility_ineligible_students_see_posting_with_reason_and_cannot_apply(): void
    {
        $this->oracle();

        // Owner decision (2026-10-01): a drive that leaves the student's branch out is hidden from them entirely.
        $other = $this->actors['OTHERBRANCH'];
        $this->as($other);
        $this->assertNotContains($this->jnfPosting->id, collect($this->getJson('/api/student/postings')->assertOk()->json('postings'))->pluck('id')->all(), 'OTHERBRANCH must NOT see the posting');
        $this->getJson("/api/student/postings/{$this->jnfPosting->id}")->assertNotFound();
        $this->apply($other, $this->jnfPosting)->assertStatus(422);

        // Branch-eligible students who miss another criterion still see it, with the reason.
        foreach (['LOWCGPA', 'DEBARRED', 'WRONGBATCH', 'BACKLOG'] as $actor) {
            $student = $this->actors[$actor];
            $this->as($student);
            $item = collect($this->getJson('/api/student/postings')->assertOk()->json('postings'))->firstWhere('id', $this->jnfPosting->id);
            $this->assertNotNull($item, "{$actor} must SEE the posting");
            $this->assertFalse($item['eligibility']['eligible'], "{$actor} must be marked ineligible");
            $this->assertNotEmpty($item['eligibility']['reasons'], "{$actor} must see a reason");

            $ineligibleFilter = collect($this->getJson('/api/student/postings?eligibility=ineligible')->json('postings'))->pluck('id');
            $this->assertContains($this->jnfPosting->id, $ineligibleFilter->all());
            $this->assertNotContains($this->jnfPosting->id, collect($this->getJson('/api/student/postings?eligibility=eligible')->json('postings'))->pluck('id')->all());

            $this->apply($student, $this->jnfPosting)->assertStatus(422);
        }
        $this->assertSame(0, Application::count());
    }

    // ============================================================================================
    // T3.3 — oracle per actor
    // ============================================================================================

    public function test_T3_3_oracle_eligible(): void
    {
        $this->oracleFor('ELIGIBLE');
    }

    public function test_T3_3_oracle_low_cgpa(): void
    {
        $this->oracleFor('LOWCGPA');
    }

    public function test_T3_3_oracle_backlog(): void
    {
        $this->oracleFor('BACKLOG');
    }

    public function test_T3_3_oracle_total_backlog_only_is_eligible(): void
    {
        $this->oracleFor('TOTALBACKLOG_ONLY');
    }

    public function test_T3_3_oracle_female_jnf_and_female_inf(): void
    {
        $this->oracleFor('FEMALE');
        $this->assertOracle('FEMALE', $this->infPosting, true, []);
    }

    public function test_T3_3_oracle_male_is_ineligible_for_female_inf(): void
    {
        $this->oracle();
        $this->assertOracle('ELIGIBLE', $this->infPosting, false, ['/female/i']);
    }

    public function test_T3_3_oracle_other_branch(): void
    {
        $this->oracleFor('OTHERBRANCH');
    }

    public function test_T3_3_oracle_wrong_batch(): void
    {
        $this->oracleFor('WRONGBATCH');
    }

    public function test_T3_3_oracle_low_10th(): void
    {
        $this->oracleFor('LOW10TH');
    }

    public function test_T3_4c_oracle_low_12th(): void
    {
        $this->oracleFor('LOW12TH');
    }

    public function test_T3_3_oracle_not_enrolled(): void
    {
        $this->oracleFor('NOTENROLLED');
    }

    public function test_T3_3_oracle_suspended(): void
    {
        $this->oracleFor('SUSPENDED');
    }

    public function test_T3_3_oracle_debarred(): void
    {
        $this->oracleFor('DEBARRED');
    }

    public function test_T3_3_oracle_query_equals_check_for_whole_population(): void
    {
        $this->oracle();

        foreach ([$this->jnfPosting, $this->infPosting] as $posting) {
            $queryIds = $this->eligibility()->eligibleStudentsQuery($posting->fresh())->pluck('id')->sort()->values()->all();
            $checkIds = StudentProfile::query()->get()
                ->filter(fn (StudentProfile $s) => $this->eligibility()->check($s, $posting->fresh())['eligible'])
                ->pluck('id')->sort()->values()->all();
            $this->assertSame($checkIds, $queryIds, "posting #{$posting->id}: query and check() disagree");
        }

        $expectedJnf = collect(['ELIGIBLE', 'TOTALBACKLOG_ONLY', 'FEMALE'])->map(fn ($a) => $this->actors[$a]->id)->sort()->values()->all();
        $this->assertSame($expectedJnf, $this->eligibility()->eligibleStudentsQuery($this->jnfPosting->fresh())->pluck('id')->sort()->values()->all());
        $this->assertSame([$this->actors['FEMALE']->id], $this->eligibility()->eligibleStudentsQuery($this->infPosting->fresh())->pluck('id')->all());
    }

    // ============================================================================================
    // T3.3b — CGPA boundary
    // ============================================================================================

    public function test_T3_3b_cgpa_boundary_and_cutoff_parsing(): void
    {
        $at = $this->student(['current_cgpa' => 7.00]);
        $below = $this->student(['current_cgpa' => 6.99]);

        foreach (['7.0', '7', '7.00', ' 7.0 '] as $i => $cutoff) {
            $data = $this->formData([], [$this->branchRow(self::CSE, $cutoff)]);
            $posting = $this->floatOk($this->jnf(null, $data, 'accepted', "Boundary {$i}"));
            $label = "cutoff \"{$cutoff}\"";

            $checkAt = $this->eligibility()->check($at->fresh(), $posting->fresh());
            $this->assertTrue($checkAt['eligible'], "{$label}: 7.00 must be eligible; reasons=".json_encode($checkAt['reasons']));
            $this->assertTrue($this->inQuery($at, $posting), "{$label}: query must include 7.00");

            $checkBelow = $this->eligibility()->check($below->fresh(), $posting->fresh());
            $this->assertFalse($checkBelow['eligible'], "{$label}: 6.99 must be ineligible");
            $this->assertReasons(['/^CGPA below cutoff \(6\.99 < 7(\.0+)?\)/'], $checkBelow['reasons'], $label);
            $this->assertFalse($this->inQuery($below, $posting), "{$label}: query must exclude 6.99");
        }

        // And through the API on the last posting.
        $posting = JobPosting::query()->latest('id')->first();
        $this->apply($at, $posting)->assertCreated();
        $this->apply($below, $posting)->assertStatus(422);
    }

    // ============================================================================================
    // T3.4b — legacy boolean backlogs
    // ============================================================================================

    public function test_T3_4b_legacy_boolean_backlogs(): void
    {
        $clean = $this->student();
        $ongoing = $this->student(['ongoing_backlogs' => 1, 'total_backlogs' => 1]);
        $totalOnly = $this->student(['ongoing_backlogs' => 0, 'total_backlogs' => 1]);
        $heavy = $this->student(['ongoing_backlogs' => 5, 'total_backlogs' => 9]);

        // Legacy row: ONLY the boolean, no numeric cap keys at all.
        $strict = $this->floatOk($this->jnf(null, $this->formData([], [['branch' => self::CSE, 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false]]), 'accepted', 'Strict'));
        $lenient = $this->floatOk($this->jnf(null, $this->formData([], [['branch' => self::CSE, 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => true]]), 'accepted', 'Lenient'));

        $this->assertArrayNotHasKey('maxOngoingBacklogs', $strict->eligibility_snapshot['eligibility'][0]['branches'][0]);

        $expect = [
            [$strict, $clean, true], [$strict, $ongoing, false], [$strict, $totalOnly, false], [$strict, $heavy, false],
            [$lenient, $clean, true], [$lenient, $ongoing, true], [$lenient, $totalOnly, true], [$lenient, $heavy, true],
        ];

        foreach ($expect as [$posting, $student, $eligible]) {
            $label = sprintf('%s / %d ongoing %d total', $posting->title(), $student->ongoing_backlogs, $student->total_backlogs);
            $check = $this->eligibility()->check($student->fresh(), $posting->fresh());
            $this->assertSame($eligible, $check['eligible'], "{$label}: ".json_encode($check['reasons']));
            $this->assertSame($eligible, $this->inQuery($student, $posting), "{$label}: query disagrees");
            if (! $eligible) {
                $this->assertReasons(['/backlog/i'], $check['reasons'], $label);
            }
        }
    }

    // ============================================================================================
    // T3.5 — questions
    // ============================================================================================

    public function test_T3_5_questions_required_mcq_edit_until_deadline_and_visible_to_admin(): void
    {
        $posting = $this->floatOk($this->jnf(), null, ['questions' => [
            ['question' => 'Why this role?', 'qtype' => 'text', 'required' => true],
            ['question' => 'Anything else?', 'qtype' => 'text', 'required' => false],
            ['question' => 'Preferred location', 'qtype' => 'mcq_single', 'options' => ['Bengaluru', 'Pune'], 'required' => true],
            ['question' => 'Languages', 'qtype' => 'mcq_multi', 'options' => ['Python', 'Go', 'Java'], 'required' => false],
        ]]);

        [$q1, $q2, $q3, $q4] = $posting->questions()->get()->all();
        $this->assertSame(['text', 'text', 'mcq_single', 'mcq_multi'], [$q1->qtype, $q2->qtype, $q3->qtype, $q4->qtype]);
        $this->assertSame([true, false, true, false], [$q1->required, $q2->required, $q3->required, $q4->required]);
        $this->assertSame(['Bengaluru', 'Pune'], $q3->options);

        $student = $this->student();
        $a1 = ['question_id' => $q1->id, 'answer' => 'Great team'];
        $a3 = ['question_id' => $q3->id, 'answer' => 'Pune'];
        $a4 = ['question_id' => $q4->id, 'answer' => ['Go', 'Python']];

        $this->apply($student, $posting, [$a3, $a4])->assertStatus(422);                                       // required text missing
        $this->apply($student, $posting, [['question_id' => $q1->id, 'answer' => '   '], $a3])->assertStatus(422); // whitespace-only
        $this->apply($student, $posting, [$a1])->assertStatus(422);                                             // required MCQ missing
        $this->apply($student, $posting, [$a1, ['question_id' => $q3->id, 'answer' => 'Delhi']])->assertStatus(422);
        $this->apply($student, $posting, [$a1, $a3, ['question_id' => $q4->id, 'answer' => ['Rust']]])->assertStatus(422);
        $this->assertSame(0, Application::count());

        $this->apply($student, $posting, [$a1, $a3, $a4])->assertCreated();
        $stored = collect(Application::sole()->answers)->keyBy('question_id');
        $this->assertSame('Great team', $stored[$q1->id]['answer']);
        $this->assertSame('Pune', $stored[$q3->id]['answer']);
        $this->assertEqualsCanonicalizing(['Go', 'Python'], $stored[$q4->id]['answer']);

        // Admin applicants view shows the answers.
        Sanctum::actingAs($this->admin);
        $answers = collect($this->getJson("/api/admin/postings/{$posting->id}/applications")->assertOk()->json('applications.0.answers'))->keyBy('question_id');
        $this->assertSame('Pune', $answers[$q3->id]['answer']);

        // Admin export includes one column per question with the answers.
        $content = $this->get("/api/admin/postings/{$posting->id}/export")->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'qa-xlsx');
        file_put_contents($path, $content);
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);
        $header = $rows[0];
        $row = array_combine($header, $rows[1]);
        $this->assertSame('Great team', $row['Q1: Why this role?']);
        $this->assertSame('Pune', $row['Q3: Preferred location']);
        $this->assertStringContainsString('Go', (string) $row['Q4: Languages']);

        // Admin edits questions before the deadline → OK.
        $edit = ['questions' => [
            ['id' => $q1->id, 'question' => 'Why this role?', 'qtype' => 'text', 'required' => true],
            ['id' => $q2->id, 'question' => 'Anything else to add?', 'qtype' => 'text', 'required' => false],
            ['id' => $q3->id, 'question' => 'Preferred location', 'qtype' => 'mcq_single', 'options' => ['Bengaluru', 'Pune'], 'required' => true],
            ['id' => $q4->id, 'question' => 'Languages', 'qtype' => 'mcq_multi', 'options' => ['Python', 'Go', 'Java'], 'required' => false],
        ]];
        $this->patchJson("/api/admin/postings/{$posting->id}", $edit)->assertOk();
        $this->assertSame('Anything else to add?', $q2->fresh()->question);

        // After the deadline → refused.
        $this->travel(6)->days();
        $edit['questions'][1]['question'] = 'Late edit';
        $this->patchJson("/api/admin/postings/{$posting->id}", $edit)->assertStatus(422);
        $this->assertSame('Anything else to add?', $q2->fresh()->question);
    }

    public function test_T3_5_no_company_route_can_add_questions(): void
    {
        $offending = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with($r->uri(), 'api/company')
                && array_intersect($r->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])
                && preg_match('/question/i', $r->uri()))
            ->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri())
            ->values()->all();
        $this->assertSame([], $offending, 'A company-side route can write posting questions');

        $source = (string) file_get_contents(base_path('app/Http/Controllers/CompanyPipelineController.php'));
        $this->assertDoesNotMatchRegularExpression('/PostingQuestion|questions\(\)->(create|update|delete)/', $source);
    }

    // ============================================================================================
    // T3.6 — edit / withdraw until the deadline
    // ============================================================================================

    public function test_T3_6_edit_withdraw_reapply_until_deadline_then_frozen(): void
    {
        $posting = $this->floatOk($this->jnf());
        $q = $posting->questions()->first();
        $student = $this->student();
        $second = $student->resumes()->create(['slot' => 2, 'label' => 'Data CV', 'file_path' => 'resumes/x/2.pdf', 'file_size' => 100, 'status' => 'pending']);
        $other = $this->student();

        $this->apply($student, $posting)->assertCreated();
        $application = Application::query()->where('student_profile_id', $student->id)->sole();

        $this->as($student);
        $this->patchJson("/api/student/applications/{$application->id}", ['resume_id' => $second->id])->assertOk();
        $this->assertSame($second->id, $application->fresh()->resume_id);
        $this->assertTrue($application->fresh()->used_unverified_resume);

        $this->patchJson("/api/student/applications/{$application->id}", ['answers' => [['question_id' => $q->id, 'answer' => 'Edited answer']]])->assertOk();
        $this->assertSame('Edited answer', $application->fresh()->answers[0]['answer']);

        $this->postJson("/api/student/applications/{$application->id}/withdraw")->assertOk();
        $this->assertSame('withdrawn', $application->fresh()->status);

        $reapply = $this->apply($student, $posting, [['question_id' => $q->id, 'answer' => 'Re-applied answer']], $second->id)->assertCreated();
        $this->assertSame($application->id, $reapply->json('application.id'), 're-apply must reuse the row');
        $this->assertSame(1, Application::query()->where('student_profile_id', $student->id)->count());
        $this->assertSame('applied', $application->fresh()->status);
        $this->as($student);
        $this->patchJson("/api/student/applications/{$application->id}", ['answers' => [['question_id' => $q->id, 'answer' => 'Edited answer']]])->assertOk();

        // Another student withdraws before the deadline (to try re-applying after it).
        $this->apply($other, $posting)->assertCreated();
        $otherApp = Application::query()->where('student_profile_id', $other->id)->sole();
        $this->as($other);
        $this->postJson("/api/student/applications/{$otherApp->id}/withdraw")->assertOk();

        $this->travel(6)->days();

        $this->as($student);
        $this->patchJson("/api/student/applications/{$application->id}", ['resume_id' => $student->resumes()->where('slot', 1)->value('id')])->assertStatus(422);
        $this->patchJson("/api/student/applications/{$application->id}", ['answers' => [['question_id' => $q->id, 'answer' => 'Too late']]])->assertStatus(422);
        $this->postJson("/api/student/applications/{$application->id}/withdraw")->assertStatus(422);
        $this->apply($other, $posting)->assertStatus(422);

        $fresh = $application->fresh();
        $this->assertSame($second->id, $fresh->resume_id);
        $this->assertSame('Edited answer', $fresh->answers[0]['answer']);
        $this->assertSame('applied', $fresh->status);
        $this->assertSame('withdrawn', $otherApp->fresh()->status);
    }

    public function test_T3_6_no_per_cycle_withdraw_prohibit_toggle(): void
    {
        foreach (['placement_cycles', 'job_postings', 'cycle_enrollments'] as $table) {
            $columns = Schema::getColumnListing($table);
            $this->assertSame([], array_values(array_filter($columns, fn ($c) => str_contains($c, 'withdraw'))), "{$table} has a withdraw toggle column");
        }
        $source = (string) file_get_contents(base_path('app/Http/Controllers/AdminPlacementCycleController.php'));
        $this->assertStringNotContainsStringIgnoringCase('withdraw', $source);
    }

    // ============================================================================================
    // T3.6b — deadline timezone
    // ============================================================================================

    public function test_T3_6b_ist_offset_deadline_is_stored_as_correct_utc_instant(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
        $posting = $this->floatOk($this->jnf(), null, ['application_deadline' => '2027-01-10T18:00:00+05:30']);

        $raw = DB::table('job_postings')->where('id', $posting->id)->value('application_deadline');
        $this->assertSame('2027-01-10 12:30:00', $raw, '18:00 IST must be stored as 12:30 UTC (app timezone is UTC)');
        $this->assertTrue($posting->fresh()->application_deadline->equalTo(Carbon::parse('2027-01-10T12:30:00Z')));
    }

    public function test_T3_6b_ist_offset_deadline_update_is_stored_as_correct_utc_instant(): void
    {
        $posting = $this->floatOk($this->jnf());
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}", ['application_deadline' => '2027-01-10T18:00:00+05:30'])->assertOk();

        $raw = DB::table('job_postings')->where('id', $posting->id)->value('application_deadline');
        $this->assertSame('2027-01-10 12:30:00', $raw, 'PATCH: 18:00 IST must be stored as 12:30 UTC');
    }

    public function test_T3_6b_ist_offset_deadline_enforced_at_the_ist_boundary(): void
    {
        $posting = $this->floatOk($this->jnf(), null, ['application_deadline' => '2027-01-10T18:00:00+05:30', 'questions' => []]);
        $early = $this->student();
        $late = $this->student();

        // Test clock pinned in UTC (the app timezone): Carbon hydrates DB datetimes in the test-now's zone otherwise.
        Carbon::setTestNow(Carbon::parse('2027-01-10T17:59:00+05:30')->utc());
        $this->apply($early, $posting, [])->assertCreated();

        Carbon::setTestNow(Carbon::parse('2027-01-10T18:01:00+05:30')->utc());
        $response = $this->apply($late, $posting, []);
        $this->assertSame(422, $response->status(), 'Applying at 18:01 IST must be refused for an 18:00 IST deadline; got '.$response->status().' '.$response->getContent());
    }

    public function test_T3_6b_naive_deadline_string_is_read_as_ist(): void
    {
        // Owner decision (QA F-005): a deadline without an offset is IST wall-clock time, i.e. 18:00 = 12:30 UTC.
        $p1 = $this->floatOk($this->jnf(null, null, 'accepted', 'Naive T'), null, ['application_deadline' => '2027-01-10T18:00']);
        $p2 = $this->floatOk($this->jnf(null, null, 'accepted', 'Naive space'), null, ['application_deadline' => '2027-01-10 18:00:00']);

        $this->assertSame('2027-01-10 12:30:00', DB::table('job_postings')->where('id', $p1->id)->value('application_deadline'));
        $this->assertSame('2027-01-10 12:30:00', DB::table('job_postings')->where('id', $p2->id)->value('application_deadline'));
    }

    public function test_T3_6b_student_api_serialises_deadline_with_explicit_zone(): void
    {
        $posting = $this->floatOk($this->jnf(), null, ['application_deadline' => '2027-01-10T12:30:00Z']);
        $student = $this->student();
        $this->as($student);

        $listValue = $this->getJson('/api/student/postings')->json('postings.0.application_deadline');
        $detailValue = $this->getJson("/api/student/postings/{$posting->id}")->json('posting.application_deadline');

        foreach (['list' => $listValue, 'detail' => $detailValue] as $where => $value) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(\.\d+)?(Z|[+-]\d\d:\d\d)$/', (string) $value, "{$where}: deadline must be ISO-8601 with an explicit zone");
            $this->assertTrue(Carbon::parse($value)->equalTo(Carbon::parse('2027-01-10T12:30:00Z')), "{$where}: wrong instant {$value}");
        }
    }

    // ============================================================================================
    // T3.6c — double submit
    // ============================================================================================

    public function test_T3_6c_double_submit_sequential_gives_one_row_and_clean_4xx(): void
    {
        $posting = $this->floatOk($this->jnf());
        $student = $this->student();

        $first = $this->apply($student, $posting);
        $second = $this->apply($student, $posting);

        $first->assertCreated();
        $this->assertTrue($second->status() >= 400 && $second->status() < 500, 'second submit must be a clean 4xx, got '.$second->status());
        $this->assertSame(1, Application::query()->where('job_posting_id', $posting->id)->where('student_profile_id', $student->id)->count());
    }

    public function test_T3_6c_double_submit_race_window_is_a_clean_4xx_not_500(): void
    {
        // Simulates the second of two concurrent submits: the other request's row appears after this request's
        // lockForUpdate() read found nothing and before its INSERT (what InnoDB gap locks + a parallel insert produce).
        $posting = $this->floatOk($this->jnf());
        $student = $this->student();
        $resumeId = $student->resumes()->value('id');

        $fired = false;
        Application::creating(function (Application $app) use (&$fired, $posting, $student, $resumeId): void {
            if ($fired) {
                return;
            }
            $fired = true;
            DB::table('applications')->insert([
                'job_posting_id' => $posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resumeId,
                'status' => 'applied', 'used_unverified_resume' => false, 'placed_elsewhere_flag' => false,
                'applied_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $response = $this->apply($student, $posting);
        $this->assertTrue($fired, 'precondition: race simulated');
        $this->assertTrue($response->status() >= 400 && $response->status() < 500, 'concurrent duplicate submit must answer a clean 4xx, got '.$response->status().' '.substr((string) $response->json('message'), 0, 160));
        $this->assertLessThanOrEqual(1, Application::query()->where('job_posting_id', $posting->id)->where('student_profile_id', $student->id)->count());
    }

    // ============================================================================================
    // T3.7 — placed elsewhere
    // ============================================================================================

    public function test_T3_7_placed_elsewhere_flag_and_remove_from_process(): void
    {
        $p1 = $this->floatOk($this->jnf($this->companyA, null, 'accepted', 'SDE at A'));
        $p2 = $this->floatOk($this->jnf($this->companyB, null, 'accepted', 'Analyst at B'));

        $x = $this->student();
        $y = $this->student();
        $z = $this->student();

        foreach ([$x, $y, $z] as $s) {
            $this->apply($s, $p1)->assertCreated();
        }
        $this->apply($x, $p2)->assertCreated();
        $this->apply($y, $p2)->assertCreated();

        // Real flow on P1: close applications → publish round 1 and 2 → announce final results.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$p1->id}/close")->assertOk();
        [$r1, $r2] = $p1->rounds()->get()->all();
        foreach ([$r1, $r2] as $round) {
            $this->postJson("/api/admin/postings/{$p1->id}/rounds/{$round->id}/results", ['roll_nos' => [$x->roll_no, $y->roll_no], 'result' => 'selected'])->assertOk();
            $this->postJson("/api/admin/postings/{$p1->id}/rounds/{$round->id}/publish", ['reject_remaining' => true])->assertOk();
        }
        $appX1 = Application::query()->where('job_posting_id', $p1->id)->where('student_profile_id', $x->id)->sole();
        $appY1 = Application::query()->where('job_posting_id', $p1->id)->where('student_profile_id', $y->id)->sole();
        $this->postJson("/api/admin/postings/{$p1->id}/results/publish", ['selections' => [
            ['application_id' => $appX1->id, 'offer_type' => 'fulltime', 'ctc_annual' => 1800000, 'block' => true, 'block_scope' => 'all'],
            ['application_id' => $appY1->id, 'offer_type' => 'fulltime', 'ctc_annual' => 1800000, 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();

        $appX2 = Application::query()->where('job_posting_id', $p2->id)->where('student_profile_id', $x->id)->sole();
        $appY2 = Application::query()->where('job_posting_id', $p2->id)->where('student_profile_id', $y->id)->sole();
        $this->assertTrue($appX2->placed_elsewhere_flag);
        $this->assertTrue($appY2->placed_elsewhere_flag);

        // Visible to admin in P2 applicants and pipeline.
        $applicants = collect($this->getJson("/api/admin/postings/{$p2->id}/applications")->assertOk()->json('applications'))->keyBy('id');
        $this->assertTrue($applicants[$appX2->id]['placed_elsewhere_flag']);
        $pipeline = collect($this->getJson("/api/admin/postings/{$p2->id}/pipeline")->assertOk()->json('applications'))->keyBy('id');
        $this->assertTrue($pipeline[$appX2->id]['placed_elsewhere_flag']);

        // Remove X with notify default (field omitted).
        $before = Mail::queued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->companyBUser->email))->count();
        $this->postJson("/api/admin/postings/{$p2->id}/applications/{$appX2->id}/remove-from-process")->assertOk();

        $row = ApplicationRoundResult::query()->where('application_id', $appX2->id)->sole();
        $this->assertSame('rejected', $row->result);
        $this->assertSame('Selected elsewhere via CDC', $row->remark);
        $this->assertNotNull($row->published_at);

        $notices = Mail::queued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->companyBUser->email));
        $this->assertSame($before + 1, $notices->count(), 'COMPANY_B must get exactly one E9 notice');
        $notice = $notices->last();
        $this->assertStringContainsString($x->roll_no, $notice->headline);
        $this->assertTrue(collect($notice->lines)->contains(fn ($l) => str_contains(strtolower($l), 'replacement')), 'E9 must invite replacement candidates');
        $this->assertSame(0, Mail::queued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->companyAUser->email))->count(), 'COMPANY_A must not be told');

        $audit = AuditLog::query()->where('action', 'application.remove_placed_elsewhere')->where('subject_id', $appX2->id)->sole();
        $this->assertSame($this->admin->id, $audit->user_id);

        // notify_company = false → no mail.
        $this->postJson("/api/admin/postings/{$p2->id}/applications/{$appY2->id}/remove-from-process", ['notify_company' => false])->assertOk();
        $this->assertSame($before + 1, Mail::queued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->companyBUser->email))->count(), 'notify_company=false must not mail');
        $this->assertSame('Selected elsewhere via CDC', ApplicationRoundResult::query()->where('application_id', $appY2->id)->sole()->remark);
        $this->assertFalse(AuditLog::query()->where('action', 'application.remove_placed_elsewhere')->where('subject_id', $appY2->id)->sole()->after['notify_company']);
    }

    // ============================================================================================
    // T3.8 — students never see applicant counts
    // ============================================================================================

    public function test_T3_8_students_never_see_applicant_counts(): void
    {
        $posting = $this->floatOk($this->jnf(), null, ['questions' => [], 'application_deadline' => now()->addDays(5)->toIso8601String()]);
        $observer = $this->student();
        $others = [];
        for ($i = 0; $i < 16; $i++) {
            $others[] = $this->student();
        }
        $this->apply($observer, $posting, [])->assertCreated();
        foreach ($others as $s) {
            $this->apply($s, $posting, [])->assertCreated();
        }
        $this->assertSame(17, Application::count());

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/events', ['title' => 'PPT by A', 'event_type' => 'ppt', 'starts_at' => now()->addDays(2)->toIso8601String(), 'company_id' => $this->companyA->id, 'audience_type' => 'posting_applicants', 'audience_filter' => ['job_posting_id' => $posting->id]])->assertCreated();
        $this->postJson('/api/admin/events/1/publish')->assertOk();

        $month = $posting->application_deadline->timezone('Asia/Kolkata')->format('Y-m');
        $endpoints = [
            '/api/student/postings',
            "/api/student/postings/{$posting->id}",
            '/api/student/applications',
            '/api/student/dashboard',
            "/api/student/calendar?month={$month}",
            '/api/student/events',
        ];

        $forbiddenKey = '/applicant|applied_count|applications_count|application_count|total_applications|withdrawn_count|eligible_count|pool_count|published_count|draft_count|audience_count|^stats$|^counts?$/i';

        $this->as($observer);
        foreach ($endpoints as $endpoint) {
            $json = $this->getJson($endpoint)->assertOk()->json();
            $hits = [];
            $walk = function ($node, string $path) use (&$walk, &$hits, $forbiddenKey): void {
                if (! is_array($node)) {
                    if (is_int($node) && $node === 17) {
                        $hits[] = "{$path}=17 (value equals the applicant count)";
                    }

                    return;
                }
                foreach ($node as $key => $value) {
                    if (is_string($key) && preg_match($forbiddenKey, $key)) {
                        $hits[] = "{$path}.{$key}";
                    }
                    $walk($value, "{$path}.{$key}");
                }
            };
            $walk($json, '$');
            $this->assertSame([], $hits, "{$endpoint} leaks applicant-count data");
        }

        // meta.total is the number of POSTINGS on the board, not applicants.
        $this->assertSame(1, $this->getJson('/api/student/postings')->json('meta.total'));
        // The dashboard's own-count keys describe the student's own applications only.
        $dash = $this->getJson('/api/student/dashboard')->json();
        $this->assertSame(1, $dash['active_applications']);
        $this->assertSame(0, $dash['unverified_applications']);
    }

    // ============================================================================================
    // T3.9 — job alerts / preferences out of scope
    // ============================================================================================

    public function test_T3_9_job_alerts_and_preferences_not_built(): void
    {
        $uris = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri());
        $this->assertSame([], $uris->filter(fn ($u) => preg_match('/alert|preference|subscription/i', $u))->values()->all());
        foreach (['job_alerts', 'alerts', 'student_preferences', 'job_preferences'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} exists");
        }
    }
}
