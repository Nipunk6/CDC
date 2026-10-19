<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PortalNotification;
use App\Models\PostingRound;
use App\Models\ShortlistProposal;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Company bulk proposals from a sheet (owner decisions 2026-10-20, P3-D56): a per-stage decision sheet with a
 * Shortlisted / On Hold dropdown, roll numbers only, bad rows skipped and reported, the CDC sees and can download the
 * uploaded file, and a roll-number list upload for any proposal type.
 */
class CompanyBulkProposalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private JobPosting $posting;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

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
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
            ],
        ]);

        $this->as($this->admin)->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        foreach (['22JE0001', '22JE0002', '22JE0003', '22JE0004', '22JE0005'] as $roll) {
            $s = StudentProfile::factory()->create(['roll_no' => $roll]);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            Application::create(['job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now()]);
        }
        StudentProfile::factory()->create(['roll_no' => '22JE0099']); // not an applicant
        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
        $this->as($this->companyUser);
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        return $this;
    }

    private function round(int $index): PostingRound
    {
        return $this->posting->rounds()->orderBy('sort_order')->get()[$index];
    }

    private function base(int $round): string
    {
        return "/api/company/postings/{$this->posting->id}/rounds/{$this->round($round)->id}";
    }

    private function sheet(array $lines, string $name = 'decisions.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, implode("\n", $lines));
    }

    private function sendDecisions(int $round, array $lines, bool $dryRun = false)
    {
        return $this->post($this->base($round).'/decision-sheet', ['file' => $this->sheet($lines), 'dry_run' => $dryRun ? '1' : '0'], ['Accept' => 'application/json']);
    }

    public function test_the_decision_sheet_lists_the_stage_with_a_dropdown(): void
    {
        $response = $this->get($this->base(0).'/decision-sheet');
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'ds').'.xlsx';
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $rows = $sheet->toArray();
        $this->assertSame(['Roll No', 'Name', 'Programme', 'Branch', 'CGPA', 'Decision'], $rows[2]);
        $this->assertSame(['22JE0001', '22JE0002', '22JE0003', '22JE0004', '22JE0005'], array_values(array_filter(array_column(array_slice($rows, 3), 0))));

        $validation = $sheet->getCell('F4')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $validation->getType());
        $this->assertSame('"Shortlisted,On Hold"', $validation->getFormula1());
        $this->assertSame(['F4:F58'], array_keys($sheet->getDataValidationCollection()), 'the dropdown covers every student row (4–8) and 50 spare rows');

        // The second stage's sheet is empty until stage 1's shortlist is published (only what the company may see).
        $file = tempnam(sys_get_temp_dir(), 'ds').'.xlsx';
        file_put_contents($file, $this->get($this->base(1).'/decision-sheet')->assertOk()->streamedContent());
        $later = IOFactory::load($file)->getActiveSheet()->toArray();
        @unlink($file);
        $this->assertSame([], array_values(array_filter(array_column(array_slice($later, 3), 0))));
    }

    public function test_preview_then_send_makes_a_shortlist_and_an_on_hold_proposal(): void
    {
        $lines = [
            'Acme · SDE · Aptitude Test: choose Shortlisted or On Hold',
            '',
            'Roll No,Name,Programme,Branch,CGPA,Decision',
            '22je0001,A,B.Tech,CSE,8,Shortlisted',
            '22JE0002,B,B.Tech,CSE,8,On Hold',
            '22JE0003,C,B.Tech,CSE,8,',            // blank → not shortlisted
            '22JE0004,D,B.Tech,CSE,8,Maybe',        // bad value
            '22JE0005,E,B.Tech,CSE,8,shortlist',
            '22JE0001,A,B.Tech,CSE,8,On Hold',      // duplicate
            '22JE0099,X,B.Tech,CSE,8,Shortlisted',  // not an applicant
        ];

        $preview = $this->sendDecisions(0, $lines, true)->assertOk();
        $preview->assertJsonPath('dry_run', true)->assertJsonPath('shortlisted', 2)->assertJsonPath('on_hold', 1)->assertJsonPath('blank', 1);
        $this->assertSame(['22JE0004', '22JE0001', '22JE0099'], array_column($preview->json('errors'), 'roll_no'));
        $this->assertSame(0, ShortlistProposal::count(), 'a preview sends nothing');

        $sent = $this->sendDecisions(0, $lines)->assertCreated();
        $this->assertCount(2, $sent->json('proposals'));
        $shortlist = ShortlistProposal::where('kind', 'shortlist')->sole();
        $onHold = ShortlistProposal::where('kind', 'waitlist')->sole();
        $this->assertSame([['roll_no' => '22JE0001'], ['roll_no' => '22JE0005']], $shortlist->payload);
        $this->assertSame([['roll_no' => '22JE0002']], $onHold->payload);
        $this->assertSame('decisions.csv', $shortlist->source_file_name);
        $this->assertSame($shortlist->source_file_path, $onHold->source_file_path, 'one stored copy for both');
        Storage::disk('local')->assertExists($shortlist->source_file_path);
        $this->assertArrayNotHasKey('source_file_path', $sent->json('proposals.0'), 'the storage path is not shown to the company');
        $this->assertSame(2, PortalNotification::where('user_id', $this->admin->id)->count(), 'the CDC is told of each proposal');

        // The company sees which proposals came from the file; the CDC sees the name and downloads the original.
        $this->assertSame('decisions.csv', $this->getJson("/api/company/postings/{$this->posting->id}/proposals")->json('proposals.0.source_file_name'));
        $this->as($this->admin);
        $admin = collect($this->getJson('/api/admin/proposals')->assertOk()->json('proposals'))->keyBy('kind');
        $this->assertSame('decisions.csv', $admin['shortlist']['source_file_name']);
        $this->assertTrue($admin['shortlist']['has_source_file']);
        $download = $this->get("/api/admin/proposals/{$shortlist->id}/file");
        $download->assertOk();
        $this->assertStringContainsString('22je0001', $download->streamedContent());

        // The CDC approves the shortlist as before: drafts only.
        $this->patchJson("/api/admin/proposals/{$shortlist->id}", ['status' => 'approved'])->assertOk();
        $this->assertSame(2, $this->round(0)->results()->whereNull('published_at')->where('result', 'selected')->count());
    }

    public function test_only_marked_kinds_become_proposals_and_bad_sheets_are_refused(): void
    {
        $this->sendDecisions(0, ['Roll No,Decision', '22JE0001,Shortlisted', '22JE0002,'])->assertCreated();
        $this->assertSame(['shortlist'], ShortlistProposal::pluck('kind')->all(), 'no On Hold rows, no On Hold proposal');

        $this->sendDecisions(0, ['Roll No,Decision', '22JE0099,Shortlisted', '22JE0003,'])->assertStatus(422)->assertJsonPath('errors.0.roll_no', '22JE0099');
        $this->sendDecisions(0, ['Roll No,Name', '22JE0001,A'])->assertStatus(422)->assertJsonFragment(['message' => 'The sheet needs a "Roll No" column and a "Decision" column. Download the decision sheet for this stage and fill it in.']);

        // Stage 2: a student not shortlisted in stage 1 is skipped.
        $this->as($this->admin)->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}/publish", ['reject_remaining' => true])->assertOk();
        $this->as($this->companyUser);
        $response = $this->sendDecisions(1, ['Roll No,Decision', '22JE0001,Shortlisted', '22JE0002,Shortlisted'])->assertCreated();
        $this->assertStringContainsString('not in this stage', $response->json('errors.0.reason'));
        $this->assertSame([['roll_no' => '22JE0001']], ShortlistProposal::where('posting_round_id', $this->round(1)->id)->sole()->payload);
    }

    public function test_roll_number_list_for_any_type_skips_unknown_rows(): void
    {
        $file = fn () => $this->sheet(['Roll No', '22JE0003', '22je0004', '22JE0004', 'NOPE1'], 'rolls.xlsx.csv');
        $url = $this->base(0).'/proposals/upload';

        $this->post($url, ['kind' => 'addendum', 'file' => $file(), 'dry_run' => '1'], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('students', 2)->assertJsonPath('dry_run', true);
        $response = $this->post($url, ['kind' => 'addendum', 'file' => $file()], ['Accept' => 'application/json'])->assertCreated();
        $this->assertSame(['22JE0004', 'NOPE1'], array_column($response->json('errors'), 'roll_no'));
        $proposal = ShortlistProposal::sole();
        $this->assertSame('addendum', $proposal->kind);
        $this->assertSame([['roll_no' => '22JE0003'], ['roll_no' => '22JE0004']], $proposal->payload);

        $this->post($url, ['kind' => 'addendum', 'file' => $this->sheet(['Roll No', 'NOPE1'])], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post($url, ['kind' => 'promote', 'file' => $file()], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('kind');
    }

    public function test_same_refusals_as_proposals_and_tenant_boundaries(): void
    {
        // While applications are open nothing can be proposed.
        $this->posting->fresh()->update(['status' => 'open', 'application_deadline' => now()->addDays(2)]);
        $this->sendDecisions(0, ['Roll No,Decision', '22JE0001,Shortlisted'])->assertStatus(422)->assertJsonFragment(['message' => 'Applications are still open. You can propose candidates once the application window closes.']);
        $this->posting->fresh()->update(['status' => 'in_process', 'application_deadline' => now()->subDay()]);

        // Another company sees nothing of this job profile.
        $other = Company::create(['name' => 'Globex', 'hr_name' => 'HR', 'hr_email' => 'hr@globex.test']);
        $this->as(User::factory()->create(['role' => 'company', 'company_id' => $other->id]));
        $this->get($this->base(0).'/decision-sheet', ['Accept' => 'application/json'])->assertNotFound();
        $this->sendDecisions(0, ['Roll No,Decision', '22JE0001,Shortlisted'])->assertNotFound();

        // The original file is for staff who handle proposals only.
        $this->as($this->companyUser);
        $this->sendDecisions(0, ['Roll No,Decision', '22JE0001,Shortlisted'])->assertCreated();
        $proposal = ShortlistProposal::sole();
        $this->get("/api/admin/proposals/{$proposal->id}/file", ['Accept' => 'application/json'])->assertForbidden();
        $super = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);
        $roleId = $this->as($super)->postJson('/api/admin/roles', ['name' => 'Students only', 'levels' => ['students' => 'read']])->assertCreated()->json('role.id');
        $this->as(User::factory()->create(['role' => 'admin', 'staff_role_id' => $roleId]))->get("/api/admin/proposals/{$proposal->id}/file", ['Accept' => 'application/json'])->assertForbidden();
    }
}
