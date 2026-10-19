<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * P6.2 stage attendance by Excel (owner decisions 2026-10-19, P3-D53): download the stage sheet, upload it with a
 * preview, Present / Absent only, blank = unmarked, corrections after publishing allowed, the company sees attendance
 * only where the stage's result is published.
 */
class AttendanceUploadTest extends TestCase
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
                'selectionRounds' => [['type' => 'aptitude_test', 'enabled' => true], ['type' => 'hr_interview', 'enabled' => true]],
            ],
        ]);

        $this->as($this->admin)->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        foreach (['22JE0001', '22JE0002', '22JE0003', '22JE0004'] as $roll) {
            $s = StudentProfile::factory()->create(['roll_no' => $roll]);
            CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $this->apps[$roll] = Application::create([
                'job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id, 'status' => 'applied', 'applied_at' => now(),
            ]);
        }
        $this->outsider = StudentProfile::factory()->create(['roll_no' => '22JE0099']); // not an applicant

        $this->patchJson("/api/admin/postings/{$this->posting->id}/close")->assertOk();
    }

    private StudentProfile $outsider;

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

    private function url(int $round, string $suffix = ''): string
    {
        return "/api/admin/postings/{$this->posting->id}/rounds/{$this->round($round)->id}/attendance{$suffix}";
    }

    private function sheet(array $lines): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('attendance.csv', implode("\n", $lines));
    }

    private function upload(int $round, array $lines, bool $dryRun = false)
    {
        return $this->post($this->url($round, '/upload'), ['file' => $this->sheet($lines), 'dry_run' => $dryRun ? '1' : '0'], ['Accept' => 'application/json']);
    }

    private function attendance(string $roll, int $round = 0): ?string
    {
        return ApplicationRoundResult::where('application_id', $this->apps[$roll]->id)->where('posting_round_id', $this->round($round)->id)->value('attendance');
    }

    public function test_the_sheet_lists_the_stage_students_with_their_current_marks(): void
    {
        $this->postJson($this->url(0), ['roll_nos_present' => ['22JE0002']])->assertOk();

        $response = $this->get($this->url(0, '/template'));
        $response->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'att').'.xlsx';
        file_put_contents($file, $response->streamedContent());
        $rows = IOFactory::load($file)->getActiveSheet()->toArray();
        @unlink($file);

        $this->assertSame(['Roll No', 'Name', 'Programme', 'Branch', 'Batch', 'Attendance'], $rows[2]);
        $data = array_values(array_filter(array_slice($rows, 3), fn ($r) => preg_match('/^22JE/', (string) $r[0])));
        $this->assertSame(['22JE0001', '22JE0002', '22JE0003', '22JE0004'], array_column($data, 0));
        $this->assertSame('Present', $data[1][5]);
        $this->assertNull($data[0][5]);

        // Stage 2's sheet lists only the students published as shortlisted in stage 1.
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}/publish", ['reject_remaining' => true])->assertOk();
        $file = tempnam(sys_get_temp_dir(), 'att').'.xlsx';
        file_put_contents($file, $this->get($this->url(1, '/template'))->assertOk()->streamedContent());
        $rows = IOFactory::load($file)->getActiveSheet()->toArray();
        @unlink($file);
        $this->assertSame(['22JE0001'], array_values(array_filter(array_column(array_slice($rows, 3), 0), fn ($r) => preg_match('/^22JE/', (string) $r))));
    }

    public function test_preview_reports_counts_and_problems_without_saving(): void
    {
        $lines = [
            'Acme · SDE · Aptitude: attendance',
            '',
            'Roll No,Name,Programme,Branch,Batch,Attendance',
            '22je0001,A,B.Tech,CSE,2027,Present',
            '22JE0002,B,B.Tech,CSE,2027,absent',
            '22JE0003,C,B.Tech,CSE,2027,',          // blank → left unmarked
            '22JE0004,D,B.Tech,CSE,2027,Maybe',     // bad value
            '22JE0001,A,B.Tech,CSE,2027,Absent',    // duplicate
            '22JE0099,X,B.Tech,CSE,2027,P',         // not an applicant
        ];

        $response = $this->upload(0, $lines, true)->assertOk();
        $response->assertJsonPath('dry_run', true)->assertJsonPath('present', 1)->assertJsonPath('absent', 1)->assertJsonPath('unchanged', 0);
        $errors = collect($response->json('errors'))->keyBy('roll_no');
        $this->assertSame(['22JE0004', '22JE0001', '22JE0099'], $errors->keys()->all());
        $this->assertStringContainsString('not Present or Absent', $errors['22JE0004']['reason']);
        $this->assertStringContainsString('more than once', $errors['22JE0001']['reason']);
        $this->assertSame(0, ApplicationRoundResult::count(), 'a preview writes nothing');
        $this->assertFalse(AuditLog::where('action', 'round.attendance')->exists());

        // The real upload saves the same thing and is audited.
        $this->upload(0, $lines)->assertOk()->assertJsonPath('present', 1)->assertJsonPath('absent', 1);
        $this->assertSame('yes', $this->attendance('22JE0001'));
        $this->assertSame('no', $this->attendance('22JE0002'));
        $this->assertNull($this->attendance('22JE0003'));
        $this->assertNull($this->attendance('22JE0004'));
        $log = AuditLog::where('action', 'round.attendance')->sole();
        $this->assertSame('sheet', $log->after['source']);
        $this->assertSame($this->admin->id, $log->user_id);
    }

    public function test_bad_sheets_and_open_applications_are_refused(): void
    {
        $this->upload(0, ['Roll No,Name', '22JE0001,A'])->assertStatus(422)->assertJsonFragment(['message' => 'The sheet needs a "Roll No" column and an "Attendance" column. Download the attendance sheet for this stage and fill it in.']);
        $this->upload(0, ['Roll No,Attendance', '22JE0001,'])->assertStatus(422);
        $this->post($this->url(0, '/upload'), [], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('file');

        // While the job profile still takes applications, attendance cannot be marked (same rule as stage results).
        $this->posting->fresh()->update(['status' => 'open', 'application_deadline' => now()->addDays(2)]);
        $this->assertTrue($this->posting->fresh()->acceptsApplications());
        $this->upload(0, ['Roll No,Attendance', '22JE0001,Present'])->assertStatus(422)->assertJsonFragment(['message' => 'Applications are still open. Close applications (or wait for the deadline) before marking attendance.']);
    }

    public function test_attendance_can_be_corrected_after_publishing_and_the_company_sees_only_published_rows(): void
    {
        $this->upload(0, ['Roll No,Attendance', '22JE0001,Present', '22JE0002,Absent', '22JE0003,Present'])->assertOk();
        $results = "/api/admin/postings/{$this->posting->id}/rounds/{$this->round(0)->id}";
        $this->postJson("{$results}/results", ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson("{$results}/publish", ['reject_remaining' => false])->assertOk(); // 22JE0003 has attendance but no published result

        // The company sees attendance for published rows only, and the stage's counts.
        $applicants = collect($this->as($this->companyUser)->getJson("/api/company/postings/{$this->posting->id}/applicants")->assertOk()->json('applicants'))->keyBy('roll_no');
        $roundId = $this->round(0)->id;
        $this->assertSame('yes', $applicants['22JE0001']['rounds'][$roundId]['attendance']);
        $this->assertSame('no', $applicants['22JE0002']['rounds'][$roundId]['attendance']);
        $this->assertArrayNotHasKey($roundId, $applicants['22JE0003']['rounds'] ?? []);
        $show = $this->getJson("/api/company/postings/{$this->posting->id}")->assertOk();
        $this->assertSame(['appeared' => 1, 'absent' => 1], $show->json('posting.rounds.0.attendance'));

        // A correction after publishing is allowed and reported.
        $this->as($this->admin);
        $preview = $this->upload(0, ['Roll No,Attendance', '22JE0002,Present'], true)->assertOk();
        $this->assertSame([['roll_no' => '22JE0002', 'from' => 'no', 'to' => 'yes']], $preview->json('corrected'));
        $this->upload(0, ['Roll No,Attendance', '22JE0002,Present'])->assertOk()->assertJsonPath('present', 1);
        $this->assertSame('yes', $this->attendance('22JE0002'));
        $this->assertSame(['22JE0002:no'], AuditLog::where('action', 'round.attendance')->latest('id')->first()->before['corrected']);

        $this->assertSame(['appeared' => 2, 'absent' => 0], $this->as($this->companyUser)->getJson("/api/company/postings/{$this->posting->id}")->json('posting.rounds.0.attendance'));
    }

    public function test_only_staff_with_progress_grid_write_can_upload(): void
    {
        $super = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);
        $roleId = $this->as($super)->postJson('/api/admin/roles', ['name' => 'Grid readers', 'levels' => ['progress_grid' => 'read', 'job_profiles' => 'read']])->assertCreated()->json('role.id');
        $reader = User::factory()->create(['role' => 'admin', 'staff_role_id' => $roleId]);

        $this->as($reader)->get($this->url(0, '/template'))->assertOk();
        $this->upload(0, ['Roll No,Attendance', '22JE0001,Present'])->assertForbidden();
        $this->as($this->companyUser)->get($this->url(0, '/template'), ['Accept' => 'application/json'])->assertForbidden();
    }
}
