<?php

namespace Tests\Feature;

use App\Mail\OfferMail;
use App\Mail\PortalNoticeMail;
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
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Superset parity S2: editing and revoking announced offers, and uploading CTCs.
 */
class OfferEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlacementCycle $cycle;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin);

        // One full-time cycle that also hosts an internship-type (INF) posting is not allowed, so the INF lives in
        // its own internship cycle; blocks are per cycle, so the FT test uses two JNFs.
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i)]);
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $this->students[] = $s;
        }
    }

    private function posting(string $title, ?PlacementCycle $cycle = null, string $type = 'jnf'): JobPosting
    {
        $cycle ??= $this->cycle;
        $company = Company::create(['name' => "{$title} Co", 'hr_name' => 'HR', 'hr_email' => uniqid().'@co.test']);
        User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => "hr-{$company->id}@co.test"]);
        $data = [
            'jobTitle' => $title,
            'internshipTitle' => $title,
            'currency' => 'INR',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'programmeSalaries' => [['programme' => StudentProfileFactory::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true]],
            'programmeStipends' => [['programme' => StudentProfileFactory::BTECH, 'total' => '80000', 'enabled' => true]],
            'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
        ];
        $form = $type === 'inf'
            ? Inf::create(['company_id' => $company->id, 'internship_title' => $title, 'internship_description' => 'x', 'status' => 'accepted', 'vacancies' => 2, 'form_data' => $data])
            : Jnf::create(['company_id' => $company->id, 'job_title' => $title, 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 2, 'form_data' => $data]);

        $this->postJson('/api/admin/postings', [
            'form_type' => $type, 'form_id' => $form->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function applyAll(JobPosting $posting, array $students): void
    {
        foreach ($students as $s) {
            Application::create([
                'job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $s->resumes()->first()->id,
                'status' => 'applied', 'applied_at' => now(),
            ]);
        }
    }

    /** Close applications and push everyone through round 1 so the final round's pool is the given rolls. */
    private function reachFinal(JobPosting $posting, array $rolls): void
    {
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        $r1 = $posting->rounds()->first();
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r1->id}/results", ['roll_nos' => $rolls, 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r1->id}/publish", ['reject_remaining' => true])->assertOk();
    }

    private function appFor(JobPosting $posting, StudentProfile $s): Application
    {
        return Application::where('job_posting_id', $posting->id)->where('student_profile_id', $s->id)->sole();
    }

    /** Google offers student 1 a Full-Time job (blocked completely); student 1 also applied to Amazon. */
    private function announced(): array
    {
        $google = $this->posting('Google');
        $amazon = $this->posting('Amazon');
        $this->applyAll($google, [$this->students[0], $this->students[1]]);
        $this->applyAll($amazon, [$this->students[0]]);
        $this->reachFinal($google, ['22JE0001', '22JE0002']);
        $a1 = $this->appFor($google, $this->students[0]);
        $a2 = $this->appFor($google, $this->students[1]);
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $a1->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'block' => true, 'block_scope' => 'all'],
            ['application_id' => $a2->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();
        Mail::fake();

        return [$google, $amazon, Offer::where('application_id', $a1->id)->sole(), Offer::where('application_id', $a2->id)->sole()];
    }

    public function test_changing_full_time_to_ppo_offered_lifts_the_block_and_clears_flags(): void
    {
        [$google, $amazon, $offer] = $this->announced();
        $this->assertTrue($this->appFor($amazon, $this->students[0])->placed_elsewhere_flag);

        $preview = $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=ppo_offered")->assertOk();
        $preview->assertJsonPath('changes_blocks', true)->assertJsonPath('new_scope', null)->assertJsonCount(1, 'lift')->assertJsonCount(0, 'create');
        $this->assertSame(1, PlacementBlock::where('active', true)->where('offer_id', $offer->id)->count()); // preview writes nothing

        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered', 'ctc_annual' => 2600000])->assertOk();

        $this->assertSame('ppo_offered', $offer->fresh()->offer_type);
        $this->assertSame(2600000, $offer->fresh()->ctc_annual);
        $this->assertFalse(PlacementBlock::where('offer_id', $offer->id)->where('active', true)->exists());
        $this->assertFalse($this->appFor($amazon, $this->students[0])->placed_elsewhere_flag);
        $this->assertTrue(app(EligibilityService::class)->check($this->students[0]->fresh(), $amazon)['eligible']);

        $log = AuditLog::where('action', 'offer.update')->sole();
        $this->assertSame('fulltime', $log->before['offer_type']);
        $this->assertSame('ppo_offered', $log->after['offer_type']);
        $this->assertSame(1, AuditLog::where('action', 'block.remove')->count());
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->students[0]->user->email));

        // Analytics follow the edited offer (ppo_offered is not "placed", D80).
        $placed = $this->getJson("/api/admin/dashboard/cycle/{$this->cycle->id}")->assertOk()->json();
        $this->assertStringNotContainsString('"offer_type":"fulltime","count":2', json_encode($placed));
    }

    public function test_changing_back_to_a_blocking_type_creates_blocks_and_flags_again(): void
    {
        [, $amazon, $offer] = $this->announced();
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered'])->assertOk();
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern_fulltime'])->assertOk();

        $this->assertSame(1, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->where('scope', 'all')->count());
        $this->assertTrue($this->appFor($amazon, $this->students[0])->placed_elsewhere_flag);

        // Apply blocking off: the type changes, blocks stay as they are.
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered', 'apply_blocking' => false])->assertOk();
        $this->assertSame(1, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count());
    }

    public function test_a_hand_lifted_block_is_reported_by_the_preview(): void
    {
        [, , $offer] = $this->announced();
        $block = PlacementBlock::where('offer_id', $offer->id)->sole();
        $this->deleteJson("/api/admin/blocks/{$block->id}")->assertOk();

        $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=intern_fulltime")->assertOk()->assertJsonPath('had_lifted_blocks', true);
    }

    public function test_ctc_only_change_keeps_blocks_and_notifies_and_no_change_is_422(): void
    {
        [, , $offer] = $this->announced();
        $this->patchJson("/api/admin/offers/{$offer->id}", ['ctc_annual' => 2400000])->assertStatus(422);
        $this->patchJson("/api/admin/offers/{$offer->id}", ['stipend_monthly' => 90000, 'ctc_annual' => null, 'currency' => 'USD'])->assertOk();
        $offer->refresh();
        $this->assertNull($offer->ctc_annual);
        $this->assertSame(90000, $offer->stipend_monthly);
        $this->assertSame('USD', $offer->currency);
        $this->assertSame(1, PlacementBlock::where('offer_id', $offer->id)->where('active', true)->count());
        Mail::assertQueued(PortalNoticeMail::class, 1);
    }

    public function test_revoke_needs_confirmation_lifts_blocks_and_removes_the_offer(): void
    {
        [$google, $amazon, $offer] = $this->announced();
        $this->postJson("/api/admin/offers/{$offer->id}/revoke", ['remark' => 'x'])->assertStatus(422);
        $this->postJson("/api/admin/offers/{$offer->id}/revoke", ['confirm' => true])->assertStatus(422);

        $this->postJson("/api/admin/offers/{$offer->id}/revoke", ['confirm' => true, 'remark' => 'Company withdrew the role'])->assertOk();

        $this->assertNull(Offer::find($offer->id));
        $this->assertSame(1, PlacementBlock::count() - PlacementBlock::where('active', true)->count()); // the row is kept, inactive
        $this->assertFalse($this->appFor($amazon, $this->students[0])->placed_elsewhere_flag);
        $log = AuditLog::where('action', 'offer.revoke')->sole();
        $this->assertSame('fulltime', $log->before['offer_type']);
        $this->assertSame('Company withdrew the role', $log->after['remark']);
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo($this->students[0]->user->email) && str_contains($m->subjectLine, 'revoked'));

        // The revoked student is not pre-ticked for a new offer on the console.
        $row = collect($this->getJson("/api/admin/postings/{$google->id}/results/prepare")->json('selected'))->firstWhere('student.roll_no', '22JE0001');
        $this->assertNull($row['offer']);
        $this->assertTrue($row['published']);
    }

    public function test_upload_ctcs_dry_run_then_apply(): void
    {
        [$google, , $offer1, $offer2] = $this->announced();
        $path = tempnam(sys_get_temp_dir(), 'ctc').'.csv';
        file_put_contents($path, "Roll Number,CTC,Interval,Currency\n22JE0001,3000000,YEAR,\n22JE0002,50000,MONTH,INR\n22JE0003,1,YEAR,\n22JE0001,5,YEAR,\n");
        $file = fn () => new \Illuminate\Http\UploadedFile($path, 'ctc.csv', 'text/csv', null, true);

        $dry = $this->post("/api/admin/postings/{$google->id}/offers/ctc-upload", ['file' => $file(), 'dry_run' => '1'], ['Accept' => 'application/json'])->assertOk();
        $dry->assertJsonPath('dry_run', true)->assertJsonCount(2, 'changes')->assertJsonCount(2, 'errors');
        $this->assertSame(2400000, $offer1->fresh()->ctc_annual);

        $this->post("/api/admin/postings/{$google->id}/offers/ctc-upload", ['file' => $file(), 'dry_run' => '0'], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame(3000000, $offer1->fresh()->ctc_annual);
        $this->assertSame(50000, $offer2->fresh()->stipend_monthly);
        $this->assertSame(2, AuditLog::where('action', 'offer.update')->count());
        @unlink($path);
    }

    public function test_students_and_companies_cannot_edit_or_revoke_offers(): void
    {
        [$google, , $offer] = $this->announced();
        $company = User::where('role', 'company')->where('company_id', $google->company()->id)->first();
        foreach ([$this->students[0]->user, $company] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/admin/offers/{$offer->id}/preview?offer_type=intern")->assertForbidden();
            $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'intern'])->assertForbidden();
            $this->postJson("/api/admin/offers/{$offer->id}/revoke", ['confirm' => true, 'remark' => 'x'])->assertForbidden();
            $this->postJson("/api/admin/postings/{$google->id}/offers/ctc-upload", [])->assertForbidden();
        }
        $this->assertSame('fulltime', $offer->fresh()->offer_type);
    }
}
