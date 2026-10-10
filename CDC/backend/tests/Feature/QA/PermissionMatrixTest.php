<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\BranchChangeRequest;
use App\Models\CampusEvent;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\PolicyDocument;
use App\Models\PortalNotification;
use App\Models\ProgrammeBranch;
use App\Models\Resume;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use Throwable;

/**
 * QA audit: every `api/*` route × every actor, with real Sanctum bearer tokens.
 *
 * Each request runs inside a savepoint that is rolled back afterwards, so destructive routes (DELETE, publish,
 * suspend, logout, ...) never change the fixture seen by the next cell. The rate-limiter cache is flushed before
 * every matrix call so the 60/min `api` bucket does not turn later cells into 429s.
 *
 * The full matrix is written to qa/evidence/permission_matrix.md BEFORE the final assertion, and the test fails
 * when any cell is unexpected (that is the point — violations are findings, not test bugs).
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ACTORS = ['guest', 'STU', 'STU_SUSP', 'CO_A', 'CO_B', 'ADMIN'];

    private const ACTOR_ROLE = [
        'guest' => null,
        'STU' => 'student',
        'STU_SUSP' => 'student',
        'CO_A' => 'company',
        'CO_B' => 'company',
        'ADMIN' => 'admin',
        'SUPER' => 'admin',
    ];

    private const BTECH = StudentProfileFactory::BTECH;

    /** Strings that only STU_OTHER's private data contains; they must never reach STU_ELIGIBLE. */
    private const STU_OTHER_MARKERS = ['22JE9002', 'Other Student Marker', 'OTHER-RESUME-MARKER', 'OTHER-BCR-MARKER', 'OTHER-NOTIF-MARKER'];

    /** Strings that only COMPANY_A's data contains; they must never reach COMPANY_B. */
    private const COMPANY_A_MARKERS = ['QA Alpha Corp', 'hr@alpha.test', 'ALPHA-SDE-MARKER', 'ALPHA-INTERN-MARKER', 'ALPHA-DRAFT-MARKER', 'ALPHA-EVENT-MARKER', 'ALPHA-NOTIF-MARKER'];

    /** @var array<string, User> */
    private array $users = [];

    /** @var array<string, string|null> */
    private array $tokens = [];

    /** @var array<string, mixed> */
    private array $ids = [];

    /** @var array<string, string> Private files the fixture relies on; restored before every isolated call. */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the audit free of side effects outside the test sandbox: no log file writes, no real mail/queue.
        config(['logging.default' => 'null']);
        Mail::fake();
        Queue::fake();
        Notification::fake();
        Storage::fake('local');
        Storage::fake('public');
    }

    // ---------------------------------------------------------------------------------------------------------
    // Test 1: route × actor permission matrix
    // ---------------------------------------------------------------------------------------------------------

    /** QA F-040 (= SEC-022) fixed in P-1.12: no policy-document show route; cross-tenant JNF/INF update answers 404 before validating. */
    public function test_every_api_route_enforces_authentication_role_and_tenant_boundaries(): void
    {
        $this->buildFixture();

        $rows = [];
        $violations = [];
        $skipped = [];
        $leakScans = [];
        $cellCount = 0;
        $fiveXx = [];

        foreach ($this->apiRoutes() as [$route, $method]) {
            $meta = $this->routeMeta($route);
            $row = ['method' => $method, 'uri' => '/'.$route->uri(), 'class' => $meta['class'], 'cells' => [], 'action' => $meta['action']];

            foreach (self::ACTORS as $actor) {
                [$url, $why] = $this->buildUrl($route, $meta, $actor);
                if ($url === null) {
                    $row['cells'][$actor] = ['skip' => true, 'reason' => $why];
                    $skipped[] = ['method' => $method, 'uri' => $row['uri'], 'actor' => $actor, 'reason' => $why];

                    continue;
                }

                $expect = $this->expectation($meta, $actor);
                $res = $this->hit($method, $url, $this->tokens[$actor]);
                $leak = $this->leakFor($actor, $res);
                [$ok, $severity, $reason] = $this->judge($expect, $res, $leak);
                $cellCount++;

                if ($res['status'] >= 500 || $res['error'] !== null && $res['status'] < 300) {
                    $fiveXx[] = ['method' => $method, 'uri' => $row['uri'], 'actor' => $actor, 'status' => $res['status'], 'error' => $res['error']];
                }

                if (in_array($actor, ['STU', 'CO_B'], true) && $res['status'] >= 200 && $res['status'] < 300 && $method === 'GET') {
                    $leakScans[] = ['actor' => $actor, 'method' => $method, 'uri' => $row['uri'], 'status' => $res['status'], 'leak' => $leak];
                }

                $row['cells'][$actor] = ['skip' => false, 'status' => $res['status'], 'ok' => $ok, 'severity' => $severity];

                if (! $ok) {
                    $violations[] = [
                        'kind' => 'matrix',
                        'method' => $method,
                        'uri' => $row['uri'],
                        'url' => $url,
                        'actor' => $actor,
                        'status' => $res['status'],
                        'expected' => $expect['label'],
                        'severity' => $severity,
                        'reason' => $reason,
                        'body' => $this->excerpt($res['body']),
                        'error' => $res['error'],
                    ];
                }
            }

            $rows[] = $row;
        }

        // Extra rows: other actors' IDs, mismatched parent/child IDs, valid-body follow-ups, signed-link tampering.
        $probes = [];
        foreach ($this->crossTenantProbes() as $probe) {
            $res = $probe['setup'] === null
                ? $this->hit($probe['method'], $probe['url'], $this->tokens[$probe['actor']], $probe['body'])
                : $this->chained($probe);
            $leak = $this->leakFor($probe['actor'], $res);
            [$ok, $severity, $reason] = $this->judge($probe['expect'], $res, $leak);
            $cellCount++;
            $probes[] = $probe + ['status' => $res['status'], 'ok' => $ok, 'severity' => $severity, 'reason' => $reason, 'resp' => $this->excerpt($res['body'])];

            if ($res['status'] >= 500) {
                $fiveXx[] = ['method' => $probe['method'], 'uri' => $probe['display'], 'actor' => $probe['actor'], 'status' => $res['status'], 'error' => $res['error']];
            }

            if (! $ok) {
                $violations[] = [
                    'kind' => 'probe',
                    'method' => $probe['method'],
                    'uri' => $probe['display'],
                    'url' => $probe['url'],
                    'actor' => $probe['actor'],
                    'status' => $res['status'],
                    'expected' => $probe['expect']['label'],
                    'severity' => $severity,
                    'reason' => $reason.' — '.$probe['what'],
                    'body' => $this->excerpt($res['body']),
                    'error' => $res['error'],
                ];
            }
        }

        $path = $this->writeMatrixReport($rows, $violations, $skipped, $probes, $leakScans, $cellCount, $fiveXx);

        $this->assertGreaterThan(100, count($rows), 'Route enumeration looks wrong: too few api routes found.');
        $this->assertSame(
            [],
            array_map(fn (array $v) => sprintf('[%s] %s %s as %s -> %d (expected %s) %s', $v['severity'], $v['method'], $v['uri'], $v['actor'], $v['status'], $v['expected'], $v['reason']), $violations),
            sprintf('%d permission-matrix violation(s). Full report: %s', count($violations), $path)
        );
    }

    // ---------------------------------------------------------------------------------------------------------
    // Test 2: rate limiting
    // ---------------------------------------------------------------------------------------------------------

    public function test_rate_limiters_throttle_authenticated_students_logins_and_signed_links(): void
    {
        $stu = $this->student('22JE9101', 'Rate Limit Student');
        $stu2 = $this->student('22JE9102', 'Second Student');
        $t1 = $stu->user->createToken('qa')->plainTextToken;
        $t2 = $stu2->user->createToken('qa')->plainTextToken;
        $resume = $this->resume($stu, 'Rate CV');
        $this->restoreFiles();
        Cache::flush();

        // 1) 61 rapid GETs to a cheap authenticated student route as one user.
        $studentStatuses = [];
        $first429Headers = null;
        for ($i = 1; $i <= 61; $i++) {
            $r = $this->hit('GET', '/api/student/profile', $t1, [], false);
            $studentStatuses[] = $r['status'];
            if ($r['status'] === 429 && $first429Headers === null) {
                $first429Headers = $this->rateHeaders($r);
            }
        }
        $firstStudent429 = array_search(429, $studentStatuses, true);
        $okBefore = count(array_filter(array_slice($studentStatuses, 0, $firstStudent429 === false ? 61 : $firstStudent429), fn ($s) => $s === 200));

        // Same IP, different user: is the bucket per user (expected) or per IP?
        $otherUser = $this->hit('GET', '/api/student/profile', $t2, [], false);

        // 2) Login brute force against one account from one IP: the per-account backoff answers 429 after 5 failures.
        Cache::flush();
        $loginStatuses = [];
        for ($i = 1; $i <= 61; $i++) {
            $loginStatuses[] = $this->hit('POST', '/api/auth/login', null, ['roll_no' => '22JE9101', 'password' => 'wrong-'.$i], false)['status'];
        }
        $firstLogin429 = array_search(429, $loginStatuses, true);
        $correctAfterThrottle = $this->hit('POST', '/api/auth/login', null, ['roll_no' => '22JE9101', 'password' => 'password'], false)['status'];

        // A spoofed X-Forwarded-For must not open a fresh bucket (no trusted proxies are configured).
        $xffLogin = $this->hit('POST', '/api/auth/login', null, ['roll_no' => '22JE9101', 'password' => 'wrong-xff'], false, ['X-Forwarded-For' => '203.0.113.9'])['status'];

        // Same account, different source IP: is the login budget per account or only per IP?
        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40']);
        $otherIpLogin = $this->hit('POST', '/api/auth/login', null, ['roll_no' => '22JE9101', 'password' => 'wrong-from-other-ip'], false)['status'];
        $this->withServerVariables([]);

        // 3) signed-files limiter: 600/min per IP on the login-free resume link.
        Cache::flush();
        $signedUrl = $resume->signedUrl();
        $signedStatuses = [];
        for ($i = 1; $i <= 601; $i++) {
            $signedStatuses[] = $this->hit('GET', $signedUrl, null, [], false)['status'];
        }
        $firstSigned429 = array_search(429, $signedStatuses, true);

        $report = $this->writeRateLimitReport([
            'student' => ['statuses' => $studentStatuses, 'first429' => $firstStudent429, 'okBefore' => $okBefore, 'headers' => $first429Headers, 'otherUser' => $otherUser['status']],
            'login' => ['statuses' => $loginStatuses, 'first429' => $firstLogin429, 'correctAfter' => $correctAfterThrottle, 'otherIp' => $otherIpLogin, 'xff' => $xffLogin],
            'signed' => ['statuses' => $signedStatuses, 'first429' => $firstSigned429],
        ]);

        $this->assertContains(429, $studentStatuses, "61 rapid authenticated GETs never returned 429. See {$report}");
        $this->assertSame(60, $firstStudent429, "Expected the 61st request to be the first 429. See {$report}");
        $this->assertSame(200, $otherUser['status'], "A different user on the same IP was throttled: api limiter is not per-user. See {$report}");
        $this->assertContains(429, $loginStatuses, "61 failed logins from one IP were never throttled. See {$report}");
        $this->assertContains(429, $signedStatuses, "601 signed-link GETs from one IP were never throttled. See {$report}");
    }

    // ---------------------------------------------------------------------------------------------------------
    // Fixture
    // ---------------------------------------------------------------------------------------------------------

    private function buildFixture(): void
    {
        // --- Users ---------------------------------------------------------------------------------------------
        $this->users['ADMIN'] = User::factory()->create(['role' => 'admin', 'name' => 'QA Admin A', 'email' => 'qa-admin-a@cdc.test', 'is_super_admin' => false]);
        $this->users['ADMIN_B'] = User::factory()->create(['role' => 'admin', 'name' => 'QA Admin B', 'email' => 'qa-admin-b@cdc.test', 'is_super_admin' => false]);
        $this->users['SUPER'] = User::factory()->create(['role' => 'admin', 'name' => 'QA Super', 'email' => 'qa-super@cdc.test', 'is_super_admin' => true]);

        $alpha = Company::create(['name' => 'QA Alpha Corp', 'hr_name' => 'Alpha HR', 'hr_email' => 'hr@alpha.test']);
        $beta = Company::create(['name' => 'QA Beta Ltd', 'hr_name' => 'Beta HR', 'hr_email' => 'hr@beta.test']);
        $this->users['CO_A'] = User::factory()->create(['role' => 'company', 'company_id' => $alpha->id, 'name' => 'Alpha HR', 'email' => 'hr@alpha.test']);
        $this->users['CO_B'] = User::factory()->create(['role' => 'company', 'company_id' => $beta->id, 'name' => 'Beta HR', 'email' => 'hr@beta.test']);
        $this->ids['company_a'] = $alpha->id;
        $this->ids['company_b'] = $beta->id;

        $cycleBase = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]];
        $ft = PlacementCycle::create(['name' => 'QA FT 2026-27', 'type' => 'fulltime'] + $cycleBase);
        $intern = PlacementCycle::create(['name' => 'QA Intern 2026-27', 'type' => 'internship'] + $cycleBase);
        $this->ids['cycle_ft'] = $ft->id;
        $this->ids['cycle_intern'] = $intern->id;

        $stuE = $this->student('22JE9001', 'Eligible Student');
        $stuO = $this->student('22JE9002', 'Other Student Marker');
        $stuS = $this->student('22JE9003', 'Suspended Student');
        $stuS->user->update(['is_active' => false]);
        $this->users['STU'] = $stuE->user;
        $this->users['STU_OTHER'] = $stuO->user;
        $this->users['STU_SUSP'] = $stuS->user;
        $this->ids['profile_e'] = $stuE->id;
        $this->ids['profile_o'] = $stuO->id;
        $this->ids['profile_s'] = $stuS->id;

        foreach ([[$ft, $stuE], [$ft, $stuO], [$intern, $stuO], [$ft, $stuS]] as [$cycle, $s]) {
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
        }

        $resumeE = $this->resume($stuE, 'Eligible CV');
        $resumeO = $this->resume($stuO, 'OTHER-RESUME-MARKER');
        $this->resume($stuS, 'Suspended CV');
        $this->ids['resume_e'] = $resumeE->id;
        $this->ids['resume_o'] = $resumeO->id;

        $photo = 'student-photos/22JE9001/qa.jpg';
        $this->files[$photo] = "\xFF\xD8\xFF\xE0qa-photo";
        $stuE->update(['photo_path' => $photo]);
        $this->restoreFiles();

        // --- Tokens (created before any savepoint, so they survive every rollback) --------------------------------
        $this->tokens['guest'] = null;
        foreach (['STU', 'STU_SUSP', 'CO_A', 'CO_B', 'ADMIN', 'SUPER'] as $actor) {
            $this->tokens[$actor] = $this->users[$actor]->createToken('qa')->plainTextToken;
        }
        // Probe-only tokens: one past Sanctum's 7-day expiration, one revoked (row deleted).
        $expired = $stuE->user->createToken('qa-expired');
        $expired->accessToken->forceFill(['created_at' => now()->subDays(8)])->save();
        $this->tokens['STU_EXPIRED'] = $expired->plainTextToken;
        $revoked = $stuE->user->createToken('qa-revoked');
        $revoked->accessToken->delete();
        $this->tokens['STU_REVOKED'] = $revoked->plainTextToken;

        // --- Forms -----------------------------------------------------------------------------------------------
        $jnfA = Jnf::create(['company_id' => $alpha->id, 'job_title' => 'ALPHA-SDE-MARKER', 'job_description' => 'QA job', 'status' => 'accepted', 'vacancies' => 3, 'form_data' => $this->formData('ALPHA-SDE-MARKER')]);
        $jnfADraft = Jnf::create(['company_id' => $alpha->id, 'job_title' => 'ALPHA-DRAFT-MARKER', 'job_description' => 'QA draft', 'status' => 'draft', 'form_data' => $this->formData('ALPHA-DRAFT-MARKER')]);
        $infA = Inf::create(['company_id' => $alpha->id, 'internship_title' => 'ALPHA-INTERN-MARKER', 'internship_description' => 'QA intern draft', 'status' => 'draft', 'form_data' => $this->formData('ALPHA-INTERN-MARKER')]);
        $infB = Inf::create(['company_id' => $beta->id, 'internship_title' => 'BETA-INTERN', 'internship_description' => 'QA intern', 'status' => 'accepted', 'vacancies' => 2, 'form_data' => $this->formData('BETA-INTERN')]);
        $this->ids['jnf_a'] = $jnfA->id;
        $this->ids['jnf_a_draft'] = $jnfADraft->id;
        $this->ids['inf_a'] = $infA->id;
        $this->ids['inf_b'] = $infB->id;

        // --- Float through the real admin API --------------------------------------------------------------------
        $this->fixtureCall('POST', '/api/admin/postings', 'ADMIN', [
            'form_type' => 'jnf', 'form_id' => $jnfA->id, 'placement_cycle_id' => $ft->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
            'questions' => [['question' => 'Why Alpha?', 'qtype' => 'text', 'required' => false]],
        ], 201, 'float COMPANY_A JNF');
        $this->fixtureCall('POST', '/api/admin/postings', 'ADMIN', [
            'form_type' => 'inf', 'form_id' => $infB->id, 'placement_cycle_id' => $intern->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ], 201, 'float COMPANY_B INF');

        $postingA = JobPosting::where('postable_type', Jnf::class)->where('postable_id', $jnfA->id)->sole();
        $postingB = JobPosting::where('postable_type', Inf::class)->where('postable_id', $infB->id)->sole();
        $this->ids['posting_a'] = $postingA->id;
        $this->ids['posting_b'] = $postingB->id;
        $roundsA = $postingA->rounds()->get();
        $this->assertGreaterThanOrEqual(2, $roundsA->count(), 'fixture: posting A should have at least two rounds');
        $this->ids['round_a1'] = $roundsA[0]->id;
        $this->ids['round_a2'] = $roundsA[1]->id;
        $this->ids['round_b1'] = $postingB->rounds()->first()->id;
        $this->ids['question_a'] = $postingA->questions()->first()?->id;

        // --- Applications ----------------------------------------------------------------------------------------
        $appE = Application::create(['job_posting_id' => $postingA->id, 'student_profile_id' => $stuE->id, 'resume_id' => $resumeE->id, 'status' => 'applied', 'used_unverified_resume' => false, 'applied_at' => now()]);
        $appOA = Application::create(['job_posting_id' => $postingA->id, 'student_profile_id' => $stuO->id, 'resume_id' => $resumeO->id, 'status' => 'applied', 'used_unverified_resume' => false, 'applied_at' => now()]);
        $appOB = Application::create(['job_posting_id' => $postingB->id, 'student_profile_id' => $stuO->id, 'resume_id' => $resumeO->id, 'status' => 'applied', 'used_unverified_resume' => false, 'applied_at' => now()]);
        $this->ids['app_e'] = $appE->id;
        $this->ids['app_o_a'] = $appOA->id;
        $this->ids['app_o_b'] = $appOB->id;

        // --- Pipeline: close, publish round 1, company proposes for round 2 ---------------------------------------
        $this->fixtureCall('PATCH', "/api/admin/postings/{$postingA->id}/close", 'ADMIN', [], 200, 'close posting A');
        $this->fixtureCall('POST', "/api/admin/postings/{$postingA->id}/rounds/{$this->ids['round_a1']}/results", 'ADMIN', ['entries' => [
            ['roll_no' => '22JE9001', 'result' => 'selected'],
            ['roll_no' => '22JE9002', 'result' => 'waitlisted'],
        ]], 200, 'round 1 results');
        $this->fixtureCall('POST', "/api/admin/postings/{$postingA->id}/rounds/{$this->ids['round_a1']}/publish", 'ADMIN', [], 200, 'publish round 1');
        $this->fixtureCall('POST', "/api/company/postings/{$postingA->id}/rounds/{$this->ids['round_a2']}/proposals", 'CO_A', [
            'kind' => 'shortlist', 'entries' => [['roll_no' => '22JE9001']],
        ], 201, 'COMPANY_A shortlist proposal');
        $this->ids['proposal_a'] = ShortlistProposal::where('job_posting_id', $postingA->id)->sole()->id;

        // --- Events ----------------------------------------------------------------------------------------------
        $this->fixtureCall('POST', '/api/admin/events', 'ADMIN', [
            'title' => 'QA Draft Workshop', 'event_type' => 'workshop', 'starts_at' => now()->addDays(3)->toIso8601String(), 'audience_type' => 'all',
        ], 201, 'draft campus event');
        $this->ids['event_draft'] = CampusEvent::where('title', 'QA Draft Workshop')->sole()->id;
        $this->ids['event_company_a'] = CampusEvent::create([
            'title' => 'ALPHA-EVENT-MARKER', 'event_type' => 'ppt', 'company_id' => $alpha->id,
            'starts_at' => now()->addDays(4), 'published_at' => now(), 'created_by' => $this->users['ADMIN']->id,
        ])->id;

        // --- Offer + block (STU_OTHER), branch-change request (STU_OTHER) ------------------------------------------
        $offer = Offer::create([
            'application_id' => $appOA->id, 'student_profile_id' => $stuO->id, 'company_id' => $alpha->id, 'job_posting_id' => $postingA->id,
            'placement_cycle_id' => $ft->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'currency' => 'INR',
            'announced_by' => $this->users['ADMIN']->id, 'announced_at' => now(),
        ]);
        $this->ids['offer'] = $offer->id;
        $this->ids['block'] = PlacementBlock::create([
            'student_profile_id' => $stuO->id, 'placement_cycle_id' => $ft->id, 'scope' => 'all', 'reason' => 'offer',
            'offer_id' => $offer->id, 'remark' => 'QA block', 'active' => true, 'blocked_by' => $this->users['ADMIN']->id,
        ])->id;
        $this->ids['bcr_o'] = BranchChangeRequest::create([
            'student_profile_id' => $stuO->id, 'current_branch' => 'Computer Science & Engineering', 'requested_branch' => 'Mining Engineering',
            'reason' => 'OTHER-BCR-MARKER wants to move to mining', 'status' => 'pending',
        ])->id;

        // --- Notifications (one per actor, plus STU_OTHER's), audit log, policy doc, custom branch ------------------
        $notifTitles = ['STU' => 'Eligible notice', 'STU_SUSP' => 'Suspended notice', 'CO_A' => 'ALPHA-NOTIF-MARKER', 'CO_B' => 'Beta notice', 'ADMIN' => 'Admin notice', 'STU_OTHER' => 'OTHER-NOTIF-MARKER'];
        foreach ($notifTitles as $key => $title) {
            $this->ids['notif'][$key] = PortalNotification::create(['user_id' => $this->users[$key]->id, 'title' => $title, 'message' => 'QA', 'type' => 'info'])->id;
        }
        $this->ids['notif']['guest'] = $this->ids['notif']['STU'];

        $this->ids['audit'] = AuditLog::create([
            'user_id' => $this->users['ADMIN']->id, 'actor_name' => 'QA Admin A', 'actor_email' => 'qa-admin-a@cdc.test',
            'action' => 'qa.fixture', 'subject_type' => StudentProfile::class, 'subject_id' => $stuE->id,
            'before' => null, 'after' => ['ok' => true], 'ip' => '127.0.0.1',
        ])->id;
        $this->ids['policy'] = PolicyDocument::create(['title' => 'QA Policy', 'type' => 'link', 'url' => 'https://example.test/policy', 'is_visible_jnf' => true, 'is_visible_inf' => true])->id;
        $this->ids['programme_branch'] = ProgrammeBranch::create(['programme_name' => self::BTECH, 'branch_name' => 'QA Custom Branch', 'is_custom' => true, 'is_active' => true, 'created_by' => $this->users['ADMIN']->id])->id;
        $this->ids['admin_b'] = $this->users['ADMIN_B']->id;
        $this->ids['super'] = $this->users['SUPER']->id;
    }

    private function student(string $roll, string $name): StudentProfile
    {
        $email = strtolower($roll).'@iitism.ac.in';

        return StudentProfile::factory()->create([
            'user_id' => User::factory()->state(['role' => 'student', 'email' => $email, 'name' => $name]),
            'roll_no' => $roll,
            'full_name' => $name,
            'institute_email' => $email,
            'phone' => '9'.str_pad(preg_replace('/\D/', '', $roll), 9, '0', STR_PAD_LEFT),
        ]);
    }

    private function resume(StudentProfile $student, string $label): Resume
    {
        $path = "resumes/{$student->roll_no}/1_qa.pdf";
        $this->files[$path] = '%PDF-1.4 qa resume';

        return $student->resumes()->create(['slot' => 1, 'label' => $label, 'file_path' => $path, 'file_size' => 18, 'status' => 'approved']);
    }

    private function formData(string $title): array
    {
        return [
            'jobTitle' => $title,
            'internshipTitle' => $title,
            'currency' => 'INR',
            'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'programmeSalaries' => [['programme' => self::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true]],
            'programmeStipends' => [['programme' => self::BTECH, 'total' => '80000', 'enabled' => true]],
            'selectionRounds' => [
                ['type' => 'aptitude_test', 'enabled' => true],
                ['type' => 'technical_interview', 'enabled' => true],
                ['type' => 'hr_interview', 'enabled' => true],
            ],
        ];
    }

    private function restoreFiles(): void
    {
        foreach ($this->files as $path => $content) {
            if (! Storage::disk('local')->exists($path)) {
                Storage::disk('local')->put($path, $content);
            }
        }
    }

    private function fixtureCall(string $method, string $uri, string $actor, array $body, int $expected, string $what): void
    {
        $res = $this->hit($method, $uri, $this->tokens[$actor], $body, false);
        $this->assertSame($expected, $res['status'], "fixture step '{$what}' failed: {$method} {$uri} -> {$res['status']} ".$this->excerpt($res['body'])." {$res['error']}");
    }

    // ---------------------------------------------------------------------------------------------------------
    // Routes, parameters, expectations
    // ---------------------------------------------------------------------------------------------------------

    /** @return list<array{0: Route, 1: string}> */
    private function apiRoutes(): array
    {
        $out = [];
        foreach (app('router')->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if ($method !== 'HEAD') {
                    $out[] = [$route, $method];
                }
            }
        }
        usort($out, fn ($a, $b) => [$a[0]->uri(), $a[1]] <=> [$b[0]->uri(), $b[1]]);

        return $out;
    }

    private function routeMeta(Route $route): array
    {
        $auth = false;
        $signed = false;
        $roles = [];
        foreach ($route->gatherMiddleware() as $mw) {
            if (! is_string($mw)) {
                continue;
            }
            if ($mw === 'auth' || str_starts_with($mw, 'auth:') || str_contains($mw, 'Authenticate')) {
                $auth = true;
            }
            if ($mw === 'signed' || str_starts_with($mw, 'signed:') || str_contains($mw, 'ValidateSignature')) {
                $signed = true;
            }
            if (str_starts_with($mw, 'role:')) {
                $roles = array_merge($roles, explode(',', substr($mw, 5)));
            }
        }

        preg_match_all('/\{(\w+)\??\}/', $route->uri(), $m);
        $action = $route->getActionName();
        $superOnly = str_contains($action, 'AdminManagementController');

        $class = match (true) {
            ! $auth => $signed ? 'public(signed)' : 'public',
            $roles === [] => 'any-auth',
            $superOnly => 'admin(super-only)',
            default => implode('/', $roles).($roles === ['company'] && $m[1] !== [] ? '(tenant)' : ''),
        };

        return ['auth' => $auth, 'signed' => $signed, 'roles' => $roles, 'params' => $m[1], 'superOnly' => $superOnly, 'class' => $class, 'action' => $action];
    }

    /**
     * Pick a real row id for each route parameter. Company routes get COMPANY_A's rows (so COMPANY_B is the
     * cross-tenant probe); student routes get STU_ELIGIBLE's rows; `{notification}` is the calling actor's own.
     */
    private function resolveParam(string $name, string $actor): ?int
    {
        return match ($name) {
            'jnf' => $this->ids['jnf_a'],
            'inf' => $this->ids['inf_a'],
            'jobPosting', 'posting' => $this->ids['posting_a'],
            'postingRound', 'round' => $this->ids['round_a1'],
            'application' => $this->ids['app_e'],
            'resume' => $this->ids['resume_e'],
            'studentProfile', 'student' => $this->ids['profile_e'],
            'placementCycle', 'cycle' => $this->ids['cycle_ft'],
            'shortlistProposal', 'proposal' => $this->ids['proposal_a'],
            'campusEvent', 'event' => $this->ids['event_draft'],
            'placementBlock', 'block' => $this->ids['block'],
            'branchChangeRequest' => $this->ids['bcr_o'],
            'notification' => $this->ids['notif'][$actor] ?? $this->ids['notif']['STU'],
            'user' => $this->ids['admin_b'],
            'company' => $this->ids['company_a'],
            'policy_document', 'policyDocument' => $this->ids['policy'],
            'programmeBranch' => $this->ids['programme_branch'],
            'offer' => $this->ids['offer'],
            'auditLog' => $this->ids['audit'],
            default => null,
        };
    }

    /** @return array{0: string|null, 1: string|null} [url, skip reason] */
    private function buildUrl(Route $route, array $meta, string $actor): array
    {
        $params = [];
        foreach ($meta['params'] as $p) {
            $id = $this->resolveParam($p, $actor);
            if ($id === null) {
                return [null, "no fixture row for route parameter {{$p}}"];
            }
            $params[$p] = $id;
        }

        if ($meta['signed']) {
            if (! $route->getName()) {
                return [null, 'signed route has no name, cannot generate a signature'];
            }

            return [URL::temporarySignedRoute($route->getName(), now()->addHour(), $params), null];
        }

        $uri = '/'.$route->uri();
        foreach ($params as $p => $id) {
            $uri = preg_replace('/\{'.preg_quote($p, '/').'\??\}/', (string) $id, $uri);
        }

        return [$uri, null];
    }

    /** @return array{kind: string, codes: list<int>, label: string} */
    private function expectation(array $meta, string $actor): array
    {
        if (! $meta['auth']) {
            return ['kind' => 'any', 'codes' => [], 'label' => 'public: any non-5xx'];
        }
        if ($actor === 'guest') {
            return ['kind' => 'deny', 'codes' => [401], 'label' => '401'];
        }
        if ($actor === 'STU_SUSP') {
            return ['kind' => 'deny', 'codes' => [403], 'label' => '403 (suspended)'];
        }

        $role = self::ACTOR_ROLE[$actor];
        if ($meta['roles'] !== [] && ! in_array($role, $meta['roles'], true)) {
            return ['kind' => 'deny', 'codes' => [403], 'label' => '403 (role mismatch)'];
        }
        if ($meta['superOnly'] && $actor !== 'SUPER') {
            return ['kind' => 'deny', 'codes' => [403], 'label' => '403 (super-admin only)'];
        }
        if ($actor === 'CO_B' && in_array('company', $meta['roles'], true) && $meta['params'] !== []) {
            return ['kind' => 'deny', 'codes' => [404, 403], 'label' => '404/403 (cross-tenant)'];
        }

        return ['kind' => 'allow', 'codes' => [], 'label' => 'allowed (not 401/403/404/5xx)'];
    }

    /** @return array{0: bool, 1: string|null, 2: string} [ok, severity, reason] */
    private function judge(array $expect, array $res, ?string $leak): array
    {
        $status = $res['status'];

        if ($status >= 500) {
            return [false, 'S2', '5xx server error'.($res['error'] ? ': '.$res['error'] : '')];
        }
        if ($res['error'] !== null && $status < 300) {
            return [false, 'S2', 'response body crashed while streaming: '.$res['error']];
        }
        if ($leak !== null) {
            return [false, 'S1', 'cross-tenant data leak: body contains '.$leak];
        }

        return match ($expect['kind']) {
            'any' => [true, null, ''],
            'allow' => match (true) {
                in_array($status, [401, 403, 404], true) => [false, 'S2', 'rightful actor denied'],
                $status === 429 => [false, 'S3', 'unexpectedly rate limited'],
                default => [true, null, ''],
            },
            default => match (true) {
                in_array($status, $expect['codes'], true) => [true, null, ''],
                $status >= 200 && $status < 300 => [false, 'S1', 'wrong actor got 2xx'],
                default => [false, 'S3', 'wrong actor got an unexpected non-2xx status (authorization not the first gate)'],
            },
        };
    }

    private function leakFor(string $actor, array $res): ?string
    {
        if ($res['status'] < 200 || $res['status'] >= 300) {
            return null;
        }
        $markers = match ($actor) {
            'STU' => self::STU_OTHER_MARKERS,
            'CO_B' => self::COMPANY_A_MARKERS,
            default => [],
        };
        foreach ($markers as $marker) {
            if (str_contains($res['body'], $marker)) {
                return '"'.$marker.'"';
            }
        }

        return null;
    }

    /**
     * Extra rows that reuse other actors' IDs (beyond the matrix's COMPANY_B column), with valid bodies so that a
     * missing ownership check would surface as a 2xx rather than hide behind a 422.
     */
    private function crossTenantProbes(): array
    {
        $i = $this->ids;
        $deny = fn (string $label = '404/403') => ['kind' => 'deny', 'codes' => [404, 403], 'label' => $label];
        $allow = ['kind' => 'allow', 'codes' => [], 'label' => 'allowed (not 401/403/404/5xx)'];
        $proposal = ['kind' => 'shortlist', 'entries' => [['roll_no' => '22JE9001']]];

        $probes = [
            ['STU', 'GET', "/api/student/resumes/{$i['resume_o']}/file", [], $deny(), "STU_OTHER's resume file"],
            ['STU', 'PATCH', "/api/student/resumes/{$i['resume_o']}", ['label' => 'hijacked'], $deny(), "rename STU_OTHER's resume (valid body)"],
            ['STU', 'DELETE', "/api/student/resumes/{$i['resume_o']}", [], $deny(), "delete STU_OTHER's resume"],
            ['STU', 'PATCH', "/api/student/applications/{$i['app_o_b']}", ['resume_id' => $i['resume_e']], $deny(), "edit STU_OTHER's live application on an OPEN posting (valid body)"],
            ['STU', 'POST', "/api/student/applications/{$i['app_o_b']}/withdraw", [], $deny(), "withdraw STU_OTHER's live application on an OPEN posting"],
            ['STU', 'PATCH', "/api/student/applications/{$i['app_o_a']}", [], $deny(), "edit STU_OTHER's application on COMPANY_A's (closed) posting"],
            ['STU', 'POST', "/api/student/applications/{$i['app_o_a']}/withdraw", [], $deny(), "withdraw STU_OTHER's application on COMPANY_A's (closed) posting"],
            ['STU', 'PATCH', "/api/auth/notifications/{$i['notif']['STU_OTHER']}/read", [], $deny(), "mark STU_OTHER's notification read"],
            ['STU', 'GET', "/api/student/postings/{$i['posting_b']}", [], $deny(), 'posting in a cycle STU_ELIGIBLE is not enrolled in'],
            ['STU', 'POST', "/api/student/postings/{$i['posting_b']}/apply", ['resume_id' => $i['resume_e']], $deny(), 'apply to a posting in a cycle STU_ELIGIBLE is not enrolled in'],
            ['STU', 'POST', "/api/student/postings/{$i['posting_b']}/apply", ['resume_id' => $i['resume_o']], $deny(), "apply using STU_OTHER's resume id to a non-enrolled cycle"],
            ['CO_B', 'PATCH', "/api/auth/notifications/{$i['notif']['CO_A']}/read", [], $deny(), "mark COMPANY_A's notification read"],
            ['CO_B', 'GET', "/api/company/jnfs/{$i['jnf_a_draft']}", [], $deny(), "view COMPANY_A's DRAFT JNF"],
            ['CO_B', 'PUT', "/api/company/jnfs/{$i['jnf_a_draft']}", ['job_title' => 'pwned', 'job_description' => 'pwned'], $deny(), "overwrite COMPANY_A's DRAFT JNF (valid body)"],
            ['CO_B', 'DELETE', "/api/company/jnfs/{$i['jnf_a_draft']}", [], $deny(), "delete COMPANY_A's DRAFT JNF"],
            ['CO_B', 'POST', "/api/company/jnfs/{$i['jnf_a_draft']}/duplicate", [], $deny(), "duplicate COMPANY_A's DRAFT JNF"],
            ['CO_B', 'POST', '/api/company/jnfs/autosave', ['id' => $i['jnf_a_draft'], 'job_title' => 'pwned', 'job_description' => 'pwned'], $deny(), "autosave over COMPANY_A's DRAFT JNF via body id"],
            ['CO_B', 'PUT', "/api/company/infs/{$i['inf_a']}", ['internship_title' => 'pwned', 'internship_description' => 'pwned'], $deny(), "overwrite COMPANY_A's DRAFT INF (valid body)"],
            ['CO_B', 'POST', '/api/company/infs/autosave', ['id' => $i['inf_a'], 'internship_title' => 'pwned', 'internship_description' => 'pwned'], $deny(), "autosave over COMPANY_A's DRAFT INF via body id"],
            ['CO_B', 'POST', "/api/company/postings/{$i['posting_a']}/rounds/{$i['round_a1']}/proposals", $proposal, $deny(), "valid shortlist proposal on COMPANY_A's closed posting"],
            ['CO_B', 'POST', "/api/company/postings/{$i['posting_b']}/rounds/{$i['round_a1']}/proposals", $proposal, $deny(), "own posting id + COMPANY_A's round id (parent/child mismatch)"],
            ['CO_A', 'GET', "/api/company/postings/{$i['posting_b']}", [], $deny(), "reverse direction: COMPANY_A reads COMPANY_B's posting"],
            ['CO_A', 'GET', "/api/company/postings/{$i['posting_b']}/applicants", [], $deny(), "COMPANY_A reads COMPANY_B's applicants"],
            ['CO_A', 'GET', "/api/company/postings/{$i['posting_b']}/export", [], $deny(), "COMPANY_A exports COMPANY_B's applicants"],
            ['CO_A', 'GET', "/api/company/infs/{$i['inf_b']}", [], $deny(), "COMPANY_A reads COMPANY_B's INF"],
            ['guest', 'GET', "/api/resumes/signed/{$i['resume_e']}", [], ['kind' => 'deny', 'codes' => [403], 'label' => '403 (no signature)'], 'resume link without a signature'],
            ['guest', 'GET', str_replace("signed/{$i['resume_e']}", "signed/{$i['resume_o']}", Resume::findOrFail($i['resume_e'])->signedUrl()), [], ['kind' => 'deny', 'codes' => [403], 'label' => '403 (bad signature)'], "signature of STU_ELIGIBLE's resume replayed on STU_OTHER's id"],
            ['guest', 'POST', '/api/auth/login', ['roll_no' => '22JE9003', 'password' => 'password'], ['kind' => 'deny', 'codes' => [403], 'label' => '403 (suspended)'], 'suspended student logs in with the right password'],
            ['STU_SUSP', 'POST', '/api/auth/logout', [], ['kind' => 'deny', 'codes' => [403], 'label' => '403 (suspended)'], 'suspended student tries to revoke own token (logout)'],
            ['SUPER', 'GET', '/api/admin/manage-admins', [], $allow, 'super admin lists admins'],
            ['SUPER', 'POST', '/api/admin/manage-admins', [], $allow, 'super admin creates admin (empty body)'],
            ['SUPER', 'DELETE', "/api/admin/manage-admins/{$i['admin_b']}", [], $allow, 'super admin deletes a non-super admin'],
            ['ADMIN', 'DELETE', "/api/admin/manage-admins/{$i['super']}", [], ['kind' => 'deny', 'codes' => [403], 'label' => '403 (super-admin only)'], 'non-super admin deletes the super admin'],
            ['STU_EXPIRED', 'GET', '/api/student/profile', [], ['kind' => 'deny', 'codes' => [401], 'label' => '401'], "STU_ELIGIBLE's token created 8 days ago (sanctum.expiration = 7 days)"],
            ['STU_REVOKED', 'GET', '/api/student/profile', [], ['kind' => 'deny', 'codes' => [401], 'label' => '401'], "STU_ELIGIBLE's revoked (deleted) token"],
            ['STU', 'GET', '/api/student/profile', [], ['kind' => 'deny', 'codes' => [401, 403], 'label' => '401/403'], "STU_ELIGIBLE's existing token right after ADMIN suspends the account via the API",
                fn () => $this->assertSame(200, $this->hit('PATCH', "/api/admin/students/{$i['profile_e']}/suspend", $this->tokens['ADMIN'], [], false)['status'], 'probe setup: suspend STU_ELIGIBLE')],
        ];

        return array_map(fn (array $p) => [
            'actor' => $p[0],
            'method' => $p[1],
            'url' => $p[2],
            'display' => preg_replace('/\?.*/', '?<signature>', $p[2]),
            'body' => $p[3],
            'expect' => $p[4],
            'what' => $p[5],
            'setup' => $p[6] ?? null,
        ], $probes);
    }

    // ---------------------------------------------------------------------------------------------------------
    // HTTP
    // ---------------------------------------------------------------------------------------------------------

    /**
     * One request with a real bearer token. With $isolate the request runs inside a savepoint that is rolled back,
     * the rate-limit cache is flushed and private files are restored, so every cell sees the same fixture.
     *
     * @return array{status: int, body: string, error: string|null, headers: \Symfony\Component\HttpFoundation\ResponseHeaderBag}
     */
    private function hit(string $method, string $uri, ?string $token, array $body = [], bool $isolate = true, array $extraHeaders = []): array
    {
        $this->app['auth']->forgetGuards();
        if ($isolate) {
            Cache::flush();
            $this->restoreFiles();
        }

        $level = DB::transactionLevel();
        if ($isolate) {
            DB::beginTransaction();
        }
        $obLevel = ob_get_level();

        try {
            $headers = ['Accept' => 'application/json'] + $extraHeaders;
            if ($token !== null) {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            $response = $this->json($method, $uri, $body, $headers);
            $base = $response->baseResponse;
            $error = null;

            if ($base instanceof StreamedResponse || $base instanceof BinaryFileResponse) {
                try {
                    $content = (string) $response->streamedContent();
                } catch (Throwable $e) {
                    $content = '';
                    $error = get_class($e).': '.$e->getMessage();
                }
            } else {
                $content = (string) $response->getContent();
            }

            $exception = property_exists($base, 'exception') ? $base->exception : null;
            if ($exception instanceof Throwable && $error === null && $response->getStatusCode() >= 500) {
                $error = get_class($exception).': '.$exception->getMessage().' @ '.basename($exception->getFile()).':'.$exception->getLine();
            }

            return ['status' => $response->getStatusCode(), 'body' => $content, 'error' => $error, 'headers' => $response->headers];
        } finally {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            if ($isolate) {
                DB::rollBack($level);
            }
            $this->app['auth']->forgetGuards();
        }
    }

    /** Run a probe's setup step and the probe itself inside ONE savepoint (e.g. suspend, then reuse the old token). */
    private function chained(array $probe): array
    {
        Cache::flush();
        $this->restoreFiles();
        $level = DB::transactionLevel();
        DB::beginTransaction();
        try {
            ($probe['setup'])();
            Cache::flush();

            return $this->hit($probe['method'], $probe['url'], $this->tokens[$probe['actor']], $probe['body'], false);
        } finally {
            DB::rollBack($level);
            $this->app['auth']->forgetGuards();
        }
    }

    private function excerpt(string $body): string
    {
        $flat = preg_replace('/\s+/', ' ', $body) ?? '';
        if ($flat !== '' && ! mb_check_encoding($flat, 'UTF-8')) {
            return '[binary '.strlen($body).' bytes]';
        }

        return mb_substr($flat, 0, 200);
    }

    private function rateHeaders(array $res): array
    {
        $out = [];
        foreach (['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After', 'X-RateLimit-Reset'] as $h) {
            $out[$h] = $res['headers']->get($h);
        }

        return $out;
    }

    // ---------------------------------------------------------------------------------------------------------
    // Reports
    // ---------------------------------------------------------------------------------------------------------

    private function evidenceDir(): string
    {
        $dir = dirname(__DIR__, 4).'/qa/evidence';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function md(string $s): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], $s);
    }

    private function writeMatrixReport(array $rows, array $violations, array $skipped, array $probes, array $leakScans, int $cellCount, array $fiveXx): string
    {
        $path = $this->evidenceDir().'/permission_matrix.md';
        $matrixCells = array_sum(array_map(fn ($r) => count(array_filter($r['cells'], fn ($c) => ! $c['skip'])), $rows));
        $bySeverity = array_count_values(array_map(fn ($v) => $v['severity'], $violations));
        ksort($bySeverity);

        $l = [];
        $l[] = '# API permission matrix (route × actor)';
        $l[] = '';
        $l[] = 'Generated by `tests/Feature/QA/PermissionMatrixTest.php` on '.now()->toDateTimeString().' (in-memory SQLite, real Sanctum bearer tokens, every request rolled back in a savepoint).';
        $l[] = '';
        $l[] = '## Summary';
        $l[] = '';
        $l[] = sprintf('- Routes × methods enumerated (`api/*`, HEAD skipped): **%d**', count($rows));
        $l[] = sprintf('- Matrix cells executed: **%d** (6 actors), skipped: **%d**', $matrixCells, count($skipped));
        $l[] = sprintf('- Extra cross-tenant / edge probes: **%d**', count($probes));
        $l[] = sprintf('- Total requests judged: **%d**', $cellCount);
        $l[] = sprintf('- Violations: **%d** %s', count($violations), $bySeverity === [] ? '' : '('.implode(', ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($bySeverity), $bySeverity)).')');
        $l[] = sprintf('- 5xx responses: **%d**', count($fiveXx));
        $l[] = '';
        $l[] = '## Actors';
        $l[] = '';
        $l[] = '| Actor | Who |';
        $l[] = '|---|---|';
        $l[] = '| guest | no token |';
        $l[] = '| STU | STU_ELIGIBLE 22JE9001 — active student, enrolled in the open FT cycle, approved resume, `applied` application to COMPANY_A\'s floated JNF posting (selected in published round 1) |';
        $l[] = '| STU_SUSP | 22JE9003 — student with `users.is_active = false` and a valid token |';
        $l[] = '| CO_A | COMPANY_A (QA Alpha Corp) — owns the accepted+floated JNF posting, rounds, a question, published round-1 result, a pending shortlist proposal, a company event, a draft JNF and a draft INF |';
        $l[] = '| CO_B | COMPANY_B (QA Beta Ltd) — owns its own accepted INF floated into the internship cycle; used as the cross-tenant probe on COMPANY_A\'s ids |';
        $l[] = '| ADMIN | ADMIN_A — `role=admin`, **not** super admin |';
        $l[] = '| SUPER | super admin (only used in probe rows for super-only routes) |';
        $l[] = '| STU_EXPIRED / STU_REVOKED | probe-only tokens of STU_ELIGIBLE: one created 8 days ago (past `sanctum.expiration`), one whose row was deleted |';
        $l[] = '';
        $l[] = 'Other fixture rows: STU_OTHER 22JE9002 (own resume, applications on both postings, offer + placement block, pending branch-change request, notification), ADMIN_B (non-super admin, target of `{user}`), draft campus event, policy document, custom programme branch, audit-log row, one notification per actor.';
        $l[] = '';
        $l[] = '### Parameter bindings used in the matrix';
        $l[] = '';
        $l[] = '`{jnf}`=COMPANY_A accepted+floated JNF #'.$this->ids['jnf_a'].', `{inf}`=COMPANY_A draft INF #'.$this->ids['inf_a'].', `{jobPosting}`=COMPANY_A posting #'.$this->ids['posting_a']
            .', `{postingRound}`=its round 1 #'.$this->ids['round_a1'].', `{application}`=STU_ELIGIBLE\'s application #'.$this->ids['app_e'].', `{resume}`=STU_ELIGIBLE\'s resume #'.$this->ids['resume_e']
            .', `{studentProfile}`=STU_ELIGIBLE #'.$this->ids['profile_e'].', `{placementCycle}`=FT cycle #'.$this->ids['cycle_ft'].', `{shortlistProposal}`=#'.$this->ids['proposal_a']
            .', `{campusEvent}`=draft event #'.$this->ids['event_draft'].', `{placementBlock}`=#'.$this->ids['block'].', `{branchChangeRequest}`=STU_OTHER\'s #'.$this->ids['bcr_o']
            .', `{notification}`=the calling actor\'s own notification, `{user}`=ADMIN_B #'.$this->ids['admin_b'].', `{company}`=COMPANY_A #'.$this->ids['company_a']
            .', `{policy_document}`=#'.$this->ids['policy'].', `{programmeBranch}`=custom branch #'.$this->ids['programme_branch'].'. Signed routes get a freshly signed URL.';
        $l[] = '';
        $l[] = '## Expectations';
        $l[] = '';
        $l[] = '- **public** routes (no `auth:sanctum`): any non-5xx for every actor.';
        $l[] = '- `auth:sanctum` routes: guest → 401; STU_SUSP → 403; role mismatch → 403; ADMIN_A on super-only routes → 403; COMPANY_B on COMPANY_A\'s resource ids → 404/403; rightful actor → anything except 401/403/404/5xx (422/409 prove authz passed).';
        $l[] = '- Any 5xx is a finding. A 2xx for a wrong actor, or another tenant\'s marker strings in a 2xx body, is **S1**. Rightful actor denied or 5xx is **S2**. A wrong actor stopped with the wrong non-2xx code (e.g. 422 because validation runs before the ownership check — an id-existence oracle) is **S3**.';
        $l[] = '- Cell legend: plain code = as expected; `**code!**` = violation; `SKIP` = parameter could not be bound.';
        $l[] = '';
        $l[] = '## Matrix';
        $l[] = '';
        $l[] = '| METHOD URI | guest | STU | STU_SUSP | CO_A | CO_B | ADMIN | verdict |';
        $l[] = '|---|---|---|---|---|---|---|---|';
        foreach ($rows as $r) {
            $cells = [];
            $bad = 0;
            $skip = 0;
            foreach (self::ACTORS as $a) {
                $c = $r['cells'][$a];
                if ($c['skip']) {
                    $cells[] = 'SKIP';
                    $skip++;
                } elseif ($c['ok']) {
                    $cells[] = (string) $c['status'];
                } else {
                    $cells[] = '**'.$c['status'].'!**';
                    $bad++;
                }
            }
            $verdict = $bad > 0 ? "VIOLATION ({$bad})" : ($skip > 0 ? 'SKIPPED' : 'OK');
            $l[] = '| `'.$r['method'].' '.$r['uri'].'` | '.implode(' | ', $cells).' | '.$verdict.' ['.$r['class'].'] |';
        }
        $l[] = '';
        $l[] = '## Violations';
        $l[] = '';
        if ($violations === []) {
            $l[] = 'None.';
        } else {
            $l[] = '| # | Sev | Request | Actor | Status | Expected | Why | Body excerpt (200 chars) |';
            $l[] = '|---|---|---|---|---|---|---|---|';
            foreach ($violations as $n => $v) {
                $l[] = sprintf(
                    '| %d | %s | `%s %s` | %s | %d | %s | %s | %s |',
                    $n + 1,
                    $v['severity'],
                    $v['method'],
                    $this->md($v['uri']),
                    $v['actor'],
                    $v['status'],
                    $this->md($v['expected']),
                    $this->md($v['reason']),
                    $this->md(($v['error'] ? '['.$v['error'].'] ' : '').$v['body'])
                );
            }
        }
        $l[] = '';
        $l[] = '## 5xx responses';
        $l[] = '';
        if ($fiveXx === []) {
            $l[] = 'None.';
        } else {
            foreach ($fiveXx as $f) {
                $l[] = sprintf('- `%s %s` as %s → %d %s', $f['method'], $this->md($f['uri']), $f['actor'], $f['status'], $this->md((string) $f['error']));
            }
        }
        $l[] = '';
        $l[] = '## Cross-tenant probes';
        $l[] = '';
        $l[] = '| Actor | Request | Target | Body sent | Expected | Actual | Verdict | Body excerpt |';
        $l[] = '|---|---|---|---|---|---|---|---|';
        foreach ($probes as $p) {
            $l[] = sprintf(
                '| %s | `%s %s` | %s | `%s` | %s | %d | %s | %s |',
                $p['actor'],
                $p['method'],
                $this->md($p['display']),
                $this->md($p['what']),
                $this->md(json_encode($p['body'], JSON_UNESCAPED_SLASHES)),
                $this->md($p['expect']['label']),
                $p['status'],
                $p['ok'] ? 'OK' : '**'.$p['severity'].' VIOLATION**',
                $this->md($p['resp'])
            );
        }
        $l[] = '';
        $l[] = '### Leak scan of 2xx GET bodies';
        $l[] = '';
        $l[] = 'Every 2xx response to STU (STU_ELIGIBLE) is scanned for STU_OTHER\'s markers ('.implode(', ', self::STU_OTHER_MARKERS).'); every 2xx response to CO_B is scanned for COMPANY_A\'s markers ('.implode(', ', self::COMPANY_A_MARKERS).'). GET endpoints scanned:';
        $l[] = '';
        $l[] = '| Actor | Endpoint | Status | Result |';
        $l[] = '|---|---|---|---|';
        foreach ($leakScans as $s) {
            $l[] = sprintf('| %s | `%s %s` | %d | %s |', $s['actor'], $s['method'], $s['uri'], $s['status'], $s['leak'] ? '**LEAK '.$this->md($s['leak']).'**' : 'clean');
        }
        $l[] = '';
        $l[] = '## Skipped cells';
        $l[] = '';
        if ($skipped === []) {
            $l[] = 'None — every route parameter was bound to a real row.';
        } else {
            foreach ($skipped as $s) {
                $l[] = sprintf('- `%s %s` as %s: %s', $s['method'], $s['uri'], $s['actor'], $this->md($s['reason']));
            }
        }
        $l[] = '';

        file_put_contents($path, implode("\n", $l));

        return $path;
    }

    private function writeRateLimitReport(array $r): string
    {
        $path = $this->evidenceDir().'/rate_limits.md';
        $fmt = fn ($i) => $i === false ? 'never' : 'request #'.($i + 1);
        $dist = function (array $statuses): string {
            $c = array_count_values($statuses);
            ksort($c);

            return implode(', ', array_map(fn ($k, $v) => "{$k}×{$v}", array_keys($c), $c));
        };

        $l = [];
        $l[] = '# Rate limiting';
        $l[] = '';
        $l[] = 'Generated by `tests/Feature/QA/PermissionMatrixTest.php::test_rate_limiters_throttle_authenticated_students_logins_and_signed_links` on '.now()->toDateTimeString().'.';
        $l[] = '';
        $l[] = '## Configuration found';
        $l[] = '';
        $l[] = '- `AppServiceProvider`: `RateLimiter::for(\'api\')` → `Limit::perMinute(60)->by($request->user()?->id ?? $request->ip())`.';
        $l[] = '- `AppServiceProvider`: `RateLimiter::for(\'signed-files\')` → `Limit::perMinute(600)->by($request->ip())`.';
        $l[] = '- `routes/api.php`: every `api/*` route except `/api/resumes/signed/{resume}` sits in `throttle:api`; the signed resume route uses `throttle:signed-files` + `signed`.';
        $l[] = '- `POST /api/auth/login` has its own `login-ip` bucket (600/min per client IP; the IP is the signed one forwarded by the Next.js server, SEC-008) and a short per-account backoff in `LoginThrottleService` (5 free failures per account+IP, then 1, 2, 4 … s capped at 60 s; 5+ failing IPs make every IP wait, capped at 30 s). `/forgot-password`, `/reset-password`, company registration and alumni outreach share the `api` bucket. The password broker separately throttles reset-link mails per email (`auth.passwords.users.throttle` = 60 s).';
        $l[] = '- Middleware priority runs `auth:sanctum` before `throttle`, so authenticated routes are keyed by user id; unauthenticated callers on protected routes get 401 before the limiter counts them.';
        $l[] = '';
        $l[] = '## Results';
        $l[] = '';
        $s = $r['student'];
        $l[] = '### 61 rapid `GET /api/student/profile` as one student (bearer token)';
        $l[] = '';
        $l[] = sprintf('- Status distribution: %s', $dist($s['statuses']));
        $l[] = sprintf('- First 429 at %s; %d × 200 before it.', $fmt($s['first429']), $s['okBefore']);
        $l[] = sprintf('- 429 headers: %s', json_encode($s['headers']));
        $l[] = sprintf('- A different student on the same IP immediately afterwards: **%d** (200 ⇒ bucket is per user, not per IP).', $s['otherUser']);
        $l[] = '';
        $g = $r['login'];
        $l[] = '### 61 failed `POST /api/auth/login` (roll number + wrong password) from one IP';
        $l[] = '';
        $l[] = sprintf('- Status distribution: %s', $dist($g['statuses']));
        $l[] = sprintf('- First 429 at %s.', $fmt($g['first429']));
        $l[] = sprintf('- Correct password from the same IP right after: **%d**.', $g['correctAfter']);
        $l[] = sprintf('- Same IP with a spoofed `X-Forwarded-For: 203.0.113.9` right after: **%d** (429 ⇒ the header cannot be used to dodge the limiter; flip side: no TrustProxies config, so behind a reverse proxy every guest would share the proxy\'s single 60/min bucket).', $g['xff']);
        $l[] = sprintf('- Same account, different source IP (10.20.30.40) right after: **%d** (429 ⇒ the per-account budget is spent too; 422 would mean per-IP only).', $g['otherIp']);
        $l[] = '';
        $sg = $r['signed'];
        $l[] = '### 601 `GET /api/resumes/signed/{resume}` (valid signature) from one IP';
        $l[] = '';
        $l[] = sprintf('- Status distribution: %s', $dist($sg['statuses']));
        $l[] = sprintf('- First 429 at %s.', $fmt($sg['first429']));
        $l[] = '';

        file_put_contents($path, implode("\n", $l));

        return $path;
    }
}
