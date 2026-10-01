<?php

namespace Tests\Feature\QA;

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
use App\Models\PortalNotification;
use App\Models\PostingRound;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * QA acceptance — Phase 2 spec Part B3 / M6 (pipeline: rounds, shortlists, waitlist, addendum, attendance, trail).
 * Every test asserts the REQUIREMENT. Waitlist behaviour follows the owner-approved override D90 (no ranks).
 *
 * Fixture: one accepted JNF of COMPANY_A floated through the real API into an FT cycle. Its form has 4
 * selectionRounds, the 2nd disabled. 12 enrolled B.Tech CSE students: 23QA0001–23QA0010 apply through the
 * student API, 23QA0011 is eligible but never applies, 23QA0012 applies and then withdraws.
 */
#[Group('qa')]
class S4PipelineTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const NOT_APPLIED = '23QA0011';

    private const WITHDRAWN = '23QA0012';

    private User $admin;

    private User $admin2;

    private Company $company;

    private User $companyUser;

    private PlacementCycle $cycle;

    private JobPosting $posting;

    /** @var array<string, StudentProfile> roll => student */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'QA Admin One', 'email' => 'admin1@cdc-qa.test']);
        $this->admin2 = User::factory()->create(['role' => 'admin', 'name' => 'QA Admin Two', 'email' => 'admin2@cdc-qa.test']);

        $this->company = Company::create(['name' => 'Company A', 'hr_name' => 'HR A', 'hr_email' => 'hr@company-a.test']);
        $this->companyUser = User::factory()->create([
            'role' => 'company', 'company_id' => $this->company->id, 'name' => 'QA Recruiter', 'email' => 'hr@company-a.test',
        ]);

        $this->cycle = PlacementCycle::create([
            'name' => 'FT 2026-27', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);

        $jnf = Jnf::create([
            'company_id' => $this->company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'currency' => 'INR',
                'eligibility' => [[
                    'programme' => self::BTECH,
                    'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]],
                ]],
                'genderFilter' => 'all',
                'graduatingBatch' => '2027',
                'programmeSalaries' => [['programme' => self::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true]],
                'selectionRounds' => [
                    ['id' => '1', 'type' => 'aptitude_test', 'enabled' => true],
                    ['id' => '2', 'type' => 'group_discussion', 'enabled' => false],
                    ['id' => '3', 'type' => 'technical_interview', 'enabled' => true],
                    ['id' => '4', 'type' => 'hr_interview', 'enabled' => true],
                ],
            ],
        ]);

        for ($i = 1; $i <= 12; $i++) {
            $roll = sprintf('23QA%04d', $i);
            $student = StudentProfile::factory()->create(['roll_no' => $roll, 'full_name' => sprintf('QA Student %02d', $i), 'phone' => '9000000000']);
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
            $student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $this->students[$roll] = $student;
        }

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(2)->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        // Real applications through the student API.
        foreach ($this->students as $roll => $student) {
            if ($roll === self::NOT_APPLIED) {
                continue;
            }
            Sanctum::actingAs($student->user);
            $this->postJson("/api/student/postings/{$this->posting->id}/apply", ['resume_id' => $student->resumes()->value('id')])->assertCreated();
        }
        Sanctum::actingAs($this->students[self::WITHDRAWN]->user);
        $this->postJson('/api/student/applications/'.$this->application(self::WITHDRAWN)->id.'/withdraw')->assertOk();
    }

    // ------------------------------------------------------------------------------------------------------------

    private function round(int $index): PostingRound
    {
        return $this->posting->rounds()->get()[$index];
    }

    private function application(string $roll): Application
    {
        return Application::where('job_posting_id', $this->posting->id)->where('student_profile_id', $this->students[$roll]->id)->sole();
    }

    private function email(string $roll): string
    {
        return $this->students[$roll]->user->email;
    }

    private function base(): string
    {
        return "/api/admin/postings/{$this->posting->id}";
    }

    private function closeApplications(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson($this->base().'/close')->assertOk();
    }

    private function asStudent(string $roll): void
    {
        Sanctum::actingAs($this->students[$roll]->user);
    }

    private function resultRow(string $roll, PostingRound $round): ?ApplicationRoundResult
    {
        return ApplicationRoundResult::where('application_id', $this->application($roll)->id)->where('posting_round_id', $round->id)->first();
    }

    /** @return list<string> */
    private function rolls(int $from, int $to): array
    {
        return array_map(fn ($i) => sprintf('23QA%04d', $i), range($from, $to));
    }

    private function studentTrail(string $roll): TestResponse
    {
        $this->asStudent($roll);

        return $this->getJson('/api/student/applications')->assertOk();
    }

    private function roundResultMailsTo(string $roll, ?string $outcome = null): int
    {
        $email = $this->email($roll);

        return Mail::queued(RoundResultMail::class, fn (RoundResultMail $m) => ($m->hasBcc($email) || $m->hasTo($email)) && ($outcome === null || $m->outcome === $outcome))->count();
    }

    /** Every key (recursively) of a decoded JSON payload. @return list<string> */
    private function keys(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }
        $keys = [];
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $keys[] = $key;
            }
            $keys = array_merge($keys, $this->keys($value));
        }

        return $keys;
    }

    // ------------------------------------------------------------------------------------------------------------
    // T4.1 Rounds
    // ------------------------------------------------------------------------------------------------------------

    public function test_T4_1a_rounds_copied_from_form_enabled_only_in_order_last_final(): void
    {
        $rounds = PostingRound::where('job_posting_id', $this->posting->id)->orderBy('sort_order')->get();

        $this->assertCount(3, $rounds, '3 enabled + 1 disabled selectionRounds must give exactly 3 posting_rounds');
        $this->assertSame(['Aptitude Test', 'Technical Interview', 'HR Interview'], $rounds->pluck('name')->all(), 'form order kept, disabled GD skipped');
        $this->assertSame(['aptitude_test', 'technical_interview', 'hr_interview'], $rounds->pluck('round_type')->all());
        $this->assertSame([1, 2, 3], $rounds->pluck('sort_order')->all());
        $this->assertSame([false, false, true], $rounds->pluck('is_final')->all(), 'last round is the (only) final round');
        $this->assertNotContains('group_discussion', $rounds->pluck('round_type')->all());

        // The admin posting view returns the same rounds.
        Sanctum::actingAs($this->admin);
        $this->assertSame(['Aptitude Test', 'Technical Interview', 'HR Interview'], collect($this->getJson($this->base())->assertOk()->json('posting.rounds'))->pluck('name')->all());
    }

    public function test_T4_1b_admin_adds_removes_reorders_rounds_refuses_removing_round_with_results_and_audits(): void
    {
        Sanctum::actingAs($this->admin);
        $base = $this->base().'/rounds';

        // Add → appended and becomes the final round.
        $this->postJson($base, ['name' => 'Managerial Round', 'round_type' => 'other'])->assertCreated();
        $rounds = $this->posting->rounds()->get();
        $this->assertSame(['Aptitude Test', 'Technical Interview', 'HR Interview', 'Managerial Round'], $rounds->pluck('name')->all());
        $this->assertSame(1, $rounds->where('is_final', true)->count());
        $managerial = $rounds->last();
        $this->assertTrue($managerial->is_final);

        // Reorder → managerial first; final flag moves to the new last round.
        $order = array_merge([$managerial->id], $rounds->slice(0, 3)->pluck('id')->all());
        $this->postJson("$base/reorder", ['ordered_round_ids' => $order])->assertOk();
        $rounds = $this->posting->rounds()->get();
        $this->assertSame(['Managerial Round', 'Aptitude Test', 'Technical Interview', 'HR Interview'], $rounds->pluck('name')->all());
        $this->assertSame('HR Interview', $rounds->firstWhere('is_final', true)->name);
        $this->assertSame(1, $rounds->where('is_final', true)->count());

        // Edit (rename / schedule).
        $this->patchJson("$base/{$managerial->id}", ['name' => 'Managerial Interview', 'scheduled_at' => now()->addDays(5)->toIso8601String()])->assertOk();
        $this->assertSame('Managerial Interview', $managerial->fresh()->name);

        // Remove a round without results.
        $this->deleteJson("$base/{$managerial->id}")->assertOk();
        $this->assertSame(['Aptitude Test', 'Technical Interview', 'HR Interview'], $this->posting->rounds()->pluck('name')->all());

        // A round that has results cannot be removed.
        $this->closeApplications();
        $r1 = $this->round(0);
        $this->postJson($this->base()."/rounds/{$r1->id}/results", ['roll_nos' => ['23QA0001'], 'result' => 'selected'])->assertOk();
        $this->deleteJson("$base/{$r1->id}")->assertStatus(422);
        $this->assertDatabaseHas('posting_rounds', ['id' => $r1->id]);

        foreach (['round.create', 'round.reorder', 'round.update', 'round.delete'] as $action) {
            $this->assertSame(1, AuditLog::where('action', $action)->where('user_id', $this->admin->id)->count(), "exactly one {$action} audit row");
        }

        // Companies cannot touch round structure.
        Sanctum::actingAs($this->companyUser);
        $this->postJson($base, ['name' => 'Sneaky', 'round_type' => 'other'])->assertForbidden();
        $this->deleteJson("$base/{$r1->id}")->assertForbidden();
        $this->assertSame(3, $this->posting->rounds()->count());
    }

    // ------------------------------------------------------------------------------------------------------------
    // T4.2 Shortlists
    // ------------------------------------------------------------------------------------------------------------

    public function test_T4_2a_company_proposes_only_admin_publishes_and_company_is_403_on_every_admin_endpoint(): void
    {
        $r1 = $this->round(0);
        $proposalUrl = "/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals";

        // While applications are open the company cannot propose (D75g).
        Sanctum::actingAs($this->companyUser);
        $this->postJson($proposalUrl, ['kind' => 'shortlist', 'entries' => [['roll_no' => '23QA0001']]])->assertStatus(422);

        $this->closeApplications();
        Sanctum::actingAs($this->companyUser);
        $this->postJson($proposalUrl, ['kind' => 'shortlist', 'entries' => [['roll_no' => '23QA0001'], ['roll_no' => '23QA0002'], ['roll_no' => '23QA0003']]])
            ->assertCreated();

        $proposal = ShortlistProposal::sole();
        $this->assertSame('pending', $proposal->status);
        $this->assertSame(0, ApplicationRoundResult::count(), 'a proposal writes no results');

        // E10 to EVERY active admin: mail + in-app.
        foreach ([$this->admin, $this->admin2] as $admin) {
            Mail::assertQueued(PortalNoticeMail::class, fn (PortalNoticeMail $m) => $m->hasTo($admin->email) && str_contains($m->subjectLine, 'Company A'));
            $this->assertSame(1, PortalNotification::where('user_id', $admin->id)->count(), "in-app E10 for {$admin->email}");
        }

        // The company token is refused on every admin pipeline / result / attendance / readd / addendum / waitlist endpoint.
        $a1 = $this->application('23QA0001');
        $p = $this->base();
        $endpoints = [
            ['POST', "$p/rounds/{$r1->id}/publish", ['reject_remaining' => true]],
            ['POST', "$p/rounds/{$r1->id}/results", ['roll_nos' => ['23QA0001'], 'result' => 'selected']],
            ['POST', "$p/rounds/{$r1->id}/attendance", ['roll_nos_present' => ['23QA0001']]],
            ['POST', "$p/rounds/{$r1->id}/readd/{$a1->id}", ['confirm' => true, 'remark' => 'x']],
            ['POST', "$p/rounds/{$r1->id}/addendum", ['roll_nos' => ['23QA0001']]],
            ['DELETE', "$p/rounds/{$r1->id}/waitlist/{$a1->id}", []],
            ['DELETE', "$p/rounds/{$r1->id}/results/{$a1->id}", []],
            ['POST', "$p/applications/{$a1->id}/remove-from-process", []],
            ['GET', "$p/pipeline", []],
            ['GET', "$p/results/prepare", []],
            ['POST', "$p/results/publish", ['selections' => [['application_id' => $a1->id, 'offer_type' => 'fulltime', 'block' => false]]]],
            ['GET', '/api/admin/proposals', []],
            ['PATCH', "/api/admin/proposals/{$proposal->id}", ['status' => 'approved']],
            ['POST', "$p/rounds", ['name' => 'X', 'round_type' => 'other']],
            ['PATCH', "$p/close", []],
        ];
        Sanctum::actingAs($this->companyUser);
        foreach ($endpoints as [$method, $url, $data]) {
            $this->assertSame(403, $this->json($method, $url, $data)->status(), "company token must get 403 on {$method} {$url}");
        }

        $this->assertSame(0, ApplicationRoundResult::count(), 'nothing written by the company calls');
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertNotSame('completed', $r1->fresh()->status, 'round not published');

        // Students see nothing yet.
        foreach (['23QA0001', '23QA0004'] as $roll) {
            $response = $this->studentTrail($roll);
            $this->assertStringNotContainsString('"result":"selected"', $response->getContent());
            foreach ($response->json('applications.0.trail') as $step) {
                $this->assertFalse($step['published']);
                $this->assertNull($step['result']);
            }
        }
    }

    public function test_T4_2b_admin_upload_xlsx_reports_every_bad_row(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);

        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['roll_no', 'result'],
            ['23QA0001', 'selected'],
            ['23QA0002', 'selected'],
            ['23QA0003', 'waitlisted'],
            [self::NOT_APPLIED, 'selected'],   // enrolled + eligible, never applied
            [self::WITHDRAWN, 'selected'],     // withdrew
            ['23QA0001', 'selected'],          // duplicate row
            ['99ZZ9999', 'selected'],          // does not exist at all
        ], null, 'A1', true);
        ob_start();
        (new Xlsx($book))->save('php://output');
        $file = UploadedFile::fake()->createWithContent('shortlist.xlsx', (string) ob_get_clean());

        Sanctum::actingAs($this->admin);
        $response = $this->post($this->base()."/rounds/{$r1->id}/results", ['file' => $file], ['Accept' => 'application/json'])->assertOk();

        // Valid rows → drafts.
        $rows = ApplicationRoundResult::where('posting_round_id', $r1->id)->get();
        $this->assertCount(3, $rows, 'exactly the 3 valid applicants become drafts');
        $this->assertTrue($rows->every(fn ($r) => $r->published_at === null), 'drafts only');
        $this->assertSame('selected', $this->resultRow('23QA0001', $r1)->result);
        $this->assertSame('selected', $this->resultRow('23QA0002', $r1)->result);
        $this->assertSame('waitlisted', $this->resultRow('23QA0003', $r1)->result);

        // Every bad row is REPORTED.
        $errors = collect($response->json('errors'))->keyBy('roll_no');
        $this->assertSame('Not an applicant of this posting.', $errors[self::NOT_APPLIED]['reason'] ?? null, 'non-applicant reported');
        $this->assertSame('Withdrew the application.', $errors[self::WITHDRAWN]['reason'] ?? null, 'withdrawn applicant reported');
        $this->assertNotNull($errors['99ZZ9999'] ?? null, 'non-existent roll reported');

        $this->assertFalse($this->studentTrail('23QA0001')->json('applications.0.trail.0.published'), 'students still see nothing');

        // The duplicate row must be reported too, and must not inflate the "written" count.
        $reportedRolls = collect($response->json('errors'))->merge($response->json('warnings'))->pluck('roll_no')->all();
        $this->assertTrue(
            in_array('23QA0001', $reportedRolls, true) && $response->json('written') === 3,
            sprintf(
                'duplicate row 23QA0001 must be REPORTED (errors/warnings) and counted once; got reported=%s written=%s message="%s"',
                json_encode($reportedRolls),
                json_encode($response->json('written')),
                $response->json('message')
            )
        );
    }

    public function test_T4_2b_admin_paste_mode_reports_unknown_rolls(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);

        // Paste mode (roll_nos + result); whitespace/case normalised; unknowns reported.
        Sanctum::actingAs($this->admin);
        $paste = $this->postJson($this->base()."/rounds/{$r1->id}/results", [
            'roll_nos' => ['23QA0004', ' 23qa0005 ', self::NOT_APPLIED, 'NOPE-1'],
            'result' => 'waitlisted',
        ])->assertOk();
        $this->assertSame(2, $paste->json('written'));
        $this->assertEqualsCanonicalizing([self::NOT_APPLIED, 'NOPE-1'], collect($paste->json('errors'))->pluck('roll_no')->all());
        $this->assertSame('waitlisted', $this->resultRow('23QA0004', $r1)->result);
        $this->assertSame('waitlisted', $this->resultRow('23QA0005', $r1)->result);
        $this->assertNull($this->resultRow('23QA0005', $r1)->published_at);

        // Students still see nothing.
        $this->assertFalse($this->studentTrail('23QA0001')->json('applications.0.trail.0.published'));
    }

    public function test_T4_2c_proposal_approve_gives_drafts_reject_needs_remark_and_notifies_company(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = "/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals";

        Sanctum::actingAs($this->companyUser);
        $this->postJson($url, ['kind' => 'shortlist', 'entries' => [['roll_no' => '23QA0001'], ['roll_no' => '23QA0002']]])->assertCreated();
        $this->postJson($url, ['kind' => 'shortlist', 'entries' => [['roll_no' => '23QA0003']]])->assertCreated();
        [$approveMe, $rejectMe] = ShortlistProposal::orderBy('id')->get()->all();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/proposals/{$approveMe->id}", ['status' => 'approved'])->assertOk();
        $this->assertSame('approved', $approveMe->fresh()->status);
        foreach (['23QA0001', '23QA0002'] as $roll) {
            $row = $this->resultRow($roll, $r1);
            $this->assertNotNull($row, "{$roll} draft written");
            $this->assertSame('selected', $row->result);
            $this->assertNull($row->published_at, 'approval writes DRAFTS only');
        }
        $this->assertSame('ongoing', $r1->fresh()->status, 'approval does not publish the round');
        $this->assertFalse($this->studentTrail('23QA0001')->json('applications.0.trail.0.published'));

        // Reject without a remark → 422, still pending.
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/proposals/{$rejectMe->id}", ['status' => 'rejected'])->assertStatus(422);
        $this->assertSame('pending', $rejectMe->fresh()->status);

        // Reject with a remark → company notified by mail + in-app (E10).
        $this->patchJson("/api/admin/proposals/{$rejectMe->id}", ['status' => 'rejected', 'admin_remark' => 'Only two seats in this round'])->assertOk();
        $this->assertSame('rejected', $rejectMe->fresh()->status);
        $this->assertSame('Only two seats in this round', $rejectMe->fresh()->admin_remark);
        $this->assertNull($this->resultRow('23QA0003', $r1), 'a rejected proposal writes nothing');

        Mail::assertQueued(PortalNoticeMail::class, fn (PortalNoticeMail $m) => $m->hasTo($this->companyUser->email)
            && str_contains($m->subjectLine, 'declined')
            && collect($m->lines)->contains(fn ($l) => str_contains($l, 'Only two seats in this round')));
        Mail::assertQueued(PortalNoticeMail::class, fn (PortalNoticeMail $m) => $m->hasTo($this->companyUser->email) && str_contains($m->subjectLine, 'accepted'));
        $this->assertSame(2, PortalNotification::where('user_id', $this->companyUser->id)->count(), 'in-app E10 for both decisions');
        $this->assertSame(1, AuditLog::where('action', 'proposal.approve')->count());
        $this->assertSame(1, AuditLog::where('action', 'proposal.reject')->count());
    }

    // ------------------------------------------------------------------------------------------------------------
    // T4.3 Publish + regret mails
    // ------------------------------------------------------------------------------------------------------------

    public function test_T4_3_publish_sends_result_and_regret_mails_and_nothing_leaks_before(): void
    {
        $this->closeApplications();
        [$r1, $r2, $r3] = $this->posting->rounds()->get()->all();
        $selected = $this->rolls(1, 5);
        $waitlisted = $this->rolls(6, 7);
        $rejected = $this->rolls(8, 10); // 0008 explicitly, 0009 + 0010 via reject_remaining

        $entries = array_merge(
            array_map(fn ($r) => ['roll_no' => $r, 'result' => 'selected'], $selected),
            array_map(fn ($r) => ['roll_no' => $r, 'result' => 'waitlisted'], $waitlisted),
            [['roll_no' => '23QA0008', 'result' => 'rejected']],
        );
        $this->postJson($this->base()."/rounds/{$r1->id}/results", ['entries' => $entries])->assertOk()->assertJsonPath('written', 8);
        $this->postJson($this->base()."/rounds/{$r1->id}/attendance", ['roll_nos_present' => $this->rolls(1, 10)])->assertOk();

        // BEFORE publish: no draft value in any student / company payload (raw JSON).
        foreach (['23QA0001' => 'selected', '23QA0006' => 'waitlisted', '23QA0008' => 'rejected'] as $roll => $draft) {
            $this->asStudent($roll);
            foreach (['/api/student/applications', "/api/student/postings/{$this->posting->id}", '/api/student/dashboard'] as $url) {
                $raw = $this->getJson($url)->assertOk()->getContent();
                $this->assertStringNotContainsString("\"result\":\"{$draft}\"", $raw, "draft {$draft} leaked to {$roll} via {$url}");
                $this->assertStringNotContainsString('"attendance":"yes"', $raw, "draft attendance leaked to {$roll} via {$url}");
            }
        }
        Sanctum::actingAs($this->companyUser);
        $raw = $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertOk()->getContent();
        $this->assertStringNotContainsString('"result":', $raw, 'company must see no draft result');
        $this->assertStringNotContainsString('"attendance":', $raw, 'company must see no draft attendance');
        $show = $this->getJson("/api/company/postings/{$this->posting->id}")->assertOk();
        $this->assertSame(0, $show->json('posting.rounds.0.published_selected'));
        $this->assertSame(0, $show->json('posting.rounds.0.published_waitlisted'));
        Mail::assertNotQueued(RoundResultMail::class);

        // Publish.
        Sanctum::actingAs($this->admin);
        $this->postJson($this->base()."/rounds/{$r1->id}/publish", ['reject_remaining' => true])
            ->assertOk()
            ->assertJsonPath('counts.selected', 5)
            ->assertJsonPath('counts.waitlisted', 2)
            ->assertJsonPath('counts.rejected', 3);

        foreach ($selected as $roll) {
            $this->assertSame(1, $this->roundResultMailsTo($roll, 'selected'), "{$roll} gets exactly one selected mail");
            $this->assertSame(1, $this->roundResultMailsTo($roll), "{$roll} gets no other round mail");
        }
        foreach ($waitlisted as $roll) {
            $this->assertSame(1, $this->roundResultMailsTo($roll, 'waitlisted'), "{$roll} gets exactly one waitlist mail");
            $this->assertSame(1, $this->roundResultMailsTo($roll));
        }
        foreach ($rejected as $roll) {
            $this->assertSame(1, $this->roundResultMailsTo($roll, 'rejected'), "{$roll} gets exactly one regret mail");
            $this->assertSame(1, $this->roundResultMailsTo($roll));
        }
        foreach ([self::NOT_APPLIED, self::WITHDRAWN] as $roll) {
            $this->assertSame(0, $this->roundResultMailsTo($roll), "{$roll} is not part of the round");
        }
        // BCC batches addressed to the portal itself (approved E4 behaviour).
        Mail::assertQueued(RoundResultMail::class, fn (RoundResultMail $m) => $m->hasTo((string) config('mail.from.address')) && count($m->bcc) > 0);

        // email_logs: one row per student (10), none for others.
        $logs = EmailLog::where('template', 'emails.round-result')->get();
        $this->assertCount(10, $logs);
        foreach (array_merge($selected, $waitlisted, $rejected) as $roll) {
            $this->assertSame(1, $logs->where('user_id', $this->students[$roll]->user_id)->count(), "one email_logs row for {$roll}");
        }

        // In-app notifications match the outcome.
        $typeFor = fn (string $roll) => PortalNotification::where('user_id', $this->students[$roll]->user_id)->where('title', 'like', '%result%')->pluck('type')->all();
        foreach ($selected as $roll) {
            $this->assertSame(['success'], $typeFor($roll));
        }
        foreach ($waitlisted as $roll) {
            $this->assertSame(['info'], $typeFor($roll));
        }
        foreach ($rejected as $roll) {
            $this->assertSame(['warning'], $typeFor($roll));
        }

        $this->assertSame('completed', $r1->fresh()->status);
        $this->assertSame('ongoing', $r2->fresh()->status, 'next round becomes ongoing');
        $this->assertSame('pending', $r3->fresh()->status);

        // AFTER: student + company see the published result.
        $this->assertSame('selected', $this->studentTrail('23QA0001')->json('applications.0.trail.0.result'));
        $this->assertSame('rejected', $this->studentTrail('23QA0010')->json('applications.0.trail.0.result'));
        Sanctum::actingAs($this->companyUser);
        $applicants = collect($this->getJson("/api/company/postings/{$this->posting->id}/applicants")->json('applicants'))->keyBy('roll_no');
        $this->assertSame('selected', $applicants['23QA0001']['rounds'][$r1->id]['result']);
        $this->assertSame('waitlisted', $applicants['23QA0006']['rounds'][$r1->id]['result']);
    }

    // ------------------------------------------------------------------------------------------------------------
    // T4.4 Waitlist
    // ------------------------------------------------------------------------------------------------------------

    public function test_T4_4a_waitlist_add_remove_by_company_proposal_and_admin_only_admin_publishes(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $proposalUrl = "/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals";
        $waitlist = fn () => ApplicationRoundResult::where('posting_round_id', $r1->id)->where('result', 'waitlisted')->get()
            ->map(fn ($r) => $r->application->studentProfile->roll_no)->sort()->values()->all();

        // Company: add 0006 + 0007.
        Sanctum::actingAs($this->companyUser);
        $this->postJson($proposalUrl, ['kind' => 'waitlist', 'entries' => [['roll_no' => '23QA0006'], ['roll_no' => '23QA0007']]])->assertCreated();
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/proposals/'.ShortlistProposal::latest('id')->first()->id, ['status' => 'approved'])->assertOk();
        $this->assertSame(['23QA0006', '23QA0007'], $waitlist());

        // Company: the complete desired waitlist is now 0007 + 0008 → 0006 removed, 0008 added.
        Sanctum::actingAs($this->companyUser);
        $this->postJson($proposalUrl, ['kind' => 'waitlist', 'entries' => [['roll_no' => '23QA0007'], ['roll_no' => '23QA0008']]])->assertCreated();
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/proposals/'.ShortlistProposal::latest('id')->first()->id, ['status' => 'approved'])->assertOk();
        $this->assertSame(['23QA0007', '23QA0008'], $waitlist());

        // Admin: add 0009 via results entries, remove draft 0008 via DELETE …/waitlist/{application}.
        $this->postJson($this->base()."/rounds/{$r1->id}/results", ['entries' => [
            ['roll_no' => '23QA0009', 'result' => 'waitlisted'],
            ['roll_no' => '23QA0010', 'result' => 'waitlisted'],
            ['roll_no' => '23QA0001', 'result' => 'selected'],
        ]])->assertOk();
        $this->deleteJson($this->base()."/rounds/{$r1->id}/waitlist/".$this->application('23QA0008')->id)->assertOk();
        $this->assertSame(['23QA0007', '23QA0009', '23QA0010'], $waitlist());
        $this->assertNull($this->resultRow('23QA0008', $r1), 'draft waitlist entry deleted');

        // Only the admin publishes.
        Sanctum::actingAs($this->companyUser);
        $this->postJson($this->base()."/rounds/{$r1->id}/publish")->assertForbidden();
        $this->deleteJson($this->base()."/rounds/{$r1->id}/waitlist/".$this->application('23QA0007')->id)->assertForbidden();
        $this->assertSame(0, ApplicationRoundResult::whereNotNull('published_at')->count());
        $this->assertNull($this->studentTrail('23QA0007')->json('applications.0.trail.0.result'));

        Sanctum::actingAs($this->admin);
        $this->postJson($this->base()."/rounds/{$r1->id}/publish")->assertOk()->assertJsonPath('counts.waitlisted', 3);
        $this->assertSame('waitlisted', $this->studentTrail('23QA0009')->json('applications.0.trail.0.result'));

        // Removing a PUBLISHED waitlist entry → published "not selected" + regret mail.
        Sanctum::actingAs($this->admin);
        $this->deleteJson($this->base()."/rounds/{$r1->id}/waitlist/".$this->application('23QA0009')->id)->assertOk();
        $row = $this->resultRow('23QA0009', $r1);
        $this->assertSame('rejected', $row->result);
        $this->assertNotNull($row->published_at);
        $this->assertSame(1, $this->roundResultMailsTo('23QA0009', 'rejected'));
        $this->assertSame(['23QA0007', '23QA0010'], $waitlist());
        // 0006 (removed via the approved company proposal — audited since the F-003 fix) + 0008 + 0009 (admin).
        $this->assertSame(3, AuditLog::where('action', 'waitlist.remove')->count());

        // Company removes a PUBLISHED waitlist entry through its waitlist proposal (complete list = only 0007):
        // the same outcome as the admin path is required — published "not selected" AND the regret mail + in-app.
        Sanctum::actingAs($this->companyUser);
        $this->postJson($proposalUrl, ['kind' => 'waitlist', 'entries' => [['roll_no' => '23QA0007']]])->assertCreated();
        $this->assertSame(['23QA0007', '23QA0010'], $waitlist(), 'nothing changes before the admin decides');
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/proposals/'.ShortlistProposal::latest('id')->first()->id, ['status' => 'approved'])->assertOk();
        $this->assertSame(['23QA0007'], $waitlist());
        $row = $this->resultRow('23QA0010', $r1);
        $this->assertSame('rejected', $row->result);
        $this->assertNotNull($row->published_at, 'published removal is immediately visible to the student');
        $this->assertSame('rejected', $this->studentTrail('23QA0010')->json('applications.0.trail.0.result'));
        $this->assertSame(
            1,
            $this->roundResultMailsTo('23QA0010', 'rejected'),
            '23QA0010 was taken off a PUBLISHED waitlist via an approved company waitlist proposal and now sees "rejected" — a regret mail must go out (admin DELETE path sends one)'
        );
        $this->assertSame(1, PortalNotification::where('user_id', $this->students['23QA0010']->user_id)->where('title', 'like', '%result%')->where('type', 'warning')->count());
    }

    /** T4.4b + T4.4c replaced by the owner-approved D90 behaviour (approved by owner in chat). */
    public function test_T4_4b_T4_4c_D90_unranked_waitlist_no_reorder_no_auto_promotion_admin_moves_any_waitlisted_as_draft(): void
    {
        $this->closeApplications();
        [$r1, $r2] = $this->posting->rounds()->get()->all();

        $this->assertFalse(Schema::hasColumn('application_round_results', 'waitlist_rank'), 'no rank column');

        $this->postJson($this->base()."/rounds/{$r1->id}/results", ['entries' => [
            ['roll_no' => '23QA0001', 'result' => 'selected'],
            ['roll_no' => '23QA0002', 'result' => 'waitlisted'],
            ['roll_no' => '23QA0003', 'result' => 'waitlisted'],
            ['roll_no' => '23QA0004', 'result' => 'waitlisted'],
            ['roll_no' => '23QA0005', 'result' => 'waitlisted'],
        ]])->assertOk();

        // The reorder endpoint is gone.
        $reorder = $this->postJson($this->base()."/rounds/{$r1->id}/waitlist/reorder", ['ordered_application_ids' => [$this->application('23QA0002')->id]]);
        $this->assertContains($reorder->status(), [404, 405], 'POST …/waitlist/reorder must not exist');

        // Publishing creates nothing in the next round by itself (no suggested promotions).
        $counts = $this->postJson($this->base()."/rounds/{$r1->id}/publish")->assertOk()->json('counts');
        $this->assertArrayNotHasKey('suggested_promotions', $counts);
        $this->assertSame(0, ApplicationRoundResult::where('posting_round_id', $r2->id)->count(), 'no auto-suggested promotions');

        // No rank / position anywhere in the payloads.
        $payloads = [];
        Sanctum::actingAs($this->admin);
        $payloads['admin pipeline'] = $this->getJson($this->base().'/pipeline')->assertOk();
        $payloads['admin prepare'] = $this->getJson($this->base().'/results/prepare')->assertOk();
        $payloads['admin applications'] = $this->getJson($this->base().'/applications')->assertOk();
        Sanctum::actingAs($this->companyUser);
        $payloads['company applicants'] = $this->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertOk();
        $payloads['company posting'] = $this->getJson("/api/company/postings/{$this->posting->id}")->assertOk();
        $this->asStudent('23QA0005');
        $payloads['student applications'] = $this->getJson('/api/student/applications')->assertOk();
        $payloads['student posting'] = $this->getJson("/api/student/postings/{$this->posting->id}")->assertOk();
        foreach ($payloads as $name => $response) {
            $bad = array_values(array_filter($this->keys($response->json()), fn ($k) => preg_match('/rank|position/i', $k)));
            $this->assertSame([], $bad, "{$name} payload must carry no rank/position keys");
            $this->assertStringNotContainsString('waitlist_rank', $response->getContent());
        }

        // The admin moves ANY waitlisted candidate (here the 4th and the 2nd added): promoted to "selected" in the
        // round they were waitlisted in, notified, and NOTHING is written into the next round (QA F-002).
        Sanctum::actingAs($this->admin);
        foreach (['23QA0005', '23QA0003'] as $roll) {
            $this->postJson($this->base()."/rounds/{$r1->id}/waitlist/".$this->application($roll)->id.'/promote')->assertOk();
            $this->assertSame('selected', $this->resultRow($roll, $r1)->result);
            $this->assertNull($this->resultRow($roll, $r2), 'the next round is not pre-decided');
        }
        $trail = $this->studentTrail('23QA0005')->json('applications.0.trail');
        $this->assertSame('selected', $trail[0]['result']);
        $this->assertFalse($trail[1]['published'], 'next round still to be decided');
        // Writing a next-round result for someone still on the waitlist is flagged with a precise hint.
        Sanctum::actingAs($this->admin);
        $this->postJson($this->base()."/rounds/{$r2->id}/results", ['entries' => [['roll_no' => '23QA0004', 'result' => 'selected']]])
            ->assertOk()->assertJsonPath('warnings.0.reason', 'Is on the previous round\'s waitlist — use "Move" on the Waitlist tab first.');

        // Someone who was neither selected nor waitlisted is still flagged.
        Sanctum::actingAs($this->admin);
        $this->postJson($this->base()."/rounds/{$r2->id}/results", ['entries' => [['roll_no' => '23QA0006', 'result' => 'selected']]])
            ->assertOk()->assertJsonCount(1, 'warnings');
    }

    // ------------------------------------------------------------------------------------------------------------
    // T4.5 Re-add and addendum
    // ------------------------------------------------------------------------------------------------------------

    public function test_T4_5_readd_rejected_requires_confirm_and_remark_notifies_company_company_can_only_propose(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = $this->base()."/rounds/{$r1->id}";
        $this->postJson("$url/results", ['roll_nos' => ['23QA0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("$url/publish", ['reject_remaining' => true])->assertOk();

        $a5 = $this->application('23QA0005');
        $this->assertSame('rejected', $this->resultRow('23QA0005', $r1)->result);

        // The company has no re-add: the admin route is 403 and no company route mentions re-add.
        Sanctum::actingAs($this->companyUser);
        $this->postJson("$url/readd/{$a5->id}", ['confirm' => true, 'remark' => 'please'])->assertForbidden();
        $companyReadd = collect(Route::getRoutes()->getRoutes())->map->uri()->filter(fn ($u) => str_starts_with($u, 'api/company') && str_contains($u, 'readd'));
        $this->assertCount(0, $companyReadd);

        Sanctum::actingAs($this->admin);
        $this->postJson("$url/readd/{$a5->id}", ['remark' => 'Mis-scored'])->assertStatus(422);
        $this->postJson("$url/readd/{$a5->id}", ['confirm' => false, 'remark' => 'Mis-scored'])->assertStatus(422);
        $this->postJson("$url/readd/{$a5->id}", ['confirm' => true])->assertStatus(422);
        $this->assertSame('rejected', $this->resultRow('23QA0005', $r1)->result, 'unchanged after refused attempts');
        Mail::assertNotQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->companyUser->email));

        $this->postJson("$url/readd/{$a5->id}", ['confirm' => true, 'remark' => 'Mis-scored test'])->assertOk();
        $row = $this->resultRow('23QA0005', $r1);
        $this->assertSame('selected', $row->result);
        $this->assertTrue($row->is_addendum);
        $this->assertNull($row->published_at, 're-add is a draft');
        Mail::assertQueued(PortalNoticeMail::class, fn (PortalNoticeMail $m) => $m->hasTo($this->companyUser->email) && str_contains($m->headline, '23QA0005'));
        $this->assertSame(1, PortalNotification::where('user_id', $this->companyUser->id)->count());
        $audit = AuditLog::where('action', 'round.readd')->sole();
        $this->assertSame('rejected', $audit->before['result']);
        $this->assertSame('selected', $audit->after['result']);
        $this->assertSame($this->admin->id, $audit->user_id);

        // The company can only PROPOSE: an addendum proposal for another rejected student is accepted as pending,
        // and approving it does not re-add (reported, needs the admin protocol).
        Sanctum::actingAs($this->companyUser);
        $this->postJson("/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals", ['kind' => 'addendum', 'entries' => [['roll_no' => '23QA0006']]])->assertCreated();
        Sanctum::actingAs($this->admin);
        $decision = $this->patchJson('/api/admin/proposals/'.ShortlistProposal::sole()->id, ['status' => 'approved'])->assertOk();
        $this->assertContains('23QA0006', collect($decision->json('errors'))->pluck('roll_no')->all());
        $this->assertSame('rejected', $this->resultRow('23QA0006', $r1)->result);
        $this->assertNotNull($this->resultRow('23QA0006', $r1)->published_at);

        // Publishing informs the re-added student as an addendum selection.
        $this->travel(1)->minutes();
        $this->postJson("$url/publish")->assertOk()->assertJsonPath('counts.selected', 1);
        Mail::assertQueued(RoundResultMail::class, fn (RoundResultMail $m) => $m->outcome === 'selected' && $m->addendum && $m->hasBcc($this->email('23QA0005')));
    }

    public function test_T4_5b_addendum_after_publish_flagged_notified_and_no_duplicate_regret(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = $this->base()."/rounds/{$r1->id}";

        // First publish: 0001–0003 selected, 0004 rejected; 0005–0010 still undecided.
        $this->postJson("$url/results", ['entries' => [
            ['roll_no' => '23QA0001', 'result' => 'selected'],
            ['roll_no' => '23QA0002', 'result' => 'selected'],
            ['roll_no' => '23QA0003', 'result' => 'selected'],
            ['roll_no' => '23QA0004', 'result' => 'rejected'],
        ]])->assertOk();
        $this->postJson("$url/publish")->assertOk();
        $this->assertSame(1, $this->roundResultMailsTo('23QA0004', 'rejected'));

        // Company proposes an addendum to the PUBLISHED round.
        $this->travel(5)->minutes();
        Sanctum::actingAs($this->companyUser);
        $this->postJson("/api/company/postings/{$this->posting->id}/rounds/{$r1->id}/proposals", ['kind' => 'addendum', 'entries' => [['roll_no' => '23QA0005'], ['roll_no' => '23QA0006']]])->assertCreated();

        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/proposals/'.ShortlistProposal::sole()->id, ['status' => 'approved'])->assertOk();
        foreach (['23QA0005', '23QA0006'] as $roll) {
            $row = $this->resultRow($roll, $r1);
            $this->assertSame('selected', $row->result);
            $this->assertTrue($row->is_addendum, "{$roll} flagged is_addendum");
            $this->assertNull($row->published_at);
        }

        $this->travel(5)->minutes();
        $this->postJson("$url/publish", ['reject_remaining' => true])->assertOk()
            ->assertJsonPath('counts.selected', 2)
            ->assertJsonPath('counts.rejected', 4);

        // Admin pipeline / history.
        $grid = collect($this->getJson($this->base().'/pipeline')->assertOk()->json('applications'))->keyBy('student.roll_no');
        $this->assertTrue($grid['23QA0005']['results'][$r1->id]['is_addendum']);
        $this->assertTrue($grid['23QA0006']['results'][$r1->id]['is_addendum']);
        $this->assertFalse($grid['23QA0001']['results'][$r1->id]['is_addendum']);
        $this->assertSame(1, AuditLog::where('action', 'proposal.approve')->count());
        $this->assertSame(2, AuditLog::where('action', 'round.publish')->count());

        // Added students notified (addendum selection), exactly once.
        foreach (['23QA0005', '23QA0006'] as $roll) {
            $this->assertSame(1, $this->roundResultMailsTo($roll, 'selected'));
            Mail::assertQueued(RoundResultMail::class, fn (RoundResultMail $m) => $m->addendum && $m->outcome === 'selected' && $m->hasBcc($this->email($roll)));
            $this->assertSame(1, PortalNotification::where('user_id', $this->students[$roll]->user_id)->where('title', 'like', '%result%')->count());
        }
        // Previously-notified students: no duplicate mail of any kind.
        $this->assertSame(1, $this->roundResultMailsTo('23QA0004'), '0004 must not get a second regret');
        foreach (['23QA0001', '23QA0002', '23QA0003'] as $roll) {
            $this->assertSame(1, $this->roundResultMailsTo($roll), "{$roll} must not be re-mailed");
        }
        $this->assertSame(1, EmailLog::where('template', 'emails.round-result')->where('user_id', $this->students['23QA0004']->user_id)->count());
        foreach ($this->rolls(7, 10) as $roll) {
            $this->assertSame(1, $this->roundResultMailsTo($roll, 'rejected'), "{$roll} regret once");
        }
    }

    /**
     * Extra (found while testing T4.5b): E4 mails are selected by the publish's `published_at` SECOND, so a second
     * publish of the same round within the same second re-sends every earlier result of that second.
     */
    public function test_T4_5b_x_second_publish_in_same_second_does_not_remail_earlier_results(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->closeApplications();
        $url = $this->base().'/rounds/'.$this->round(0)->id;

        $this->postJson("$url/results", ['entries' => [
            ['roll_no' => '23QA0001', 'result' => 'selected'],
            ['roll_no' => '23QA0002', 'result' => 'rejected'],
        ]])->assertOk();
        $this->postJson("$url/publish")->assertOk();

        // Same wall-clock second: an addendum is added and published (e.g. a second admin working in parallel).
        $this->postJson("$url/addendum", ['roll_nos' => ['23QA0003']])->assertOk();
        $this->postJson("$url/publish")->assertOk()->assertJsonPath('counts.selected', 1);

        $this->assertSame(1, $this->roundResultMailsTo('23QA0003'), 'addendum student mailed once');
        $this->assertSame(1, $this->roundResultMailsTo('23QA0002'), '23QA0002 already got a regret in the first publish and must not get a second one');
        $this->assertSame(1, $this->roundResultMailsTo('23QA0001'), '23QA0001 must not be re-mailed');
    }

    // ------------------------------------------------------------------------------------------------------------
    // T4.6 Attendance / T4.7 Trail / T4.8 Grid
    // ------------------------------------------------------------------------------------------------------------

    public function test_T4_6_attendance_is_admin_only_bulk_and_shown_in_pipeline(): void
    {
        $this->closeApplications();
        $r1 = $this->round(0);
        $url = $this->base()."/rounds/{$r1->id}/attendance";

        Sanctum::actingAs($this->companyUser);
        $this->postJson($url, ['roll_nos_present' => ['23QA0001']])->assertForbidden();
        $this->asStudent('23QA0001');
        $this->postJson($url, ['roll_nos_present' => ['23QA0001']])->assertForbidden();
        $this->assertSame(0, ApplicationRoundResult::count());
        $this->assertCount(0, collect(Route::getRoutes()->getRoutes())->map->uri()->filter(fn ($u) => ! str_starts_with($u, 'api/admin') && str_contains($u, 'attendance')), 'attendance is writable only under /api/admin');

        Sanctum::actingAs($this->admin);
        $this->postJson($url, ['roll_nos_present' => ['23QA0001', '23QA0002', '23qa0003'], 'roll_nos_absent' => ['23QA0004', '99NOPE']])
            ->assertOk()->assertJsonPath('errors.0.roll_no', '99NOPE');
        $this->assertSame(3, ApplicationRoundResult::where('attendance', 'yes')->count());
        $this->assertSame(1, ApplicationRoundResult::where('attendance', 'no')->count());
        $this->assertSame(1, AuditLog::where('action', 'round.attendance')->count());

        $grid = collect($this->getJson($this->base().'/pipeline')->assertOk()->json('applications'))->keyBy('student.roll_no');
        $this->assertSame('yes', $grid['23QA0001']['results'][$r1->id]['attendance']);
        $this->assertSame('yes', $grid['23QA0003']['results'][$r1->id]['attendance']);
        $this->assertSame('no', $grid['23QA0004']['results'][$r1->id]['attendance']);
        $this->assertSame('pending', $grid['23QA0004']['results'][$r1->id]['result']);
    }

    public function test_T4_7_student_trail_shows_published_round_and_pending_draft_round_without_leak(): void
    {
        $this->closeApplications();
        [$r1, $r2] = $this->posting->rounds()->get()->all();

        $this->postJson($this->base()."/rounds/{$r1->id}/attendance", ['roll_nos_present' => ['23QA0001']])->assertOk();
        $this->postJson($this->base()."/rounds/{$r1->id}/results", ['roll_nos' => ['23QA0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base()."/rounds/{$r1->id}/publish", ['reject_remaining' => true])->assertOk();

        // Round 2: draft "absent" + "rejected" for the same student.
        $this->postJson($this->base()."/rounds/{$r2->id}/attendance", ['roll_nos_absent' => ['23QA0001']])->assertOk();
        $this->postJson($this->base()."/rounds/{$r2->id}/results", ['roll_nos' => ['23QA0001'], 'result' => 'rejected'])->assertOk();

        $response = $this->studentTrail('23QA0001');
        $trail = $response->json('applications.0.trail');
        $this->assertCount(3, $trail);
        $this->assertTrue($trail[0]['published']);
        $this->assertSame('selected', $trail[0]['result']);
        $this->assertSame('yes', $trail[0]['attendance'], 'appeared shown');
        $this->assertFalse($trail[1]['published'], 'round 2 pending');
        $this->assertNull($trail[1]['result']);
        $this->assertNull($trail[1]['attendance']);
        $this->assertFalse($trail[2]['published']);

        foreach (['/api/student/applications', "/api/student/postings/{$this->posting->id}", '/api/student/dashboard'] as $url) {
            $raw = $this->getJson($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('"result":"rejected"', $raw, "round-2 draft leaked via {$url}");
            $this->assertStringNotContainsString('"attendance":"no"', $raw, "round-2 draft attendance leaked via {$url}");
        }
    }

    public function test_T4_8_admin_req20_grid_per_student_eligible_applied_appeared_selected(): void
    {
        $this->closeApplications();
        [$r1, $r2] = $this->posting->rounds()->get()->all();
        $this->postJson($this->base()."/rounds/{$r1->id}/attendance", ['roll_nos_present' => $this->rolls(1, 8), 'roll_nos_absent' => $this->rolls(9, 10)])->assertOk();
        $this->postJson($this->base()."/rounds/{$r1->id}/results", ['roll_nos' => $this->rolls(1, 4), 'result' => 'selected'])->assertOk();
        $this->postJson($this->base()."/rounds/{$r1->id}/publish", ['reject_remaining' => true])->assertOk();
        $this->postJson($this->base()."/rounds/{$r2->id}/results", ['roll_nos' => ['23QA0001'], 'result' => 'selected'])->assertOk();

        $payload = $this->getJson($this->base().'/pipeline')->assertOk();
        $this->assertSame([$r1->id, $r2->id, $this->round(2)->id], collect($payload->json('rounds'))->pluck('id')->all(), 'one column per round');
        $grid = collect($payload->json('applications'))->keyBy('student.roll_no');

        // Applied: every live applicant is a row.
        foreach ($this->rolls(1, 10) as $roll) {
            $this->assertTrue($grid->has($roll), "applicant {$roll} in the grid");
        }
        // Appeared + Selected / Not per round, with draft vs published distinction.
        $this->assertSame('yes', $grid['23QA0001']['results'][$r1->id]['attendance']);
        $this->assertSame('no', $grid['23QA0009']['results'][$r1->id]['attendance']);
        $this->assertSame('selected', $grid['23QA0001']['results'][$r1->id]['result']);
        $this->assertSame('rejected', $grid['23QA0005']['results'][$r1->id]['result']);
        $this->assertTrue($grid['23QA0005']['results'][$r1->id]['published']);
        $this->assertSame('selected', $grid['23QA0001']['results'][$r2->id]['result']);
        $this->assertFalse($grid['23QA0001']['results'][$r2->id]['published']);

        // Eligible – Applied / Not Applied: the eligible student who never applied must be visible to the admin
        // (QA F-009 fix: the posting's "Eligible" list, GET /admin/postings/{p}/eligible — not the pipeline grid).
        $notApplied = collect($this->getJson($this->base().'/eligible?status=not_applied')->assertOk()->json('students'));
        $this->assertContains(self::NOT_APPLIED, $notApplied->pluck('roll_no')->all(), 'req-20: an ELIGIBLE-BUT-NOT-APPLIED student must be listed');
        $this->assertFalse($notApplied->firstWhere('roll_no', self::NOT_APPLIED)['applied']);
        $this->assertNotContains('23QA0001', $notApplied->pluck('roll_no')->all());
        $applied = collect($this->getJson($this->base().'/eligible?status=applied')->assertOk()->json('students'))->pluck('roll_no');
        $this->assertContains('23QA0001', $applied->all());
    }
}
