<?php

namespace Tests\Feature\QA;

use App\Http\Controllers\AdminResultController;
use App\Mail\OfferMail;
use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\PortalNotification;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\ProgrammeCatalogue;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * QA acceptance — Phase 2 spec Part B4 / M7 (results, offers, blocking matrix), driven through the real flow:
 * students apply via the student API → admin closes applications → publishes rounds in order → drafts the
 * final round → GET results/prepare → POST results/publish → eligibility consequences checked with REAL
 * apply attempts on other postings.
 *
 * BLOCKING RULES: owner decision 2026-10-01 (QA F-004) — a full-time offer, an accepted PPO or an Intern + Full-Time
 * offer blocks the student completely (every open cycle they are enrolled in); an internship or an Intern +
 * performance-PPO offer blocks internship opportunities (the internship cycle) while full-time stays open. Every
 * student here is enrolled in C and the internship cycle, so INFs float natively into the internship cycle.
 *
 * FIXTURE NOTE (T5.6/T5.7 debarment only): the float API refuses mixed-type cycles (an INF only into an internship
 * cycle, a JNF only into a full-time cycle — AdminPostingController::store, spec M5.3). Where a test needs both types
 * in one cycle, the posting is floated into a staging cycle of its own type and moved with a direct DB update
 * (floatPosting()).
 */
#[Group('qa')]
class S5ResultsBlockingTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const MTECH = 'M.Tech (2 Year) - GATE';

    private const IMTECH = 'Integrated M.Tech (5 Year) - JEE Advanced';

    private User $admin;

    /** Cycle "C" (full-time). */
    private PlacementCycle $ft;

    /** Internship cycle. */
    private PlacementCycle $intern;

    /** Second full-time cycle "C2". */
    private PlacementCycle $ft2;

    private PlacementCycle $stageFt;

    private PlacementCycle $stageIntern;

    /** @var array<string, StudentProfile> roll => student */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'QA Admin', 'email' => 'admin@cdc-qa.test']);

        $cycle = fn (string $name, string $type) => PlacementCycle::create([
            'name' => $name, 'type' => $type, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]], ['programme' => self::MTECH, 'batches' => [2027]]],
        ]);
        $this->ft = $cycle('FT 2026-27 (C)', 'fulltime');
        $this->intern = $cycle('Internship 2026-27', 'internship');
        $this->ft2 = $cycle('FT 2026-27 second (C2)', 'fulltime');
        $this->stageFt = $cycle('Staging FT', 'fulltime');
        $this->stageIntern = $cycle('Staging Intern', 'internship');

        for ($i = 1; $i <= 6; $i++) {
            $this->makeStudent(sprintf('24QA%04d', $i), self::BTECH, 'Computer Science & Engineering');
        }
        $this->makeStudent('24QM0001', self::MTECH, 'Computer Science and Engineering');
    }

    private function makeStudent(string $roll, string $programme, string $branch): void
    {
        $student = StudentProfile::factory()->create([
            'roll_no' => $roll, 'full_name' => 'QA '.$roll, 'programme' => $programme, 'branch' => $branch, 'graduating_batch' => 2027,
        ]);
        foreach ([$this->ft, $this->intern] as $cycle) {
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        }
        $student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
        $this->students[$roll] = $student;
    }

    // ------------------------------------------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function formData(string $title): array
    {
        return [
            'jobTitle' => $title,
            'internshipTitle' => $title,
            'currency' => 'INR',
            'eligibility' => [
                ['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]],
                ['programme' => self::MTECH, 'branches' => [['branch' => 'Computer Science and Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]],
            ],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'programmeSalaries' => [
                ['programme' => self::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true],
                ['programme' => self::IMTECH, 'ctcAnnual' => '3000000', 'enabled' => true],
                ['programme' => self::MTECH, 'ctcAnnual' => '1800000', 'enabled' => true],
            ],
            'programmeStipends' => [
                ['programme' => self::BTECH, 'total' => '80000', 'enabled' => true],
                ['programme' => self::IMTECH, 'total' => '90000', 'enabled' => true],
                ['programme' => self::MTECH, 'total' => '60000', 'enabled' => true],
            ],
            'selectionRounds' => [['type' => 'technical_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
        ];
    }

    private function makeForm(string $type, string $title, array $formOverrides = []): Jnf|Inf
    {
        $data = array_replace($this->formData($title), $formOverrides);
        $slug = Str::slug($title);
        $company = Company::create(['name' => "{$title} Ltd", 'hr_name' => 'HR', 'hr_email' => "{$slug}@co-qa.test"]);
        User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'name' => "{$title} HR", 'email' => "hr-{$slug}@co-qa.test"]);

        return $type === 'inf'
            ? Inf::create(['company_id' => $company->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => 'accepted', 'vacancies' => 3, 'form_data' => $data])
            : Jnf::create(['company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3, 'form_data' => $data]);
    }

    private function floatRequest(Jnf|Inf $form, PlacementCycle $cycle): TestResponse
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson('/api/admin/postings', [
            'form_type' => $form instanceof Inf ? 'inf' : 'jnf',
            'form_id' => $form->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ]);
    }

    /** Float through the API; a type that the API refuses for $cycle is floated into a staging cycle and moved (fixture). */
    private function floatPosting(string $type, PlacementCycle $cycle, string $title, array $formOverrides = []): JobPosting
    {
        $form = $this->makeForm($type, $title, $formOverrides);
        $native = ($type === 'inf') === ($cycle->type === 'internship');
        $this->floatRequest($form, $native ? $cycle : ($type === 'inf' ? $this->stageIntern : $this->stageFt))->assertCreated();

        $posting = JobPosting::where('postable_type', $form::class)->where('postable_id', $form->id)->sole();
        if (! $native) {
            $posting->update(['placement_cycle_id' => $cycle->id]); // FIXTURE ONLY — see class docblock.
        }

        return $posting->fresh();
    }

    private function apply(string $roll, JobPosting $posting): TestResponse
    {
        $student = $this->students[$roll];
        Sanctum::actingAs($student->user);

        // Production (PHP-FPM) builds a fresh container + controller per request. The test kernel keeps resolved
        // controllers on the Route objects across requests, and StudentApplicationController's EligibilityService
        // memoises a student's active blocks per instance (D68) — without this flush a block created after the
        // student's first apply in the same test would be invisible (harness artifact; see QA report observation).
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $route->flushController();
        }

        return $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $student->resumes()->value('id')]);
    }

    private function appFor(JobPosting $posting, string $roll): Application
    {
        return Application::where('job_posting_id', $posting->id)->where('student_profile_id', $this->students[$roll]->id)->sole();
    }

    /** Close applications, publish every non-final round in order (given rolls selected, the rest rejected), then draft the final round. */
    private function reachFinal(JobPosting $posting, array $rounds1Selected, ?array $finalDrafts = null): void
    {
        Sanctum::actingAs($this->admin);
        $base = "/api/admin/postings/{$posting->id}";
        $this->patchJson("$base/close")->assertOk();

        $rounds = $posting->rounds()->get();
        foreach ($rounds->slice(0, -1) as $round) {
            $this->postJson("$base/rounds/{$round->id}/results", ['roll_nos' => $rounds1Selected, 'result' => 'selected'])->assertOk();
            $this->postJson("$base/rounds/{$round->id}/publish", ['reject_remaining' => true])->assertOk();
        }

        $finalDrafts ??= $rounds1Selected;
        if ($finalDrafts !== []) {
            $this->postJson("$base/rounds/{$rounds->last()->id}/results", ['roll_nos' => $finalDrafts, 'result' => 'selected'])->assertOk();
        }
    }

    private function prepare(JobPosting $posting): TestResponse
    {
        Sanctum::actingAs($this->admin);

        return $this->getJson("/api/admin/postings/{$posting->id}/results/prepare")->assertOk();
    }

    /**
     * POST results/publish the way the console does: CTC/stipend prefilled and block toggle from the prepare
     * suggestion unless $byRoll overrides them. $byRoll: roll => [offer_type, ?block, ?block_scope, ?ctc_annual, ?stipend_monthly].
     */
    private function publishResults(JobPosting $posting, array $byRoll, array $extra = []): TestResponse
    {
        $prepare = $this->prepare($posting);
        $matrix = collect($prepare->json('offer_types'))->keyBy('value');
        $suggested = collect($prepare->json('selected'))->merge($prepare->json('waitlisted'))->keyBy('student.roll_no');

        $selections = [];
        foreach ($byRoll as $roll => $options) {
            $type = $options['offer_type'];
            $block = $matrix[$type]['block'] ?? null;
            $selection = [
                'application_id' => $options['application_id'] ?? $this->appFor($posting, $roll)->id,
                'offer_type' => $type,
                'ctc_annual' => array_key_exists('ctc_annual', $options) ? $options['ctc_annual'] : ($suggested[$roll]['suggested']['ctc_annual'] ?? null),
                'stipend_monthly' => array_key_exists('stipend_monthly', $options) ? $options['stipend_monthly'] : ($suggested[$roll]['suggested']['stipend_monthly'] ?? null),
                'block' => $options['block'] ?? ($block !== null),
                'block_scope' => $options['block_scope'] ?? ($block['scope'] ?? null),
            ];
            $selections[] = array_filter($selection, fn ($v) => $v !== null);
        }

        Sanctum::actingAs($this->admin);

        return $this->postJson("/api/admin/postings/{$posting->id}/results/publish", ['selections' => $selections] + $extra);
    }

    /** Full announcement: all $byRoll students go through every round and get the given offers. */
    private function announce(JobPosting $posting, array $byRoll): TestResponse
    {
        $this->reachFinal($posting, array_keys($byRoll));

        return $this->publishResults($posting, $byRoll);
    }

    private function assertApplyBlocked(string $roll, JobPosting $posting, string $reasonPattern, string $why): void
    {
        $response = $this->apply($roll, $posting);
        $this->assertSame(422, $response->status(), "{$why}: apply must be refused (got {$response->status()}: {$response->getContent()})");
        $reasons = $response->json('reasons') ?? [];
        $this->assertTrue(
            collect($reasons)->contains(fn ($r) => preg_match($reasonPattern, (string) $r) === 1),
            "{$why}: expected a reason matching {$reasonPattern}, got ".json_encode($reasons)
        );
    }

    /** @return array<int, string> cycle id => scope of the student's active blocks */
    private function blockedCycles(string $roll): array
    {
        return PlacementBlock::where('student_profile_id', $this->students[$roll]->id)->where('active', true)
            ->orderBy('placement_cycle_id')->pluck('scope', 'placement_cycle_id')->all();
    }

    private function assertApplyOk(string $roll, JobPosting $posting, string $why): void
    {
        $response = $this->apply($roll, $posting);
        $this->assertSame(201, $response->status(), "{$why}: apply must succeed (got {$response->status()}: {$response->getContent()})");
    }

    // ------------------------------------------------------------------------------------------------------------
    // T5.1
    // ------------------------------------------------------------------------------------------------------------

    public function test_T5_1_prepare_suggests_type_and_block_admin_overrides_publish_returns_totals(): void
    {
        $jnf = $this->floatPosting('jnf', $this->ft, 'Alpha FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Beta Intern');
        foreach (['24QA0001', '24QA0002', '24QA0003', '24QA0004'] as $roll) {
            $this->apply($roll, $jnf)->assertCreated();
            $this->apply($roll, $inf)->assertCreated();
        }

        // JNF → fulltime, block "all", CTC prefilled.
        $this->reachFinal($jnf, ['24QA0001', '24QA0002', '24QA0003'], ['24QA0001', '24QA0002']);
        $prepare = $this->prepare($jnf);
        $this->assertNull($prepare->json('blocked_reason'));
        foreach (collect($prepare->json('selected'))->keyBy('student.roll_no') as $roll => $row) {
            $this->assertSame('fulltime', $row['suggested']['offer_type'], "{$roll} JNF default offer type");
            $this->assertSame(['scope' => 'all'], $row['suggested']['block'], "{$roll} JNF suggested block");
            $this->assertSame(2400000, $row['suggested']['ctc_annual']);
            $this->assertNull($row['suggested']['stipend_monthly']);
        }
        $this->assertSame(1, $prepare->json('regret_estimate'), '24QA0003 reached the final round without a selection');

        $matrix = collect($prepare->json('offer_types'))->mapWithKeys(fn ($t) => [$t['value'] => $t['block']['scope'] ?? null])->all();
        $this->assertSame([
            'intern' => 'internships_only', 'intern_ppo' => 'all', 'ppo_offered' => null,
            'fulltime' => 'all', 'intern_fulltime' => 'all', 'intern_performance_ppo' => 'internships_only',
        ], $matrix, 'B4 matrix exposed to the console');

        // Override: 24QA0001 → intern_fulltime with NO block; 24QA0002 → default (fulltime, block all).
        $publish = $this->publishResults($jnf, [
            '24QA0001' => ['offer_type' => 'intern_fulltime', 'block' => false],
            '24QA0002' => ['offer_type' => 'fulltime'],
        ])->assertOk();
        // 24QA0002's complete block reaches C and the internship cycle (owner rule, QA F-004).
        $publish->assertJsonPath('offers', 2)->assertJsonPath('blocks', 2)->assertJsonPath('regrets', 1);
        $this->assertArrayHasKey('flagged', $publish->json());
        $this->assertStringContainsString('2 offer(s)', $publish->json('message'));

        $this->assertSame('intern_fulltime', $this->appFor($jnf, '24QA0001')->offer->offer_type, 'type override honoured');
        $this->assertSame(0, PlacementBlock::where('student_profile_id', $this->students['24QA0001']->id)->count(), 'block override (off) honoured');
        $this->assertSame([$this->ft->id => 'all', $this->intern->id => 'all'], $this->blockedCycles('24QA0002'));

        // INF → intern, block internships only, stipend prefilled.
        $this->reachFinal($inf, ['24QA0003', '24QA0004']);
        foreach (collect($this->prepare($inf)->json('selected'))->keyBy('student.roll_no') as $roll => $row) {
            $this->assertSame('intern', $row['suggested']['offer_type'], "{$roll} INF default offer type");
            $this->assertSame(['scope' => 'internships_only'], $row['suggested']['block']);
            $this->assertSame(80000, $row['suggested']['stipend_monthly']);
            $this->assertNull($row['suggested']['ctc_annual']);
        }
        $this->publishResults($inf, ['24QA0003' => ['offer_type' => 'intern']])->assertOk()
            ->assertJsonPath('offers', 1)->assertJsonPath('blocks', 1)->assertJsonPath('regrets', 1);
        $offer = $this->appFor($inf, '24QA0003')->offer;
        $this->assertSame('intern', $offer->offer_type);
        $this->assertSame(80000, $offer->stipend_monthly);
        $this->assertSame('internships_only', PlacementBlock::where('offer_id', $offer->id)->sole()->scope);
    }

    // ------------------------------------------------------------------------------------------------------------
    // T5.2 Blocking matrix (real apply attempts)
    // ------------------------------------------------------------------------------------------------------------

    public function test_T5_2a_intern_blocks_the_internship_cycle_full_time_stays_open(): void
    {
        // The API itself refuses a JNF in an internship cycle, so an internship cycle only ever holds internships.
        $this->floatRequest($this->makeForm('jnf', 'Refused JNF'), $this->intern)->assertStatus(422);

        $inf1 = $this->floatPosting('inf', $this->intern, 'Intern One');
        $inf2 = $this->floatPosting('inf', $this->intern, 'Intern Two');
        $jnf = $this->floatPosting('jnf', $this->ft, 'FT Drive');
        $this->apply('24QA0001', $inf1)->assertCreated();
        $this->apply('24QA0002', $inf1)->assertCreated();

        $this->announce($inf1, ['24QA0001' => ['offer_type' => 'intern']])->assertOk()->assertJsonPath('blocks', 1);
        $this->assertSame([$this->intern->id => 'internships_only'], $this->blockedCycles('24QA0001'));

        $this->assertApplyBlocked('24QA0001', $inf2, '/^Blocked from internships: accepted an Internship offer/', 'intern offer vs another INF in the internship cycle');
        $this->assertApplyOk('24QA0001', $jnf, 'intern offer vs a full-time posting (full-time stays open)');
        $this->assertApplyOk('24QA0002', $inf2, 'student without offer');
    }

    public function test_T5_2b_intern_ppo_blocks_completely(): void
    {
        $source = $this->floatPosting('jnf', $this->ft, 'PPO Source');
        $jnf2 = $this->floatPosting('jnf', $this->ft, 'Other FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Intern Drive');
        $this->apply('24QA0001', $source)->assertCreated();

        $this->announce($source, ['24QA0001' => ['offer_type' => 'intern_ppo']])->assertOk()->assertJsonPath('blocks', 2);
        $this->assertSame([$this->ft->id => 'all', $this->intern->id => 'all'], $this->blockedCycles('24QA0001'));

        $this->assertApplyBlocked('24QA0001', $jnf2, '/^Blocked: accepted a PPO/', 'intern_ppo vs JNF');
        $this->assertApplyBlocked('24QA0001', $inf, '/^Blocked: accepted a PPO/', 'intern_ppo vs INF');
    }

    public function test_T5_2c_ppo_offered_creates_no_block_and_is_labelled_distinctly(): void
    {
        $source = $this->floatPosting('jnf', $this->ft, 'PPO Offered Source');
        $jnf2 = $this->floatPosting('jnf', $this->ft, 'Other FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Intern Drive');
        $this->apply('24QA0001', $source)->assertCreated();

        $this->announce($source, ['24QA0001' => ['offer_type' => 'ppo_offered']])->assertOk()->assertJsonPath('blocks', 0);
        $this->assertSame(0, PlacementBlock::count());

        $this->assertApplyOk('24QA0001', $jnf2, 'ppo_offered vs JNF');
        $this->assertApplyOk('24QA0001', $inf, 'ppo_offered vs INF');

        // Offered vs accepted are distinguishable everywhere the API names offer types.
        $this->assertNotSame(Offer::LABELS['ppo_offered'], Offer::LABELS['intern_ppo']);
        $this->assertStringContainsStringIgnoringCase('not accepted', Offer::LABELS['ppo_offered']);
        $this->assertStringContainsStringIgnoringCase('accepted', Offer::LABELS['intern_ppo']);
        $labels = collect($this->prepare($source)->json('offer_types'))->pluck('label', 'value');
        $this->assertSame(Offer::LABELS['ppo_offered'], $labels['ppo_offered']);
        $this->assertSame(Offer::LABELS['intern_ppo'], $labels['intern_ppo']);
        Sanctum::actingAs($this->students['24QA0001']->user);
        $offer = collect($this->getJson('/api/student/applications')->json('applications'))->firstWhere('posting.id', $source->id)['offer'];
        $this->assertSame('PPO offered (not accepted)', $offer['label']);
    }

    public function test_T5_2d_fulltime_blocks_completely(): void
    {
        $source = $this->floatPosting('jnf', $this->ft, 'FT Source');
        $jnf2 = $this->floatPosting('jnf', $this->ft, 'Other FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Intern Drive');
        $this->apply('24QA0001', $source)->assertCreated();

        $this->announce($source, ['24QA0001' => ['offer_type' => 'fulltime']])->assertOk()->assertJsonPath('blocks', 2);
        $this->assertSame([$this->ft->id => 'all', $this->intern->id => 'all'], $this->blockedCycles('24QA0001'));

        $this->assertApplyBlocked('24QA0001', $jnf2, '/^Blocked: accepted a Full-Time offer/', 'fulltime vs JNF');
        $this->assertApplyBlocked('24QA0001', $inf, '/^Blocked: accepted a Full-Time offer/', 'fulltime vs INF');
    }

    public function test_T5_2e_intern_fulltime_exists_and_blocks_completely(): void
    {
        $this->assertContains('intern_fulltime', Offer::TYPES);
        $this->assertArrayHasKey('intern_fulltime', Offer::LABELS);

        $source = $this->floatPosting('jnf', $this->ft, 'IFT Source');
        $jnf2 = $this->floatPosting('jnf', $this->ft, 'Other FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Intern Drive');
        $this->apply('24QA0001', $source)->assertCreated();

        $this->announce($source, ['24QA0001' => ['offer_type' => 'intern_fulltime']])->assertOk();
        $this->assertSame('intern_fulltime', Offer::sole()->offer_type, 'DB accepts the new enum value');
        $this->assertSame([$this->ft->id => 'all', $this->intern->id => 'all'], $this->blockedCycles('24QA0001'));

        $this->assertApplyBlocked('24QA0001', $jnf2, '/^Blocked: accepted an Intern \+ Full-Time offer/', 'intern_fulltime vs JNF');
        $this->assertApplyBlocked('24QA0001', $inf, '/^Blocked: accepted an Intern \+ Full-Time offer/', 'intern_fulltime vs INF');
    }

    public function test_T5_2f_intern_performance_ppo_blocks_internships_only_jnf_still_open(): void
    {
        $source = $this->floatPosting('inf', $this->intern, 'IPP Source');
        $jnf2 = $this->floatPosting('jnf', $this->ft, 'Other FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Other Intern');
        $this->apply('24QA0001', $source)->assertCreated();

        $this->announce($source, ['24QA0001' => ['offer_type' => 'intern_performance_ppo']])->assertOk()->assertJsonPath('blocks', 1);
        $this->assertSame([$this->intern->id => 'internships_only'], $this->blockedCycles('24QA0001'));

        $this->assertApplyOk('24QA0001', $jnf2, 'intern_performance_ppo vs another FT posting (must stay eligible)');
        $this->assertApplyBlocked('24QA0001', $inf, '/Blocked from internships/i', 'intern_performance_ppo vs INF');
    }

    public function test_T5_2g_no_student_route_to_decline_or_change_an_offer(): void
    {
        $studentMutations = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with($route->uri(), 'api/student'))
            ->filter(fn ($route) => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) !== [])
            ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
            ->values();

        $this->assertNotEmpty($studentMutations);
        foreach ($studentMutations as $route) {
            $this->assertDoesNotMatchRegularExpression('/offer|decline|reject|accept|block/i', $route, "student route {$route} looks like an offer decision");
        }
    }

    public function test_T5_2h_admin_unblock_restores_eligibility_keeps_row_and_audits(): void
    {
        $source = $this->floatPosting('jnf', $this->ft, 'FT Source');
        $jnf2 = $this->floatPosting('jnf', $this->ft, 'Other FT');
        $this->apply('24QA0001', $source)->assertCreated();
        $this->announce($source, ['24QA0001' => ['offer_type' => 'fulltime']])->assertOk();
        $this->assertApplyBlocked('24QA0001', $jnf2, '/^Blocked/', 'before unblock');

        $block = PlacementBlock::where('placement_cycle_id', $this->ft->id)->sole();
        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/admin/blocks/{$block->id}")->assertOk();

        $block->refresh();
        $this->assertSame(2, PlacementBlock::count(), 'never hard-deleted');
        $this->assertSame([$this->intern->id => 'all'], $this->blockedCycles('24QA0001'), 'lifting C leaves the internship-cycle block in place');
        $this->assertFalse($block->active);
        $this->assertSame($this->admin->id, $block->unblocked_by);
        $this->assertNotNull($block->unblocked_at);
        $audit = AuditLog::where('action', 'block.remove')->sole();
        $this->assertSame($block->id, $audit->subject_id);
        $this->assertSame($this->admin->id, $audit->user_id);

        $this->assertApplyOk('24QA0001', $jnf2, 'after unblock (posting that refused before)');
        $jnf3 = $this->floatPosting('jnf', $this->ft, 'Brand New FT');
        $this->assertApplyOk('24QA0001', $jnf3, 'after unblock (new JNF)');

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/admin/blocks/{$block->id}")->assertStatus(422);
    }

    public function test_T5_2i_admin_overrides_fulltime_block_scope_to_internships_only(): void
    {
        $source = $this->floatPosting('jnf', $this->ft, 'FT Source');
        $jnf2 = $this->floatPosting('jnf', $this->ft, 'Other FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Intern Drive');
        $this->apply('24QA0001', $source)->assertCreated();

        $this->reachFinal($source, ['24QA0001']);
        $this->assertSame(['scope' => 'all'], $this->prepare($source)->json('selected.0.suggested.block'), 'suggestion is "all"');
        $this->publishResults($source, ['24QA0001' => ['offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'internships_only']])->assertOk();

        $this->assertSame([$this->ft->id => 'internships_only', $this->intern->id => 'internships_only'], $this->blockedCycles('24QA0001'), 'override stored on every cycle it reaches');
        $this->assertSame('fulltime', PlacementBlock::first()->offer->offer_type);
        $this->assertSame(['internships_only', 'internships_only'], AuditLog::where('action', 'block.create')->get()->map(fn ($a) => $a->after['scope'])->all());

        $this->assertApplyOk('24QA0001', $jnf2, 'override honoured: FT posting stays open');
        $this->assertApplyBlocked('24QA0001', $inf, '/Blocked from internships/i', 'override still blocks internships');
    }

    // ------------------------------------------------------------------------------------------------------------
    // T5.3 – T5.6
    // ------------------------------------------------------------------------------------------------------------

    public function test_T5_3_complete_block_reaches_every_open_cycle_the_student_is_enrolled_in(): void
    {
        // Owner decision (QA F-004) replaces spec B3's "per cycle" for complete blocks.
        CycleEnrollment::create(['placement_cycle_id' => $this->ft2->id, 'student_profile_id' => $this->students['24QA0001']->id, 'status' => 'active']);
        $source = $this->floatPosting('jnf', $this->ft, 'FT Source');
        $sameCycle = $this->floatPosting('jnf', $this->ft, 'Other FT C1');
        $otherCycle = $this->floatPosting('jnf', $this->ft2, 'FT In C2');
        $this->apply('24QA0001', $source)->assertCreated();

        $this->announce($source, ['24QA0001' => ['offer_type' => 'fulltime']])->assertOk()->assertJsonPath('blocks', 3);
        $this->assertSame([$this->ft->id => 'all', $this->intern->id => 'all', $this->ft2->id => 'all'], $this->blockedCycles('24QA0001'), 'C, internship and C2 — not the staging cycles the student is not enrolled in');

        $this->assertApplyBlocked('24QA0001', $sameCycle, '/^Blocked/', 'same cycle');
        $this->assertApplyBlocked('24QA0001', $otherCycle, '/^Blocked: accepted a Full-Time offer/', 'C2 posting');
    }

    public function test_T5_4_ctc_prefilled_per_programme_with_name_normalisation_manual_edit_persists_stipend_for_inf(): void
    {
        // The PHP port of getDisplayName() (salarygrid.tsx).
        $this->assertTrue(ProgrammeCatalogue::sameProgramme(self::MTECH, 'M.Tech'));
        $this->assertTrue(ProgrammeCatalogue::sameProgramme(self::MTECH, 'M.Tech (2 Year)'));
        $this->assertTrue(ProgrammeCatalogue::sameProgramme(self::BTECH, 'B.Tech / Double Major / Dual Degree'));
        $this->assertFalse(ProgrammeCatalogue::sameProgramme(self::IMTECH, self::MTECH));

        // Name normalisation through the real flow: salary rows keyed by DISPLAY names (getDisplayName output).
        $displayNamed = $this->floatPosting('jnf', $this->ft, 'Display Named FT', ['programmeSalaries' => [
            ['programme' => 'B.Tech / Double Major / Dual Degree', 'ctcAnnual' => '2500000', 'enabled' => true],
            ['programme' => 'Integrated M.Tech', 'ctcAnnual' => '3100000', 'enabled' => true],
            ['programme' => 'M.Tech', 'ctcAnnual' => '1700000', 'enabled' => true],
        ]]);
        $this->apply('24QM0001', $displayNamed)->assertCreated();
        $this->apply('24QA0002', $displayNamed)->assertCreated();
        $this->reachFinal($displayNamed, ['24QM0001', '24QA0002']);
        $rows = collect($this->prepare($displayNamed)->json('selected'))->keyBy('student.roll_no');
        $this->assertSame(1700000, $rows['24QM0001']['suggested']['ctc_annual'], '"M.Tech (2 Year) - GATE" matches the "M.Tech" row');
        $this->assertSame(2500000, $rows['24QA0002']['suggested']['ctc_annual'], 'B.Tech long name matches its display name');

        $jnf = $this->floatPosting('jnf', $this->ft, 'Per Programme FT');
        $inf = $this->floatPosting('inf', $this->intern, 'Per Programme Intern');
        foreach (['24QM0001', '24QA0001'] as $roll) {
            $this->apply($roll, $jnf)->assertCreated();
            $this->apply($roll, $inf)->assertCreated();
        }

        $this->reachFinal($jnf, ['24QM0001', '24QA0001']);
        $rows = collect($this->prepare($jnf)->json('selected'))->keyBy('student.roll_no');
        $this->assertSame(1800000, $rows['24QM0001']['suggested']['ctc_annual'], 'M.Tech student gets the M.Tech CTC (not the B.Tech one, not the max)');
        $this->assertSame(2400000, $rows['24QA0001']['suggested']['ctc_annual'], 'B.Tech student gets the B.Tech CTC');

        // Manual edit persists.
        $this->publishResults($jnf, [
            '24QM0001' => ['offer_type' => 'fulltime', 'ctc_annual' => 1950000],
            '24QA0001' => ['offer_type' => 'fulltime'],
        ])->assertOk();
        $this->assertSame(1950000, $this->appFor($jnf, '24QM0001')->offer->ctc_annual);
        $this->assertSame(2400000, $this->appFor($jnf, '24QA0001')->offer->ctc_annual);
        $this->assertSame('INR', $this->appFor($jnf, '24QM0001')->offer->currency);

        // INF → stipend per programme.
        $this->reachFinal($inf, ['24QM0001', '24QA0001']);
        $rows = collect($this->prepare($inf)->json('selected'))->keyBy('student.roll_no');
        $this->assertSame(60000, $rows['24QM0001']['suggested']['stipend_monthly']);
        $this->assertSame(80000, $rows['24QA0001']['suggested']['stipend_monthly']);
        $this->assertNull($rows['24QM0001']['suggested']['ctc_annual']);
    }

    public function test_T5_5_credit_score_system_not_built(): void
    {
        $roots = [base_path('app'), base_path('routes'), base_path('database'), base_path('config'), base_path('resources')];
        foreach (['app', 'components', 'lib'] as $dir) {
            if (is_dir(base_path("../frontend/{$dir}"))) {
                $roots[] = base_path("../frontend/{$dir}");
            }
        }

        $hits = [];
        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|jsx?|tsx?)$/', $file->getFilename())) {
                    continue;
                }
                if (preg_match('/credit[\s_-]*score|creditScore/i', (string) file_get_contents($file->getPathname()))) {
                    $hits[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $hits, 'credit-score system is out of scope (Q5.5) and must not exist');
    }

    public function test_T5_6_debarred_blocks_everything_in_cycle_with_reason_and_can_be_lifted(): void
    {
        CycleEnrollment::create(['placement_cycle_id' => $this->ft2->id, 'student_profile_id' => $this->students['24QA0001']->id, 'status' => 'active']);
        $jnf = $this->floatPosting('jnf', $this->ft, 'FT In C');
        $inf = $this->floatPosting('inf', $this->ft, 'Intern In C'); // fixture: mixed cycle
        $c2 = $this->floatPosting('jnf', $this->ft2, 'FT In C2');

        Sanctum::actingAs($this->admin);
        $payload = ['student_profile_id' => $this->students['24QA0001']->id, 'placement_cycle_id' => $this->ft->id, 'scope' => 'internships_only', 'reason' => 'debarred'];
        $this->postJson('/api/admin/blocks', $payload)->assertStatus(422); // remark required
        $this->postJson('/api/admin/blocks', $payload + ['remark' => 'Skipped the PPT'])->assertCreated()->assertJsonPath('block.scope', 'all');
        $this->assertSame(1, AuditLog::where('action', 'block.create')->count());

        $reason = '/^You are debarred from this placement cycle\. \(Skipped the PPT\)$/';
        $this->assertApplyBlocked('24QA0001', $jnf, $reason, 'debarred vs JNF');
        $this->assertApplyBlocked('24QA0001', $inf, $reason, 'debarred vs INF');
        Sanctum::actingAs($this->students['24QA0001']->user);
        $board = collect($this->getJson('/api/student/postings')->assertOk()->json('postings'))->keyBy('id');
        $this->assertFalse($board[$jnf->id]['eligibility']['eligible']);
        $this->assertMatchesRegularExpression($reason, $board[$jnf->id]['eligibility']['reasons'][0]);
        $this->assertApplyOk('24QA0001', $c2, 'debarment is per cycle');

        // Companies cannot debar.
        $company = User::where('role', 'company')->first();
        Sanctum::actingAs($company);
        $this->postJson('/api/admin/blocks', $payload + ['remark' => 'x'])->assertForbidden();

        // Unblockable.
        Sanctum::actingAs($this->admin);
        $this->deleteJson('/api/admin/blocks/'.PlacementBlock::sole()->id)->assertOk();
        $this->assertApplyOk('24QA0001', $jnf, 'after lifting the debarment');
    }

    // ------------------------------------------------------------------------------------------------------------
    // T5.7 – T5.8
    // ------------------------------------------------------------------------------------------------------------

    public function test_T5_7_publish_side_effects_offers_blocks_flags_status_mails_analytics(): void
    {
        CycleEnrollment::create(['placement_cycle_id' => $this->ft2->id, 'student_profile_id' => $this->students['24QA0001']->id, 'status' => 'active']);
        $google = $this->floatPosting('jnf', $this->ft, 'Google');
        $amazon = $this->floatPosting('jnf', $this->ft, 'Amazon');
        $internC = $this->floatPosting('inf', $this->intern, 'Intern Drive');
        $c2 = $this->floatPosting('jnf', $this->ft2, 'C2 Drive');
        foreach (['24QA0001', '24QA0002', '24QA0003', '24QA0004'] as $roll) {
            $this->apply($roll, $google)->assertCreated();
        }
        $this->apply('24QA0001', $amazon)->assertCreated();
        $this->apply('24QA0001', $internC)->assertCreated();
        $this->apply('24QA0001', $c2)->assertCreated();
        $this->apply('24QA0005', $amazon)->assertCreated();

        Sanctum::actingAs($this->admin);
        $before = $this->getJson("/api/admin/dashboard/cycle/{$this->ft->id}")->assertOk()->json('totals');
        $this->assertSame(0, $before['offers']);
        $this->assertSame(0, $before['placed']);

        // 0001–0003 clear round 1 (0004 rejected there); 0001 + 0002 get offers; 0003 is not selected in the final.
        // Complete blocks: 0001 → C, internship, C2 (3); 0002 → C, internship (2).
        $this->reachFinal($google, ['24QA0001', '24QA0002', '24QA0003'], ['24QA0001', '24QA0002']);
        $this->publishResults($google, ['24QA0001' => ['offer_type' => 'fulltime'], '24QA0002' => ['offer_type' => 'fulltime']])
            ->assertOk()->assertJsonPath('offers', 2)->assertJsonPath('blocks', 5)->assertJsonPath('regrets', 1)->assertJsonPath('flagged', 3);

        // Offers.
        $companyId = $google->postable->company_id;
        foreach (['24QA0001', '24QA0002'] as $roll) {
            $offer = $this->appFor($google, $roll)->offer;
            $this->assertNotNull($offer, "offer for {$roll}");
            $this->assertSame($this->students[$roll]->id, $offer->student_profile_id);
            $this->assertSame($companyId, $offer->company_id);
            $this->assertSame($google->id, $offer->job_posting_id);
            $this->assertSame($this->ft->id, $offer->placement_cycle_id);
            $this->assertSame('fulltime', $offer->offer_type);
            $this->assertSame(2400000, $offer->ctc_annual);
            $this->assertSame('INR', $offer->currency);
            $this->assertSame($this->admin->id, $offer->announced_by);
            $this->assertNotNull($offer->announced_at);

            $blocks = PlacementBlock::where('offer_id', $offer->id)->orderBy('placement_cycle_id')->get();
            $cycles = $roll === '24QA0001' ? [$this->ft->id, $this->intern->id, $this->ft2->id] : [$this->ft->id, $this->intern->id];
            $this->assertSame($cycles, $blocks->pluck('placement_cycle_id')->all(), "{$roll} blocked in every cycle they are enrolled in");
            foreach ($blocks as $block) {
                $this->assertSame(['offer', 'all', true, $this->admin->id], [$block->reason, $block->scope, $block->active, $block->blocked_by]);
            }
        }
        $this->assertSame(2, Offer::count());

        // Placed-elsewhere flags: 0001's other live applications in every cycle the block reaches.
        $this->assertTrue($this->appFor($amazon, '24QA0001')->placed_elsewhere_flag);
        $this->assertTrue($this->appFor($internC, '24QA0001')->placed_elsewhere_flag);
        $this->assertTrue($this->appFor($c2, '24QA0001')->placed_elsewhere_flag, 'the complete block reaches C2 too');
        $this->assertFalse($this->appFor($amazon, '24QA0005')->placed_elsewhere_flag);
        $this->assertFalse($this->appFor($google, '24QA0001')->placed_elsewhere_flag);

        // Status.
        $final = $google->rounds()->get()->last();
        $this->assertSame('completed', $google->fresh()->status);
        $this->assertSame('completed', $final->fresh()->status);
        $finalRows = ApplicationRoundResult::where('posting_round_id', $final->id)->get()->keyBy(fn ($r) => $r->application->studentProfile->roll_no);
        $this->assertSame('selected', $finalRows['24QA0001']->result);
        $this->assertSame('rejected', $finalRows['24QA0003']->result);
        $this->assertTrue($finalRows->every(fn ($r) => $r->published_at !== null), 'final round fully published');

        // E5 offer mails + final regret; in-app.
        foreach (['24QA0001', '24QA0002'] as $roll) {
            $email = $this->students[$roll]->user->email;
            Mail::assertQueued(OfferMail::class, fn (OfferMail $m) => $m->hasTo($email) && $m->offerLabel === 'Full-Time' && $m->companyName === 'Google Ltd' && str_contains((string) $m->compensation, '2,400,000') && $m->blockNote !== null);
            $this->assertSame(1, PortalNotification::where('user_id', $this->students[$roll]->user_id)->where('title', 'like', 'Offer from%')->count());
        }
        Mail::assertQueued(OfferMail::class, 2);
        $regretTo = fn (string $roll) => Mail::queued(RoundResultMail::class, fn (RoundResultMail $m) => $m->outcome === 'rejected' && $m->hasBcc($this->students[$roll]->user->email))->count();
        $this->assertSame(1, $regretTo('24QA0003'), 'final-round regret');
        $this->assertSame(1, $regretTo('24QA0004'), 'round-1 regret only, not repeated at the final publish');
        $this->assertSame(0, $regretTo('24QA0001'));

        // Audit + analytics.
        $this->assertSame(2, AuditLog::where('action', 'offer.create')->count());
        $this->assertSame(5, AuditLog::where('action', 'block.create')->count());
        $this->assertSame(1, AuditLog::where('action', 'result.publish')->count());

        Sanctum::actingAs($this->admin);
        $after = $this->getJson("/api/admin/dashboard/cycle/{$this->ft->id}")->assertOk();
        $this->assertSame(2, $after->json('totals.offers'));
        $this->assertSame(2, $after->json('totals.placed'));
        $this->assertSame($before['drives_completed'] + 1, $after->json('totals.drives_completed'));
        $this->assertSame(2, collect($after->json('offers_by_type'))->firstWhere('type', 'fulltime')['count']);
    }

    public function test_T5_7_publish_is_atomic_no_partial_offers_or_blocks_on_failure(): void
    {
        // Code check: every domain write of publish() sits inside ONE DB::transaction closure.
        $method = new \ReflectionMethod(AdminResultController::class, 'publish');
        $lines = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
        $this->assertSame(1, substr_count($source, 'DB::transaction('), 'exactly one transaction');
        $txStart = strpos($source, 'DB::transaction(');
        $txEnd = strpos($source, '$this->audit->log(');
        foreach (['ApplicationRoundResult::query()->updateOrCreate', 'Offer::create', 'PlacementBlock::create', 'flagLiveApplications', "\$final->update(['status' => 'completed'])", "\$jobPosting->update(['status' => 'completed'])"] as $write) {
            $at = strpos($source, $write);
            $this->assertNotFalse($at, "{$write} present");
            $this->assertTrue($at > $txStart && $at < $txEnd, "{$write} must be inside the transaction");
        }

        $google = $this->floatPosting('jnf', $this->ft, 'Google');
        $amazon = $this->floatPosting('jnf', $this->ft, 'Amazon');
        foreach (['24QA0001', '24QA0002'] as $roll) {
            $this->apply($roll, $google)->assertCreated();
        }
        $this->apply('24QA0001', $amazon)->assertCreated();
        $this->reachFinal($google, ['24QA0001', '24QA0002']);
        $final = $google->rounds()->get()->last();

        $assertNothingWritten = function (string $why) use ($google, $amazon, $final): void {
            $this->assertSame(0, Offer::count(), "{$why}: no offers");
            $this->assertSame(0, PlacementBlock::count(), "{$why}: no blocks");
            $this->assertFalse($this->appFor($amazon, '24QA0001')->placed_elsewhere_flag, "{$why}: no flags");
            $this->assertSame('in_process', $google->fresh()->status, "{$why}: posting status unchanged");
            $this->assertNotSame('completed', $final->fresh()->status, "{$why}: final round not completed");
            $this->assertSame(0, ApplicationRoundResult::where('posting_round_id', $final->id)->whereNotNull('published_at')->count(), "{$why}: final round unpublished");
            $this->assertSame(0, AuditLog::whereIn('action', ['offer.create', 'block.create', 'result.publish'])->count(), "{$why}: no audit");
            Mail::assertNotQueued(OfferMail::class);
        };

        // (1) A selection pointing at ANOTHER posting's application → clean 4xx, nothing written.
        $this->publishResults($google, [
            '24QA0001' => ['offer_type' => 'fulltime'],
            'other' => ['offer_type' => 'fulltime', 'application_id' => $this->appFor($amazon, '24QA0001')->id],
        ])->assertStatus(422)->assertJsonPath('message', 'Application #'.$this->appFor($amazon, '24QA0001')->id.' is not a live application of this posting.');
        $assertNothingWritten('foreign application');

        // (2) Mid-way failure (the 2nd offer insert throws) → everything rolled back.
        $created = 0;
        Offer::creating(function () use (&$created): void {
            if (++$created === 2) {
                throw new \RuntimeException('QA injected failure on the 2nd offer');
            }
        });
        $response = $this->publishResults($google, ['24QA0001' => ['offer_type' => 'fulltime'], '24QA0002' => ['offer_type' => 'fulltime']]);
        $this->assertSame(500, $response->status());
        $this->assertSame(2, $created, 'the first offer insert really ran before the failure');
        $assertNothingWritten('mid-way failure');
    }

    public function test_T5_8_double_publish_creates_no_duplicates_and_returns_clean_4xx(): void
    {
        $google = $this->floatPosting('jnf', $this->ft, 'Google');
        $this->apply('24QA0001', $google)->assertCreated();
        $this->apply('24QA0002', $google)->assertCreated();
        $this->reachFinal($google, ['24QA0001', '24QA0002'], ['24QA0001']);

        // The same application twice in one request → refused, nothing written.
        $appId = $this->appFor($google, '24QA0001')->id;
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $appId, 'offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'all'],
            ['application_id' => $appId, 'offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'all'],
        ]])->assertStatus(422);
        $this->assertSame(0, Offer::count());

        $selection = ['24QA0001' => ['offer_type' => 'fulltime']];
        $this->publishResults($google, $selection)->assertOk()->assertJsonPath('offers', 1);

        $second = $this->publishResults($google, $selection);
        $this->assertSame(422, $second->status(), 'second publish must be a clean 4xx, got '.$second->status());
        $this->assertStringContainsString('already has an offer', (string) $second->json('message'));

        $this->assertSame(1, Offer::count(), 'no duplicate offer');
        $this->assertSame(2, PlacementBlock::count(), 'no duplicate blocks (one per cycle reached: C + internship)');
        Mail::assertQueued(OfferMail::class, 1);

        // DB-level guarantee: unique index on offers.application_id.
        $unique = collect(DB::select("PRAGMA index_list('offers')"))
            ->filter(fn ($index) => (int) $index->unique === 1)
            ->contains(fn ($index) => collect(DB::select("PRAGMA index_info('{$index->name}')"))->pluck('name')->all() === ['application_id']);
        $this->assertTrue($unique, 'offers.application_id must carry a UNIQUE index');
    }

    /**
     * Extra (T5.8 race): two publishes in flight for the same student (double-click / two admins). Both pass the
     * pre-transaction "already has an offer" check; the loser hits the UNIQUE index inside the transaction.
     * Simulated by committing the competing offer row right before this request's own insert.
     */
    public function test_T5_8_x_concurrent_double_publish_loser_gets_clean_4xx_not_500(): void
    {
        $google = $this->floatPosting('jnf', $this->ft, 'Google');
        $this->apply('24QA0001', $google)->assertCreated();
        $this->reachFinal($google, ['24QA0001']);
        $application = $this->appFor($google, '24QA0001');

        $raced = false;
        Offer::creating(function (Offer $offer) use (&$raced, $application): void {
            if (! $raced && $offer->application_id === $application->id) {
                $raced = true;
                DB::table('offers')->insert([
                    'application_id' => $application->id, 'student_profile_id' => $application->student_profile_id,
                    'company_id' => $offer->company_id, 'job_posting_id' => $offer->job_posting_id, 'placement_cycle_id' => $offer->placement_cycle_id,
                    'offer_type' => 'fulltime', 'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $response = $this->publishResults($google, ['24QA0001' => ['offer_type' => 'fulltime']]);
        $this->assertTrue($raced);
        $this->assertLessThanOrEqual(1, Offer::count(), 'never two offers for one application');
        $this->assertTrue(
            $response->status() >= 400 && $response->status() < 500,
            'the losing publish of a race must get a clean 4xx ("already has an offer"), got '.$response->status().': '.mb_substr((string) $response->getContent(), 0, 200)
        );
    }
}
