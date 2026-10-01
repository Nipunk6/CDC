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

class ResultsAndBlocksTest extends TestCase
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

    public function test_publishing_ft_results_creates_offers_blocks_flags_and_mails(): void
    {
        $google = $this->posting('Google');
        $amazon = $this->posting('Amazon');
        $this->applyAll($google, $this->students);
        $this->applyAll($amazon, [$this->students[0], $this->students[1]]);
        $this->reachFinal($google, ['22JE0001', '22JE0002', '22JE0003']);

        $prepare = $this->getJson("/api/admin/postings/{$google->id}/results/prepare")->assertOk();
        $this->assertSame(0, count($prepare->json('selected')));
        $this->assertSame(3, $prepare->json('regret_estimate'));

        $final = $google->rounds()->where('is_final', true)->first();
        $this->postJson("/api/admin/postings/{$google->id}/rounds/{$final->id}/results", ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();

        $prepare = $this->getJson("/api/admin/postings/{$google->id}/results/prepare")->assertOk();
        $this->assertSame('fulltime', $prepare->json('selected.0.suggested.offer_type'));
        $this->assertSame(2400000, $prepare->json('selected.0.suggested.ctc_annual'));
        $this->assertSame('all', $prepare->json('selected.0.suggested.block.scope'));

        $a1 = $this->appFor($google, $this->students[0]);
        $a2 = $this->appFor($google, $this->students[1]);

        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $a1->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'block' => true, 'block_scope' => 'all'],
            ['application_id' => $a2->id, 'offer_type' => 'ppo_offered', 'ctc_annual' => 2000000, 'block' => false],
        ]])->assertOk()->assertJsonPath('offers', 2)->assertJsonPath('blocks', 1)->assertJsonPath('regrets', 1)->assertJsonPath('flagged', 1);

        $this->assertSame(2, Offer::count());
        $block = PlacementBlock::sole();
        $this->assertSame('all', $block->scope);
        $this->assertSame($this->students[0]->id, $block->student_profile_id);
        $this->assertTrue($this->appFor($amazon, $this->students[0])->placed_elsewhere_flag);
        $this->assertFalse($this->appFor($amazon, $this->students[1])->placed_elsewhere_flag);
        $this->assertSame('completed', $google->fresh()->status);

        Mail::assertQueued(OfferMail::class, 2);
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'rejected' && $m->hasBcc($this->students[2]->user->email) && $m->roundName === 'HR Interview');
        $this->assertSame(2, AuditLog::where('action', 'offer.create')->count());
        $this->assertSame(1, AuditLog::where('action', 'block.create')->count());
        $this->assertSame(1, AuditLog::where('action', 'result.publish')->count());

        // Blocked student is now ineligible for Amazon, with the offer reason; the PPO-offered student is not blocked.
        $service = app(EligibilityService::class);
        $this->assertContains('Blocked: accepted a Full-Time offer.', $service->check($this->students[0]->fresh(), $amazon)['reasons']);
        $this->assertTrue($service->check($this->students[1]->fresh(), $amazon)['eligible']);

        // The student sees the offer and the block.
        Sanctum::actingAs($this->students[0]->user);
        $apps = collect($this->getJson('/api/student/applications')->json('applications'));
        $this->assertSame('fulltime', $apps->firstWhere('posting.id', $google->id)['offer']['offer_type']);
        $this->assertStringContainsString('Full-Time offer', $this->getJson('/api/student/profile')->json('student.active_blocks.0.message'));

        // A second publish cannot double-offer.
        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $a1->id, 'offer_type' => 'fulltime', 'block' => false],
        ]])->assertStatus(422);
    }

    public function test_intern_performance_ppo_blocks_only_internships_and_unblock_restores(): void
    {
        $internCycle = PlacementCycle::create([
            'name' => 'Intern', 'type' => 'internship', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        CycleEnrollment::create(['placement_cycle_id' => $internCycle->id, 'student_profile_id' => $this->students[0]->id, 'status' => 'active']);

        $ft1 = $this->posting('FT One');
        $ft2 = $this->posting('FT Two');
        $this->applyAll($ft1, [$this->students[0]]);
        $this->reachFinal($ft1, ['22JE0001']);

        $this->postJson("/api/admin/postings/{$ft1->id}/results/publish", ['selections' => [
            ['application_id' => $this->appFor($ft1, $this->students[0])->id, 'offer_type' => 'intern_performance_ppo', 'stipend_monthly' => 100000, 'block' => true, 'block_scope' => 'internships_only'],
        ]])->assertOk();

        // Owner rule (QA F-004): intern + performance PPO blocks internship opportunities only — it reaches the
        // internship cycle the student is enrolled in (which holds only internships), full-time stays open.
        $blocks = PlacementBlock::where('reason', 'offer')->get()->keyBy('placement_cycle_id');
        $this->assertCount(2, $blocks);
        $this->assertSame('internships_only', $blocks[$this->cycle->id]->scope);
        $this->assertSame('internships_only', $blocks[$internCycle->id]->scope);

        $service = app(EligibilityService::class);
        $this->assertTrue($service->check($this->students[0]->fresh(), $ft2)['eligible'], 'still eligible for another FT posting');

        $intern = $this->posting('Intern Co', $internCycle, 'inf');
        $this->assertFalse(app(EligibilityService::class)->check($this->students[0]->fresh(), $intern)['eligible'], 'internships are closed');

        // Admin is god: lifting the internship-cycle block restores eligibility; the row is kept.
        $internBlock = $blocks[$internCycle->id];
        $this->deleteJson("/api/admin/blocks/{$internBlock->id}")->assertOk();
        $this->deleteJson("/api/admin/blocks/{$internBlock->id}")->assertStatus(422);
        $this->assertFalse($internBlock->fresh()->active);
        $this->assertNotNull($internBlock->fresh()->unblocked_at);
        $this->assertTrue(app(EligibilityService::class)->check($this->students[0]->fresh(), $intern)['eligible']);
        $this->assertSame(1, AuditLog::where('action', 'block.remove')->count());
        $this->assertSame(2, PlacementBlock::count(), 'blocks are never hard-deleted');
    }

    public function test_remove_from_process_notifies_company(): void
    {
        $google = $this->posting('Google');
        $amazon = $this->posting('Amazon');
        $this->applyAll($google, [$this->students[0]]);
        $this->applyAll($amazon, [$this->students[0]]);
        $this->reachFinal($google, ['22JE0001']);

        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $this->appFor($google, $this->students[0])->id, 'offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk();

        $amazonApp = $this->appFor($amazon, $this->students[0]);
        $this->assertTrue($amazonApp->placed_elsewhere_flag);

        $this->postJson("/api/admin/postings/{$amazon->id}/applications/{$amazonApp->id}/remove-from-process")->assertOk();

        $row = ApplicationRoundResult::where('application_id', $amazonApp->id)->sole();
        $this->assertSame('rejected', $row->result);
        $this->assertSame('Selected elsewhere via CDC', $row->remark);
        $this->assertNotNull($row->published_at);
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => str_contains($m->headline, 'selected elsewhere') && collect($m->lines)->contains(fn ($l) => str_contains($l, 'Replacement request')));
        $this->assertSame(1, AuditLog::where('action', 'application.remove_placed_elsewhere')->count());

        $this->postJson("/api/admin/postings/{$amazon->id}/applications/{$amazonApp->id}/remove-from-process")->assertStatus(422);
    }

    public function test_results_publish_guards(): void
    {
        $google = $this->posting('Google');
        $this->applyAll($google, $this->students);
        $this->patchJson("/api/admin/postings/{$google->id}/close")->assertOk();
        [$r1, $final] = $google->rounds()->get()->all();

        // Round 1 not published yet → refused.
        $this->postJson("/api/admin/postings/{$google->id}/rounds/{$final->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $this->appFor($google, $this->students[0])->id, 'offer_type' => 'fulltime', 'block' => false],
        ]])->assertStatus(422);
        $this->assertNotNull($this->getJson("/api/admin/postings/{$google->id}/results/prepare")->json('blocked_reason'));

        $this->postJson("/api/admin/postings/{$google->id}/rounds/{$r1->id}/results", ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$google->id}/rounds/{$r1->id}/publish", ['reject_remaining' => true])->assertOk();

        // 22JE0004 was not selected in round 1 → no offer for them.
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $this->appFor($google, $this->students[3])->id, 'offer_type' => 'fulltime', 'block' => false],
        ]])->assertStatus(422);

        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $this->appFor($google, $this->students[0])->id, 'offer_type' => 'fulltime', 'block' => false],
        ]])->assertOk()->assertJsonPath('regrets', 1);

        // Nothing left → 422.
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => []])->assertStatus(422);
    }

    public function test_placed_elsewhere_waitlisted_candidate_is_removed_from_the_waitlist_and_lift_clears_flags(): void
    {
        $google = $this->posting('Google');
        $amazon = $this->posting('Amazon');
        $this->applyAll($google, [$this->students[0]]);
        $this->applyAll($amazon, [$this->students[0], $this->students[1]]);

        // Amazon: student 1 waitlisted (published) in round 1.
        $this->patchJson("/api/admin/postings/{$amazon->id}/close")->assertOk();
        $ar1 = $amazon->rounds()->first();
        $this->postJson("/api/admin/postings/{$amazon->id}/rounds/{$ar1->id}/results", ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0002', 'result' => 'waitlisted'],
        ]])->assertOk();
        $this->postJson("/api/admin/postings/{$amazon->id}/rounds/{$ar1->id}/publish")->assertOk();

        $this->reachFinal($google, ['22JE0001']);
        $this->postJson("/api/admin/postings/{$google->id}/results/publish", ['selections' => [
            ['application_id' => $this->appFor($google, $this->students[0])->id, 'offer_type' => 'fulltime', 'block' => true, 'block_scope' => 'all'],
        ]])->assertOk()->assertJsonPath('flagged', 1);

        $amazonApp = $this->appFor($amazon, $this->students[0]);
        $this->postJson("/api/admin/postings/{$amazon->id}/applications/{$amazonApp->id}/remove-from-process")->assertOk();
        $row = ApplicationRoundResult::where('application_id', $amazonApp->id)->where('posting_round_id', $ar1->id)->sole();
        $this->assertSame('rejected', $row->result);
        $this->assertSame($this->appFor($amazon, $this->students[1])->id, ApplicationRoundResult::where('posting_round_id', $ar1->id)->where('result', 'waitlisted')->sole()->application_id);
        $this->assertTrue($this->getJson("/api/admin/postings/{$amazon->id}/applications")->json('applications.0.out_of_process'));

        // Lifting the only block clears remaining placed-elsewhere flags in that cycle.
        $other = $this->posting('Other');
        $this->applyAll($other, [$this->students[0]]);
        $this->appFor($other, $this->students[0])->update(['placed_elsewhere_flag' => true]);
        $this->deleteJson('/api/admin/blocks/'.PlacementBlock::sole()->id)->assertOk();
        $this->assertFalse($this->appFor($other, $this->students[0])->fresh()->placed_elsewhere_flag);
    }

    public function test_debarment_is_always_cycle_wide(): void
    {
        $this->postJson('/api/admin/blocks', [
            'student_profile_id' => $this->students[0]->id, 'placement_cycle_id' => $this->cycle->id,
            'scope' => 'internships_only', 'reason' => 'debarred', 'remark' => 'Misconduct',
        ])->assertCreated()->assertJsonPath('block.scope', 'all');
    }
}
