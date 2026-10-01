<?php

namespace Tests\Feature\QA;

use App\Models\Company;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-005 with the owner's decision (2026-10-01): every admin date-time is IST unless it carries an
 * offset, and is stored as the matching UTC instant — deadlines, round schedules and event start times alike.
 */
#[Group('qa')]
class FixF005TimezoneTest extends TestCase
{
    use RefreshDatabase;

    private PlacementCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
    }

    private function float(string $deadline): TestResponse
    {
        $company = Company::create(['name' => 'Co '.uniqid(), 'hr_name' => 'HR', 'hr_email' => uniqid().'@co.test']);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'Role', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 1, 'form_data' => [
            'jobTitle' => 'Role',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
        ]]);

        return $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id, 'application_deadline' => $deadline]);
    }

    public function test_a_naive_deadline_already_past_in_ist_is_refused(): void
    {
        Carbon::setTestNow(Carbon::parse('2027-01-10T12:00:00Z')); // 17:30 IST

        // 15:00 IST is in the past even though 15:00 UTC would not be.
        $this->float('2027-01-10T15:00')->assertStatus(422)->assertJsonPath('errors.application_deadline.0', 'The application deadline must be in the future.');
        $this->float('2027-01-10T18:00')->assertCreated();
        $this->float('not a date')->assertStatus(422);
    }

    public function test_round_schedule_is_read_as_ist(): void
    {
        $this->float('2027-01-10T18:00')->assertCreated();
        $posting = JobPosting::sole();

        $this->postJson("/api/admin/postings/{$posting->id}/rounds", ['name' => 'HR', 'round_type' => 'hr_interview', 'scheduled_at' => '2027-01-15T10:00'])->assertCreated();
        $round = $posting->rounds()->get()->last();
        $this->assertSame('2027-01-15 04:30:00', DB::table('posting_rounds')->where('id', $round->id)->value('scheduled_at'));

        $this->patchJson("/api/admin/postings/{$posting->id}/rounds/{$round->id}", ['scheduled_at' => '2027-01-16T09:00:00+05:30'])->assertOk();
        $this->assertSame('2027-01-16 03:30:00', DB::table('posting_rounds')->where('id', $round->id)->value('scheduled_at'));

        $this->patchJson("/api/admin/postings/{$posting->id}/rounds/{$round->id}", ['scheduled_at' => null])->assertOk();
        $this->assertNull(DB::table('posting_rounds')->where('id', $round->id)->value('scheduled_at'));
    }

    public function test_event_start_is_read_as_ist(): void
    {
        $id = $this->postJson('/api/admin/events', ['title' => 'PPT', 'event_type' => 'ppt', 'starts_at' => '2027-01-20T16:00'])->assertCreated()->json('event.id');
        $this->assertSame('2027-01-20 10:30:00', DB::table('events')->where('id', $id)->value('starts_at'));

        $this->putJson("/api/admin/events/{$id}", ['title' => 'PPT', 'event_type' => 'ppt', 'starts_at' => '2027-01-21T16:00'])->assertOk();
        $this->assertSame('2027-01-21 10:30:00', DB::table('events')->where('id', $id)->value('starts_at'));
    }
}
