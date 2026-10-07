<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\BranchChangeRequest;
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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Superset parity S4.1/S4.2/S4.4/S4.7/S4.8: student list filters, Download as Excel, pending requests banner and
 * enrolment suspend / reactivate.
 */
class StudentDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private const MTECH = 'M.Tech';

    private User $admin;

    private User $companyUser;

    private PlacementCycle $cycle;

    private PlacementCycle $otherCycle;

    private JobPosting $posting;

    /** @var array<string, StudentProfile> roll number => student */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);

        $allowed = [['programme' => StudentProfileFactory::BTECH, 'batches' => [2026, 2027]]];
        $this->cycle = PlacementCycle::create(['name' => 'FT 2027', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open', 'allowed_programmes' => $allowed]);
        $this->otherCycle = PlacementCycle::create(['name' => 'Intern 2026', 'type' => 'internship', 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30', 'status' => 'open', 'allowed_programmes' => $allowed]);

        $rows = [
            // roll, gender, programme, branch, batch, cgpa, 10th, 12th, ongoing, total, phone, personal email
            ['22JE0001', 'male', StudentProfileFactory::BTECH, 'Computer Science & Engineering', 2027, 9.10, 95, 92, 0, 0, '9811111111', 'aarav@gmail.com'],
            ['22JE0002', 'female', StudentProfileFactory::BTECH, 'Mining Engineering', 2027, 7.00, 80, 70, 1, 2, '9822222222', null],
            ['22JE0003', 'other', StudentProfileFactory::BTECH, 'Computer Science & Engineering', 2026, 6.00, 60, 65, 0, 3, null, 'third@yahoo.com'],
            ['22MT0004', 'male', self::MTECH, 'Computer Science & Engineering', 2028, 8.00, null, 75, 0, 0, null, null],
            ['22JE0005', 'female', StudentProfileFactory::BTECH, 'Computer Science & Engineering', 2027, 8.50, 88, 85, 2, 2, null, null],
        ];
        foreach ($rows as [$roll, $gender, $programme, $branch, $batch, $cgpa, $tenth, $twelfth, $ongoing, $total, $phone, $personal]) {
            $this->s[$roll] = StudentProfile::factory()->create([
                'roll_no' => $roll, 'institute_email' => strtolower($roll).'@iitism.ac.in', 'gender' => $gender, 'programme' => $programme,
                'branch' => $branch, 'graduating_batch' => $batch, 'current_cgpa' => $cgpa, 'tenth_percent' => $tenth, 'twelfth_percent' => $twelfth,
                'ongoing_backlogs' => $ongoing, 'total_backlogs' => $total, 'phone' => $phone, 'personal_email' => $personal,
            ]);
        }
        $this->s['22JE0005']->user->update(['is_active' => false]);

        foreach (['22JE0001', '22JE0002', '22JE0003', '22MT0004'] as $roll) {
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $this->s[$roll]->id, 'status' => 'active']);
        }
        CycleEnrollment::create(['placement_cycle_id' => $this->otherCycle->id, 'student_profile_id' => $this->s['22JE0001']->id, 'status' => 'active']);
        CycleEnrollment::create(['placement_cycle_id' => $this->otherCycle->id, 'student_profile_id' => $this->s['22JE0005']->id, 'status' => 'active']);

        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [
                    ['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true],
                    ['branch' => 'Mining Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true],
                ]]],
                'selectionRounds' => [['type' => 'technical_interview', 'enabled' => true]],
            ],
        ]);
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        // 22JE0001 is placed (Full-Time); 22JE0002 holds only a PPO offer, which does not count as placed (D80).
        $this->offer('22JE0001', 'fulltime', $company);
        $this->offer('22JE0002', 'ppo_offered', $company);

        // 22JE0003 has an active block; 22MT0004 only a lifted one.
        PlacementBlock::create(['student_profile_id' => $this->s['22JE0003']->id, 'placement_cycle_id' => $this->cycle->id, 'scope' => 'all', 'reason' => 'manual', 'active' => true]);
        PlacementBlock::create(['student_profile_id' => $this->s['22MT0004']->id, 'placement_cycle_id' => $this->cycle->id, 'scope' => 'all', 'reason' => 'manual', 'active' => false]);
    }

    private function offer(string $roll, string $type, Company $company): void
    {
        $resume = $this->s[$roll]->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
        $application = Application::create([
            'job_posting_id' => $this->posting->id, 'student_profile_id' => $this->s[$roll]->id, 'resume_id' => $resume->id,
            'status' => 'applied', 'applied_at' => now(),
        ]);
        Offer::create([
            'application_id' => $application->id, 'student_profile_id' => $this->s[$roll]->id, 'company_id' => $company->id,
            'job_posting_id' => $this->posting->id, 'placement_cycle_id' => $this->cycle->id, 'offer_type' => $type, 'ctc_annual' => 1000000,
            'announced_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function rolls(string $query): array
    {
        $response = $this->getJson('/api/admin/students?'.$query)->assertOk();

        return collect($response->json('students'))->pluck('roll_no')->sort()->values()->all();
    }

    private function sheetOf($response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'std');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    /** @return array<string, array{0: array<string, mixed>, 1: list<string>}> */
    public static function filterCases(): array
    {
        return [
            'batch multi-select' => [['batches' => [2026, 2028]], ['22JE0003', '22MT0004']],
            'programme multi-select' => [['programmes' => [self::MTECH]], ['22MT0004']],
            'branch multi-select' => [['branches' => ['Mining Engineering']], ['22JE0002']],
            'gender' => [['genders' => ['female', 'other']], ['22JE0002', '22JE0003', '22JE0005']],
            'class x range' => [['tenth_min' => 80, 'tenth_max' => 90], ['22JE0002', '22JE0005']],
            'class xii range' => [['twelfth_min' => 75], ['22JE0001', '22JE0005', '22MT0004']],
            'cgpa range' => [['cgpa_min' => 7, 'cgpa_max' => 8.5], ['22JE0002', '22JE0005', '22MT0004']],
            'ongoing backlogs max' => [['ongoing_backlogs_max' => 0], ['22JE0001', '22JE0003', '22MT0004']],
            'total backlogs max' => [['total_backlogs_max' => 2], ['22JE0001', '22JE0002', '22JE0005', '22MT0004']],
            'placed' => [['placement_status' => 'placed'], ['22JE0001']],
            'not placed' => [['placement_status' => 'not_placed'], ['22JE0002', '22JE0003', '22JE0005', '22MT0004']],
            'blocked' => [['blocked_status' => 'blocked'], ['22JE0003']],
            'not blocked' => [['blocked_status' => 'not_blocked'], ['22JE0001', '22JE0002', '22JE0005', '22MT0004']],
            'search by mobile' => [['search' => '98222'], ['22JE0002']],
            'search by personal email' => [['search' => 'yahoo'], ['22JE0003']],
            'account status (existing)' => [['status' => 'suspended'], ['22JE0005']],
            'combined' => [['batches' => [2027], 'genders' => ['male', 'female'], 'cgpa_min' => 8], ['22JE0001', '22JE0005']],
        ];
    }

    #[DataProvider('filterCases')]
    public function test_each_filter_returns_exactly_the_matching_students(array $filters, array $expected): void
    {
        Sanctum::actingAs($this->admin);

        $this->assertSame($expected, $this->rolls(http_build_query($filters)));
    }

    public function test_placement_status_can_be_limited_to_one_placement(): void
    {
        Sanctum::actingAs($this->admin);

        $this->assertSame(['22JE0001'], $this->rolls('placement_status=placed&cycle_id='.$this->cycle->id));
        $this->assertSame([], $this->rolls('placement_status=placed&cycle_id='.$this->otherCycle->id));
        $this->getJson('/api/admin/students?cycle_id=99999')->assertStatus(422);
        $this->getJson('/api/admin/students?cgpa_min=abc')->assertStatus(422);
    }

    public function test_list_returns_contact_columns_and_honours_per_page(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/students?search=22JE0001')->assertOk();
        $response->assertJsonPath('students.0.phone', '9811111111')
            ->assertJsonPath('students.0.personal_email', 'aarav@gmail.com')
            ->assertJsonPath('students.0.institute_email', '22je0001@iitism.ac.in')
            ->assertJsonPath('students.0.has_photo', false);

        $this->getJson('/api/admin/students?per_page=2')->assertOk()
            ->assertJsonPath('meta.per_page', 2)->assertJsonPath('meta.last_page', 3)->assertJsonCount(2, 'students');
    }

    public function test_enrolled_list_takes_the_same_filters_scoped_to_its_placement(): void
    {
        Sanctum::actingAs($this->admin);
        $url = "/api/admin/placement-cycles/{$this->cycle->id}/enrollments";
        $rolls = fn (string $q) => collect($this->getJson($url.'?'.$q)->assertOk()->json('enrollments'))->pluck('student_profile.roll_no')->sort()->values()->all();

        $this->assertSame(['22JE0001', '22JE0002', '22JE0003', '22MT0004'], $rolls(''));
        $this->assertSame(['22JE0002', '22JE0003'], $rolls('genders[]=female&genders[]=other'));
        $this->assertSame(['22JE0001'], $rolls('placement_status=placed'));
        $this->assertSame(['22JE0003'], $rolls('blocked_status=blocked'));
        $this->assertSame(['22JE0002'], $rolls('search=98222'));

        // Placement Status here means "in this placement": 22JE0001 is placed in FT 2027, not in Intern 2026.
        $other = collect($this->getJson("/api/admin/placement-cycles/{$this->otherCycle->id}/enrollments?placement_status=not_placed")->json('enrollments'))
            ->pluck('student_profile.roll_no')->sort()->values()->all();
        $this->assertSame(['22JE0001', '22JE0005'], $other);
    }

    public function test_download_as_excel_default_and_template_follow_the_filters_and_are_audited(): void
    {
        Sanctum::actingAs($this->admin);

        $sheet = $this->sheetOf($this->get('/api/admin/students/export?batches[]=2027&genders[]=male')->assertOk());
        $headers = array_column(\App\Services\TemplateExports::STUDENTS_DEFAULT, 'label');
        $this->assertSame($headers, array_values(array_filter($sheet->rangeToArray('A1:P1')[0], fn ($v) => $v !== null)));
        $this->assertSame('22JE0001', $sheet->getCell('B2')->getValue());
        $this->assertSame('9811111111', $sheet->getCell('O2')->getValue());
        $this->assertNull($sheet->getCell('B3')->getValue());

        $log = AuditLog::where('action', 'student.export')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(1, $log->after['count']);
        $this->assertEquals([2027], $log->after['filters']['batches']);
        $this->assertNull($log->after['template_id']);

        $id = $this->postJson('/api/admin/export-templates', ['name' => 'Short'])->assertCreated()->json('template.id');
        $this->patchJson("/api/admin/export-templates/{$id}", ['columns' => [['key' => 'roll_no', 'label' => 'Roll'], ['key' => 'mobile', 'label' => '=Phone']]])->assertOk();

        $sheet = $this->sheetOf($this->get("/api/admin/students/export?placement_status=placed&template={$id}")->assertOk());
        $this->assertSame(['Roll', '=Phone'], [$sheet->getCell('A1')->getValue(), $sheet->getCell('B1')->getValue()]);
        $this->assertSame('22JE0001', $sheet->getCell('A2')->getValue());
        $this->assertNull($sheet->getCell('A3')->getValue());
        $this->assertSame($id, AuditLog::where('action', 'student.export')->latest('id')->first()->after['template_id']);

        $this->get('/api/admin/students/export?template=99999')->assertNotFound();
    }

    public function test_pending_requests_counts_branch_changes_and_resumes(): void
    {
        BranchChangeRequest::create(['student_profile_id' => $this->s['22JE0001']->id, 'current_branch' => 'Computer Science & Engineering', 'requested_branch' => 'Mining Engineering', 'reason' => 'x', 'status' => 'pending']);
        BranchChangeRequest::create(['student_profile_id' => $this->s['22JE0002']->id, 'current_branch' => 'Mining Engineering', 'requested_branch' => 'Computer Science & Engineering', 'reason' => 'x', 'status' => 'rejected']);
        foreach (['pending', 'pending', 'approved'] as $i => $status) {
            $this->s['22JE0003']->resumes()->create(['slot' => $i + 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => $status]);
        }

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/students/pending-requests')->assertOk()
            ->assertExactJson(['branch_changes' => 1, 'resumes' => 2, 'total' => 3]);
    }

    public function test_suspending_an_enrolment_is_audited_and_blocks_apply_with_the_reason(): void
    {
        $student = $this->s['22JE0003'];
        PlacementBlock::query()->update(['active' => false]); // so the enrolment is the only thing in the way
        $enrollment = CycleEnrollment::where('placement_cycle_id', $this->cycle->id)->where('student_profile_id', $student->id)->sole();
        $url = "/api/admin/placement-cycles/{$this->cycle->id}/enrollments/{$enrollment->id}";

        Sanctum::actingAs($student->user);
        $card = collect($this->getJson('/api/student/postings')->assertOk()->json('postings'))->firstWhere('id', $this->posting->id);
        $this->assertTrue($card['eligibility']['eligible']);

        Sanctum::actingAs($this->admin);
        $this->patchJson($url, ['status' => 'paused'])->assertStatus(422);
        $this->patchJson($url, [])->assertStatus(422);
        $this->patchJson($url, ['status' => 'suspended'])->assertOk()->assertJsonPath('enrollment.status', 'suspended');

        $log = AuditLog::where('action', 'cycle.enrollment_status')->sole();
        $this->assertSame(['student_profile_id' => $student->id, 'status' => 'active'], $log->before);
        $this->assertSame(['student_profile_id' => $student->id, 'status' => 'suspended'], $log->after);
        $this->assertSame(CycleEnrollment::class, $log->subject_type);

        // The job profile stays on the board, ineligible, with the reason; apply is refused with the same reason.
        Sanctum::actingAs($student->user);
        $card = collect($this->getJson('/api/student/postings')->assertOk()->json('postings'))->firstWhere('id', $this->posting->id);
        $this->assertFalse($card['eligibility']['eligible']);
        $this->assertContains('Your enrolment in this placement is suspended.', $card['eligibility']['reasons']);
        $resume = $student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
        $this->postJson("/api/student/postings/{$this->posting->id}/apply", ['resume_id' => $resume->id])->assertStatus(422)
            ->assertJsonFragment(['reasons' => ['Your enrolment in this placement is suspended.']]);

        // The enrolled list shows the status and filters on it.
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/enrollments?status=suspended")->assertOk()
            ->assertJsonCount(1, 'enrollments')->assertJsonPath('enrollments.0.student_profile.roll_no', '22JE0003');

        // Reactivate: audited, eligible again. A second identical request changes nothing and logs nothing.
        $this->patchJson($url, ['status' => 'active'])->assertOk()->assertJsonPath('enrollment.status', 'active');
        $this->patchJson($url, ['status' => 'active'])->assertOk();
        $this->assertSame(2, AuditLog::where('action', 'cycle.enrollment_status')->count());
        Sanctum::actingAs($student->user);
        $card = collect($this->getJson('/api/student/postings')->json('postings'))->firstWhere('id', $this->posting->id);
        $this->assertTrue($card['eligibility']['eligible']);
    }

    public function test_an_enrolment_of_another_placement_is_not_found(): void
    {
        $enrollment = CycleEnrollment::where('placement_cycle_id', $this->otherCycle->id)->first();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/placement-cycles/{$this->cycle->id}/enrollments/{$enrollment->id}", ['status' => 'suspended'])->assertNotFound();
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_students_and_companies_cannot_use_the_directory_routes(): void
    {
        $enrollment = CycleEnrollment::where('placement_cycle_id', $this->cycle->id)->first();

        foreach ([$this->s['22JE0001']->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/students?placement_status=placed')->assertForbidden();
            $this->getJson('/api/admin/students/export')->assertForbidden();
            $this->getJson('/api/admin/students/pending-requests')->assertForbidden();
            $this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/enrollments?genders[]=male")->assertForbidden();
            $this->patchJson("/api/admin/placement-cycles/{$this->cycle->id}/enrollments/{$enrollment->id}", ['status' => 'suspended'])->assertForbidden();
        }

        $this->assertSame('active', $enrollment->fresh()->status);
        $this->assertSame(0, AuditLog::where('action', 'student.export')->count());
    }
}
