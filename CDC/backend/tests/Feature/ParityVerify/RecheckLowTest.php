<?php

namespace Tests\Feature\ParityVerify;

use App\Models\Application;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Independent re-check of the LOW verification fixes (L1–L29 except L15). Adversarial probes only; a failing test
 * here is a finding and is kept failing on purpose.
 */
class RecheckLowTest extends TestCase
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
        Storage::fake('local');
        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin);

        $allowed = [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]];
        $this->cycleA = PlacementCycle::create(['name' => 'FT A', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => $allowed]);
        $this->cycleB = PlacementCycle::create(['name' => 'FT B', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => $allowed]);

        for ($i = 1; $i <= 5; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i), 'full_name' => "Student {$i}"]);
            foreach ([$this->cycleA, $this->cycleB] as $cycle) {
                CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            }
            $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $this->students[] = $s;
        }
    }

    private function jnf(string $title, int $rounds = 2, array $extra = []): Jnf
    {
        $company = Company::create(['name' => "{$title} Co", 'hr_name' => 'HR', 'hr_email' => strtolower($title).'@co.test']);
        $types = ['aptitude_test', 'technical_interview', 'hr_interview'];

        return Jnf::create([
            'company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => $extra + [
                'jobTitle' => $title,
                'currency' => 'INR',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'programmeSalaries' => [['programme' => StudentProfileFactory::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true]],
                'selectionRounds' => array_map(fn ($t) => ['type' => $t, 'enabled' => true], array_slice($types, 0, $rounds)),
            ],
        ]);
    }

    private function posting(string $title, int $rounds = 2, bool $close = true): JobPosting
    {
        $jnf = $this->jnf($title, $rounds);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycleA->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();

        $posting = JobPosting::latest('id')->first();
        foreach ($this->students as $s) {
            Application::create([
                'job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $s->resumes()->first()->id,
                'status' => 'applied', 'applied_at' => now(),
            ]);
        }
        if ($close) {
            $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        }

        return $posting;
    }

    private function base(JobPosting $posting, int $i): string
    {
        return "/api/admin/postings/{$posting->id}/rounds/{$posting->rounds()->get()[$i]->id}";
    }

    private function offer(): Offer
    {
        $p = $this->posting('Google');
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => true])->assertOk();
        $application = Application::where('job_posting_id', $p->id)->where('student_profile_id', $this->students[0]->id)->sole();
        $this->postJson("/api/admin/postings/{$p->id}/results/publish", ['selections' => [
            ['application_id' => $application->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();

        return Offer::where('application_id', $application->id)->sole();
    }

    /** @return list<int> */
    private function blockedCycles(Offer $offer): array
    {
        return PlacementBlock::where('offer_id', $offer->id)->where('active', true)->orderBy('placement_cycle_id')->pluck('placement_cycle_id')->unique()->values()->all();
    }

    // ------------------------------------------------------------------ L7

    /**
     * A block lifted by hand stays lifted through a chain of scope changes (all → internships_only → all), and a block
     * an offer edit created and the admin then lifted by hand is also never re-created by the API default.
     */
    public function test_l7_hand_lifted_blocks_survive_a_chain_of_scope_changes_with_the_api_default(): void
    {
        $offer = $this->offer();
        $this->assertSame([$this->cycleA->id, $this->cycleB->id], $this->blockedCycles($offer));

        $b = PlacementBlock::where('offer_id', $offer->id)->where('placement_cycle_id', $this->cycleB->id)->sole();
        $this->deleteJson("/api/admin/blocks/{$b->id}")->assertOk();

        // Full-Time → Internship (internships_only): only the offer's own cycle A is a target.
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern'])->assertOk();
        $this->assertSame([$this->cycleA->id], $this->blockedCycles($offer));

        // Internship → Full-Time (all): A is re-scoped, the hand-lifted B never comes back.
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'fulltime'])->assertOk();
        $this->assertSame([$this->cycleA->id], $this->blockedCycles($offer));
        $this->assertSame('all', PlacementBlock::where('offer_id', $offer->id)->where('active', true)->sole()->scope);

        // Now the admin lifts A by hand too (a block that an offer edit created).
        $a = PlacementBlock::where('offer_id', $offer->id)->where('active', true)->sole();
        $this->deleteJson("/api/admin/blocks/{$a->id}")->assertOk();

        // Any default type change, including one that changes the scope twice, re-creates nothing.
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern_performance_ppo'])->assertOk();
        $this->assertSame([], $this->blockedCycles($offer));
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern_fulltime'])->assertOk();
        $this->assertSame([], $this->blockedCycles($offer));
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'fulltime', 'apply_blocking' => true])->assertOk();
        $this->assertSame([], $this->blockedCycles($offer));
        // A null flag is the default, not "reapply".
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern_ppo', 'reapply_blocking' => null])->assertOk();
        $this->assertSame([], $this->blockedCycles($offer));

        // Only the explicit flag brings them back.
        $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=fulltime")->assertOk()->assertJsonCount(2, 'restorable');
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'fulltime', 'reapply_blocking' => true])->assertOk();
        $this->assertSame([$this->cycleA->id, $this->cycleB->id], $this->blockedCycles($offer));
    }

    // ------------------------------------------------------------------ L9 / L10

    /** One student entered four ways (lower-case roll, roll, institute email in capitals, personal email): written once. */
    public function test_l9_one_student_entered_four_ways_is_written_once_and_three_duplicates_are_reported(): void
    {
        $this->students[0]->update(['institute_email' => 'one.student@iitism.test', 'personal_email' => 'One.Personal@Gmail.test']);
        $p = $this->posting('Acme');

        $response = $this->postJson($this->base($p, 0).'/results', ['entries' => [
            ['roll_no' => '22je0001', 'result' => 'selected'],
            ['roll_no' => '22JE0001', 'result' => 'rejected'],
            ['roll_no' => '  ONE.STUDENT@IITISM.TEST ', 'result' => 'rejected'],
            ['roll_no' => 'one.personal@gmail.test', 'result' => 'waitlisted'],
        ]])->assertOk();

        $response->assertJsonPath('written', 1);
        $duplicates = collect($response->json('errors'))->filter(fn ($e) => str_contains($e['reason'], 'more than once'));
        $this->assertCount(3, $duplicates, json_encode($response->json('errors')));
        $round = $p->rounds()->get()[0];
        $this->assertSame(['selected'], $round->results()->pluck('result')->all(), 'the first entry wins');
    }

    // ------------------------------------------------------------------ L16 / L17

    public function test_l17_student_card_carries_any_stage_published_and_flips_after_the_first_publish(): void
    {
        $p = $this->posting('Initech');
        $student = $this->students[1];

        Sanctum::actingAs($student->user);
        $this->getJson("/api/student/postings/{$p->id}")->assertOk()->assertJsonPath('posting.any_stage_published', false);

        Sanctum::actingAs($this->admin);
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => false])->assertOk();

        Sanctum::actingAs($student->user);
        $this->getJson("/api/student/postings/{$p->id}")->assertOk()->assertJsonPath('posting.any_stage_published', true);
        // Never a count.
        $this->assertArrayNotHasKey('applicant_count', $this->getJson("/api/student/postings/{$p->id}")->json('posting'));
    }

    public function test_l16_apply_and_detail_agree_for_a_scheduled_job_profile(): void
    {
        $jnf = $this->jnf('Later', 1);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycleA->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
            'scheduled_open_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $posting = JobPosting::latest('id')->first();
        $this->assertNotNull($posting->scheduled_open_at, 'fixture: the job profile is scheduled');

        $student = $this->students[2];
        Sanctum::actingAs($student->user);
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $student->resumes()->first()->id])->assertNotFound();
    }

    // ------------------------------------------------------------------ L28

    public function test_l28_eligible_tab_search_treats_percent_and_underscore_literally(): void
    {
        $p = $this->posting('Hooli');
        $base = "/api/admin/postings/{$p->id}/eligible";

        $this->assertGreaterThan(0, $this->getJson($base.'?search=22JE')->assertOk()->json('meta.total'), 'fixture: students are eligible');
        $this->getJson($base.'?search=%25')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($base.'?search=_')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($base.'?search=22JE_001')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_l28_communication_log_subject_search_is_literal(): void
    {
        $p = $this->posting('Pied');
        foreach (['Stage_1 update', 'Stage 1 update', '100% done'] as $i => $subject) {
            EmailLog::create([
                'recipient_email' => "r{$i}@x.test", 'subject' => $subject, 'template' => 't', 'status' => 'sent',
                'job_posting_id' => $p->id, 'kind' => 'stage_email',
            ]);
        }
        $subjects = fn (string $q) => collect($this->getJson("/api/admin/postings/{$p->id}/communications?search=".rawurlencode($q))->assertOk()->json('messages'))
            ->pluck('subject')->filter(fn ($s) => in_array($s, ['Stage_1 update', 'Stage 1 update', '100% done'], true))->sort()->values()->all();

        $this->assertSame(['Stage_1 update'], $subjects('_'));
        $this->assertSame(['100% done'], $subjects('%'));
    }

    public function test_l28_add_new_job_company_picker_is_literal(): void
    {
        Company::create(['name' => 'A_B Corp', 'hr_name' => 'x', 'hr_email' => 'ab@x.test']);
        Company::create(['name' => 'AxB Corp', 'hr_name' => 'x', 'hr_email' => 'axb@x.test']);
        Company::create(['name' => '100% Corp', 'hr_name' => 'x', 'hr_email' => 'pc@x.test']);

        $names = fn (string $q) => collect($this->getJson('/api/admin/form-builder/companies?search='.rawurlencode($q))->assertOk()->json('companies') ?? [])
            ->pluck('name')->sort()->values()->all();

        $this->assertSame(['A_B Corp'], $names('A_B'));
        $this->assertSame(['100% Corp'], $names('%'));
    }

    // ------------------------------------------------------------------ L1 (company-facing mail/notification text)

    /**
     * L1 asks for Class X / Class XII Percentage instead of "10th % / 12th %" in the JNF/INF review screens. The
     * admin's review edit tells the company which fields changed (email + in-app notification + form history), using
     * AdminFormReviewController::detectChangedFields labels.
     */
    public function test_l1_form_edit_change_summary_sent_to_the_company_uses_the_renamed_labels(): void
    {
        $jnf = $this->jnf('Umbrella', 1, ['minTenthPercent' => '60', 'minTwelfthPercent' => '60']);
        $data = $jnf->form_data;
        $data['minTenthPercent'] = '70';
        $data['minTwelfthPercent'] = '75';

        $fields = $this->patchJson("/api/admin/jnfs/{$jnf->id}/form-data", ['form_data' => $data])->assertOk()->json('changed_fields');

        $this->assertNotEmpty($fields);
        foreach ($fields as $label) {
            $this->assertStringNotContainsString('10th %', $label);
            $this->assertStringNotContainsString('12th %', $label);
        }
    }
}
