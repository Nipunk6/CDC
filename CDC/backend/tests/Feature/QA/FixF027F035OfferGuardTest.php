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
 * Regression for QA F-027 (CR-05) and F-035 (P-1.13, owner priority): an offer must never go to a student who
 * already holds an offer, or has an active block that applies, in that placement cycle; the final-round publish
 * must not offer someone whose rejection there was already published (that needs Re-add); and Re-add must not bring
 * back a student who already holds an offer or is blocked.
 */
#[Group('qa')]
class FixF027F035OfferGuardTest extends TestCase
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

    public function test_F027_final_publish_refuses_a_candidate_whose_final_rejection_was_published(): void
    {
        $this->clearFirstRound(['22JE0001', '22JE0002']);
        $final = $this->posting->rounds()->get()[1];
        ApplicationRoundResult::create(['application_id' => $this->apps['22JE0002']->id, 'posting_round_id' => $final->id, 'result' => 'rejected', 'published_at' => now(), 'decided_by' => $this->admin->id]);

        $this->publishFinal([$this->offerFor('22JE0002')])->assertStatus(422)->assertJsonFragment(['message' => '22JE0002 was not selected in the final stage. Change their decision on the final stage\'s shortlist page first.']);
        $this->assertSame(0, Offer::count());

        $this->publishFinal([$this->offerFor('22JE0001')])->assertOk();
        $this->assertSame(1, Offer::count());
    }

    public function test_F035_no_offer_for_a_student_who_already_holds_one_in_the_cycle(): void
    {
        $other = $this->floatPosting('Globex', 'globex');
        $student = $this->apps['22JE0001']->studentProfile;
        $otherApp = $this->apply($other, $student);
        Offer::create([
            'application_id' => $otherApp->id, 'student_profile_id' => $student->id, 'company_id' => $other->company()->id,
            'job_posting_id' => $other->id, 'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'fulltime',
            'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);

        $this->clearFirstRound(['22JE0001', '22JE0002']);
        $this->publishFinal([$this->offerFor('22JE0001')])->assertStatus(422)
            ->assertJsonFragment(['message' => '22JE0001 already holds an offer in this placement cycle (Globex). Revoke that offer first if this is intended.']);
        $this->assertSame(1, Offer::count());

        // The announcement console is told why, so it can disable that candidate's row.
        $this->postJson($this->base(1).'/results', ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();
        $rows = collect($this->getJson("/api/admin/postings/{$this->posting->id}/results/prepare")->assertOk()->json('selected'))->keyBy('application_id');
        $this->assertSame('22JE0001 already holds an offer in this placement cycle (Globex). Revoke that offer first if this is intended.', $rows[$this->apps['22JE0001']->id]['offer_refusal']);
        $this->assertNull($rows[$this->apps['22JE0002']->id]['offer_refusal']);
    }

    public function test_full_time_cycle_allows_one_more_full_time_offer_after_an_intern_performance_ppo(): void
    {
        // Owner decision 2026-10-18: Intern + performance-based PPO, then at most one more offer, and only Full-Time.
        $other = $this->floatPosting('Globex', 'globex');
        $student = $this->apps['22JE0001']->studentProfile;
        $otherApp = $this->apply($other, $student);
        Offer::create([
            'application_id' => $otherApp->id, 'student_profile_id' => $student->id, 'company_id' => $other->company()->id,
            'job_posting_id' => $other->id, 'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'intern_performance_ppo',
            'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);
        $this->clearFirstRound(['22JE0001', '22JE0002']);

        // Not as Intern + Full-Time…
        $this->publishFinal([['offer_type' => 'intern_fulltime'] + $this->offerFor('22JE0001')])->assertStatus(422)
            ->assertJsonFragment(['message' => '22JE0001 holds an Intern + performance-based PPO offer in this placement cycle (Globex), so the only further offer allowed is a Full-Time one.']);
        $this->assertSame(1, Offer::count());

        // …but as Full-Time, yes: two offers now.
        $this->publishFinal([$this->offerFor('22JE0001')])->assertOk();
        $this->assertSame(['fulltime', 'intern_performance_ppo'], Offer::where('student_profile_id', $student->id)->orderBy('offer_type')->pluck('offer_type')->all());

        // A third offer in the cycle is refused, whatever its type.
        $third = $this->floatPosting('Initech', 'initech');
        $this->assertSame(
            '22JE0001 already holds 2 offers in this placement cycle, the most allowed. Revoke one first if this is intended.',
            app(\App\Services\BlockingPolicy::class)->offerRefusal($student->fresh(), $third, null, 'fulltime')
        );

        // The pair must stay Intern + performance-based PPO and Full-Time when an offer is edited.
        $fullTime = Offer::where('student_profile_id', $student->id)->where('offer_type', 'fulltime')->sole();
        $this->patchJson("/api/admin/offers/{$fullTime->id}", ['offer_type' => 'intern_fulltime'])->assertStatus(422)->assertJsonValidationErrors('offer_type');
        $this->assertSame('fulltime', $fullTime->fresh()->offer_type);
    }

    public function test_internship_cycle_still_allows_one_offer_only(): void
    {
        $intern = PlacementCycle::create([
            'name' => 'Intern', 'type' => 'internship', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $this->posting->update(['placement_cycle_id' => $intern->id, 'offer_type' => 'intern']);
        $student = $this->apps['22JE0001']->studentProfile;
        Offer::create([
            'application_id' => $this->apps['22JE0002']->id, 'student_profile_id' => $student->id, 'company_id' => $this->posting->company()->id,
            'job_posting_id' => $this->posting->id, 'placement_cycle_id' => $intern->id, 'offer_type' => 'intern_performance_ppo',
            'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);

        $this->assertStringContainsString('already holds an offer', (string) app(\App\Services\BlockingPolicy::class)->offerRefusal($student, $this->posting->fresh(), null, 'fulltime'));
    }

    public function test_F035_no_offer_while_an_applicable_block_is_active(): void
    {
        $this->clearFirstRound(['22JE0002', '22JE0003', '22JE0004']);
        $block = fn (string $roll, string $scope, string $reason) => PlacementBlock::create([
            'student_profile_id' => $this->apps[$roll]->student_profile_id, 'placement_cycle_id' => $this->cycle->id,
            'scope' => $scope, 'reason' => $reason, 'active' => true, 'blocked_by' => $this->admin->id,
        ]);
        $all = $block('22JE0002', 'all', 'manual');
        $block('22JE0003', 'internships_only', 'debarred');
        $block('22JE0004', 'internships_only', 'manual'); // does not apply to a full-time posting

        $this->publishFinal([$this->offerFor('22JE0002')])->assertStatus(422)->assertJsonPath('message', fn (string $m) => str_starts_with($m, '22JE0002 is blocked in this placement cycle'));
        $this->publishFinal([$this->offerFor('22JE0003')])->assertStatus(422)->assertJsonPath('message', fn (string $m) => str_starts_with($m, '22JE0003 is blocked in this placement cycle'));
        $this->assertSame(0, Offer::count());

        $all->update(['active' => false]);
        $this->publishFinal([$this->offerFor('22JE0002'), $this->offerFor('22JE0004')])->assertOk();
        $this->assertSame(2, Offer::count());
    }

    public function test_F035_bringing_back_refuses_a_student_who_holds_an_offer_or_is_blocked(): void
    {
        $this->clearFirstRound(['22JE0001']); // 22JE0002–4 published as not selected in round 1
        // Bringing back = changing the published "not selected" on the stage (Re-add is gone since 2026-10-18).
        $readd = fn (string $roll) => $this->postJson($this->base(0).'/results', ['roll_nos' => [$roll], 'result' => 'selected']);

        $blocked = PlacementBlock::create([
            'student_profile_id' => $this->apps['22JE0002']->student_profile_id, 'placement_cycle_id' => $this->cycle->id,
            'scope' => 'all', 'reason' => 'manual', 'active' => true, 'blocked_by' => $this->admin->id,
        ]);
        $readd('22JE0002')->assertOk()->assertJsonPath('written', 0)->assertJsonPath('errors.0.reason', fn (string $m) => str_starts_with($m, '22JE0002 is blocked in this placement cycle'));
        $this->assertSame('rejected', ApplicationRoundResult::where('application_id', $this->apps['22JE0002']->id)->value('result'));

        $blocked->update(['active' => false]);
        $readd('22JE0002')->assertOk()->assertJsonPath('changed', 1);
    }
}
