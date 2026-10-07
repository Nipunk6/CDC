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
 * Superset parity S8.5: placement reports.
 */
class ReportsTest extends TestCase
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

    private function sheet($response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'rep');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    public function test_each_report_downloads_with_the_right_rows(): void
    {
        [$google, , $offer] = $this->announced();
        $this->patchJson("/api/admin/offers/{$offer->id}", ['offer_type' => 'ppo_offered'])->assertOk(); // student 1 → not placed (D80)
        $r1 = $google->rounds()->first();
        ApplicationRoundResult::query()->where('posting_round_id', $r1->id)->first()->update(['attendance' => 'no']);

        $this->getJson('/api/admin/reports')->assertOk()->assertJsonCount(6, 'reports');
        $base = "/api/admin/placement-cycles/{$this->cycle->id}/reports";

        $offers = $this->sheet($this->get("{$base}/job_offers")->assertOk());
        $this->assertSame('S.No.', $offers->getCell('A3')->getValue());
        $this->assertEquals(2, $offers->getCell('A5')->getValue()); // two offers on rows 4-5
        $this->assertNull($offers->getCell('A6')->getValue());
        $this->assertStringStartsWith('Downloaded on ', (string) $offers->getCell('A7')->getValue());

        $placed = collect($this->sheet($this->get("{$base}/students_placed")->assertOk())->toArray())->flatten();
        $this->assertTrue($placed->contains('22JE0002'));
        $this->assertFalse($placed->contains('22JE0001'));
        $notPlaced = collect($this->sheet($this->get("{$base}/students_not_placed")->assertOk())->toArray())->flatten();
        $this->assertTrue($notPlaced->contains('22JE0001'));
        $this->assertTrue($notPlaced->contains('22JE0003'));
        $this->assertStringContainsString('(3 of 4 enrolled)', $this->sheet($this->get("{$base}/students_not_placed"))->getCell('A1')->getValue());

        $matrix = $this->sheet($this->get("{$base}/placement_matrix")->assertOk());
        $this->assertContains('Google Co', collect($matrix->rangeToArray('A3:Z3')[0])->filter()->all());
        // Placing offers only (D80, fix L13): student 1's "PPO offered" is not counted.
        $headers = collect($matrix->rangeToArray('A3:Z3')[0])->filter()->values();
        $totalCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($headers->search('Total Offers') + 1);
        $this->assertEquals(1, collect(range(4, $matrix->getHighestDataRow()))->sum(fn ($r) => (int) $matrix->getCell($totalCol.$r)->getValue()));

        $absent = collect($this->sheet($this->get("{$base}/absentees")->assertOk())->toArray())->flatten();
        $this->assertTrue($absent->contains($r1->name));

        $this->get("{$base}/job_profiles")->assertOk();
        $this->get("{$base}/nope")->assertNotFound();
        $this->assertSame(7, AuditLog::where('action', 'report.download')->count());
    }

    public function test_reports_are_admin_only(): void
    {
        $company = User::factory()->create(['role' => 'company', 'company_id' => Company::create(['name' => 'X', 'hr_name' => 'h', 'hr_email' => 'x@x.test'])->id]);
        foreach ([$this->students[0]->user, $company] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/reports')->assertForbidden();
            $this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/reports/job_offers")->assertForbidden();
        }
    }
}
