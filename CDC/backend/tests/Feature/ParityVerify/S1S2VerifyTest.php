<?php

namespace Tests\Feature\ParityVerify;

use App\Mail\PortalNoticeMail;
use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\ExportTemplate;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\PortalNotification;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Independent QA probes for Superset parity S1 (stage shortlist workspace) and S2 (edit / revoke offers), plus the
 * pipeline invariants (D70, D75, D84, D90, D92) that must still hold. A failing probe is a finding.
 */
class S1S2VerifyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlacementCycle $cycleA;

    private PlacementCycle $cycleB;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin);

        $allowed = [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]];
        $this->cycleA = PlacementCycle::create(['name' => 'FT A', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => $allowed]);
        $this->cycleB = PlacementCycle::create(['name' => 'FT B', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => $allowed]);

        for ($i = 1; $i <= 6; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i), 'phone' => '+919000000000']);
            CycleEnrollment::create(['placement_cycle_id' => $this->cycleA->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            CycleEnrollment::create(['placement_cycle_id' => $this->cycleB->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'x.pdf', 'file_size' => 1, 'status' => $i === 1 ? 'pending' : 'approved']);
            $this->students[] = $s;
        }
    }

    // ---------------------------------------------------------------- helpers

    private function posting(string $title, ?PlacementCycle $cycle = null, int $rounds = 3): JobPosting
    {
        $cycle ??= $this->cycleA;
        Sanctum::actingAs($this->admin);
        $company = Company::create(['name' => "{$title} Co", 'hr_name' => 'HR', 'hr_email' => strtolower($title).'@co.test']);
        User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr-'.strtolower($title).'@co.test']);
        $types = ['aptitude_test', 'technical_interview', 'hr_interview'];
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => $title,
                'currency' => 'INR',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'programmeSalaries' => [['programme' => StudentProfileFactory::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true]],
                'selectionRounds' => array_map(fn ($t) => ['type' => $t, 'enabled' => true], array_slice($types, 0, $rounds)),
            ],
        ]);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function apply(JobPosting $posting, array $students): void
    {
        foreach ($students as $s) {
            $resume = $s->resumes()->first();
            Application::create([
                'job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id,
                'status' => 'applied', 'used_unverified_resume' => $resume->status !== 'approved', 'applied_at' => now(),
            ]);
        }
    }

    private function companyUser(JobPosting $posting): User
    {
        return User::where('role', 'company')->where('company_id', $posting->company()->id)->firstOrFail();
    }

    private function round(JobPosting $posting, int $i)
    {
        return $posting->rounds()->get()[$i];
    }

    private function base(JobPosting $posting, int $i): string
    {
        return "/api/admin/postings/{$posting->id}/rounds/{$this->round($posting, $i)->id}";
    }

    private function close(JobPosting $posting): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
    }

    private function app(JobPosting $posting, StudentProfile $s): Application
    {
        return Application::where('job_posting_id', $posting->id)->where('student_profile_id', $s->id)->sole();
    }

    private function row(JobPosting $posting, int $round, StudentProfile $s): ?ApplicationRoundResult
    {
        return ApplicationRoundResult::where('posting_round_id', $this->round($posting, $round)->id)
            ->where('application_id', $this->app($posting, $s)->id)->first();
    }

    private function sheet(string $content)
    {
        $path = tempnam(sys_get_temp_dir(), 'probe');
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    /**
     * Google (cycle A, 2 stages) announces a Full-Time offer to student 1 with a "completely" block. Student 1 also
     * applied to Amazon (cycle A) and Meta (cycle B), so both get the placed-elsewhere flag.
     *
     * @return array{0: JobPosting, 1: JobPosting, 2: JobPosting, 3: Offer}
     */
    private function announced(): array
    {
        $google = $this->posting('Google', $this->cycleA, 2);
        $amazon = $this->posting('Amazon', $this->cycleA, 2);
        $meta = $this->posting('Meta', $this->cycleB, 2);
        $this->apply($google, [$this->students[1], $this->students[2]]);
        $this->apply($amazon, [$this->students[1]]);
        $this->apply($meta, [$this->students[1]]);

        $this->close($google);
        $this->postJson($this->base($google, 0).'/results', ['roll_nos' => ['22JE0002', '22JE0003'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($google, 0).'/publish', ['reject_remaining' => true])->assertOk();
        $a = $this->app($google, $this->students[1]);
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $a->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();
        Mail::fake();

        return [$google, $amazon, $meta, Offer::where('application_id', $a->id)->sole()];
    }

    // ---------------------------------------------------------------- S1 probes

    public function test_per_row_decision_on_a_published_row_is_refused_and_the_row_is_unchanged(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        $this->postJson($this->base($p, 0).'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'],
            ['roll_no' => '22JE0002', 'result' => 'rejected'],
        ]])->assertOk()->assertJsonPath('written', 2);
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => true])->assertOk();

        $this->postJson($this->base($p, 0).'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'rejected'],
            ['roll_no' => '22JE0002', 'result' => 'selected'],
            ['roll_no' => '22JE0003', 'result' => 'pending'],
        ]])->assertOk()->assertJsonPath('written', 0)->assertJsonCount(3, 'errors');

        $this->assertSame('selected', $this->row($p, 0, $this->students[0])->result);
        $this->assertSame('rejected', $this->row($p, 0, $this->students[1])->result);
        $this->assertSame('rejected', $this->row($p, 0, $this->students[2])->result);

        // A published row cannot be "cleared" either.
        $this->deleteJson($this->base($p, 0).'/results/'.$this->app($p, $this->students[0])->id)->assertStatus(422);
        $this->assertNotNull($this->row($p, 0, $this->students[0])?->published_at);
    }

    public function test_strict_toggle_is_off_by_default_warns_outside_pool_and_refuses_when_on(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        // Stage 1: only 22JE0001 shortlisted; the rest stay undecided (no reject_remaining), so 22JE0004 is outside
        // stage 2's pool without having been rejected earlier.
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => false])->assertOk();

        $r2 = $this->base($p, 1).'/results';
        $this->postJson($r2, ['roll_nos' => ['22JE0004'], 'result' => 'selected', 'strict' => true])->assertOk()
            ->assertJsonPath('written', 0)->assertJsonPath('errors.0.roll_no', '22JE0004');
        $this->assertNull($this->row($p, 1, $this->students[3]));

        // Upload path with strict=1 is refused the same way.
        $csv = UploadedFile::fake()->createWithContent('s.csv', "Roll Number,Result\n22JE0004,selected\n");
        $this->post($r2, ['file' => $csv, 'result' => 'selected', 'strict' => '1'], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('written', 0);
        $this->assertNull($this->row($p, 1, $this->students[3]));

        // Default (no flag): saved with a warning (D70(b)).
        $this->postJson($r2, ['roll_nos' => ['22JE0004'], 'result' => 'selected'])->assertOk()
            ->assertJsonPath('written', 1)->assertJsonPath('warnings.0.roll_no', '22JE0004');
        $this->assertSame('selected', $this->row($p, 1, $this->students[3])->result);
        $this->assertNull($this->row($p, 1, $this->students[3])->published_at);

        $log = AuditLog::where('action', 'round.results_draft')->latest('id')->first();
        $this->assertFalse($log->after['strict']);
    }

    public function test_email_identifiers_personal_and_institute_case_insensitive_with_unknown_and_withdrawn_reported(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->students[1]->update(['personal_email' => 'foo.bar@personal.test']);
        $this->app($p, $this->students[2])->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->close($p);

        $response = $this->postJson($this->base($p, 0).'/results', ['roll_nos' => [
            'FOO.BAR@personal.TEST',
            strtoupper($this->students[3]->institute_email),
            $this->students[2]->institute_email, // withdrawn
            'ghost@nowhere.test',
        ], 'result' => 'waitlisted'])->assertOk();

        $this->assertSame(2, $response->json('written'), json_encode($response->json()));
        $errors = collect($response->json('errors'));
        $this->assertTrue($errors->contains(fn ($e) => $e['roll_no'] === 'ghost@nowhere.test'));
        $this->assertTrue($errors->contains(fn ($e) => $e['roll_no'] === '22JE0003' && str_contains($e['reason'], 'Withdrew')));
        $this->assertSame('waitlisted', $this->row($p, 0, $this->students[1])->result);
        $this->assertSame('waitlisted', $this->row($p, 0, $this->students[3])->result);
    }

    /**
     * INFO probe: an email stored in mixed case is matched only because MySQL's *_ci collation compares
     * case-insensitively; the code lower-cases the input but not the column. Fails on SQLite (the test DB).
     */
    public function test_email_identifier_matches_a_mixed_case_stored_email_independent_of_collation(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->students[1]->update(['personal_email' => 'Foo.Bar@Personal.test']);
        $this->close($p);
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['foo.bar@personal.test'], 'result' => 'selected'])
            ->assertOk()->assertJsonPath('written', 1);
    }

    /** The same student named twice (roll number and email) should be written once and the duplicate reported. */
    public function test_same_student_by_roll_and_email_is_counted_once_and_reported(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        $response = $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0002', $this->students[1]->institute_email], 'result' => 'selected'])->assertOk();
        $this->assertSame(1, $response->json('written'), json_encode($response->json()));
        $this->assertNotEmpty(array_merge($response->json('errors'), $response->json('warnings')), 'Duplicate entry was not reported');
    }

    public function test_drafts_are_invisible_and_unmailed_until_publish_then_regrets_go_in_bcc_batches_once(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        Mail::fake(); // forget the opening (E2) mails
        $this->postJson($this->base($p, 0).'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'],
            ['roll_no' => '22JE0002', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0003', 'result' => 'rejected'],
        ]])->assertOk();

        Mail::assertNotQueued(RoundResultMail::class);
        Mail::assertNotSent(RoundResultMail::class);
        Mail::assertNothingQueued();
        Mail::assertNothingSent();

        Sanctum::actingAs($this->students[2]->user);
        $trail = collect($this->getJson('/api/student/applications')->assertOk()->json('applications.0.trail'));
        $this->assertFalse($trail->first()['published']);
        $this->assertStringNotContainsString('rejected', json_encode($trail->first()));

        Sanctum::actingAs($this->companyUser($p));
        $company = $this->getJson("/api/company/postings/{$p->id}/applicants")->assertOk();
        foreach ($company->json('applicants') as $a) {
            $this->assertEmpty($a['rounds']);
        }

        Sanctum::actingAs($this->admin);
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => true])->assertOk();
        // Regrets: 22JE0003 + the 3 undecided, in BCC, the To being the portal's own address.
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'rejected' && count($m->bcc) === 4
            && $m->hasBcc($this->students[2]->user->email) && ! $m->hasTo($this->students[2]->user->email));
        $before = count(Mail::queued(RoundResultMail::class));

        // A second publish with nothing new is refused and mails nobody again.
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => true])->assertStatus(422);
        $this->assertSame($before, count(Mail::queued(RoundResultMail::class)));
    }

    public function test_stages_publish_in_order_and_the_final_stage_only_via_shortlist_for_offer(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        $this->postJson($this->base($p, 1).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 1).'/publish', ['reject_remaining' => false])->assertStatus(422);
        $this->assertNull($this->row($p, 1, $this->students[0])->published_at);

        $this->postJson($this->base($p, 2).'/results', ['roll_nos' => ['22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 2).'/publish', ['reject_remaining' => false])->assertStatus(422)
            ->assertJsonFragment(['message' => 'The final stage is published from the Shortlist for Offer page, where offers are created.']);
    }

    public function test_on_hold_semantics_unchanged_publish_then_promote_moves_into_next_stage_pool(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'waitlisted'])->assertOk();
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => false])->assertOk();
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'waitlisted' && $m->hasBcc($this->students[0]->user->email));

        $this->getJson($this->base($p, 1).'/shortlist')->assertOk()->assertJsonPath('counts.candidates', 0);
        // Writing them straight into stage 2 warns "On Hold at the previous stage".
        $this->postJson($this->base($p, 1).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected', 'strict' => true])
            ->assertOk()->assertJsonPath('written', 0);

        $this->postJson($this->base($p, 0).'/waitlist/'.$this->app($p, $this->students[0])->id.'/promote')->assertOk();
        $row = $this->row($p, 0, $this->students[0]);
        $this->assertSame('selected', $row->result);
        $this->assertNotNull($row->published_at);
        $this->getJson($this->base($p, 1).'/shortlist')->assertOk()->assertJsonPath('counts.candidates', 1);
        $this->assertTrue(AuditLog::where('action', 'waitlist.promote')->exists());
    }

    public function test_shortlist_counter_matches_the_progress_grid_pool_count(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => false])->assertOk();
        // Out-of-pool entry with a warning (D70(b)).
        $this->postJson($this->base($p, 1).'/results', ['roll_nos' => ['22JE0005'], 'result' => 'selected'])->assertOk()->assertJsonPath('written', 1);

        $grid = collect($this->getJson("/api/admin/postings/{$p->id}/pipeline")->assertOk()->json('rounds'))->firstWhere('id', $this->round($p, 1)->id);
        $page = $this->getJson($this->base($p, 1).'/shortlist')->assertOk();
        $this->assertFalse(collect($page->json('candidates'))->firstWhere('student.roll_no', '22JE0005')['in_pool']);
        // "M candidates" should be the same number on the Progress Grid header and the stage page.
        $this->assertSame($grid['pool_count'], $page->json('counts.candidates'), 'Progress Grid says "out of '.$grid['pool_count'].' candidates", the stage page "out of '.$page->json('counts.candidates').' candidates".');
    }

    public function test_stage_of_another_job_profile_is_404_on_every_stage_route(): void
    {
        $a = $this->posting('Acme');
        $b = $this->posting('Beta');
        $this->apply($a, $this->students);
        $this->close($a);
        $foreign = $this->round($b, 0)->id;
        $this->getJson("/api/admin/postings/{$a->id}/rounds/{$foreign}/shortlist")->assertNotFound();
        $this->getJson("/api/admin/postings/{$a->id}/rounds/{$foreign}/shortlist/export")->assertNotFound();
        $this->postJson("/api/admin/postings/{$a->id}/rounds/{$foreign}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertNotFound();
    }

    public function test_every_new_route_refuses_students_companies_and_guests(): void
    {
        [$google, , , $offer] = $this->announced();
        $r = $this->round($google, 0)->id;
        $routes = [
            ['GET', "/api/admin/postings/{$google->id}/rounds/{$r}/shortlist", []],
            ['GET', "/api/admin/postings/{$google->id}/rounds/{$r}/shortlist/export", []],
            ['POST', "/api/admin/postings/{$google->id}/rounds/{$r}/results", ['roll_nos' => ['22JE0003'], 'result' => 'rejected']],
            ['GET', "/api/admin/offers/{$offer->id}/preview?offer_type=ppo_offered", []],
            ['PATCH', "/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered']],
            ['POST', "/api/admin/offers/{$offer->id}/revoke", ['confirm' => true, 'remark' => 'x']],
            ['POST', "/api/admin/postings/{$google->id}/offers/ctc-upload", ['dry_run' => true]],
        ];
        foreach ([$this->students[1]->user, $this->companyUser($google)] as $user) {
            Sanctum::actingAs($user);
            foreach ($routes as [$method, $url, $body]) {
                $this->json($method, $url, $body)->assertForbidden();
            }
        }
        $this->app->make('auth')->forgetGuards();
        foreach ($routes as [$method, $url, $body]) {
            $this->json($method, $url, $body)->assertUnauthorized();
        }
        $this->assertNotNull(Offer::find($offer->id));
        $this->assertSame('fulltime', $offer->fresh()->offer_type);
    }

    public function test_download_current_shortlist_title_rows_ist_footer_and_formula_safety(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-07 20:00:00', 'UTC'));
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        $this->round($p, 0)->update(['name' => '=cmd|\' /C calc\'!A0']);
        $this->students[1]->update(['full_name' => '@SUM(1+1)*cmd']);
        $this->postJson($this->base($p, 0).'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'],
            ['roll_no' => '22JE0002', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0003', 'result' => 'rejected'],
        ]])->assertOk();

        $sheet = $this->sheet($this->get($this->base($p, 0).'/shortlist/export')->assertOk()->streamedContent());
        $title = (string) $sheet->getCell('A1')->getValue();
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A1')->getDataType());
        $this->assertStringContainsString("shortlisted during '=cmd|' /C calc'!A0' to be proceeded to {$this->round($p, 1)->name}", $title);

        $cells = collect($sheet->toArray(null, false, false))->flatten()->filter()->map(fn ($v) => (string) $v);
        $this->assertTrue($cells->contains('Downloaded on 08 Oct 2026, 01:30 AM IST'), 'IST footer missing or wrong: '.$cells->last());
        foreach ($sheet->getRowIterator(3) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $this->assertNotSame(DataType::TYPE_FORMULA, $cell->getDataType(), 'Formula cell at '.$cell->getCoordinate());
            }
        }
        $this->assertTrue($cells->contains('On Hold'));
        $this->assertTrue($cells->contains('Not selected'));
        $this->assertTrue($cells->contains('+919000000000'));
        $this->assertSame(3 + 6 + 2, $sheet->getHighestRow()); // title, blank, header, 6 rows, blank, footer
    }

    public function test_download_current_shortlist_with_a_custom_template_keeps_the_ist_footer(): void
    {
        $p = $this->posting('Acme');
        $this->apply($p, $this->students);
        $this->close($p);
        $template = ExportTemplate::create(['name' => 'Mine', 'type' => 'STUDENT_LIST', 'columns' => [['key' => 'roll_no', 'label' => 'Roll']], 'created_by' => $this->admin->id]);

        $sheet = $this->sheet($this->get($this->base($p, 0)."/shortlist/export?template={$template->id}")->assertOk()->streamedContent());
        $cells = collect($sheet->toArray(null, false, false))->flatten()->filter()->map(fn ($v) => (string) $v);
        $this->assertTrue($cells->contains(fn ($v) => str_contains($v, 'to be proceeded to')));
        $this->assertTrue($cells->contains(fn ($v) => str_starts_with($v, 'Downloaded on ') && str_ends_with($v, 'IST')), 'Custom-template shortlist has no "Downloaded on … IST" footer');
    }

    public function test_company_and_student_payloads_never_expose_admin_only_flags(): void
    {
        [$google, $amazon] = $this->announced();
        $this->assertTrue($this->app($amazon, $this->students[1])->placed_elsewhere_flag);

        Sanctum::actingAs($this->companyUser($amazon));
        $json = $this->getJson("/api/company/postings/{$amazon->id}/applicants")->assertOk()->getContent();
        $this->assertStringNotContainsString('placed_elsewhere', $json);
        $this->assertStringNotContainsString('used_unverified_resume', $json);
        $this->assertStringNotContainsString('offers', $json);

        Sanctum::actingAs($this->students[1]->user);
        $json = $this->getJson('/api/student/applications')->assertOk()->getContent();
        $this->assertStringNotContainsString('placed_elsewhere_flag', $json);
    }

    // ---------------------------------------------------------------- S2 probes

    public function test_full_time_to_ppo_offered_lifts_blocks_in_every_cycle_clears_flags_audits_and_notifies(): void
    {
        [$google, $amazon, $meta, $offer] = $this->announced();
        $this->assertSame(2, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count(), 'Full-Time should block in both cycles (D91)');
        $this->assertTrue($this->app($amazon, $this->students[1])->placed_elsewhere_flag);
        $this->assertTrue($this->app($meta, $this->students[1])->placed_elsewhere_flag);

        $preview = $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=ppo_offered")->assertOk();
        $preview->assertJsonPath('changes_blocks', true)->assertJsonCount(2, 'lift')->assertJsonCount(0, 'create');
        $this->assertSame(2, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count());

        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered'])->assertOk();
        $this->assertSame(0, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count());
        $this->assertSame(2, PlacementBlock::where('offer_id', $offer->id)->where('active', false)->whereNotNull('unblocked_at')->count());
        $this->assertFalse($this->app($amazon, $this->students[1])->placed_elsewhere_flag);
        $this->assertFalse($this->app($meta, $this->students[1])->placed_elsewhere_flag);

        $log = AuditLog::where('action', 'offer.update')->sole();
        $this->assertSame('fulltime', $log->before['offer_type']);
        $this->assertSame('ppo_offered', $log->after['offer_type']);
        $this->assertSame(2, $log->after['placed_elsewhere_flags_cleared']);
        $this->assertSame(2, AuditLog::where('action', 'block.remove')->count());
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->students[1]->user->email));
        $this->assertTrue(PortalNotification::where('user_id', $this->students[1]->user_id)->exists());

        // And back: blocks and flags return in both cycles.
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'fulltime'])->assertOk();
        $this->assertSame(2, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count());
        $this->assertTrue($this->app($amazon, $this->students[1])->placed_elsewhere_flag);
        $this->assertTrue($this->app($meta, $this->students[1])->placed_elsewhere_flag);
    }

    public function test_full_time_to_internship_keeps_internship_scope_only_and_clears_full_time_flags(): void
    {
        [$google, $amazon, $meta, $offer] = $this->announced();
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern'])->assertOk();

        $active = PlacementBlock::where('offer_id', $offer->id)->where('active', true)->get();
        $this->assertSame(['internships_only'], $active->pluck('scope')->unique()->values()->all());
        $this->assertSame([$this->cycleA->id], $active->pluck('placement_cycle_id')->all()); // cycle B is full-time
        $this->assertFalse($this->app($amazon, $this->students[1])->placed_elsewhere_flag);
        $this->assertFalse($this->app($meta, $this->students[1])->placed_elsewhere_flag);
    }

    public function test_revoke_lifts_only_its_own_blocks_keeps_flags_another_block_still_covers_and_notifies(): void
    {
        [$google, $amazon, $meta, $offer] = $this->announced();
        $manual = PlacementBlock::create([
            'student_profile_id' => $this->students[1]->id, 'placement_cycle_id' => $this->cycleA->id, 'scope' => 'all',
            'reason' => 'manual', 'active' => true, 'blocked_by' => $this->admin->id,
        ]);

        $this->postJson("/api/admin/offers/{$offer->id}/revoke", ['confirm' => false, 'remark' => 'x'])->assertStatus(422);
        $this->postJson("/api/admin/offers/{$offer->id}/revoke", ['confirm' => true, 'remark' => '   '])->assertStatus(422);
        $this->assertNotNull(Offer::find($offer->id));

        $this->postJson("/api/admin/offers/{$offer->id}/revoke", ['confirm' => true, 'remark' => 'Role withdrawn'])->assertOk();
        $this->assertNull(Offer::find($offer->id));
        $this->assertTrue($manual->fresh()->active, 'A manual block must survive the revoke');
        $this->assertSame(1, PlacementBlock::where('active', true)->where('student_profile_id', $this->students[1]->id)->count());
        $this->assertTrue($this->app($amazon, $this->students[1])->placed_elsewhere_flag, 'Cycle A is still blocked by the manual block');
        $this->assertFalse($this->app($meta, $this->students[1])->placed_elsewhere_flag);

        $log = AuditLog::where('action', 'offer.revoke')->sole();
        $this->assertSame('fulltime', $log->before['offer_type']);
        $this->assertSame('Role withdrawn', $log->after['remark']);
        $this->assertSame(2, AuditLog::where('action', 'block.remove')->count());
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->students[1]->user->email) && str_contains($m->subjectLine, 'revoked'));

        // The revoked offer is gone from analytics-style readers: the Progress Grid no longer shows "Offered".
        $apps = collect($this->getJson("/api/admin/postings/{$google->id}/pipeline")->assertOk()->json('applications'));
        $this->assertFalse($apps->firstWhere('student.roll_no', '22JE0002')['offer_here']);
    }

    public function test_upload_ctcs_dry_run_writes_nothing_rejects_formula_cells_and_never_touches_blocks(): void
    {
        [$google, , , $offer] = $this->announced();
        $make = fn () => UploadedFile::fake()->createWithContent('ctc.csv', "Roll Number,CTC,CTC Interval,Currency\n22JE0002,3000000,YEAR,usd\n22JE0003,=1+1,YEAR,\n");

        $this->post("/api/admin/postings/{$google->id}/offers/ctc-upload", ['file' => $make(), 'dry_run' => '1'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(1, 'changes')->assertJsonCount(1, 'errors');
        $this->assertSame(2400000, $offer->fresh()->ctc_annual);
        $this->assertSame(0, AuditLog::where('action', 'offer.update')->count());

        $this->post("/api/admin/postings/{$google->id}/offers/ctc-upload", ['file' => $make(), 'dry_run' => '0'], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(3000000, $offer->fresh()->ctc_annual);
        $this->assertSame('USD', $offer->fresh()->currency);
        $this->assertSame(2, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count());
        $this->assertSame('upload', AuditLog::where('action', 'offer.update')->sole()->after['source']);
    }

    public function test_changing_an_empty_ctc_to_zero_is_not_reported_as_nothing_changed(): void
    {
        [, , , $offer] = $this->announced();
        $offer->update(['ctc_annual' => null, 'stipend_monthly' => 50000]);
        $this->patchJson("/api/admin/offers/{$offer->id}", ['ctc_annual' => 0])->assertOk();
    }

    public function test_type_change_ignores_hand_lifted_blocks_when_the_api_default_is_used(): void
    {
        // D109(b)/D91: a block the admin lifted by hand should not silently come back. The dialog unticks the box, but
        // the endpoint defaults apply_blocking to true.
        [, , , $offer] = $this->announced();
        foreach (PlacementBlock::where('offer_id', $offer->id)->get() as $block) {
            $this->deleteJson("/api/admin/blocks/{$block->id}")->assertOk();
        }
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern_fulltime'])->assertOk();
        $this->assertSame(0, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count(), 'Hand-lifted blocks were re-created by a PATCH that did not ask for it');
    }

    // ---------------------------------------------------------------- B2-11 Reconcile Ineligible Students

    public function test_reconcile_ineligible_students_action_exists(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())->map(fn ($r) => $r->uri())->filter(fn ($u) => str_contains(strtolower($u), 'reconcile'));
        $this->assertNotEmpty($routes->all(), 'No "Reconcile Ineligible Students" route exists (owner answer B2-11: YES, per-stage admin action with reasons report, Excel and regret mail).');
    }
}
