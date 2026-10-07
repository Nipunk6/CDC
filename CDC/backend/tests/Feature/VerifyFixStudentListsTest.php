<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\ExportTemplate;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\StudentCategory;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\Like;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Parity verification fixes: the placement's filtered "Download as Excel" (M5), literal `%`/`_` in student searches
 * (L28), Superset's "Current Course Name" on import (L18) and the company delete message wording (L5).
 */
class VerifyFixStudentListsTest extends TestCase
{
    use RefreshDatabase;

    private const CSE = 'Computer Science & Engineering';

    private User $admin;

    private PlacementCycle $cycle;

    /** @var array<string, StudentProfile> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT 2027', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);

        // roll => gender, CGPA, enrolment status (null = not enrolled), personal email, name
        $rows = [
            '22JE0101' => ['male', 8.00, 'active', 'a_b@gmail.com', 'Asha Rao'],
            '22JE0102' => ['female', 6.50, 'active', 'axb@gmail.com', 'Ravi_K'],
            '22JE0103' => ['female', 9.00, 'suspended', null, 'Meera Iyer'],
            '22JE0104' => ['male', 7.00, 'active', null, 'Kabir Das'],
            '22JE0105' => ['female', 9.50, null, null, 'Not Enrolled'],
        ];
        foreach ($rows as $roll => [$gender, $cgpa, $status, $personal, $name]) {
            $this->s[$roll] = StudentProfile::factory()->create([
                'roll_no' => $roll, 'institute_email' => strtolower($roll).'@iitism.ac.in', 'full_name' => $name, 'gender' => $gender,
                'current_cgpa' => $cgpa, 'personal_email' => $personal, 'programme' => StudentProfileFactory::BTECH, 'branch' => self::CSE,
                'graduating_batch' => 2027,
            ]);
            if ($status !== null) {
                CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $this->s[$roll]->id, 'status' => $status]);
            }
        }
    }

    // ------------------------------------------------------------------ M5

    /** @return list<string> roll numbers on the enrolled list for a query */
    private function listed(string $query): array
    {
        return collect($this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/enrollments?{$query}")->assertOk()->json('enrollments'))
            ->pluck('student_profile.roll_no')->sort()->values()->all();
    }

    /** @return list<list<mixed>> every row of the downloaded workbook, header first */
    private function downloaded(string $query): array
    {
        $response = $this->get("/api/admin/placement-cycles/{$this->cycle->id}/students/export?{$query}")->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'm5');
        file_put_contents($path, $response->streamedContent());
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, false, false, false);
        @unlink($path);

        return $rows;
    }

    /** @return list<string> roll numbers (first column) of the downloaded workbook */
    private function downloadedRolls(string $query): array
    {
        $rows = $this->downloaded($query);
        array_shift($rows);

        return collect($rows)->pluck(0)->filter()->sort()->values()->all();
    }

    public function test_m5_placement_download_exports_exactly_the_filtered_enrolled_list(): void
    {
        Sanctum::actingAs($this->admin);

        $cases = [
            '' => ['22JE0101', '22JE0102', '22JE0103', '22JE0104'],
            'genders[]=female' => ['22JE0102', '22JE0103'],
            'genders[]=female&status=active' => ['22JE0102'],
            'status=suspended' => ['22JE0103'],
            'cgpa_min=7&genders[]=male' => ['22JE0101', '22JE0104'],
            'search=Kabir' => ['22JE0104'],
            'cgpa_min=9.9' => [],
        ];
        foreach ($cases as $query => $expected) {
            $this->assertSame($expected, $this->listed($query), "enrolled list: {$query}");
            $this->assertSame($expected, $this->downloadedRolls($query), "Download as Excel: {$query}");
        }

        // The default columns are unchanged by filtering.
        $this->assertSame($this->downloaded('')[0], $this->downloaded('genders[]=female')[0]);
        $this->assertSame('Roll No', $this->downloaded('genders[]=female')[0][0]);
    }

    public function test_m5_filtered_download_with_a_template_and_the_audit_row(): void
    {
        Sanctum::actingAs($this->admin);
        $template = ExportTemplate::create(['name' => 'Rolls', 'type' => 'STUDENT_LIST', 'columns' => [['key' => 'roll_no', 'label' => 'Roll'], ['key' => 'name', 'label' => 'Name']]]);

        $rows = $this->downloaded("genders[]=female&template={$template->id}");
        $this->assertSame([['Roll', 'Name'], ['22JE0102', 'Ravi_K'], ['22JE0103', 'Meera Iyer']], $rows);

        $log = AuditLog::where('action', 'cycle.export')->latest('id')->firstOrFail();
        $this->assertSame(['genders' => ['female']], $log->after['filters']);
        $this->assertSame(2, $log->after['count']);
        $this->assertSame(4, $log->after['enrolled']);
        $this->assertSame($template->id, $log->after['template_id']);

        $this->downloaded('status=active&cgpa_max=7');
        $log = AuditLog::where('action', 'cycle.export')->latest('id')->firstOrFail();
        $this->assertSame(['cgpa_max' => '7', 'status' => 'active'], $log->after['filters']);
        $this->assertSame(2, $log->after['count']);
        $this->assertNull($log->after['template_id']);

        $this->downloaded('');
        $log = AuditLog::where('action', 'cycle.export')->latest('id')->firstOrFail();
        $this->assertSame([], $log->after['filters']);
        $this->assertSame(4, $log->after['count']);

        $this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/students/export?cgpa_min=abc")->assertStatus(422);
        $this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/students/export?status=placed")->assertStatus(422);
    }

    public function test_m5_students_and_companies_cannot_download_the_enrolled_list(): void
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);
        foreach ([$this->s['22JE0101']->user, $companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/students/export?genders[]=female")->assertForbidden();
        }
    }

    // ------------------------------------------------------------------ L28

    public function test_l28_like_contains_escapes_wildcards_and_backslash(): void
    {
        $this->assertSame('%a\\%b\\_c\\\\d%', Like::contains('a%b_c\\d'));
        $this->assertSame('%22JE%', Like::contains('22JE'));
    }

    public function test_l28_directory_and_enrolled_list_search_treat_wildcards_literally(): void
    {
        Sanctum::actingAs($this->admin);
        $directory = fn (string $search) => collect($this->getJson('/api/admin/students?search='.urlencode($search))->assertOk()->json('students'))
            ->pluck('roll_no')->sort()->values()->all();

        $this->assertSame([], $directory('%'));
        $this->assertSame([], $directory('\\'));
        $this->assertSame(['22JE0101'], $directory('a_b'), '"_" must not match the "x" in axb@gmail.com');
        $this->assertSame(['22JE0102'], $directory('Ravi_'));
        $this->assertSame(['22JE0101', '22JE0102', '22JE0103', '22JE0104', '22JE0105'], $directory('22je01'), 'search stays case-insensitive');

        $this->assertSame([], $this->listed('search='.urlencode('%')));
        $this->assertSame(['22JE0101'], $this->listed('search=a_b'));
        $this->assertSame([], $this->downloadedRolls('search='.urlencode('%')));
    }

    public function test_l28_category_member_search_treats_wildcards_literally(): void
    {
        $category = StudentCategory::create(['title' => 'Sports quota']);
        $category->students()->attach([$this->s['22JE0101']->id, $this->s['22JE0102']->id, $this->s['22JE0104']->id], ['assigned_by' => $this->admin->id]);

        Sanctum::actingAs($this->admin);
        $members = fn (string $search) => collect($this->getJson("/api/admin/student-categories/{$category->id}/students?search=".urlencode($search))->assertOk()->json('students'))
            ->pluck('roll_no')->sort()->values()->all();

        $this->assertSame([], $members('%'));
        $this->assertSame(['22JE0102'], $members('_'));
        $this->assertSame(['22JE0101'], $members('asha'));
        $this->assertSame(['22JE0101', '22JE0102', '22JE0104'], $members(''));
    }

    // ------------------------------------------------------------------ L18

    private function import(string $csv, array $extra = [])
    {
        return $this->post('/api/admin/students/import', array_merge([
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ], $extra), ['Accept' => 'application/json']);
    }

    public function test_l18_superset_current_course_name_fills_programme_and_branch(): void
    {
        Sanctum::actingAs($this->admin);
        $header = '"Institute Roll Number (Mandatory)","First Name (Mandatory)","Middle Name","Last Name","Gender (M/F/O)","Email Address (Mandatory)","Current Course Name"';
        $csv = implode("\n", [
            $header,
            '"25je0501","Asha","","Rao","F","25je0501@iitism.ac.in","B.Tech - Computer Science and Engineering"',
            '"25mb0502","Vikram","","Shah","M","25mb0502@iitism.ac.in","MBA - Finance"',
            '"25je0503","Nitin","","Paul","M","25je0503@iitism.ac.in","B.Tech - Underwater Basket Weaving"',
            '"25je0504","Ira","","Sen","F","25je0504@iitism.ac.in",""',
            '"25je0505","Om","","Roy","X","25je0505@iitism.ac.in","Computer Science & Engineering"',
        ]);

        $dry = $this->import($csv, ['default_batch' => 2029, 'dry_run' => 1])->assertOk();
        $this->assertSame(2, $dry->json('valid_rows'));
        $errors = collect($dry->json('errors'));

        // An unreadable course name is one clear error, not "programme/branch required".
        $row4 = $errors->where('row', 4)->values();
        $this->assertCount(1, $row4);
        $this->assertSame('programme', $row4[0]['field']);
        $this->assertStringContainsString('Current Course Name "B.Tech - Underwater Basket Weaving" does not match a programme and branch', $row4[0]['reason']);

        // A blank course name keeps the usual required-field errors.
        $this->assertEqualsCanonicalizing(['programme', 'branch'], $errors->where('row', 5)->pluck('field')->all());

        // A branch without a programme is ambiguous; other errors on the row are still listed.
        $row6 = $errors->where('row', 6)->pluck('reason', 'field');
        $this->assertStringContainsString('"Computer Science & Engineering" does not match', $row6['programme']);
        $this->assertArrayHasKey('gender', $row6->all());
        $this->assertArrayNotHasKey('branch', $row6->all());

        $this->import($csv, ['default_batch' => 2029])->assertOk()->assertJsonPath('created', 2);
        $asha = StudentProfile::where('roll_no', '25JE0501')->sole();
        $this->assertSame(StudentProfileFactory::BTECH, $asha->programme);
        $this->assertSame(self::CSE, $asha->branch);
        $vikram = StudentProfile::where('roll_no', '25MB0502')->sole();
        $this->assertSame('MBA (2 Year) - CAT', $vikram->programme);
        $this->assertSame('MBA - Finance', $vikram->branch);
        $this->assertFalse(StudentProfile::where('roll_no', '25JE0503')->exists());
    }

    public function test_l18_course_name_with_a_branch_column_and_programme_column_precedence(): void
    {
        Sanctum::actingAs($this->admin);
        $csv = implode("\n", [
            '"Institute Roll Number (Mandatory)","Full Name (Mandatory)","Gender (M/F/O)","Email Address (Mandatory)","Current Course Name","Programme","Branch"',
            // The course names only the programme; the Branch column names the branch ("&" vs "and" does not matter).
            '"25mt0601","Tara Bose","F","25mt0601@iitism.ac.in","M.Tech (2 Year) - GATE","","Computer Science & Engineering"',
            // A filled Programme column wins over the course name.
            '"25je0602","Dev Nair","M","25je0602@iitism.ac.in","MBA - Finance","'.StudentProfileFactory::BTECH.'","Mining Engineering"',
        ]);

        $this->import($csv, ['default_batch' => 2028])->assertOk()->assertJsonPath('created', 2)->assertJsonPath('errors', []);
        $tara = StudentProfile::where('roll_no', '25MT0601')->sole();
        $this->assertSame('M.Tech (2 Year) - GATE', $tara->programme);
        $this->assertSame('Computer Science and Engineering', $tara->branch);
        $dev = StudentProfile::where('roll_no', '25JE0602')->sole();
        $this->assertSame(StudentProfileFactory::BTECH, $dev->programme);
        $this->assertSame('Mining Engineering', $dev->branch);
    }

    // ------------------------------------------------------------------ L5

    public function test_l5_company_cannot_delete_an_opened_form_and_is_told_so(): void
    {
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'form_data' => []]);
        $inf = Inf::create(['company_id' => $company->id, 'internship_title' => 'Intern', 'internship_description' => 'x', 'status' => 'accepted', 'form_data' => []]);
        foreach ([$jnf, $inf] as $form) {
            JobPosting::create([
                'postable_type' => $form::class, 'postable_id' => $form->id, 'placement_cycle_id' => $this->cycle->id,
                'application_deadline' => now()->addDays(3), 'status' => 'open', 'eligibility_snapshot' => [],
            ]);
        }

        Sanctum::actingAs($companyUser);
        $this->deleteJson("/api/company/jnfs/{$jnf->id}")->assertStatus(422)
            ->assertJsonPath('message', 'This JNF has been opened for applications and cannot be deleted.');
        $this->deleteJson("/api/company/infs/{$inf->id}")->assertStatus(422)
            ->assertJsonPath('message', 'This INF has been opened for applications and cannot be deleted.');
        $this->assertNotNull($jnf->fresh());
        $this->assertNotNull($inf->fresh());
    }
}
