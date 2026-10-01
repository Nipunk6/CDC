<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-004 with the owner's decision (2026-10-01):
 *  - an internship offer blocks the whole internship cycle; full-time stays open;
 *  - in a full-time cycle a full-time offer, an accepted PPO or an intern + full-time offer blocks the student
 *    COMPLETELY (every cycle they are enrolled in);
 *  - an intern + performance-based PPO blocks internship opportunities only, not full-time;
 *  - the admin picks "Intern + Full-Time" (and "Intern + performance PPO") as the posting's offer category at float.
 */
#[Group('qa')]
class FixF004BlockingTest extends TestCase
{
    use RefreshDatabase;

    private PlacementCycle $ft;

    private PlacementCycle $intern;

    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $make = fn (string $name, string $type) => PlacementCycle::create([
            'name' => $name, 'type' => $type, 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $this->ft = $make('FT', 'fulltime');
        $this->intern = $make('Intern', 'internship');
        $this->student = StudentProfile::factory()->create(['roll_no' => '22JE0001']);
        foreach ([$this->ft, $this->intern] as $c) {
            CycleEnrollment::create(['placement_cycle_id' => $c->id, 'student_profile_id' => $this->student->id, 'status' => 'active']);
        }
        $this->student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
    }

    private function float(string $type, PlacementCycle $cycle, ?string $offerType = null, int $expect = 201): ?JobPosting
    {
        $company = Company::create(['name' => 'Co '.uniqid(), 'hr_name' => 'HR', 'hr_email' => uniqid().'@co.test']);
        $data = [
            'jobTitle' => 'Role', 'internshipTitle' => 'Role', 'currency' => 'INR',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'programmeSalaries' => [['programme' => StudentProfileFactory::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true]],
            'programmeStipends' => [['programme' => StudentProfileFactory::BTECH, 'total' => '80000', 'enabled' => true]],
            'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
        ];
        $form = $type === 'inf'
            ? Inf::create(['company_id' => $company->id, 'internship_title' => 'Role', 'internship_description' => 'x', 'status' => 'accepted', 'vacancies' => 2, 'form_data' => $data])
            : Jnf::create(['company_id' => $company->id, 'job_title' => 'Role', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 2, 'form_data' => $data]);
        $payload = ['form_type' => $type, 'form_id' => $form->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDay()->toIso8601String()];
        if ($offerType) {
            $payload['offer_type'] = $offerType;
        }
        $this->postJson('/api/admin/postings', $payload)->assertStatus($expect);

        return $expect === 201 ? JobPosting::latest('id')->first() : null;
    }

    private function apply(JobPosting $p): Application
    {
        return Application::create(['job_posting_id' => $p->id, 'student_profile_id' => $this->student->id, 'resume_id' => $this->student->resumes()->first()->id, 'status' => 'applied', 'applied_at' => now()]);
    }

    /** Close, then announce the given offer type with the suggested block. */
    private function announce(JobPosting $p, Application $app, ?string $offerType = null): array
    {
        $this->patchJson("/api/admin/postings/{$p->id}/close")->assertOk();
        $prepare = $this->getJson("/api/admin/postings/{$p->id}/results/prepare")->assertOk();
        $this->postJson("/api/admin/postings/{$p->id}/rounds/{$p->rounds()->first()->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $prepare = $this->getJson("/api/admin/postings/{$p->id}/results/prepare")->assertOk();
        $suggested = $prepare->json('selected.0.suggested');
        $type = $offerType ?? $suggested['offer_type'];
        $block = $type === $suggested['offer_type'] ? $suggested['block'] : $prepare->collect('offer_types')->firstWhere('value', $type)['block'];
        $this->postJson("/api/admin/postings/{$p->id}/results/publish", ['selections' => [[
            'application_id' => $app->id, 'offer_type' => $type, 'block' => $block !== null, 'block_scope' => $block['scope'] ?? null,
        ]]])->assertOk();

        return $suggested;
    }

    private function eligible(JobPosting $p): bool
    {
        return app(EligibilityService::class)->check($this->student->fresh(), $p->fresh())['eligible'];
    }

    private function blockedCycles(): array
    {
        return PlacementBlock::where('student_profile_id', $this->student->id)->where('active', true)->pluck('scope', 'placement_cycle_id')->all();
    }

    public function test_full_time_offer_blocks_the_student_completely(): void
    {
        $jnf = $this->float('jnf', $this->ft);
        $otherInf = $this->float('inf', $this->intern);
        $otherApp = $this->apply($otherInf);
        $this->announce($jnf, $this->apply($jnf));

        $this->assertSame([$this->ft->id => 'all', $this->intern->id => 'all'], $this->blockedCycles());
        $this->assertFalse($this->eligible($this->float('jnf', $this->ft)));
        $this->assertFalse($this->eligible($otherInf));
        $this->assertTrue($otherApp->fresh()->placed_elsewhere_flag, 'live applications in the other cycle are flagged too');
    }

    public function test_accepted_ppo_in_the_internship_cycle_blocks_full_time_too(): void
    {
        $inf = $this->float('inf', $this->intern);
        $this->announce($inf, $this->apply($inf), 'intern_ppo');

        $this->assertSame([$this->intern->id => 'all', $this->ft->id => 'all'], $this->blockedCycles());
        $this->assertFalse($this->eligible($this->float('jnf', $this->ft)), 'a student who accepted a PPO is out of full-time placements');
    }

    public function test_internship_offer_blocks_the_whole_internship_cycle_only(): void
    {
        $inf = $this->float('inf', $this->intern);
        $suggested = $this->announce($inf, $this->apply($inf));

        $this->assertSame('intern', $suggested['offer_type']);
        $this->assertSame([$this->intern->id => 'internships_only'], $this->blockedCycles());
        $this->assertFalse($this->eligible($this->float('inf', $this->intern)));
        $this->assertTrue($this->eligible($this->float('jnf', $this->ft)), 'full-time stays open');
    }

    public function test_intern_performance_ppo_category_blocks_internships_not_full_time(): void
    {
        $inf = $this->float('inf', $this->intern, 'intern_performance_ppo');
        $suggested = $this->announce($inf, $this->apply($inf));

        $this->assertSame('intern_performance_ppo', $suggested['offer_type'], 'the float-time category pre-selects the offer type');
        $this->assertFalse($this->eligible($this->float('inf', $this->intern)));
        $this->assertTrue($this->eligible($this->float('jnf', $this->ft)));
    }

    public function test_intern_plus_full_time_category_at_float_blocks_completely(): void
    {
        $jnf = $this->float('jnf', $this->ft, 'intern_fulltime');
        $this->assertSame('intern_fulltime', $jnf->offer_type);
        $suggested = $this->announce($jnf, $this->apply($jnf));

        $this->assertSame('intern_fulltime', $suggested['offer_type']);
        $this->assertSame([$this->ft->id => 'all', $this->intern->id => 'all'], $this->blockedCycles());

        // Students see the category on the job board.
        Sanctum::actingAs($this->student->user);
        $this->assertSame('Intern + Full-Time', $this->getJson('/api/student/postings')->json('postings.0.offer_label'));
    }

    public function test_offer_block_follows_the_student_into_a_cycle_they_join_later(): void
    {
        $jnf = $this->float('jnf', $this->ft);
        $this->announce($jnf, $this->apply($jnf));
        $later = PlacementCycle::create([
            'name' => 'FT phase 2', 'type' => 'fulltime', 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);

        $this->postJson("/api/admin/placement-cycles/{$later->id}/enroll", ['roll_nos' => ['22JE0001']])->assertOk()->assertJsonPath('blocks_carried', 1);
        $this->assertSame('all', $this->blockedCycles()[$later->id]);
        $this->assertFalse($this->eligible($this->float('jnf', $later)), 'placed full-time = blocked in a cycle opened later too');
        $this->assertSame(1, AuditLog::where('action', 'block.create')->where('after->via', 'enrolment')->count());
    }

    public function test_internship_block_follows_into_a_later_internship_cycle_only(): void
    {
        $inf = $this->float('inf', $this->intern);
        $this->announce($inf, $this->apply($inf));
        $make = fn (string $type) => PlacementCycle::create([
            'name' => "Later {$type}", 'type' => $type, 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $laterIntern = $make('internship');
        $laterFt = $make('fulltime');

        $this->postJson("/api/admin/placement-cycles/{$laterIntern->id}/enroll", ['roll_nos' => ['22JE0001']])->assertOk()->assertJsonPath('blocks_carried', 1);
        $this->postJson("/api/admin/placement-cycles/{$laterFt->id}/enroll", ['roll_nos' => ['22JE0001']])->assertOk()->assertJsonPath('blocks_carried', 0);
        $this->assertFalse($this->eligible($this->float('inf', $laterIntern)));
        $this->assertTrue($this->eligible($this->float('jnf', $laterFt)));
    }

    public function test_a_lifted_block_does_not_come_back_on_enrolment(): void
    {
        $jnf = $this->float('jnf', $this->ft);
        $this->announce($jnf, $this->apply($jnf));
        foreach (PlacementBlock::all() as $block) {
            $this->deleteJson("/api/admin/blocks/{$block->id}")->assertOk();
        }
        $later = PlacementCycle::create([
            'name' => 'FT phase 2', 'type' => 'fulltime', 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $this->postJson("/api/admin/placement-cycles/{$later->id}/enroll", ['roll_nos' => ['22JE0001']])->assertOk()->assertJsonPath('blocks_carried', 0);
    }

    public function test_backfill_extends_blocks_created_before_the_rule_change(): void
    {
        // An accepted PPO announced under the old rule: blocked in its own (internship) cycle only, so a live
        // full-time application was never flagged.
        $jnfApp = $this->apply($this->float('jnf', $this->ft));
        $inf = $this->float('inf', $this->intern);
        $this->announce($inf, $this->apply($inf), 'intern_ppo');
        PlacementBlock::where('placement_cycle_id', $this->ft->id)->delete();
        $jnfApp->update(['placed_elsewhere_flag' => false]);
        $this->assertSame([$this->intern->id => 'all'], $this->blockedCycles());

        $this->artisan('placement:extend-offer-blocks')->expectsOutput('1 block(s) added, 1 live application(s) flagged as placed elsewhere.')->assertSuccessful();
        $this->assertSame([$this->intern->id => 'all', $this->ft->id => 'all'], $this->blockedCycles());
        $this->assertTrue($jnfApp->fresh()->placed_elsewhere_flag);
        $this->assertSame(1, AuditLog::where('action', 'block.create')->where('after->via', 'extend-offer-blocks')->count());

        $this->artisan('placement:extend-offer-blocks')->expectsOutput('0 block(s) added, 0 live application(s) flagged as placed elsewhere.')->assertSuccessful();
    }

    public function test_ppo_offered_creates_no_block_anywhere(): void
    {
        $inf = $this->float('inf', $this->intern);
        $this->announce($inf, $this->apply($inf), 'ppo_offered');
        $this->assertSame([], $this->blockedCycles());
    }

    public function test_float_category_must_fit_the_form_type(): void
    {
        $this->float('jnf', $this->ft, 'intern', 422);
        $this->float('inf', $this->intern, 'intern_fulltime', 422);
        $this->assertSame('fulltime', $this->float('jnf', $this->ft)->offer_type);
        $this->assertSame('intern', $this->float('inf', $this->intern)->offer_type);
    }
}
