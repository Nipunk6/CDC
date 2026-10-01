<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * QA Section 10 — Company side (spec B7 "Company portal additions", Q10.x, M6.4; D71).
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S10CompanyTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private User $admin;

    private Company $companyA;

    private Company $companyB;

    private User $userA;

    private User $userB;

    private PlacementCycle $ft;

    private PlacementCycle $intern;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->companyA = Company::create(['name' => 'Alpha Systems', 'hr_name' => 'HR', 'hr_email' => 'hr@alpha.qa.test']);
        $this->companyB = Company::create(['name' => 'Beta Labs', 'hr_name' => 'HR', 'hr_email' => 'hr@beta.qa.test']);
        $this->userA = User::factory()->create(['role' => 'company', 'company_id' => $this->companyA->id, 'email' => 'hr@alpha.qa.test']);
        $this->userB = User::factory()->create(['role' => 'company', 'company_id' => $this->companyB->id, 'email' => 'hr@beta.qa.test']);
        $base = ['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]]];
        $this->ft = PlacementCycle::create(['name' => 'FT', 'type' => 'fulltime'] + $base);
        $this->intern = PlacementCycle::create(['name' => 'Intern', 'type' => 'internship'] + $base);
    }

    // ------------------------------------------------------------------ helpers

    private function form(Company $company, string $title, string $status = 'accepted', string $type = 'jnf'): Jnf|Inf
    {
        $data = ['jobTitle' => $title, 'internshipTitle' => $title, 'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]]];

        return $type === 'inf'
            ? Inf::create(['company_id' => $company->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => $status, 'form_data' => $data])
            : Jnf::create(['company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => $status, 'form_data' => $data]);
    }

    private function float(Jnf|Inf $form): JobPosting
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => $form instanceof Inf ? 'inf' : 'jnf', 'form_id' => $form->id,
            'placement_cycle_id' => $form instanceof Inf ? $this->intern->id : $this->ft->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
            'questions' => [['question' => 'Secret question of '.$form->company->name, 'qtype' => 'text']],
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function student(string $name): StudentProfile
    {
        $this->seq++;
        $roll = sprintf('26CO%04d', $this->seq);
        $email = strtolower($roll).'@students.qa.test';
        $student = StudentProfile::factory()->create([
            'roll_no' => $roll, 'institute_email' => $email, 'full_name' => $name, 'phone' => '98000'.sprintf('%05d', $this->seq),
            'user_id' => User::factory()->state(['role' => 'student', 'email' => $email]),
        ]);
        CycleEnrollment::create(['placement_cycle_id' => $this->ft->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        return $student;
    }

    private function apply(StudentProfile $student, JobPosting $posting): Application
    {
        Storage::disk('local')->put("resumes/{$student->roll_no}/1.pdf", '%PDF-1.4 qa');
        $resume = $student->resumes()->firstOrCreate(['slot' => 1], ['label' => 'CV', 'file_path' => "resumes/{$student->roll_no}/1.pdf", 'file_size' => 10, 'status' => 'approved']);

        return Application::create([
            'job_posting_id' => $posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id,
            'status' => 'applied', 'applied_at' => now(), 'answers' => [['question_id' => $posting->questions()->first()->id, 'answer' => 'Answer by '.$student->full_name]],
        ]);
    }

    private function close(JobPosting $posting): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
    }

    // ------------------------------------------------------------------ T10.1

    public function test_T10_1_company_postings_list_only_its_own_floated_forms(): void
    {
        $floatedJnf = $this->float($this->form($this->companyA, 'Alpha Floated JNF'));
        $floatedInf = $this->float($this->form($this->companyA, 'Alpha Floated INF', 'accepted', 'inf'));
        $this->form($this->companyA, 'Alpha Accepted Not Floated');
        $this->form($this->companyA, 'Alpha Rejected', 'rejected');
        $this->form($this->companyA, 'Alpha Submitted', 'submitted');
        $this->form($this->companyA, 'Alpha Draft', 'draft');
        $cancelled = $this->float($this->form($this->companyA, 'Alpha Cancelled'));
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$cancelled->id}/cancel")->assertOk();
        $this->float($this->form($this->companyB, 'Beta Floated'));

        $s = $this->student('Applicant One');
        $this->apply($s, $floatedJnf);

        Sanctum::actingAs($this->userA);
        $response = $this->getJson('/api/company/postings')->assertOk();
        $postings = collect($response->json('postings'));
        $this->assertEqualsCanonicalizing([$floatedJnf->id, $floatedInf->id], $postings->pluck('id')->all());
        $this->assertSame(1, $postings->firstWhere('id', $floatedJnf->id)['applicant_count']);
        foreach (['Not Floated', 'Rejected', 'Submitted', 'Alpha Draft', 'Beta Floated'] as $absent) {
            $this->assertStringNotContainsString($absent, $response->getContent());
        }
        // Recorded: a cancelled float is hidden from the company list (implemented, D62 lifecycle).
        $this->assertNotContains($cancelled->id, $postings->pluck('id')->all());
    }

    // ------------------------------------------------------------------ T10.2

    public function test_T10_2_idor_sweep_every_company_postings_and_proposals_route_refuses_foreign_ids(): void
    {
        // COMPANY_A world with distinctive data.
        $postingA = $this->float($this->form($this->companyA, 'AlphaSecretDrive'));
        $roundA = $postingA->rounds()->orderBy('sort_order')->first();
        $roundA->update(['name' => 'AlphaRoundZeta']);
        $studentA = $this->student('Zelda Alphaapplicant');
        $applicationA = $this->apply($studentA, $postingA);
        $this->close($postingA);
        $proposalA = ShortlistProposal::create(['job_posting_id' => $postingA->id, 'posting_round_id' => $roundA->id, 'proposed_by' => $this->userA->id, 'kind' => 'shortlist', 'payload' => [['roll_no' => $studentA->roll_no]], 'status' => 'pending']);
        ApplicationRoundResult::create(['application_id' => $applicationA->id, 'posting_round_id' => $roundA->id, 'result' => 'selected', 'published_at' => now()]);

        // COMPANY_B's own posting (for mixed-id attempts).
        $postingB = $this->float($this->form($this->companyB, 'BetaDrive'));
        $roundB = $postingB->rounds()->orderBy('sort_order')->first();
        $this->close($postingB);

        $secrets = ['AlphaSecretDrive', 'AlphaRoundZeta', 'Zelda', $studentA->roll_no, $studentA->phone, 'Secret question of Alpha', 'Answer by'];

        $ids = [
            'jobPosting' => ['foreign' => $postingA->id, 'own' => $postingB->id],
            'postingRound' => ['foreign' => $roundA->id, 'own' => $roundB->id],
            'shortlistProposal' => ['foreign' => $proposalA->id, 'own' => null],
            'application' => ['foreign' => $applicationA->id, 'own' => null],
        ];

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with($r->uri(), 'api/company/') && (str_contains($r->uri(), 'postings') || str_contains($r->uri(), 'proposals')))
            ->values();
        $this->assertGreaterThanOrEqual(6, $routes->count(), 'company postings/proposals routes found: '.$routes->map->uri()->implode(', '));

        $attempts = 0;
        $report = [];
        Sanctum::actingAs($this->userB);
        foreach ($routes as $route) {
            $params = $route->parameterNames();
            foreach ($params as $p) {
                $this->assertArrayHasKey($p, $ids, "unmapped route parameter {{$p}} in {$route->uri()} — extend the sweep");
            }
            if ($params === []) {
                // Collection routes: COMPANY_A's data must not appear in COMPANY_B's list.
                $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
                $response = $this->json($method, '/'.$route->uri());
                foreach ($secrets as $secret) {
                    $this->assertStringNotContainsString((string) $secret, $response->getContent(), "{$method} /{$route->uri()} leaks '{$secret}'");
                }
                $report[] = "{$method} /{$route->uri()} → {$response->status()} (no params)";

                continue;
            }

            // Every combination where at least one id is COMPANY_A's.
            $combos = [[]];
            foreach ($params as $p) {
                $next = [];
                foreach ($combos as $combo) {
                    foreach (['foreign', 'own'] as $kind) {
                        if ($ids[$p][$kind] !== null) {
                            $next[] = $combo + [$p => [$kind, $ids[$p][$kind]]];
                        }
                    }
                }
                $combos = $next;
            }

            foreach ($combos as $combo) {
                if (! collect($combo)->contains(fn ($c) => $c[0] === 'foreign')) {
                    continue;
                }
                $uri = '/'.$route->uri();
                foreach ($combo as $p => [, $id]) {
                    $uri = str_replace('{'.$p.'}', (string) $id, $uri);
                }
                foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                    $payload = $method === 'GET' ? [] : ['kind' => 'shortlist', 'entries' => [['roll_no' => $studentA->roll_no]]];
                    $response = $this->json($method, $uri, $payload);
                    $attempts++;
                    $report[] = "{$method} {$uri} → {$response->status()}";
                    $this->assertContains($response->status(), [403, 404], "IDOR: {$method} {$uri} as COMPANY_B returned {$response->status()}: ".substr($response->getContent(), 0, 300));
                    foreach ($secrets as $secret) {
                        $this->assertStringNotContainsString((string) $secret, $response->getContent(), "{$method} {$uri} leaks '{$secret}'");
                    }
                }
            }
        }

        $this->assertGreaterThanOrEqual(7, $attempts, implode("\n", $report));
        $this->assertSame(1, ShortlistProposal::count(), 'COMPANY_B could not create a proposal on COMPANY_A\'s posting');

        // COMPANY_B proposing COMPANY_A's applicant on B's OWN posting is refused without revealing anything.
        $r = $this->postJson("/api/company/postings/{$postingB->id}/rounds/{$roundB->id}/proposals", ['kind' => 'shortlist', 'entries' => [['roll_no' => $studentA->roll_no]]])->assertStatus(422);
        $this->assertStringNotContainsString('Zelda', $r->getContent());
        $this->assertSame('Not an applicant of this posting.', $r->json('errors.0.reason'));

        // The sweep cannot be satisfied trivially: COMPANY_A itself gets 200s on the same URLs.
        Sanctum::actingAs($this->userA);
        $this->getJson("/api/company/postings/{$postingA->id}")->assertOk();
        $this->getJson("/api/company/postings/{$postingA->id}/applicants")->assertOk()->assertJsonPath('applicants.0.roll_no', $studentA->roll_no);
        $this->getJson("/api/company/postings/{$postingA->id}/proposals")->assertOk()->assertJsonCount(1, 'proposals');
        $this->get("/api/company/postings/{$postingA->id}/export")->assertOk();
    }

    // ------------------------------------------------------------------ T10.3

    public function test_T10_3_company_never_sees_draft_round_results(): void
    {
        $posting = $this->float($this->form($this->companyA, 'Alpha Pipeline'));
        [$r1, $r2] = $posting->rounds()->orderBy('sort_order')->get()->all();
        $kept = $this->student('Kept Candidate');
        $cut = $this->student('Cut Candidate');
        $this->apply($kept, $posting);
        $this->apply($cut, $posting);
        $this->close($posting);

        // Company proposes, admin approves → DRAFT results only.
        Sanctum::actingAs($this->userA);
        $this->postJson("/api/company/postings/{$posting->id}/rounds/{$r1->id}/proposals", ['kind' => 'shortlist', 'entries' => [['roll_no' => $kept->roll_no]]])->assertCreated();
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/proposals/'.ShortlistProposal::sole()->id, ['status' => 'approved'])->assertOk();
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r1->id}/results", ['roll_nos' => [$cut->roll_no], 'result' => 'rejected'])->assertOk();
        $this->assertSame(2, ApplicationRoundResult::whereNull('published_at')->count());

        $assertNoResultsVisible = function () use ($posting, $r1): void {
            Sanctum::actingAs($this->userA);
            $applicants = $this->getJson("/api/company/postings/{$posting->id}/applicants")->assertOk();
            foreach ($applicants->json('applicants') as $a) {
                $this->assertEmpty($a['rounds'], 'draft result exposed in applicants for '.$a['roll_no']);
            }
            $this->assertStringNotContainsString('"result"', $applicants->getContent());
            $show = $this->getJson("/api/company/postings/{$posting->id}")->assertOk();
            $round = collect($show->json('posting.rounds'))->firstWhere('id', $r1->id);
            $this->assertSame(0, $round['published_selected']);
            $this->assertNotSame('completed', $round['status']);
            $this->assertStringNotContainsString('rejected', strtolower($show->getContent()));
        };
        $assertNoResultsVisible();

        // Students see nothing either.
        Sanctum::actingAs($kept->user);
        $this->assertFalse($this->getJson('/api/student/applications')->json('applications.0.trail.0.published'));

        // Publish round 1 → visible; then a round-2 draft stays hidden.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r1->id}/publish")->assertOk();
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r2->id}/results", ['roll_nos' => [$kept->roll_no], 'result' => 'selected'])->assertOk();

        Sanctum::actingAs($this->userA);
        $applicants = collect($this->getJson("/api/company/postings/{$posting->id}/applicants")->json('applicants'))->keyBy('roll_no');
        $this->assertSame('selected', $applicants[$kept->roll_no]['rounds'][$r1->id]['result']);
        $this->assertSame('rejected', $applicants[$cut->roll_no]['rounds'][$r1->id]['result']);
        $this->assertArrayNotHasKey($r2->id, $applicants[$kept->roll_no]['rounds'], 'round-2 draft hidden');
        $round2 = collect($this->getJson("/api/company/postings/{$posting->id}")->json('posting.rounds'))->firstWhere('id', $r2->id);
        $this->assertSame(0, $round2['published_selected']);

        // Companies can never publish.
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r2->id}/publish")->assertStatus(403);
        $this->assertNull(ApplicationRoundResult::where('posting_round_id', $r2->id)->sole()->published_at);
    }
}
