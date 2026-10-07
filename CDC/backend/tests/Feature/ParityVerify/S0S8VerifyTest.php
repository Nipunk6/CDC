<?php

namespace Tests\Feature\ParityVerify;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\StudentCategory;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\SettingsService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Tests\TestCase;

/**
 * Independent QA probes for Superset parity S0 (renames) and S8 (admin hub, users, placements extras, student
 * categories, reports). Written by the verifier; a failing probe is a finding.
 */
class S0S8VerifyTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private User $admin;

    private PlacementCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin', 'is_super_admin' => true]);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT 2027', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    private function companyUser(string $name = 'Acme'): User
    {
        $company = Company::create(['name' => $name, 'hr_name' => 'HR', 'hr_email' => uniqid().'@co.test']);

        return User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => uniqid().'@co.test']);
    }

    private function student(string $roll, array $attrs = [], bool $enrol = true, string $status = 'active'): StudentProfile
    {
        $s = StudentProfile::factory()->create(['roll_no' => $roll] + $attrs);
        if ($enrol) {
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => $status]);
        }
        $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'x.pdf', 'file_size' => 1, 'status' => 'approved']);

        return $s;
    }

    private function jnf(string $companyName, array $extraFormData = []): Jnf
    {
        $company = Company::create(['name' => $companyName, 'hr_name' => 'HR', 'hr_email' => uniqid().'@co.test']);

        return Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3, 'form_data' => [
            'jobTitle' => 'SDE',
            'currency' => 'INR',
            'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
            'selectionRounds' => [['type' => 'hr_interview', 'enabled' => true]],
        ] + $extraFormData]);
    }

    private function float(Jnf $jnf, array $extra = []): JobPosting
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(2)->toIso8601String(),
        ] + $extra)->assertCreated()->json('posting.id');

        return JobPosting::findOrFail($id);
    }

    private function category(string $title): int
    {
        Sanctum::actingAs($this->admin);

        return $this->postJson('/api/admin/student-categories', ['title' => $title])->assertCreated()->json('category.id');
    }

    private function sheet($response): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $path = tempnam(sys_get_temp_dir(), 'vrf');
        file_put_contents($path, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        return $sheet;
    }

    /** @return array<string, int> roll => row number in a report sheet (header on row 3) */
    private function rowsByRoll(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $rollColumn = 'B'): array
    {
        $rows = [];
        for ($r = 4; $r <= $sheet->getHighestRow(); $r++) {
            $v = $sheet->getCell($rollColumn.$r)->getValue();
            if (is_string($v) && $v !== '') {
                $rows[$v] = $r;
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------- permissions

    public function test_student_and_company_are_refused_on_every_in_scope_admin_route(): void
    {
        $student = $this->student('22JE9001');
        $categoryId = $this->category('Minor in Finance');
        $target = User::factory()->create(['role' => 'admin']);

        $routes = [
            ['GET', '/api/admin/settings'],
            ['PATCH', '/api/admin/settings'],
            ['POST', '/api/admin/settings/logo'],
            ['DELETE', '/api/admin/settings/logo'],
            ['GET', '/api/admin/manage-admins'],
            ['POST', '/api/admin/manage-admins'],
            ['PATCH', "/api/admin/manage-admins/{$target->id}"],
            ['DELETE', "/api/admin/manage-admins/{$target->id}"],
            ['GET', '/api/admin/placement-cycles'],
            ['POST', '/api/admin/placement-cycles'],
            ['PATCH', "/api/admin/placement-cycles/{$this->cycle->id}/publish"],
            ['GET', '/api/admin/student-categories'],
            ['POST', '/api/admin/student-categories'],
            ['PATCH', "/api/admin/student-categories/{$categoryId}"],
            ['DELETE', "/api/admin/student-categories/{$categoryId}"],
            ['GET', "/api/admin/student-categories/{$categoryId}/students"],
            ['POST', "/api/admin/student-categories/{$categoryId}/students"],
            ['DELETE', "/api/admin/student-categories/{$categoryId}/students/{$student->id}"],
            ['GET', "/api/admin/students/{$student->id}/categories"],
            ['GET', '/api/admin/reports'],
        ];
        foreach (array_keys(\App\Services\ReportService::REPORTS) as $key) {
            $routes[] = ['GET', "/api/admin/placement-cycles/{$this->cycle->id}/reports/{$key}"];
        }

        foreach ([$student->user, $this->companyUser()] as $actor) {
            Sanctum::actingAs($actor);
            foreach ($routes as [$method, $uri]) {
                $status = $this->json($method, $uri, ['title' => 'X', 'roll_nos' => ['22JE9001'], 'institute_name' => 'Hacked', 'first_name' => 'Z'])->status();
                $this->assertContains($status, [403, 404], "{$actor->role} {$method} {$uri} returned {$status}");
            }
        }

        $this->assertSame(0, AuditLog::query()->whereNotIn('action', ['student_category.create'])->count(), 'a refused request wrote an audit row');
        $this->assertNull(app(SettingsService::class)->get('institute_name'));
        $this->assertTrue(StudentCategory::query()->whereKey($categoryId)->exists());
    }

    public function test_a_normal_admin_cannot_edit_users_and_edit_validates_and_audits_only_changes(): void
    {
        $normal = User::factory()->create(['role' => 'admin', 'is_super_admin' => false]);
        $target = User::factory()->create(['role' => 'admin', 'name' => 'Asha Kumari Rao', 'email' => 'asha@iitism.ac.in']);
        $studentUser = $this->student('22JE9002')->user;

        Sanctum::actingAs($normal);
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'X'])->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/manage-admins/{$studentUser->id}", ['first_name' => 'X'])->assertStatus(422);
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'Asha', 'mobile' => '9876543210'])->assertStatus(422);
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['middle_name' => 'K'])->assertStatus(422); // first name required

        $this->patchJson("/api/admin/manage-admins/{$target->id}", [
            'first_name' => ' Asha ', 'middle_name' => '', 'last_name' => 'Rao', 'designation' => 'Placement Officer',
            'mobile' => '+91 9876543210', 'alias' => 'TPO', 'email' => 'evil@x.test',
        ])->assertOk()->assertJsonPath('user.name', 'Asha Rao')->assertJsonPath('user.email', 'asha@iitism.ac.in');

        $log = AuditLog::query()->where('action', 'admin.update')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Asha Kumari Rao', $log->before['name']);
        $this->assertArrayNotHasKey('email', $log->after);
        $this->assertSame('+91 9876543210', $log->after['mobile']);

        // No change -> no audit row.
        $this->patchJson("/api/admin/manage-admins/{$target->id}", ['first_name' => 'Asha', 'last_name' => 'Rao', 'designation' => 'Placement Officer', 'mobile' => '+91 9876543210', 'alias' => 'TPO'])->assertOk();
        $this->assertSame(1, AuditLog::query()->where('action', 'admin.update')->count());
    }

    // ---------------------------------------------------------------- audit coverage of in-scope admin writes

    public function test_every_in_scope_admin_write_writes_an_audit_row(): void
    {
        Sanctum::actingAs($this->admin);
        $student = $this->student('22JE9003');
        $expect = function (string $action, int $count = 1): void {
            $this->assertSame($count, AuditLog::query()->where('action', $action)->count(), $action);
        };

        $this->patchJson('/api/admin/settings', ['institute_name' => 'IIT (ISM) Dhanbad'])->assertOk();
        $expect('settings.institute_name_update');
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertOk();
        $expect('setting.update');
        $this->post('/api/admin/settings/logo', ['logo' => UploadedFile::fake()->image('l.png', 32, 32)], ['Accept' => 'application/json'])->assertOk();
        $this->deleteJson('/api/admin/settings/logo')->assertOk();
        $expect('settings.logo_update', 2);

        $id = $this->postJson('/api/admin/placement-cycles', [
            'name' => 'Draft one', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_draft' => true,
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ])->assertCreated()->json('placement_cycle.id');
        $expect('cycle.create');
        $this->patchJson("/api/admin/placement-cycles/{$id}/publish")->assertOk();
        $expect('cycle.publish');

        $cat = $this->category('Minor in Marketing');
        $expect('student_category.create');
        $this->patchJson("/api/admin/student-categories/{$cat}", ['description' => 'This category will be assigned to students having minor in Marketing'])->assertOk();
        $expect('student_category.update');
        $this->postJson("/api/admin/student-categories/{$cat}/students", ['roll_nos' => ['22JE9003']])->assertOk();
        $expect('student_category.assign');
        $this->deleteJson("/api/admin/student-categories/{$cat}/students/{$student->id}")->assertOk();
        $expect('student_category.unassign');
        $this->deleteJson("/api/admin/student-categories/{$cat}")->assertOk();
        $expect('student_category.delete');
        $this->assertSame('Minor in Marketing', AuditLog::query()->where('action', 'student_category.delete')->sole()->before['title']);

        $this->get("/api/admin/placement-cycles/{$this->cycle->id}/reports/job_profiles")->assertOk();
        $expect('report.download');
    }

    public function test_settings_cannot_write_the_logo_path_or_unknown_keys(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson('/api/admin/settings', ['account_logo' => ['path' => '../.env', 'mime' => 'image/png']])->assertStatus(422);
        $this->assertNull(app(SettingsService::class)->get('account_logo'));
        $this->get('/api/branding/logo')->assertNotFound();
        $this->getJson('/api/admin/settings')->assertOk()->assertJsonMissingPath('settings.account_logo');
    }

    // ---------------------------------------------------------------- S8.3 drafts

    public function test_editing_a_placement_cannot_turn_it_into_a_draft(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/placement-cycles/{$this->cycle->id}", [
            'name' => 'FT 2027', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'is_draft' => true,
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ])->assertOk();
        $this->assertFalse($this->cycle->fresh()->is_draft);
    }

    public function test_a_draft_placement_job_profile_cannot_be_applied_to_or_listed_for_students(): void
    {
        $s = $this->student('22JE9010');
        $posting = $this->float($this->jnf('Zeta'));
        $this->cycle->update(['is_draft' => true]); // only reachable directly; proves the read guards

        Sanctum::actingAs($s->user);
        $this->getJson('/api/student/postings')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $s->resumes()->first()->id, 'answers' => []])->assertNotFound();
        $this->assertSame(0, Application::count());
    }

    // ---------------------------------------------------------------- S8.4 categories in EligibilityService

    public function test_category_rule_check_and_query_agree_across_mixed_students(): void
    {
        $a = $this->category('Minor in Data Science');
        $b = $this->category('Double Major in CSE');

        $students = [
            'ok' => $this->student('22JE0101'),
            'suspended' => $this->student('22JE0102', [], true, 'suspended'),
            'inactive' => $this->student('22JE0103'),
            'lowcgpa' => $this->student('22JE0104', ['current_cgpa' => 5.0]),
            'onlyB' => $this->student('22JE0105'),
            'none' => $this->student('22JE0106'),
            'both' => $this->student('22JE0107'),
            'notEnrolled' => $this->student('22JE0108', [], false),
        ];
        $students['inactive']->user->update(['is_active' => false]);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/student-categories/{$a}/students", ['roll_nos' => ['22JE0101', '22JE0102', '22JE0103', '22JE0104', '22JE0107', '22JE0108']])->assertOk();
        $this->postJson("/api/admin/student-categories/{$b}/students", ['roll_nos' => ['22JE0105', '22JE0107']])->assertOk();

        $posting = $this->float($this->jnf('Acme'), ['allowed_student_categories' => [$a]]);
        $service = app(EligibilityService::class);

        $agree = function (array $expectedKeys) use ($service, $students, &$posting): void {
            $posting = $posting->fresh();
            $queryIds = $service->eligibleStudentsQuery($posting)->pluck('id')->sort()->values()->all();
            $checkIds = collect($students)->filter(fn ($s) => $service->check($s->fresh(), $posting)['eligible'])->pluck('id')->sort()->values()->all();
            $this->assertSame($checkIds, $queryIds, 'check() and eligibleStudentsQuery() disagree');
            $expected = collect($expectedKeys)->map(fn ($k) => $students[$k]->id)->sort()->values()->all();
            $this->assertSame($expected, $queryIds);
        };

        $agree(['ok', 'both']);
        $this->assertContains('Requires student category: Minor in Data Science.', $service->check($students['onlyB']->fresh(), $posting)['reasons']);

        $this->patchJson("/api/admin/postings/{$posting->id}/eligibility", ['allowedStudentCategories' => [$a, $b], 'notify_newly_eligible' => false])->assertOk();
        $agree(['ok', 'onlyB', 'both']);
        $this->assertTrue(AuditLog::query()->where('action', 'posting.eligibility_update')->exists());

        $this->patchJson("/api/admin/postings/{$posting->id}/eligibility", ['allowedStudentCategories' => [], 'notify_newly_eligible' => false])->assertOk();
        $agree(['ok', 'onlyB', 'none', 'both']);
    }

    /**
     * The open dialog's Allowed Student Categories is the CDC's choice. A company writes its own JNF form_data (free
     * JSON), so a key it injects there must never decide or override who may apply.
     */
    public function test_company_form_data_cannot_override_the_admins_allowed_categories(): void
    {
        $a = $this->category('Minor in Finance');
        $b = $this->category('Minor in Manufacturing');

        $posting = $this->float($this->jnf('Injector', ['allowedStudentCategories' => [$b]]), ['allowed_student_categories' => [$a]]);
        $this->assertSame([$a], EligibilityService::categoryIds($posting->eligibility_snapshot ?? []), 'the admin-selected categories were replaced by the company form_data value');
    }

    public function test_company_form_data_alone_cannot_add_a_category_restriction(): void
    {
        $b = $this->category('Minor in Manufacturing');
        $this->student('22JE0201');

        $posting = $this->float($this->jnf('Injector2', ['allowedStudentCategories' => [$b]]));
        $this->assertSame([], EligibilityService::categoryIds($posting->eligibility_snapshot ?? []), 'a company-supplied allowedStudentCategories restricted eligibility without the CDC choosing it');
    }

    // ---------------------------------------------------------------- S8.5 reports

    private function offer(JobPosting $posting, StudentProfile $s, string $type, ?int $ctc, string $currency = 'INR'): Offer
    {
        $app = Application::create([
            'job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $s->resumes()->first()->id,
            'status' => 'applied', 'applied_at' => now(),
        ]);

        return Offer::create([
            'application_id' => $app->id, 'student_profile_id' => $s->id, 'company_id' => $posting->company()->id,
            'job_posting_id' => $posting->id, 'placement_cycle_id' => $posting->placement_cycle_id, 'offer_type' => $type,
            'ctc_annual' => $ctc, 'currency' => $currency, 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);
    }

    public function test_reports_follow_d80_count_only_inr_for_best_ctc_and_are_formula_safe(): void
    {
        $s1 = $this->student('22JE0301');
        $s2 = $this->student('22JE0302');
        $s3 = $this->student('22JE0303');
        $s4 = $this->student('22JE0304');
        $s5 = $this->student('22JE0305', ['full_name' => '=HYPERLINK("http://evil.test","x")']);

        $evil = $this->float($this->jnf('=1+2'));
        $beta = $this->float($this->jnf('Beta'));
        $this->offer($evil, $s1, 'ppo_offered', 900000);
        $this->offer($evil, $s2, 'fulltime', 1000000);
        $this->offer($beta, $s2, 'fulltime', 200000, 'USD');
        $this->offer($beta, $s3, 'fulltime', 300000, 'USD');
        $this->offer($beta, $s5, 'fulltime', 500000);

        Sanctum::actingAs($this->admin);
        $base = "/api/admin/placement-cycles/{$this->cycle->id}/reports";

        $placed = $this->sheet($this->get("{$base}/students_placed")->assertOk());
        $rows = $this->rowsByRoll($placed);
        $this->assertSame(['22JE0302', '22JE0303', '22JE0305'], array_keys($rows));
        $this->assertEquals(1000000, $placed->getCell('L'.$rows['22JE0302'])->getValue(), 'Best CTC must be the INR maximum');
        $this->assertNull($placed->getCell('L'.$rows['22JE0303'])->getValue(), 'a USD-only student must have no INR Best CTC');
        $this->assertSame(DataType::TYPE_STRING, $placed->getCell('C'.$rows['22JE0305'])->getDataType());
        $this->assertStringContainsString('(3 of 5 enrolled)', (string) $placed->getCell('A1')->getValue());

        $notPlaced = $this->sheet($this->get("{$base}/students_not_placed")->assertOk());
        $rows = $this->rowsByRoll($notPlaced);
        $this->assertSame(['22JE0301', '22JE0304'], array_keys($rows));
        $this->assertSame('Yes', $notPlaced->getCell('K'.$rows['22JE0301'])->getValue());

        $offers = $this->sheet($this->get("{$base}/job_offers")->assertOk());
        for ($r = 4; $r <= 8; $r++) {
            foreach (['C', 'F'] as $col) {
                $cell = $offers->getCell($col.$r);
                if ($cell->getValue() !== null) {
                    $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), "{$col}{$r} is not an explicit string");
                }
            }
        }
        $this->assertContains('=1+2', collect($offers->rangeToArray('F4:F8', null, false, false))->flatten()->all());

        $matrix = $this->sheet($this->get("{$base}/placement_matrix")->assertOk());
        $headers = [];
        foreach ($matrix->getRowIterator(3, 3)->current()->getCellIterator() as $cell) {
            $headers[(string) $cell->getValue()] = $cell->getDataType();
        }
        $this->assertArrayHasKey('=1+2', $headers);
        $this->assertSame(DataType::TYPE_STRING, $headers['=1+2']);

        $profiles = collect($this->sheet($this->get("{$base}/job_profiles")->assertOk())->toArray())->flatten();
        $this->assertTrue($profiles->contains('Accepting Applications'));
    }

    public function test_reports_for_another_placement_do_not_leak_offers(): void
    {
        $s = $this->student('22JE0401');
        $this->offer($this->float($this->jnf('Gamma')), $s, 'fulltime', 1200000);
        $other = PlacementCycle::create([
            'name' => 'Other', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);

        Sanctum::actingAs($this->admin);
        $offers = collect($this->sheet($this->get("/api/admin/placement-cycles/{$other->id}/reports/job_offers")->assertOk())->toArray())->flatten();
        $this->assertFalse($offers->contains('22JE0401'));
        $this->getJson("/api/admin/placement-cycles/{$this->cycle->id}/reports/../../settings")->assertNotFound();
    }

    // ---------------------------------------------------------------- S0: Excel headers unchanged

    public function test_cycle_student_export_headers_are_the_pre_parity_headers(): void
    {
        $this->student('22JE0501');
        Sanctum::actingAs($this->admin);
        $sheet = $this->sheet($this->get("/api/admin/placement-cycles/{$this->cycle->id}/students/export")->assertOk());
        $headers = array_values(array_filter($sheet->rangeToArray('A1:Z1')[0], fn ($v) => $v !== null));
        foreach (['Roll No', 'Name', 'Programme', 'Branch', 'CGPA'] as $h) {
            $this->assertContains($h, $headers);
        }
        foreach (['Roll Number', 'Passout Batch', 'Mobile No.', 'Contact No.', 'Class X Percentage', 'Social Category'] as $renamed) {
            $this->assertNotContains($renamed, $headers, "export header renamed to {$renamed}");
        }
    }
}
