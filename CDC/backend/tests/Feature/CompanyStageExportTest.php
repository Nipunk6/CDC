<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Company portal downloads: one stage's students (shortlisted / not selected / all — published decisions only) and
 * the job profile's final offers.
 */
class CompanyStageExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private JobPosting $posting;

    /** @var list<Application> */
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
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        for ($i = 0; $i < 5; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i + 1)]);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $this->apps[] = Application::create(['job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
        }
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();

        // Stage 1 published: 1 and 2 shortlisted, 3 on hold, 4 not selected; 5 stays undecided.
        $this->postJson($this->stage(0).'/results', ['entries' => [
            ['roll_no' => '22JE0001', 'result' => 'selected'], ['roll_no' => '22JE0002', 'result' => 'selected'],
            ['roll_no' => '22JE0003', 'result' => 'waitlisted'], ['roll_no' => '22JE0004', 'result' => 'rejected'],
        ]])->assertOk();
        $this->postJson($this->stage(0).'/publish', ['reject_remaining' => false])->assertOk();
        // A later DRAFT change is not visible to the company yet: 22JE0002 still counts as shortlisted.
        $this->postJson($this->stage(0).'/results', ['entries' => [['roll_no' => '22JE0002', 'result' => 'rejected']]])->assertOk();
    }

    private function stage(int $index): string
    {
        return "/api/admin/postings/{$this->posting->id}/rounds/{$this->posting->rounds()->get()[$index]->id}";
    }

    /** @return list<string> the roll numbers (first column) of a downloaded workbook, header excluded. */
    private function rolls($response): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        return array_values(array_filter(array_map(fn ($row) => $row[0], array_slice($rows, 1))));
    }

    /** Number of "Open resume" hyperlinks in a downloaded workbook. */
    private function resumeLinks($response): int
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $count = count(IOFactory::load($path)->getActiveSheet()->getHyperlinkCollection());
        unlink($path);

        return $count;
    }

    public function test_company_downloads_a_stage_by_published_decision(): void
    {
        Sanctum::actingAs($this->companyUser);
        $round = $this->posting->rounds()->first();
        $url = "/api/company/postings/{$this->posting->id}/rounds/{$round->id}/export";

        $shortlisted = $this->get($url.'?decision=shortlisted')->assertOk();
        $this->assertSame(['22JE0001', '22JE0002'], $this->rolls($shortlisted));
        $this->assertSame(2, $this->resumeLinks($shortlisted), 'every row links to the resume the student applied with');
        $this->assertSame(['22JE0004'], $this->rolls($this->get($url.'?decision=rejected')->assertOk()));
        $this->assertSame(['22JE0001', '22JE0002', '22JE0003', '22JE0004'], $this->rolls($this->get($url.'?decision=all')->assertOk()));

        $this->getJson($url.'?decision=maybe')->assertStatus(422);
    }

    public function test_company_downloads_the_final_offers(): void
    {
        $offerApp = $this->apps[0];
        Offer::create([
            'application_id' => $offerApp->id, 'student_profile_id' => $offerApp->student_profile_id, 'company_id' => $this->posting->company()->id,
            'job_posting_id' => $this->posting->id, 'placement_cycle_id' => $this->posting->placement_cycle_id, 'offer_type' => 'fulltime',
            'ctc_annual' => 1800000, 'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);

        Sanctum::actingAs($this->companyUser);
        $response = $this->get("/api/company/postings/{$this->posting->id}/offers/export")->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        unlink($path);

        $this->assertSame('Roll No', $rows[0][0]);
        $this->assertNotContains('Phone', $rows[0], 'contact details only when the CDC shares them');
        $this->assertCount(2, array_filter($rows, fn ($r) => $r[0] !== null), 'header + one offer');
        $this->assertSame('22JE0001', $rows[1][0]);
        $this->assertContains('Full-Time', $rows[1]);
        $this->assertSame('Resume Link', end($rows[0]));
        $this->assertSame('Open resume', end($rows[1]));
    }

    public function test_another_company_cannot_download(): void
    {
        $other = Company::create(['name' => 'Globex', 'hr_name' => 'HR', 'hr_email' => 'hr@globex.test']);
        Sanctum::actingAs(User::factory()->create(['role' => 'company', 'company_id' => $other->id]));
        $round = $this->posting->rounds()->first();

        $this->get("/api/company/postings/{$this->posting->id}/rounds/{$round->id}/export?decision=all")->assertNotFound();
        $this->get("/api/company/postings/{$this->posting->id}/offers/export")->assertNotFound();
    }
}
