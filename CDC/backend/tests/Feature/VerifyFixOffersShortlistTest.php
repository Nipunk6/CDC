<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\ExportTemplate;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\ExportFieldCatalogue;
use Carbon\Carbon;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Verification fixes L7–L12 (offer edit blocking default, "M candidates", duplicate and mixed-case identifiers,
 * template shortlist footer, `[addendum]` marker) and the owner note on "Attached Resume".
 */
class VerifyFixOffersShortlistTest extends TestCase
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

        for ($i = 1; $i <= 5; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i)]);
            foreach ([$this->cycleA, $this->cycleB] as $cycle) {
                CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            }
            $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $this->students[] = $s;
        }
    }

    private function posting(string $title, int $rounds = 2): JobPosting
    {
        $company = Company::create(['name' => "{$title} Co", 'hr_name' => 'HR', 'hr_email' => strtolower($title).'@co.test']);
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
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();

        return $posting;
    }

    private function base(JobPosting $posting, int $i): string
    {
        return "/api/admin/postings/{$posting->id}/rounds/{$posting->rounds()->get()[$i]->id}";
    }

    /** A Full-Time offer to 22JE0001 that blocks completely in cycles A and B. */
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

    /** @return list<int> cycle ids with an active block of this offer */
    private function blockedCycles(Offer $offer): array
    {
        return PlacementBlock::where('offer_id', $offer->id)->where('active', true)->orderBy('placement_cycle_id')->pluck('placement_cycle_id')->all();
    }

    private function sheet($response)
    {
        $path = tempnam(sys_get_temp_dir(), 'fix');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    // ------------------------------------------------------------------ L7

    public function test_a_hand_lifted_block_never_comes_back_unless_reapply_blocking_is_true(): void
    {
        $offer = $this->offer();
        $this->assertSame([$this->cycleA->id, $this->cycleB->id], $this->blockedCycles($offer));
        $handLifted = PlacementBlock::where('offer_id', $offer->id)->where('placement_cycle_id', $this->cycleB->id)->sole();
        $this->deleteJson("/api/admin/blocks/{$handLifted->id}")->assertOk();

        // Full-Time → PPO offered: the policy still lifts cycle A's block; nothing to restore (PPO offered never blocks).
        $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=ppo_offered")->assertOk()
            ->assertJsonPath('had_lifted_blocks', true)->assertJsonCount(1, 'lift')->assertJsonCount(0, 'restorable');
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered'])->assertOk();
        $this->assertSame([], $this->blockedCycles($offer));

        // Back to Full-Time with the API default: cycle A (lifted by the offer edit) is blocked again, cycle B is not.
        $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=fulltime")->assertOk()
            ->assertJsonCount(1, 'create')->assertJsonPath('restorable.0.cycle', 'FT B');
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'fulltime'])->assertOk();
        $this->assertSame([$this->cycleA->id], $this->blockedCycles($offer));
        $this->assertSame('default', AuditLog::where('action', 'offer.update')->latest('id')->first()->after['blocking']);

        // The older apply_blocking key never re-applies a hand-lifted block.
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered', 'apply_blocking' => true])->assertOk();
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'fulltime', 'apply_blocking' => true])->assertOk();
        $this->assertSame([$this->cycleA->id], $this->blockedCycles($offer));

        // reapply_blocking: false leaves every block as it is.
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered', 'reapply_blocking' => false])->assertOk();
        $this->assertSame([$this->cycleA->id], $this->blockedCycles($offer));

        // Only an explicit reapply_blocking: true brings the hand-lifted block back (same scope: cycle A's block stays).
        $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=intern_fulltime&reapply_blocking=1")->assertOk()
            ->assertJsonCount(0, 'lift')->assertJsonPath('create.0.cycle', 'FT B');
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern_fulltime', 'reapply_blocking' => true])->assertOk();
        $this->assertSame([$this->cycleA->id, $this->cycleB->id], $this->blockedCycles($offer));
        $log = AuditLog::where('action', 'offer.update')->latest('id')->first();
        $this->assertSame('reapply', $log->after['blocking']);
        $this->assertCount(1, $log->after['blocks_created']);
        $this->assertSame('offer.update', AuditLog::where('action', 'block.create')->latest('id')->first()->after['via']);
        $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=fulltime")->assertOk()->assertJsonPath('had_lifted_blocks', false);
    }

    public function test_upload_ctcs_treats_an_empty_ctc_changed_to_zero_as_a_change(): void
    {
        $offer = $this->offer();
        $offer->update(['ctc_annual' => null, 'stipend_monthly' => 50000]);
        $path = tempnam(sys_get_temp_dir(), 'ctc').'.csv';
        file_put_contents($path, "Roll Number,CTC,Interval\n22JE0001,0,YEAR\n");
        $file = new UploadedFile($path, 'ctc.csv', 'text/csv', null, true);

        $this->post("/api/admin/postings/{$offer->job_posting_id}/offers/ctc-upload", ['file' => $file, 'dry_run' => '1'], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonCount(1, 'changes')->assertJsonPath('changes.0.after.ctc_annual', 0);
        @unlink($path);

        // And through the dialog's PATCH, a string amount is compared as a number: no false "changed".
        $this->patchJson("/api/admin/offers/{$offer->id}", ['stipend_monthly' => '50000'])->assertStatus(422);
    }

    // ------------------------------------------------------------------ L8

    public function test_shortlist_counts_split_the_pool_from_outside_pool_rows(): void
    {
        $p = $this->posting('Acme');
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => false])->assertOk();
        $this->postJson($this->base($p, 1).'/results', ['roll_nos' => ['22JE0001', '22JE0005'], 'result' => 'selected'])->assertOk();

        $this->getJson($this->base($p, 1).'/shortlist')->assertOk()
            ->assertJsonPath('counts.candidates', 2)
            ->assertJsonPath('counts.pool', 2)
            ->assertJsonPath('counts.outside_pool', 1)
            ->assertJsonPath('counts.selected', 2)
            ->assertJsonPath('meta.total', 3);
    }

    // ------------------------------------------------------------------ L9 / L10

    public function test_a_student_listed_twice_is_written_once_and_the_first_entry_wins(): void
    {
        $p = $this->posting('Acme');
        $this->students[2]->update(['institute_email' => 'Mixed.Case@IITISM.test']);

        $response = $this->postJson($this->base($p, 0).'/results', ['entries' => [
            ['roll_no' => '22JE0003', 'result' => 'selected'],
            ['roll_no' => 'mixed.case@iitism.test', 'result' => 'rejected'],
            ['roll_no' => '22je0004', 'result' => 'waitlisted'],
            ['roll_no' => ' 22JE0004 ', 'result' => 'waitlisted'],
        ]])->assertOk();

        $this->assertSame(2, $response->json('written'));
        $errors = collect($response->json('errors'));
        $this->assertCount(2, $errors);
        $this->assertSame('Listed more than once (as 22JE0003 and mixed.case@iitism.test). Only the first entry was used.', $errors->firstWhere('roll_no', '22JE0003')['reason']);
        $this->assertSame('Listed more than once. Only the first entry was used.', $errors->firstWhere('roll_no', '22JE0004')['reason']);

        $results = $p->rounds()->first()->results()->with('application.studentProfile')->get()->mapWithKeys(fn ($r) => [$r->application->studentProfile->roll_no => $r->result]);
        $this->assertSame(['22JE0003' => 'selected', '22JE0004' => 'waitlisted'], $results->sortKeys()->all());
    }

    // ------------------------------------------------------------------ L11 / L12

    public function test_template_downloads_keep_the_addendum_marker_and_the_shortlist_footer(): void
    {
        $this->travelTo(Carbon::parse('2026-10-07 20:00:00', 'UTC'));
        $p = $this->posting('Acme');
        $this->postJson($this->base($p, 0).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base($p, 0).'/publish', ['reject_remaining' => false])->assertOk();
        $this->postJson($this->base($p, 0).'/addendum', ['roll_nos' => ['22JE0002'], 'remark' => 'Late entry'])->assertOk();

        $template = ExportTemplate::create(['name' => 'Mine', 'type' => 'STUDENT_LIST', 'columns' => [['key' => 'roll_no', 'label' => 'Roll'], ['key' => 'stages', 'label' => '']], 'created_by' => $this->admin->id]);
        $sheet = $this->sheet($this->get("/api/admin/postings/{$p->id}/export?template={$template->id}")->assertOk());
        $rows = collect($sheet->toArray(null, false, false))->keyBy(0);
        $this->assertSame('Shortlisted', $rows['22JE0001'][1]);
        $this->assertSame('Shortlisted [addendum] (draft)', $rows['22JE0002'][1]);

        $shortlist = $this->sheet($this->get($this->base($p, 0)."/shortlist/export?template={$template->id}")->assertOk());
        $cells = collect($shortlist->toArray(null, false, false))->flatten()->filter()->map(fn ($v) => (string) $v);
        $this->assertStringContainsString("shortlisted during '{$p->rounds()->first()->name}' to be proceeded to", (string) $shortlist->getCell('A1')->getValue());
        $this->assertSame('Downloaded on 08 Oct 2026, 01:30 AM IST', $cells->last());

        // Other template downloads are unchanged: no footer.
        $applicants = collect($sheet->toArray(null, false, false))->flatten()->filter()->map(fn ($v) => (string) $v);
        $this->assertFalse($applicants->contains(fn ($v) => str_starts_with($v, 'Downloaded on ')));
    }

    public function test_attached_resume_is_admin_only_in_the_field_catalogue(): void
    {
        $this->assertSame('admin', ExportFieldCatalogue::fields()['resume_label']['audience']);
    }
}
