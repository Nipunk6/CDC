<?php

namespace Tests\Feature\QA;

use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
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
 * Regression for QA F-002 (CR-01): "Move to next round" must PROMOTE the waitlisted student within the round they
 * were waitlisted in (published "selected", mailed), so they enter the next round's pool like everyone else — it
 * must never pre-decide the next round for them.
 */
#[Group('qa')]
class FixF002WaitlistPromotionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private JobPosting $posting;

    /** @var array<string, Application> */
    private array $apps = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);
        $cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'group_discussion', 'enabled' => true], ['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDay()->toIso8601String()])->assertCreated();
        $this->posting = JobPosting::sole();
        foreach (['22JE0001', '22JE0002', '22JE0003', '22JE0004'] as $roll) {
            $s = StudentProfile::factory()->create(['roll_no' => $roll]);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $this->apps[$roll] = Application::create(['job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
        }
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    private function round(int $i)
    {
        return $this->posting->rounds()->get()[$i];
    }

    private function base(int $i): string
    {
        return "/api/admin/postings/{$this->posting->id}/rounds/{$this->round($i)->id}";
    }

    public function test_move_promotes_within_the_waitlisted_round_and_the_next_round_still_decides(): void
    {
        $this->postJson($this->base(0).'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'],
            ['roll_no' => '22JE0002', 'result' => 'waitlisted'],
            ['roll_no' => '22JE0004', 'result' => 'waitlisted'],
        ]])->assertOk();
        $this->postJson($this->base(0).'/publish', ['reject_remaining' => true])->assertOk();
        $this->postJson($this->base(1).'/publish', [])->assertStatus(422); // nothing decided in round 2 yet

        // Move the LAST waitlisted student on.
        $this->postJson($this->base(0).'/waitlist/'.$this->apps['22JE0004']->id.'/promote')->assertOk();

        $row = ApplicationRoundResult::where('application_id', $this->apps['22JE0004']->id)->where('posting_round_id', $this->round(0)->id)->sole();
        $this->assertSame('selected', $row->result, 'promotion happens in the round the student was waitlisted in');
        $this->assertNotNull($row->published_at);
        $this->assertSame(0, ApplicationRoundResult::where('posting_round_id', $this->round(1)->id)->count(), 'nothing is pre-decided in the next round');
        Mail::assertQueued(RoundResultMail::class, fn ($m) => $m->outcome === 'selected' && $m->hasBcc($this->apps['22JE0004']->studentProfile->user->email) && $m->nextRound === $this->round(1)->name);
        $this->assertSame(1, AuditLog::where('action', 'waitlist.promote')->count());

        // Round 2: only 22JE0001 is entered; "mark everyone else" must now reach the promoted student too.
        $this->postJson($this->base(1).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk()->assertJsonCount(0, 'warnings');
        $this->postJson($this->base(1).'/publish', ['reject_remaining' => true])->assertOk()->assertJsonPath('counts.rejected', 1);
        $this->assertSame('rejected', ApplicationRoundResult::where('application_id', $this->apps['22JE0004']->id)->where('posting_round_id', $this->round(1)->id)->value('result'));

        // The still-waitlisted 22JE0002 is not in round 2's pool: writing a round-2 result for them is flagged.
        $this->postJson($this->base(2).'/results', ['roll_nos' => ['22JE0002'], 'result' => 'selected'])->assertOk()->assertJsonCount(1, 'warnings');
    }

    public function test_promote_guards(): void
    {
        $this->postJson($this->base(0).'/results', ['entries' => [['roll_no' => '22JE0001', 'result' => 'selected'], ['roll_no' => '22JE0002', 'result' => 'waitlisted']]])->assertOk();
        // Not published yet → refuse (the admin simply edits the draft instead).
        $this->postJson($this->base(0).'/waitlist/'.$this->apps['22JE0002']->id.'/promote')->assertStatus(422);
        $this->postJson($this->base(0).'/publish', [])->assertOk();
        // Not on the waitlist → refuse.
        $this->postJson($this->base(0).'/waitlist/'.$this->apps['22JE0001']->id.'/promote')->assertStatus(422);
        // Companies can never promote.
        Sanctum::actingAs($this->companyUser);
        $this->postJson($this->base(0).'/waitlist/'.$this->apps['22JE0002']->id.'/promote')->assertForbidden();
    }

    public function test_publish_never_publishes_a_later_result_for_someone_rejected_earlier(): void
    {
        // QA F-032 / CR-13 (fixed with F-002): a stray later-round draft for a student published "not selected"
        // earlier is not published.
        $this->postJson($this->base(0).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base(0).'/publish', ['reject_remaining' => true])->assertOk();
        ApplicationRoundResult::create(['application_id' => $this->apps['22JE0002']->id, 'posting_round_id' => $this->round(1)->id, 'result' => 'selected']);
        $this->postJson($this->base(1).'/results', ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();

        $this->postJson($this->base(1).'/publish', [])->assertOk()->assertJsonPath('counts.selected', 1);
        $this->assertNull(ApplicationRoundResult::where('application_id', $this->apps['22JE0002']->id)->where('posting_round_id', $this->round(1)->id)->value('published_at'));
    }

    public function test_final_round_console_never_pre_ticks_an_undecided_student(): void
    {
        $this->postJson($this->base(0).'/results', ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson($this->base(0).'/publish', [])->assertOk();
        $this->postJson($this->base(1).'/results', ['entries' => [['roll_no' => '22JE0001', 'result' => 'selected'], ['roll_no' => '22JE0002', 'result' => 'waitlisted']]])->assertOk();
        $this->postJson($this->base(1).'/publish', [])->assertOk();
        $this->postJson($this->base(1).'/waitlist/'.$this->apps['22JE0002']->id.'/promote')->assertOk();

        $prepare = $this->getJson("/api/admin/postings/{$this->posting->id}/results/prepare")->assertOk();
        $this->assertSame([], $prepare->json('selected'), 'nobody has a final-round decision yet, so nobody is offered');
    }
}
