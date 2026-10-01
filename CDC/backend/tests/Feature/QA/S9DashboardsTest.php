<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\CampusEvent;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * QA Section 9 — Dashboards & calendar (spec B7 "Dashboards", Q9.x, M8.2, M10.1, M10.2; D77, D80, D81, D88).
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S9DashboardsTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    /** Phase 1 `/admin/dashboard` stats keys (df7034b AdminDashboardController). */
    private const PHASE1_STATS_KEYS = [
        'companies_total', 'companies_with_submissions',
        'jnf_total', 'jnf_submitted', 'jnf_under_review', 'jnf_accepted', 'jnf_rejected', 'jnf_draft',
        'inf_total', 'inf_submitted', 'inf_under_review', 'inf_accepted', 'inf_rejected', 'inf_draft',
        'pending_reviews',
    ];

    private User $admin;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-10-01T06:00:00Z'));
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    private function cycle(string $type = 'fulltime', string $name = 'FT'): PlacementCycle
    {
        return PlacementCycle::create([
            'name' => $name.' '.uniqid(), 'type' => $type, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    private function student(array $attributes = [], ?PlacementCycle $cycle = null, string $enrolment = 'active', bool $active = true): StudentProfile
    {
        $this->seq++;
        $roll = sprintf('26DB%04d', $this->seq);
        $email = strtolower($roll).'@students.qa.test';
        $student = StudentProfile::factory()->create(array_merge([
            'roll_no' => $roll, 'institute_email' => $email,
            'user_id' => User::factory()->state(['role' => 'student', 'email' => $email, 'is_active' => $active]),
        ], $attributes));
        if ($cycle) {
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => $enrolment]);
        }

        return $student->fresh('user');
    }

    private function formData(string $title, string $cgpa = '7.0'): array
    {
        return [
            'jobTitle' => $title, 'internshipTitle' => $title, 'currency' => 'INR', 'graduatingBatch' => '2027', 'genderFilter' => 'all',
            'eligibility' => [['programme' => self::BTECH, 'branches' => [
                ['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => $cgpa, 'backlogsAllowed' => true],
                ['branch' => 'Mining Engineering', 'selected' => true, 'cgpa' => $cgpa, 'backlogsAllowed' => true],
            ]]],
            'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
        ];
    }

    /** Float a JNF/INF through the real admin API. */
    private function posting(PlacementCycle $cycle, string $title, string $deadline, string $cgpa = '7.0'): JobPosting
    {
        $company = Company::create(['name' => $title.' Co', 'hr_name' => 'HR', 'hr_email' => uniqid().'@co.qa.test']);
        $form = $cycle->type === 'internship'
            ? Inf::create(['company_id' => $company->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => 'accepted', 'form_data' => $this->formData($title, $cgpa)])
            : Jnf::create(['company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'form_data' => $this->formData($title, $cgpa)]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => $cycle->type === 'internship' ? 'inf' : 'jnf', 'form_id' => $form->id,
            'placement_cycle_id' => $cycle->id, 'application_deadline' => $deadline,
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function apply(StudentProfile $student, JobPosting $posting, ?string $appliedAt = null, string $status = 'applied', string $resumeStatus = 'approved'): Application
    {
        $resume = $student->resumes()->firstOrCreate(['slot' => 1], ['label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => $resumeStatus]);

        return Application::create([
            'job_posting_id' => $posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id,
            'status' => $status, 'applied_at' => $appliedAt ? Carbon::parse($appliedAt) : now(),
            'withdrawn_at' => $status === 'withdrawn' ? now() : null,
            'used_unverified_resume' => $resumeStatus !== 'approved',
        ]);
    }

    private function offer(Application $application, string $type, ?int $ctc, ?int $stipend = null, string $currency = 'INR'): Offer
    {
        $posting = $application->jobPosting;

        return Offer::create([
            'application_id' => $application->id, 'student_profile_id' => $application->student_profile_id,
            'company_id' => $posting->company()->id, 'job_posting_id' => $posting->id, 'placement_cycle_id' => $posting->placement_cycle_id,
            'offer_type' => $type, 'ctc_annual' => $ctc, 'stipend_monthly' => $stipend, 'currency' => $currency,
            'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);
    }

    /** Independent median: middle value, or the mean of the two middle values for an even count. */
    private static function median(array $values): ?float
    {
        sort($values);
        $n = count($values);
        if ($n === 0) {
            return null;
        }

        return $n % 2 ? (float) $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }

    /**
     * Known cycle: 10 enrolled (6 CSE, 4 Mining; 3 female), offers:
     *  s1 fulltime 10L · s2 fulltime 12L (+ a second fulltime with no CTC) · s3 ppo_offered 15L (NOT placed)
     *  s4 intern_fulltime 20L (+ stipend 1L) · s5 fulltime USD 30,000 (non-INR) ; s9 suspended account, s10 suspended enrolment.
     *
     * @return array{cycle: PlacementCycle, students: list<StudentProfile>, postings: array<string, JobPosting>}
     */
    private function knownCycle(): array
    {
        $cycle = $this->cycle('fulltime', 'Known');
        $s = [];
        $s[1] = $this->student([], $cycle);
        $s[2] = $this->student(['gender' => 'female'], $cycle);
        $s[3] = $this->student([], $cycle);
        $s[4] = $this->student([], $cycle);
        $s[5] = $this->student(['branch' => 'Mining Engineering', 'gender' => 'female'], $cycle);
        $s[6] = $this->student([], $cycle);
        $s[7] = $this->student([], $cycle);
        $s[8] = $this->student(['branch' => 'Mining Engineering', 'gender' => 'female'], $cycle);
        $s[9] = $this->student(['branch' => 'Mining Engineering'], $cycle, 'active', active: false);
        $s[10] = $this->student(['branch' => 'Mining Engineering'], $cycle, 'suspended');

        $completed = $this->posting($cycle, 'Completed', now()->addDays(2)->toIso8601String());
        $ongoingOpen = $this->posting($cycle, 'Open', now()->addDays(2)->toIso8601String());
        $ongoingProcess = $this->posting($cycle, 'Process', now()->addDays(2)->toIso8601String());
        $cancelled = $this->posting($cycle, 'Cancelled', now()->addDays(2)->toIso8601String());
        $completed->update(['status' => 'completed']);
        $ongoingProcess->update(['status' => 'in_process']);
        $cancelled->update(['status' => 'cancelled']);

        // applied_at chosen around IST midnight: 2026-09-10T20:00Z is 11 Sep 01:30 IST.
        $a1 = $this->apply($s[1], $completed, '2026-09-10T10:00:00Z');
        $a2 = $this->apply($s[2], $completed, '2026-09-10T20:00:00Z');
        $a3 = $this->apply($s[3], $completed, '2026-09-10T20:00:00Z');
        $a4 = $this->apply($s[4], $completed, '2026-09-10T20:00:00Z');
        $a5 = $this->apply($s[5], $completed, '2026-09-13T05:00:00Z');
        $a2b = $this->apply($s[2], $ongoingProcess, '2026-09-13T06:00:00Z');
        $this->apply($s[6], $cancelled, '2026-09-12T06:00:00Z'); // cancelled posting: not counted

        $this->offer($a1, 'fulltime', 1_000_000);
        $this->offer($a2, 'fulltime', 1_200_000, 50_000);
        $this->offer($a2b, 'fulltime', null);
        $this->offer($a3, 'ppo_offered', 1_500_000);
        $this->offer($a4, 'intern_fulltime', 2_000_000, 100_000);
        $this->offer($a5, 'fulltime', 30_000, null, 'USD');

        // Noise in ANOTHER cycle must not leak into this one.
        $other = $this->cycle('fulltime', 'Other');
        CycleEnrollment::create(['placement_cycle_id' => $other->id, 'student_profile_id' => $s[6]->id, 'status' => 'active']);
        $otherPosting = $this->posting($other, 'Elsewhere', now()->addDays(2)->toIso8601String());
        $this->offer($this->apply($s[6], $otherPosting, '2026-09-20T06:00:00Z'), 'fulltime', 9_900_000);

        return ['cycle' => $cycle, 'students' => $s, 'postings' => compact('completed', 'ongoingOpen', 'ongoingProcess', 'cancelled')];
    }

    // ------------------------------------------------------------------ T9.1

    public function test_T9_1_overview_and_cycle_dashboards_expose_every_required_block(): void
    {
        ['cycle' => $cycle] = $this->knownCycle();
        Sanctum::actingAs($this->admin);

        $overview = $this->getJson('/api/admin/dashboard/overview')->assertOk();
        $this->assertNotEmpty($overview->json('cycles'));
        foreach (['id', 'name', 'type', 'status', 'enrolled', 'placed', 'placed_percent', 'offers', 'postings'] as $key) {
            $this->assertArrayHasKey($key, $overview->json('cycles.0'), "overview cycles[].{$key}");
        }

        $detail = $this->getJson("/api/admin/dashboard/cycle/{$cycle->id}")->assertOk();
        $detail->assertJsonStructure([
            'cycle' => ['id', 'name', 'type', 'status'],
            'totals' => ['enrolled', 'placed', 'unplaced', 'placed_percent', 'offers', 'applications', 'drives_completed', 'drives_ongoing'],
            'by_programme' => [['key', 'enrolled', 'placed', 'placed_percent']],
            'by_batch' => [['key', 'enrolled', 'placed', 'placed_percent']],
            'by_gender' => [['key', 'enrolled', 'placed', 'placed_percent']],
            'branch_table' => [['programme', 'branch', 'enrolled', 'placed', 'placed_percent', 'offers', 'average_ctc', 'highest_ctc']],
            'offers_by_type' => [['type', 'label', 'count']],
            'ctc' => ['count', 'highest', 'average', 'median', 'lowest'],
            'stipend' => ['count', 'highest', 'average', 'median', 'lowest'],
            'applications_over_time' => [['date', 'count']],
        ]);
    }

    public function test_T9_1b_dashboard_numbers_match_independently_computed_values(): void
    {
        ['cycle' => $cycle, 'students' => $s] = $this->knownCycle();
        Sanctum::actingAs($this->admin);
        $d = $this->getJson("/api/admin/dashboard/cycle/{$cycle->id}")->assertOk()->json();

        // ---- placed / placed % : distinct students with any offer except ppo_offered, over ALL 10 enrolled.
        $placedIds = [$s[1]->id, $s[2]->id, $s[4]->id, $s[5]->id]; // s3 has only ppo_offered; s2 counted once
        $enrolled = 10;
        $this->assertSame($enrolled, $d['totals']['enrolled']);
        $this->assertSame(count($placedIds), $d['totals']['placed']);
        $this->assertSame($enrolled - count($placedIds), $d['totals']['unplaced']);
        $this->assertEquals(round(count($placedIds) * 100 / $enrolled, 1), $d['totals']['placed_percent']);
        $this->assertSame(6, $d['totals']['offers']);

        // ---- CTC (INR only; accepted offers only — the ppo_offered 15L is left out: owner decision 2026-10-01)
        $ctcs = [1_000_000, 1_200_000, 2_000_000];
        $this->assertSame(count($ctcs), $d['ctc']['count']);
        $this->assertEquals(max($ctcs), $d['ctc']['highest']);
        $this->assertEquals(min($ctcs), $d['ctc']['lowest']);
        $this->assertEqualsWithDelta(array_sum($ctcs) / count($ctcs), $d['ctc']['average'], 0.5);
        $this->assertEqualsWithDelta(self::median($ctcs), $d['ctc']['median'], 0.5, 'odd count → the middle value (1.2M)');
        $this->assertSame(['USD' => 1], $d['non_inr_offers']);

        // ---- stipend
        $stipends = [50_000, 100_000];
        $this->assertSame(2, $d['stipend']['count']);
        $this->assertEquals(100_000, $d['stipend']['highest']);
        $this->assertEquals(50_000, $d['stipend']['lowest']);
        $this->assertEqualsWithDelta(self::median($stipends), $d['stipend']['median'], 0.5);

        // ---- offers by type
        $byType = collect($d['offers_by_type'])->pluck('count', 'type')->all();
        $this->assertEquals(['fulltime' => 4, 'ppo_offered' => 1, 'intern_fulltime' => 1], $byType);

        // ---- drives completed vs ongoing (cancelled excluded), counted per posting.
        $this->assertSame(1, $d['totals']['drives_completed']);
        $this->assertSame(2, $d['totals']['drives_ongoing']);

        // ---- branch table
        $branches = collect($d['branch_table'])->keyBy('branch');
        $this->assertSame(6, $branches['Computer Science & Engineering']['enrolled']);
        $this->assertSame(3, $branches['Computer Science & Engineering']['placed']);
        $this->assertEquals(50.0, $branches['Computer Science & Engineering']['placed_percent']);
        $this->assertEqualsWithDelta(1_400_000, $branches['Computer Science & Engineering']['average_ctc'], 0.5, 'accepted offers only');
        $this->assertEquals(2_000_000, $branches['Computer Science & Engineering']['highest_ctc']);
        $this->assertSame(4, $branches['Mining Engineering']['enrolled']);
        $this->assertSame(1, $branches['Mining Engineering']['placed']);
        $this->assertEquals(25.0, $branches['Mining Engineering']['placed_percent']);
        $this->assertNull($branches['Mining Engineering']['average_ctc'], 'USD-only branch has no INR average');

        // ---- gender split
        $gender = collect($d['by_gender'])->keyBy('key');
        $this->assertSame(3, $gender['Female']['enrolled']);
        $this->assertSame(2, $gender['Female']['placed']);
        $this->assertSame(7, $gender['Male']['enrolled']);
        $this->assertSame(2, $gender['Male']['placed']);

        // ---- applications over time, bucketed by IST day, gaps filled, cancelled posting ignored
        $this->assertSame([
            ['date' => '2026-09-10', 'count' => 1],
            ['date' => '2026-09-11', 'count' => 3],
            ['date' => '2026-09-12', 'count' => 0],
            ['date' => '2026-09-13', 'count' => 2],
        ], $d['applications_over_time']);
        $this->assertSame(6, $d['totals']['applications']);

        // ---- overview row agrees with the cycle page
        $row = collect($this->getJson('/api/admin/dashboard/overview')->json('cycles'))->firstWhere('id', $cycle->id);
        $this->assertSame(10, $row['enrolled']);
        $this->assertSame(4, $row['placed']);
        $this->assertEquals(40.0, $row['placed_percent']);
        $this->assertSame(6, $row['offers']);
        $this->assertSame(3, $row['postings']);
    }

    public function test_T9_1b_zero_offer_and_empty_cycles_do_not_divide_by_zero(): void
    {
        $noOffers = $this->cycle('fulltime', 'NoOffers');
        $this->student([], $noOffers);
        $this->student([], $noOffers);
        $this->student([], $noOffers);
        $empty = $this->cycle('internship', 'Empty');
        Sanctum::actingAs($this->admin);

        $d = $this->getJson("/api/admin/dashboard/cycle/{$noOffers->id}")->assertOk()->json();
        $this->assertSame(3, $d['totals']['enrolled']);
        $this->assertSame(0, $d['totals']['placed']);
        $this->assertEquals(0, $d['totals']['placed_percent']);
        $this->assertSame(['count' => 0, 'highest' => null, 'average' => null, 'median' => null, 'lowest' => null], $d['ctc']);
        $this->assertSame(['count' => 0, 'highest' => null, 'average' => null, 'median' => null, 'lowest' => null], $d['stipend']);
        $this->assertSame([], $d['applications_over_time']);

        $e = $this->getJson("/api/admin/dashboard/cycle/{$empty->id}")->assertOk()->json();
        $this->assertSame(0, $e['totals']['enrolled']);
        $this->assertEquals(0, $e['totals']['placed_percent']);
        $this->assertNull($e['ctc']['median']);

        $overview = collect($this->getJson('/api/admin/dashboard/overview')->assertOk()->json('cycles'))->keyBy('id');
        $this->assertEquals(0, $overview[$empty->id]['placed_percent']);
        $this->assertEquals(0, $overview[$noOffers->id]['placed_percent']);
    }

    public function test_T9_1c_phase1_admin_dashboard_response_is_unchanged(): void
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.qa.test']);
        Jnf::create(['company_id' => $company->id, 'job_title' => 'A', 'job_description' => 'x', 'status' => 'submitted']);
        Jnf::create(['company_id' => $company->id, 'job_title' => 'B', 'job_description' => 'x', 'status' => 'accepted']);
        Inf::create(['company_id' => $company->id, 'internship_title' => 'C', 'internship_description' => 'x', 'status' => 'submitted']);

        Sanctum::actingAs($this->admin);
        $r = $this->getJson('/api/admin/dashboard')->assertOk()->json();

        $this->assertSame(['stats', 'recent_submissions'], array_keys($r));
        $this->assertSame(self::PHASE1_STATS_KEYS, array_keys($r['stats']));
        $this->assertSame(['jnfs', 'infs'], array_keys($r['recent_submissions']));
        $this->assertSame(2, $r['stats']['pending_reviews']);
        $this->assertSame(2, $r['stats']['jnf_total']);
        $this->assertSame(1, $r['stats']['companies_with_submissions']);
        $this->assertSame(['id', 'job_title', 'status', 'updated_at', 'company', 'edit_access_requested_at', 'graduating_batch'], array_keys($r['recent_submissions']['jnfs'][0]));
    }

    // ------------------------------------------------------------------ T9.2

    public function test_T9_2_placement_denominator_is_all_enrolled_students_including_suspended(): void
    {
        $cycle = $this->cycle();
        $placed = $this->student([], $cycle);
        $this->student([], $cycle);                          // plain unplaced
        $this->student([], $cycle, 'active', active: false); // suspended ACCOUNT
        $this->student([], $cycle, 'suspended');             // suspended ENROLMENT
        $this->student();                                    // not enrolled → never counted
        $posting = $this->posting($cycle, 'Denominator', now()->addDay()->toIso8601String());
        $this->offer($this->apply($placed, $posting), 'fulltime', 1_000_000);

        Sanctum::actingAs($this->admin);
        $d = $this->getJson("/api/admin/dashboard/cycle/{$cycle->id}")->assertOk()->json('totals');

        // Spec Q9.2: denominator = ALL enrolled students. Implemented: every cycle_enrollments row regardless of
        // enrolment status or account suspension → 4 (recorded: suspended account and suspended enrolment ARE counted).
        $this->assertSame(4, $d['enrolled']);
        $this->assertSame(1, $d['placed']);
        $this->assertEquals(25.0, $d['placed_percent']);
    }

    // ------------------------------------------------------------------ T9.3a

    public function test_T9_3a_student_dashboard_shows_profile_resumes_trail_upcoming_and_offer_banner(): void
    {
        $cycle = $this->cycle();
        $student = $this->student([], $cycle);
        $student->resumes()->create(['slot' => 2, 'label' => 'Data', 'file_path' => 'r2.pdf', 'file_size' => 1, 'status' => 'approved']);
        $student->resumes()->create(['slot' => 3, 'label' => 'Core', 'file_path' => 'r3.pdf', 'file_size' => 1, 'status' => 'rejected', 'admin_remark' => 'Fix']);

        $applied = $this->posting($cycle, 'Applied Drive', now()->addDays(3)->toIso8601String());
        $open = $this->posting($cycle, 'Open Drive', now()->addDays(5)->toIso8601String());
        $application = $this->apply($student, $applied, null, 'applied', 'pending'); // slot 1, pending → unverified flag
        $round1 = $applied->rounds()->orderBy('sort_order')->first();
        $round2 = $applied->rounds()->orderBy('sort_order')->skip(1)->first();
        $round2->update(['scheduled_at' => now()->addDays(6)]);
        CampusEvent::create(['title' => 'Big PPT', 'event_type' => 'ppt', 'starts_at' => now()->addDays(4), 'published_at' => now(), 'audience_type' => 'all']);
        CampusEvent::create(['title' => 'Secret draft', 'event_type' => 'ppt', 'starts_at' => now()->addDays(4), 'audience_type' => 'all']);

        // Round 1 published as selected; round 2 has a DRAFT result that must not leak into the trail.
        $applied->update(['status' => 'in_process']);
        ApplicationRoundResult::create(['application_id' => $application->id, 'posting_round_id' => $round1->id, 'result' => 'selected', 'attendance' => 'yes', 'published_at' => now()]);
        ApplicationRoundResult::create(['application_id' => $application->id, 'posting_round_id' => $round2->id, 'result' => 'rejected']);

        Sanctum::actingAs($student->user);
        $d = $this->getJson('/api/student/dashboard')->assertOk()->json();

        $this->assertSame($student->roll_no, $d['student']['roll_no']);
        $this->assertSame(['total' => 3, 'approved' => 1, 'pending' => 1, 'rejected' => 1], $d['resumes']);
        $this->assertSame(1, $d['unverified_applications'], 'unverified-resume warning count');
        $this->assertSame(1, $d['active_applications']);

        $trail = collect($d['applications'])->firstWhere('posting.id', $applied->id)['trail'];
        $this->assertTrue($trail[0]['published']);
        $this->assertSame('selected', $trail[0]['result']);
        $this->assertFalse($trail[1]['published'], 'draft round-2 result is not shown');
        $this->assertNull($trail[1]['result']);

        $upcoming = collect($d['upcoming']);
        $this->assertTrue($upcoming->contains(fn ($i) => $i['type'] === 'event' && $i['title'] === 'Big PPT'));
        $this->assertFalse($upcoming->contains(fn ($i) => $i['title'] === 'Secret draft'), 'draft events never shown');
        $this->assertTrue($upcoming->contains(fn ($i) => $i['type'] === 'deadline' && str_contains($i['title'], 'Open Drive')));
        $this->assertTrue($upcoming->contains(fn ($i) => $i['type'] === 'round' && str_contains($i['title'], 'Applied Drive')));
        $this->assertSame($upcoming->sortBy('at')->values()->all(), $upcoming->all(), 'upcoming sorted by time');
        $this->assertSame([], $d['offers'], 'no offer banner before placement');

        // Placed → offer banner + block sentence.
        $offer = $this->offer($application, 'fulltime', 1_800_000);
        PlacementBlock::create(['student_profile_id' => $student->id, 'placement_cycle_id' => $cycle->id, 'scope' => 'all', 'reason' => 'offer', 'offer_id' => $offer->id, 'active' => true, 'blocked_by' => $this->admin->id]);
        $d = $this->getJson('/api/student/dashboard')->assertOk()->json();
        $this->assertCount(1, $d['offers']);
        $this->assertSame('fulltime', $d['offers'][0]['offer_type']);
        $this->assertSame('Applied Drive Co', $d['offers'][0]['company']);
        $this->assertEquals(1_800_000, $d['offers'][0]['ctc_annual']);
        $this->assertNotEmpty($d['active_blocks']);
        $this->assertSame('fulltime', collect($d['applications'])->firstWhere('posting.id', $applied->id)['offer']['offer_type']);
    }

    // ------------------------------------------------------------------ T9.3b

    public function test_T9_3b_nudge_lists_only_eligible_unapplied_open_postings_by_deadline(): void
    {
        $ft = $this->cycle('fulltime', 'FT');
        $intern = $this->cycle('internship', 'Intern');
        $suspendedCycle = $this->cycle('fulltime', 'SuspendedEnrolment');
        $student = $this->student([], $ft);
        CycleEnrollment::create(['placement_cycle_id' => $intern->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        CycleEnrollment::create(['placement_cycle_id' => $suspendedCycle->id, 'student_profile_id' => $student->id, 'status' => 'suspended']);

        $later = $this->posting($ft, 'Later', now()->addDays(4)->toIso8601String());
        $sooner = $this->posting($ft, 'Sooner', now()->addDays(2)->toIso8601String());
        $applied = $this->posting($ft, 'Applied', now()->addDays(3)->toIso8601String());
        $ineligible = $this->posting($ft, 'HighCutoff', now()->addDays(3)->toIso8601String(), '9.5');
        $expired = $this->posting($ft, 'Expired', now()->addDays(3)->toIso8601String());
        $withdrawnExpired = $this->posting($ft, 'WithdrawnExpired', now()->addDays(3)->toIso8601String());
        $withdrawnOpen = $this->posting($ft, 'WithdrawnOpen', now()->addDays(3)->toIso8601String());
        $cancelled = $this->posting($ft, 'Cancelled', now()->addDays(3)->toIso8601String());
        $blocked = $this->posting($intern, 'BlockedIntern', now()->addDays(3)->toIso8601String());
        $notActive = $this->posting($suspendedCycle, 'SuspendedCycle', now()->addDays(3)->toIso8601String());

        $this->apply($student, $applied);
        $this->apply($student, $withdrawnExpired, null, 'withdrawn');
        $this->apply($student, $withdrawnOpen, null, 'withdrawn');
        $expired->forceFill(['application_deadline' => now()->subHour()])->save();
        $withdrawnExpired->forceFill(['application_deadline' => now()->subHour()])->save();
        $cancelled->update(['status' => 'cancelled']);
        PlacementBlock::create(['student_profile_id' => $student->id, 'placement_cycle_id' => $intern->id, 'scope' => 'all', 'reason' => 'debarred', 'remark' => 'QA', 'active' => true, 'blocked_by' => $this->admin->id]);

        Sanctum::actingAs($student->user);
        $d = $this->getJson('/api/student/dashboard')->assertOk()->json();
        $ids = array_column($d['nudges'], 'id');

        $this->assertSame([$sooner->id, $later->id], $ids, 'eligible ∧ not applied ∧ deadline future, soonest first; got titles: '.implode(', ', array_column($d['nudges'], 'title')));
        $this->assertSame(2, $d['nudges_total']);
        foreach ([$applied, $ineligible, $expired, $withdrawnExpired, $cancelled, $blocked, $notActive] as $excluded) {
            $this->assertNotContains($excluded->id, $ids, "{$excluded->title()} must not be nudged");
        }
        // Recorded (implemented interpretation): a posting the student withdrew from while it is still open is NOT nudged.
        $this->assertNotContains($withdrawnOpen->id, $ids);
    }

    // ------------------------------------------------------------------ T9.3c

    public function test_T9_3c_calendar_admin_vs_student_relevance_and_ist_month_bucketing(): void
    {
        $cycle = $this->cycle('fulltime', 'Cal');
        $otherCycle = $this->cycle('fulltime', 'NotMine');
        $student = $this->student([], $cycle);
        $outsider = $this->student();

        // Deadline at 2027-01-31T20:00Z = 1 Feb 2027 01:30 IST → February.
        $boundary = $this->posting($cycle, 'Boundary', '2027-01-31T20:00:00Z');
        $notMine = $this->posting($otherCycle, 'NotMine', '2027-02-10T06:00:00Z');
        $notApplied = $this->posting($cycle, 'NotApplied', '2027-02-11T06:00:00Z');
        $this->apply($student, $boundary);
        $boundary->rounds()->orderBy('sort_order')->first()->update(['scheduled_at' => Carbon::parse('2027-02-15T04:30:00Z')]);
        $notApplied->rounds()->orderBy('sort_order')->first()->update(['scheduled_at' => Carbon::parse('2027-02-16T04:30:00Z')]);
        $notMine->rounds()->orderBy('sort_order')->first()->update(['scheduled_at' => Carbon::parse('2027-02-17T04:30:00Z')]);

        CampusEvent::create(['title' => 'Feb talk', 'event_type' => 'ppt', 'starts_at' => Carbon::parse('2027-02-05T06:00:00Z'), 'published_at' => now(), 'audience_type' => 'all']);
        CampusEvent::create(['title' => 'Feb draft', 'event_type' => 'ppt', 'starts_at' => Carbon::parse('2027-02-06T06:00:00Z'), 'audience_type' => 'all']);
        CampusEvent::create(['title' => 'Mining only', 'event_type' => 'ppt', 'starts_at' => Carbon::parse('2027-02-07T06:00:00Z'), 'published_at' => now(), 'audience_type' => 'branches', 'audience_filter' => ['branches' => [['programme' => self::BTECH, 'branch' => 'Mining Engineering']]]]);
        CampusEvent::create(['title' => 'March midnight', 'event_type' => 'other', 'starts_at' => Carbon::parse('2027-02-28T19:00:00Z'), 'published_at' => now(), 'audience_type' => 'all']); // 1 Mar 00:30 IST

        $titles = fn (array $items) => collect($items)->pluck('title')->sort()->values()->all();

        // ---- admin: everything in the IST month, drafts flagged
        Sanctum::actingAs($this->admin);
        $feb = $this->getJson('/api/admin/calendar?month=2027-02')->assertOk()->json('items');
        $this->assertSame($titles([
            ['title' => 'Apply by: Boundary'], ['title' => 'Apply by: NotMine'], ['title' => 'Apply by: NotApplied'],
            ['title' => 'Aptitude Test — Boundary'], ['title' => 'Aptitude Test — NotApplied'], ['title' => 'Aptitude Test — NotMine'],
            ['title' => 'Feb talk'], ['title' => 'Feb draft'], ['title' => 'Mining only'],
        ]), $titles($feb));
        $this->assertTrue(collect($feb)->firstWhere('title', 'Feb draft')['draft']);
        $jan = $this->getJson('/api/admin/calendar?month=2027-01')->assertOk()->json('items');
        $this->assertNotContains('Apply by: Boundary', $titles($jan), '01:30 IST on 1 Feb must not be bucketed into January');
        $this->assertContains('March midnight', $titles($this->getJson('/api/admin/calendar?month=2027-03')->json('items')));
        $this->getJson('/api/admin/calendar?month=2027-13')->assertStatus(422);

        // ---- student: own cycles' deadlines, own applications' rounds, own-audience published events
        Sanctum::actingAs($student->user);
        $feb = $this->getJson('/api/student/calendar?month=2027-02')->assertOk()->json('items');
        $this->assertSame($titles([
            ['title' => 'Apply by: Boundary'], ['title' => 'Apply by: NotApplied'],
            ['title' => 'Aptitude Test — Boundary'], ['title' => 'Feb talk'],
        ]), $titles($feb));
        foreach ($feb as $item) {
            $this->assertFalse($item['draft']);
            $this->assertStringStartsWith('/student/', $item['link']);
        }
        $boundaryItem = collect($feb)->firstWhere('title', 'Apply by: Boundary');
        $this->assertTrue(Carbon::parse($boundaryItem['at'])->equalTo(Carbon::parse('2027-01-31T20:00:00Z')));
        $this->assertSame([], $this->getJson('/api/student/calendar?month=2027-01')->json('items'), 'January is empty for the student');
        $this->assertSame(['March midnight'], $titles($this->getJson('/api/student/calendar?month=2027-03')->json('items')), 'month navigation + IST bucketing at month end');

        Sanctum::actingAs($outsider->user);
        $this->assertSame(['Feb talk'], $titles($this->getJson('/api/student/calendar?month=2027-02')->json('items')), 'student in no cycle sees only all-student events');
    }

    /**
     * Extra (incidental) probe for T9.3c: the same instant sent with an explicit IST offset must land on the same
     * calendar slot. Laravel stores a Carbon's wall-clock time without converting it to UTC (app timezone).
     */
    public function test_T9_3cx_deadline_sent_with_ist_offset_is_stored_as_the_same_instant(): void
    {
        $cycle = $this->cycle();
        $posting = $this->posting($cycle, 'Offset', '2027-02-01T01:30:00+05:30');

        $this->assertTrue(
            $posting->application_deadline->equalTo(Carbon::parse('2027-01-31T20:00:00Z')),
            'deadline sent as 2027-02-01T01:30:00+05:30 was stored as '.$posting->application_deadline->toIso8601String().' (expected 2027-01-31T20:00:00+00:00 — 5h30m later than the admin chose)'
        );
    }

    // ------------------------------------------------------------------ T9.4

    public function test_T9_4_season_report_is_not_built(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())->map->uri()->filter(fn ($u) => str_contains(strtolower($u), 'season') || str_contains(strtolower($u), 'report'));
        $this->assertCount(0, $routes, 'season report is out of scope (spec Q9.4): '.$routes->implode(', '));

        $hits = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path())) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && preg_match('/season[_ -]?report|SeasonReport/i', file_get_contents($file->getPathname()))) {
                $hits[] = $file->getPathname();
            }
        }
        $this->assertSame([], $hits);
    }
}
