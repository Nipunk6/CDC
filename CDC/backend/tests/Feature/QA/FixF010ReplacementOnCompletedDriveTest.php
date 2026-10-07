<?php

namespace Tests\Feature\QA;

use App\Models\Application;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-010 (CR-04): E9 tells the company to send a "Replacement request" when a selected student
 * is placed elsewhere — that must still work after the drive is completed, all the way to a new offer.
 */
#[Group('qa')]
class FixF010ReplacementOnCompletedDriveTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_drive_takes_a_replacement_request_through_to_an_offer(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $hr = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 1, 'form_data' => [
            'jobTitle' => 'SDE',
            'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
        ]]);
        Sanctum::actingAs($admin);
        $this->postJson('/api/admin/postings', ['form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id, 'application_deadline' => now()->addDay()->toIso8601String()])->assertCreated();
        $posting = JobPosting::sole();
        $apps = [];
        foreach (['22JE0001', '22JE0002'] as $roll) {
            $s = StudentProfile::factory()->create(['roll_no' => $roll]);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $apps[$roll] = Application::create(['job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
        }
        $final = $posting->rounds()->sole();
        $base = "/api/admin/postings/{$posting->id}";
        $this->patchJson("{$base}/close")->assertOk();
        $this->postJson("{$base}/rounds/{$final->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("{$base}/results/publish", ['selections' => [['application_id' => $apps['22JE0001']->id, 'offer_type' => 'fulltime', 'block' => false]]])->assertOk();
        $this->assertSame('completed', $posting->fresh()->status);

        // Company: a shortlist is refused on a completed drive, a replacement request is accepted.
        Sanctum::actingAs($hr);
        $propose = fn (string $kind) => $this->postJson("/api/company/postings/{$posting->id}/rounds/{$final->id}/proposals", ['kind' => $kind, 'entries' => [['roll_no' => '22JE0002']]]);
        $propose('shortlist')->assertStatus(422)->assertJsonPath('message', 'This job profile is completed. You can still send a replacement request or an addendum.');
        $propose('replacement_request')->assertCreated();

        // CDC: approve (22JE0002 was published "not selected", so Re-add is the way back in), then offer.
        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/proposals/'.ShortlistProposal::sole()->id, ['status' => 'approved'])->assertOk();
        $this->postJson("{$base}/rounds/{$final->id}/readd/{$apps['22JE0002']->id}", ['confirm' => true, 'remark' => 'Replacement request from the company'])->assertOk();
        $this->assertContains('22JE0002', collect($this->getJson("{$base}/results/prepare")->assertOk()->json('selected'))->pluck('student.roll_no')->all());
        $this->postJson("{$base}/results/publish", ['selections' => [['application_id' => $apps['22JE0002']->id, 'offer_type' => 'fulltime', 'block' => false]]])->assertOk();
        $this->assertSame(2, Offer::count());
    }
}
