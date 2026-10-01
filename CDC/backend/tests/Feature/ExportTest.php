<?php

namespace Tests\Feature;

use App\Models\Application;
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
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $companyUser;

    private JobPosting $posting;

    private PlacementCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id]);
        $this->cycle = PlacementCycle::create(['name' => 'FT 2026-27', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]]]);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => ['jobTitle' => 'SDE', 'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]]]]);

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
            'questions' => [['question' => 'Languages', 'qtype' => 'mcq_multi', 'options' => ['Go', 'Rust']]],
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        $student = StudentProfile::factory()->create(['roll_no' => '22JE0001', 'phone' => '9111111111', 'personal_email' => 'me@gmail.test', 'home_state' => '=HYPERLINK("http://evil.test","x")']);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);
        Storage::disk('local')->put('resumes/22JE0001/1_x.pdf', '%PDF-1.4 test');
        $resume = $student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/22JE0001/1_x.pdf', 'file_size' => 10, 'status' => 'approved']);
        Application::create([
            'job_posting_id' => $this->posting->id, 'student_profile_id' => $student->id, 'resume_id' => $resume->id,
            'status' => 'applied', 'applied_at' => now(), 'placed_elsewhere_flag' => true,
            'answers' => [['question_id' => $this->posting->questions()->first()->id, 'answer' => ['Go', 'Rust']]],
        ]);
    }

    private function sheet(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $content);
        $book = IOFactory::load($path);
        $rows = $book->getActiveSheet()->toArray();
        $links = [];
        foreach ($book->getActiveSheet()->getHyperlinkCollection() as $cell => $link) {
            $links[$cell] = $link->getUrl();
        }
        unlink($path);

        return [$rows, $links];
    }

    public function test_admin_export_has_everything_and_a_working_signed_link(): void
    {
        $response = $this->get("/api/admin/postings/{$this->posting->id}/export")->assertOk();
        [$rows, $links] = $this->sheet($response->streamedContent());

        $header = $rows[0];
        foreach (['Roll No', 'Phone', 'Placed Elsewhere Flag', 'Q1: Languages', 'R1: HR Interview', 'Resume Link'] as $column) {
            $this->assertContains($column, $header);
        }
        $this->assertSame('22JE0001', $rows[1][0]);
        $this->assertSame('Go, Rust', $rows[1][array_search('Q1: Languages', $header, true)]);
        $this->assertSame('YES', $rows[1][array_search('Placed Elsewhere Flag', $header, true)]);

        // User text starting with "=" stays text, never a formula.
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $book = IOFactory::load($path);
        $sheet = $book->getActiveSheet();
        $col = array_search('Home State', $header, true) + 1;
        $cell = $sheet->getCell([$col, 2]);
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cell->getDataType());
        unlink($path);

        $url = array_values($links)[0];
        $this->app['auth']->forgetGuards();
        $this->get($url)->assertOk();
    }

    public function test_company_export_is_restricted_and_scoped(): void
    {
        Sanctum::actingAs($this->companyUser);
        $response = $this->get("/api/company/postings/{$this->posting->id}/export")->assertOk();
        [$rows] = $this->sheet($response->streamedContent());

        $this->assertNotContains('Phone', $rows[0]);
        $this->assertNotContains('Personal Email', $rows[0]);
        $this->assertNotContains('Placed Elsewhere Flag', $rows[0]);
        $this->assertNotContains('Unverified Resume Flag', $rows[0]);
        $this->assertContains('Resume Link', $rows[0]);

        // Contact details appear only when the admin enabled sharing.
        $this->posting->update(['share_contact_details' => true]);
        [$rows] = $this->sheet($this->get("/api/company/postings/{$this->posting->id}/export")->streamedContent());
        $this->assertContains('Phone', $rows[0]);
        $this->assertContains('9111111111', $rows[1]);

        // Another company's posting → 404.
        $other = Company::create(['name' => 'Other', 'hr_name' => 'HR', 'hr_email' => 'hr@other.test']);
        Sanctum::actingAs(User::factory()->create(['role' => 'company', 'company_id' => $other->id]));
        $this->get("/api/company/postings/{$this->posting->id}/export", ['Accept' => 'application/json'])->assertNotFound();
    }

    public function test_cycle_students_export(): void
    {
        $response = $this->get("/api/admin/placement-cycles/{$this->cycle->id}/students/export")->assertOk();
        [$rows] = $this->sheet($response->streamedContent());
        $this->assertSame('Roll No', $rows[0][0]);
        $this->assertSame('22JE0001', $rows[1][0]);
        $this->assertEquals(1, $rows[1][array_search('Applications (live)', $rows[0], true)]);
    }

    public function test_duplicate_round_names_keep_separate_columns_and_headers_are_text(): void
    {
        $this->posting->rounds()->create(['name' => 'HR Interview', 'round_type' => 'other', 'sort_order' => 2, 'status' => 'pending']);
        $this->posting->rounds()->create(['name' => '=HYPERLINK("http://evil.test","x")', 'round_type' => 'other', 'sort_order' => 3, 'status' => 'pending']);

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->get("/api/admin/postings/{$this->posting->id}/export")->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        $header = $sheet->toArray()[0];
        unlink($path);

        $this->assertContains('R1: HR Interview', $header);
        $this->assertContains('R2: HR Interview', $header);
        $col = array_search('R3: =HYPERLINK("http://evil.test","x")', $header, true);
        $this->assertNotFalse($col);
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $sheet->getCell([$col + 1, 1])->getDataType());
    }

    public function test_student_with_activity_cannot_be_unenrolled_and_audit_keeps_actor_name(): void
    {
        $student = StudentProfile::where('roll_no', '22JE0001')->sole();
        $this->deleteJson("/api/admin/placement-cycles/{$this->cycle->id}/enroll/{$student->id}")->assertStatus(422);

        $log = \App\Models\AuditLog::where('action', 'posting.float')->sole();
        $this->assertSame($this->admin->name, $log->actor_name);
        $this->admin->delete();
        $this->assertSame($log->fresh()->actor_name, $log->actor_name);
        $this->assertNull($log->fresh()->user_id);
    }
}
