<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Company;
use App\Models\CycleEnrollment;
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
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regressions for the QA findings approved in the owner's QA follow-up and fixed in P-1.13:
 * F-022 (unverified-resume flag on the results console), F-026 (rejecting an approved resume re-flags its live
 * applications), F-028 (unblock is atomic and clears "placed elsewhere" flags a remaining internships-only block does
 * not cover), F-033 (attendance is locked once that student's result in the stage is published).
 */
#[Group('qa')]
class FixApprovedS4LeftoversTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private PlacementCycle $cycle;

    private JobPosting $posting;

    /** @var array<string, Application> */
    private array $apps = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($this->admin);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $this->posting = $this->floatPosting('Acme', 'acme');
        foreach (['22JE0001', '22JE0002', '22JE0003', '22JE0004'] as $roll) {
            $s = StudentProfile::factory()->create(['roll_no' => $roll]);
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $this->apps[$roll] = $this->apply($this->posting, $s);
        }
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    private function floatPosting(string $name, string $slug): JobPosting
    {
        $company = Company::create(['name' => $name, 'hr_name' => 'HR', 'hr_email' => "hr@{$slug}.qa.test"]);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id, 'application_deadline' => now()->addDay()->toIso8601String()])->assertCreated();

        return JobPosting::latest('id')->first();
    }

    private function apply(JobPosting $posting, StudentProfile $s): Application
    {
        $resume = $s->resumes()->first() ?? $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);

        return Application::create(['job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
    }

    private function base(int $i): string
    {
        $round = $this->posting->rounds()->get()[$i];

        return "/api/admin/postings/{$this->posting->id}/rounds/{$round->id}";
    }

    /** Everyone in $rolls clears round 1 (published); the rest are published as not selected. */
    private function clearFirstRound(array $rolls): void
    {
        $this->postJson($this->base(0).'/results', ['roll_nos' => $rolls, 'result' => 'selected'])->assertOk();
        $this->postJson($this->base(0).'/publish', ['reject_remaining' => true])->assertOk();
    }

    private function offerFor(string $roll): array
    {
        return ['application_id' => $this->apps[$roll]->id, 'offer_type' => 'fulltime', 'ctc_annual' => 1800000, 'block' => true, 'block_scope' => 'all'];
    }

    private function publishFinal(array $selections)
    {
        return $this->postJson("/api/admin/postings/{$this->posting->id}/results/publish", ['selections' => $selections, 'reject_remaining' => false]);
    }

    public function test_F022_results_console_shows_the_unverified_resume_flag(): void
    {
        $this->apps['22JE0001']->update(['used_unverified_resume' => true]);
        $this->clearFirstRound(['22JE0001', '22JE0002']);
        $this->postJson($this->base(1).'/results', ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();

        $rows = collect($this->getJson("/api/admin/postings/{$this->posting->id}/results/prepare")->assertOk()->json('selected'))->keyBy('application_id');
        $this->assertTrue($rows[$this->apps['22JE0001']->id]['used_unverified_resume']);
        $this->assertFalse($rows[$this->apps['22JE0002']->id]['used_unverified_resume']);
    }

    public function test_F026_rejecting_an_approved_resume_reflags_its_live_applications(): void
    {
        $live = $this->apps['22JE0001'];
        $resume = $live->resume;
        $other = $this->floatPosting('Globex', 'globex');
        $withdrawn = $this->apply($other, $live->studentProfile);
        $withdrawn->update(['status' => 'withdrawn']);
        $this->assertFalse($live->fresh()->used_unverified_resume);

        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected', 'admin_remark' => 'Fake internship listed'])->assertOk();
        $this->assertTrue($live->fresh()->used_unverified_resume, 'the live application is flagged again');
        $this->assertFalse($withdrawn->fresh()->used_unverified_resume, 'a withdrawn application is left alone');

        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved'])->assertOk();
        $this->assertFalse($live->fresh()->used_unverified_resume, 'approving it again clears the flag');
    }

    public function test_F028_unblock_clears_flags_that_a_remaining_internships_only_block_does_not_cover(): void
    {
        $student = $this->apps['22JE0001']->studentProfile;
        $block = fn (string $scope) => PlacementBlock::create([
            'student_profile_id' => $student->id, 'placement_cycle_id' => $this->cycle->id,
            'scope' => $scope, 'reason' => 'manual', 'active' => true, 'blocked_by' => $this->admin->id,
        ]);
        $all = $block('all');
        $internsOnly = $block('internships_only');
        $this->apps['22JE0001']->update(['placed_elsewhere_flag' => true]); // a live application on a full-time posting

        $this->deleteJson("/api/admin/blocks/{$all->id}")->assertOk();

        $this->assertFalse($this->apps['22JE0001']->fresh()->placed_elsewhere_flag, 'only an internships-only block is left, and this is a full-time posting');
        $this->assertTrue($internsOnly->fresh()->active);
        $this->assertSame(1, \App\Models\AuditLog::where('action', 'block.remove')->sole()->after['placed_elsewhere_flags_cleared']);
    }

    public function test_F028_unblock_keeps_flags_while_another_all_block_is_active(): void
    {
        $student = $this->apps['22JE0001']->studentProfile;
        $block = fn () => PlacementBlock::create([
            'student_profile_id' => $student->id, 'placement_cycle_id' => $this->cycle->id,
            'scope' => 'all', 'reason' => 'manual', 'active' => true, 'blocked_by' => $this->admin->id,
        ]);
        $first = $block();
        $block();
        $this->apps['22JE0001']->update(['placed_elsewhere_flag' => true]);

        $this->deleteJson("/api/admin/blocks/{$first->id}")->assertOk();
        $this->assertTrue($this->apps['22JE0001']->fresh()->placed_elsewhere_flag);
    }

    public function test_F033_attendance_is_locked_once_the_result_is_published(): void
    {
        $url = $this->base(0).'/attendance';
        $this->postJson($url, ['roll_nos_present' => ['22JE0001', '22JE0002']])->assertOk();
        $this->postJson($this->base(0).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base(0).'/publish', ['reject_remaining' => false])->assertOk(); // 22JE0001 published; 22JE0002 still a draft

        $response = $this->postJson($url, ['roll_nos_absent' => ['22JE0001', '22JE0002']])->assertOk();

        $attendance = fn (string $roll) => ApplicationRoundResult::where('application_id', $this->apps[$roll]->id)->value('attendance');
        $this->assertSame('yes', $attendance('22JE0001'), 'published row keeps its attendance');
        $this->assertSame('no', $attendance('22JE0002'), 'unpublished row is still editable');
        $this->assertSame(['22JE0001'], array_column($response->json('errors'), 'roll_no'));
        $this->assertStringContainsString('published', $response->json('errors.0.reason'));

        // Re-sending the same value for a published row is not an error.
        $this->postJson($url, ['roll_nos_present' => ['22JE0001']])->assertOk()->assertJsonCount(0, 'errors');
    }
}
