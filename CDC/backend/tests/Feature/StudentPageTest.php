<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\StudentNote;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentAccountService;
use App\Support\ExportFieldCatalogue;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Superset parity S4.5 (admin student page: placements, attendance, resumes, notes, reports) and
 * S4.6 (CDC-entered academic extras).
 */
class StudentPageTest extends TestCase
{
    use RefreshDatabase;

    private const NOTE = 'Confidential: spoke to parents about attendance';

    private User $admin;

    private User $companyUser;

    private PlacementCycle $cycle;

    private JobPosting $posting;

    /** @var list<StudentProfile> */
    private array $students = [];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $this->companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.test']);

        $this->cycle = PlacementCycle::create([
            'name' => 'FT 2026-27', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => StudentProfileFactory::BTECH, 'batches' => [2027]]],
        ]);

        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 3,
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => StudentProfileFactory::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'selectionRounds' => [
                    ['type' => 'aptitude_test', 'enabled' => true],
                    ['type' => 'technical_interview', 'enabled' => true],
                ],
            ],
        ]);

        for ($i = 0; $i < 3; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('22JE%04d', $i + 1)]);
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $this->students[] = $s;
        }

        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDay()->toIso8601String(),
        ])->assertCreated();
        $this->posting = JobPosting::sole();

        foreach (array_slice($this->students, 0, 2) as $s) {
            $resume = $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'pending']);
            Application::create([
                'job_posting_id' => $this->posting->id, 'student_profile_id' => $s->id, 'resume_id' => $resume->id,
                'status' => 'applied', 'used_unverified_resume' => true, 'applied_at' => now(),
            ]);
        }
    }

    private function student(int $i): StudentProfile
    {
        return $this->students[$i];
    }

    /** Stage 1 appeared + shortlisted (published), stage 2 absent (draft), and a Full-Time offer for student 0. */
    private function seedResultsAndOffer(): void
    {
        $application = Application::where('student_profile_id', $this->student(0)->id)->sole();
        [$r1, $r2] = $this->posting->rounds()->get()->all();
        ApplicationRoundResult::create(['application_id' => $application->id, 'posting_round_id' => $r1->id, 'attendance' => 'yes', 'result' => 'selected', 'published_at' => now()]);
        ApplicationRoundResult::create(['application_id' => $application->id, 'posting_round_id' => $r2->id, 'attendance' => 'no', 'result' => 'rejected']);
        Offer::create([
            'application_id' => $application->id, 'student_profile_id' => $this->student(0)->id, 'company_id' => $this->companyUser->company_id,
            'job_posting_id' => $this->posting->id, 'placement_cycle_id' => $this->cycle->id, 'offer_type' => 'fulltime',
            'ctc_annual' => 2400000, 'currency' => 'INR', 'announced_by' => $this->admin->id, 'announced_at' => now(),
        ]);
    }

    private function sheetRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $content);
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, false, false, false);
        @unlink($path);

        return $rows;
    }

    // ------------------------------------------------------------------ S4.5 page payload

    public function test_show_returns_summary_placements_with_attendance_and_resumes(): void
    {
        $this->seedResultsAndOffer();

        $response = $this->getJson("/api/admin/students/{$this->student(0)->id}")->assertOk();
        $response->assertJsonPath('student.summary.applications_count', 1)
            ->assertJsonPath('student.summary.offers_count', 1)
            ->assertJsonPath('student.placements.0.cycle.name', 'FT 2026-27')
            ->assertJsonPath('student.placements.0.placed', true)
            ->assertJsonPath('student.placements.0.status_label', 'Placed (SDE at Acme)')
            ->assertJsonPath('student.placements.0.applications.0.title', 'SDE')
            ->assertJsonPath('student.placements.0.applications.0.stages.0.attendance', 'yes')
            ->assertJsonPath('student.placements.0.applications.0.stages.0.published', true)
            ->assertJsonPath('student.placements.0.applications.0.stages.1.attendance', 'no')
            ->assertJsonPath('student.placements.0.applications.0.stages.1.published', false)
            ->assertJsonPath('student.resumes.0.label', 'CV')
            ->assertJsonPath('student.resumes.0.status', 'pending');
        $this->assertNotEmpty($response->json('student.resumes.0.preview_url'));
        $this->assertArrayNotHasKey('file_path', $response->json('student.resumes.0'));

        // A PPO that was only offered does not make the student placed (D80).
        Offer::query()->update(['offer_type' => 'ppo_offered']);
        $this->getJson("/api/admin/students/{$this->student(0)->id}")
            ->assertJsonPath('student.placements.0.placed', false)
            ->assertJsonPath('student.placements.0.status_label', 'Enrolled');

        $this->getJson("/api/admin/students/{$this->student(2)->id}")
            ->assertJsonPath('student.placements.0.status_label', 'Enrolled')
            ->assertJsonPath('student.placements.0.applications', [])
            ->assertJsonPath('student.summary.applications_count', 0);
    }

    // ------------------------------------------------------------------ Notes

    public function test_notes_crud_is_audited_and_limited_to_author_or_super_admin(): void
    {
        $id = $this->student(0)->id;

        $this->postJson("/api/admin/students/{$id}/notes", ['body' => '  '])->assertStatus(422);
        $noteId = $this->postJson("/api/admin/students/{$id}/notes", ['body' => self::NOTE])
            ->assertCreated()
            ->assertJsonPath('note.body', self::NOTE)
            ->assertJsonPath('note.author.id', $this->admin->id)
            ->json('note.id');

        $this->getJson("/api/admin/students/{$id}/notes")->assertOk()->assertJsonPath('notes.0.body', self::NOTE);
        $log = AuditLog::where('action', 'student.note_create')->sole();
        $this->assertSame($id, $log->subject_id);
        $this->assertSame(self::NOTE, $log->after['body']);

        // A note of another student is not reachable through this student.
        $this->deleteJson("/api/admin/students/{$this->student(1)->id}/notes/{$noteId}")->assertNotFound();

        // Another (non-super) admin cannot delete it; a super admin can.
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->deleteJson("/api/admin/students/{$id}/notes/{$noteId}")->assertForbidden();
        $this->assertSame(1, StudentNote::count());

        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_super_admin' => true]));
        $this->deleteJson("/api/admin/students/{$id}/notes/{$noteId}")->assertOk();
        $this->assertSame(0, StudentNote::count());
        $this->assertSame(self::NOTE, AuditLog::where('action', 'student.note_delete')->sole()->before['body']);

        // The author may delete their own note.
        Sanctum::actingAs($this->admin);
        $other = $this->postJson("/api/admin/students/{$id}/notes", ['body' => 'Second'])->json('note.id');
        $this->deleteJson("/api/admin/students/{$id}/notes/{$other}")->assertOk();
    }

    public function test_notes_never_appear_in_student_or_company_payloads(): void
    {
        $student = $this->student(0);
        $this->postJson("/api/admin/students/{$student->id}/notes", ['body' => self::NOTE])->assertCreated();

        Sanctum::actingAs($student->user);
        foreach (['/api/student/profile', '/api/student/dashboard', '/api/student/applications'] as $url) {
            $response = $this->getJson($url)->assertOk();
            $this->assertStringNotContainsString(self::NOTE, $response->getContent(), $url);
        }

        Sanctum::actingAs($this->companyUser);
        foreach (["/api/company/postings/{$this->posting->id}/applicants", "/api/company/postings/{$this->posting->id}", '/api/company/dashboard'] as $url) {
            $response = $this->getJson($url);
            $this->assertLessThan(500, $response->status(), $url);
            $this->assertStringNotContainsString(self::NOTE, $response->getContent(), $url);
        }
    }

    // ------------------------------------------------------------------ Reports

    public function test_placement_report_downloads_with_stage_results_attendance_and_audit(): void
    {
        $this->seedResultsAndOffer();

        $response = $this->get("/api/admin/students/{$this->student(0)->id}/placement-report")->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('22je0001-placement-report.xlsx', $response->headers->get('Content-Disposition'));

        $rows = $this->sheetRows($response->streamedContent());
        $this->assertStringContainsString('22JE0001', $rows[0][0]);
        $header = $rows[2];
        $data = array_combine($header, $rows[3]);
        $this->assertSame('SDE', $data['Job Profile']);
        $this->assertSame('Acme', $data['Company']);
        $this->assertSame('FT 2026-27', $data['Placement']);
        $this->assertSame('Shortlisted', $data['Stage 1 Result']);
        $this->assertSame('Yes', $data['Stage 1 Attendance']);
        $this->assertSame('Not selected (draft)', $data['Stage 2 Result']);
        $this->assertSame('No', $data['Stage 2 Attendance']);
        $this->assertSame('Full-Time', $data['Offer']);

        $log = AuditLog::where('action', 'student.placement_report')->sole();
        $this->assertSame($this->student(0)->id, $log->subject_id);
    }

    public function test_eligibility_report_lists_reasons_and_skips_cancelled_job_profiles(): void
    {
        $student = $this->student(2);
        $student->update(['current_cgpa' => 5.5]);

        // A cancelled job profile in the same cycle is left out.
        $otherJnf = $this->posting->postable->replicate();
        $otherJnf->save();
        $cancelled = $this->posting->replicate();
        $cancelled->postable_id = $otherJnf->id;
        $cancelled->status = 'cancelled';
        $cancelled->save();

        $response = $this->get("/api/admin/students/{$student->id}/eligibility-report")->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));

        $rows = $this->sheetRows($response->streamedContent());
        $header = $rows[2];
        $data = array_combine($header, $rows[3]);
        $this->assertSame('SDE', $data['Job Profile']);
        $this->assertSame('No', $data['Eligible']);
        $this->assertStringContainsString('CGPA below cutoff (5.5 < 6', $data['Reasons']);
        $this->assertNull($rows[4][1] ?? null, 'only one non-cancelled job profile');

        $log = AuditLog::where('action', 'student.eligibility_report')->sole();
        $this->assertSame(1, $log->after['job_profiles']);
        $this->assertSame(0, $log->after['eligible']);

        // An eligible student gets "Yes" and no reasons.
        $rows = $this->sheetRows($this->get("/api/admin/students/{$this->student(1)->id}/eligibility-report")->streamedContent());
        $this->assertSame('Yes', array_combine($rows[2], $rows[3])['Eligible']);
    }

    public function test_report_cells_are_formula_safe(): void
    {
        $this->student(0)->update(['full_name' => '=HYPERLINK("http://x")']);
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->get("/api/admin/students/{$this->student(0)->id}/placement-report")->streamedContent());
        $cell = IOFactory::load($path)->getActiveSheet()->getCell('A1');
        $this->assertSame('s', $cell->getDataType());
        @unlink($path);
    }

    // ------------------------------------------------------------------ Mark all as verified

    public function test_mark_all_as_verified_reuses_the_decision_path_and_skips_stale_files(): void
    {
        $student = $this->student(0);
        $first = $student->resumes()->sole();
        $second = $student->resumes()->create(['slot' => 2, 'label' => 'Core CV', 'file_path' => 'resumes/y.pdf', 'file_size' => 1, 'status' => 'pending']);

        $response = $this->postJson("/api/admin/students/{$student->id}/resumes/verify-all", [
            'resumes' => [
                ['id' => $first->id, 'expected_updated_at' => $first->updated_at->toIso8601String()],
                ['id' => $second->id, 'expected_updated_at' => now()->subDay()->toIso8601String()], // replaced since
            ],
        ])->assertOk();

        $response->assertJsonPath('verified', 1)->assertJsonPath('skipped', 1);
        $this->assertSame('approved', $first->fresh()->status);
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertSame(1, AuditLog::where('action', 'resume.approve')->count());
        // B3: approving clears the unverified flag on the application that used it.
        $this->assertFalse((bool) Application::where('resume_id', $first->id)->sole()->used_unverified_resume);

        // Another student's resume cannot be verified through this student.
        $foreign = $this->student(1)->resumes()->sole();
        $this->postJson("/api/admin/students/{$student->id}/resumes/verify-all", [
            'resumes' => [['id' => $foreign->id, 'expected_updated_at' => $foreign->updated_at->toIso8601String()]],
        ])->assertOk()->assertJsonPath('verified', 0);
        $this->assertSame('pending', $foreign->fresh()->status);
    }

    // ------------------------------------------------------------------ S4.6 academic extras

    private function extras(): array
    {
        return [
            'current_semester' => 7,
            'course_start_date' => '2023-07-25',
            'course_end_date' => '2027-05-31',
            'lateral_entry' => true,
            'tenth_board' => 'CBSE',
            'tenth_passing_year' => 2019,
            'twelfth_board' => 'ICSE',
            'twelfth_passing_year' => 2021,
            'previous_degree' => 'B.Sc',
            'previous_degree_score' => 8.4,
            'previous_degree_score_type' => 'cgpa',
        ];
    }

    public function test_admin_update_saves_academic_extras_with_audit_and_validation(): void
    {
        $student = $this->student(0);

        $this->patchJson("/api/admin/students/{$student->id}", $this->extras())
            ->assertOk()
            ->assertJsonPath('student.current_semester', 7)
            ->assertJsonPath('student.course_end_date', '2027-05-31')
            ->assertJsonPath('student.lateral_entry', true)
            ->assertJsonPath('student.tenth_board', 'CBSE');

        $log = AuditLog::where('action', 'student.update')->sole();
        $this->assertSame(7, $log->after['current_semester']);
        $this->assertSame('CBSE', $log->after['tenth_board']);

        $this->getJson("/api/admin/students/{$student->id}")->assertJsonPath('student.previous_degree_score', '8.40');

        // End before start (the stored start counts when only the end is sent), CGPA above 10, bad score type.
        $this->patchJson("/api/admin/students/{$student->id}", ['course_end_date' => '2022-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors('course_end_date');
        $this->patchJson("/api/admin/students/{$student->id}", ['previous_degree_score' => 75])
            ->assertStatus(422)->assertJsonValidationErrors('previous_degree_score');
        $this->patchJson("/api/admin/students/{$student->id}", ['previous_degree_score_type' => 'grade'])
            ->assertStatus(422)->assertJsonValidationErrors('previous_degree_score_type');
        $this->patchJson("/api/admin/students/{$student->id}", ['current_semester' => 13])
            ->assertStatus(422)->assertJsonValidationErrors('current_semester');
    }

    public function test_student_cannot_change_academic_extras_and_sees_them_read_only(): void
    {
        $student = $this->student(0);
        $student->update(['tenth_board' => 'CBSE', 'current_semester' => 5]);

        Sanctum::actingAs($student->user);
        $this->patchJson('/api/student/profile', ['tenth_board' => 'Hacked', 'current_semester' => 8, 'lateral_entry' => true, 'phone' => '9999999999'])
            ->assertOk();

        $fresh = $student->fresh();
        $this->assertSame('CBSE', $fresh->tenth_board);
        $this->assertSame(5, $fresh->current_semester);
        $this->assertFalse((bool) $fresh->lateral_entry);
        $this->assertSame('9999999999', $fresh->phone);
        $this->assertSame(['personal_email', 'phone', 'home_state', 'linkedin_url', 'github_url'], StudentProfile::SELF_EDITABLE);

        $this->getJson('/api/student/profile')->assertJsonPath('student.tenth_board', 'CBSE')->assertJsonPath('student.current_semester', 5);
    }

    public function test_bulk_import_accepts_extras_at_the_end_and_old_files_still_import(): void
    {
        $base = fn (string $roll) => [
            $roll, 'New Student', strtolower($roll).'@iitism.ac.in', StudentProfileFactory::BTECH, 'Computer Science & Engineering', '2027', 'male',
            '8.1', '0', '0', '90', '91', '2004-05-06', '', '', 'GEN', 'no', 'Bihar',
        ];
        $extras = ['7', '25-07-2023', '2027-05-31', 'yes', 'CBSE', '2019', 'ICSE', '2021', '', '', ''];

        $header = implode(',', array_merge(StudentAccountService::IMPORT_COLUMNS, StudentAccountService::IMPORT_EXTRA_COLUMNS));
        $csv = $header."\n".implode(',', array_merge($base('23JE0001'), $extras))."\n".implode(',', $base('23JE0002'));

        $this->post('/api/admin/students/import', ['file' => UploadedFile::fake()->createWithContent('s.csv', $csv)])
            ->assertOk()->assertJsonPath('created', 2);

        $new = StudentProfile::where('roll_no', '23JE0001')->sole();
        $this->assertSame(7, $new->current_semester);
        $this->assertSame('2023-07-25', $new->course_start_date->format('Y-m-d'));
        $this->assertTrue($new->lateral_entry);
        $this->assertSame('ICSE', $new->twelfth_board);
        $this->assertSame(2021, $new->twelfth_passing_year);

        $old = StudentProfile::where('roll_no', '23JE0002')->sole();
        $this->assertNull($old->current_semester);
        $this->assertFalse((bool) $old->lateral_entry);

        // A bad extra is reported against its row.
        $bad = $header."\n".implode(',', array_merge($base('23JE0003'), ['7', '2027-01-01', '2026-01-01', '', '', '', '', '', '', '', '']));
        $this->post('/api/admin/students/import?dry_run=1', ['file' => UploadedFile::fake()->createWithContent('s.csv', $bad)])
            ->assertOk()->assertJsonPath('errors.0.field', 'course_end_date');

        // The template ends with the new columns (human-readable headers since Superset parity S5.6).
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->get('/api/admin/students/import/template')->streamedContent());
        $headers = IOFactory::load($path)->getActiveSheet()->toArray()[0];
        $this->assertSame(StudentAccountService::IMPORT_HEADERS['home_state'], $headers[count(StudentAccountService::IMPORT_COLUMNS) - 1]);
        $this->assertSame(StudentAccountService::IMPORT_HEADERS['previous_degree_score_type'], end($headers));
    }

    public function test_academic_update_accepts_extras_and_blank_cells_keep_values(): void
    {
        $student = $this->student(0);
        $student->update(['tenth_board' => 'CBSE']);

        $csv = "roll_no,current_cgpa,ongoing_backlogs,total_backlogs,current_semester,course_start_date,course_end_date,lateral_entry,tenth_board,tenth_passing_year,twelfth_board,twelfth_passing_year,previous_degree,previous_degree_score,previous_degree_score_type\n"
            ."22JE0001,7.9,,,6,,,,,2019,,,M.Sc,72.5,percentage\n"
            ."22JE0002,8.2,0,0\n"
            ."22JE0003,,,,,,,,,,,,,11,cgpa";

        $response = $this->post('/api/admin/students/academics/import', ['file' => UploadedFile::fake()->createWithContent('a.csv', $csv)])->assertOk();
        $response->assertJsonPath('updated', 2)->assertJsonPath('errors.0.roll_no', '22JE0003')->assertJsonPath('errors.0.field', 'previous_degree_score');

        $fresh = $student->fresh();
        $this->assertSame(6, $fresh->current_semester);
        $this->assertSame('CBSE', $fresh->tenth_board);
        $this->assertSame(2019, $fresh->tenth_passing_year);
        $this->assertSame('percentage', $fresh->previous_degree_score_type);

        $log = AuditLog::where('action', 'student.academics_sync')->where('subject_id', $student->id)->sole();
        $this->assertSame(6, $log->after['current_semester']);

        // The template ends with the extras.
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $this->get('/api/admin/students/academics/template')->streamedContent());
        $headers = IOFactory::load($path)->getActiveSheet()->toArray()[0];
        $this->assertSame(['roll_no', 'current_cgpa', 'ongoing_backlogs', 'total_backlogs', 'current_semester'], array_slice($headers, 0, 5));
    }

    public function test_field_catalogue_has_the_extras_with_superset_labels_admin_only(): void
    {
        $fields = ExportFieldCatalogue::fields();
        foreach ([
            'tenth_passing_year' => 'Year of passing 10th', 'tenth_board' => 'Xth Board', 'twelfth_passing_year' => 'Year of passing 12th',
            'twelfth_board' => 'XIIth Board', 'current_semester' => 'Current Semester', 'course_start_date' => 'Course Start Date',
            'course_end_date' => 'Course End Date', 'lateral_entry' => 'Lateral Entry', 'previous_degree' => 'Previous Degree',
            'previous_degree_score' => 'Previous Degree Score',
        ] as $key => $label) {
            $this->assertSame($label, $fields[$key]['label'] ?? null, $key);
            $this->assertSame('admin', $fields[$key]['audience']);
        }

        $student = $this->student(0);
        $student->update(['previous_degree_score' => 72.5, 'previous_degree_score_type' => 'percentage', 'lateral_entry' => true]);
        $context = ['student' => $student->fresh()];
        $this->assertSame('72.5%', $fields['previous_degree_score']['value']($context));
        $this->assertSame('Yes', $fields['lateral_entry']['value']($context));
    }

    // ------------------------------------------------------------------ Permissions

    public function test_students_and_companies_cannot_reach_the_new_admin_routes(): void
    {
        $id = $this->student(0)->id;
        $note = StudentNote::create(['student_profile_id' => $id, 'author_id' => $this->admin->id, 'body' => self::NOTE]);

        foreach ([$this->student(0)->user, $this->companyUser] as $user) {
            Sanctum::actingAs($user);
            $this->getJson("/api/admin/students/{$id}/notes")->assertForbidden();
            $this->postJson("/api/admin/students/{$id}/notes", ['body' => 'x'])->assertForbidden();
            $this->deleteJson("/api/admin/students/{$id}/notes/{$note->id}")->assertForbidden();
            $this->getJson("/api/admin/students/{$id}/placement-report")->assertForbidden();
            $this->getJson("/api/admin/students/{$id}/eligibility-report")->assertForbidden();
            $this->postJson("/api/admin/students/{$id}/resumes/verify-all", ['resumes' => []])->assertForbidden();
            $this->getJson("/api/admin/students/{$id}")->assertForbidden();
        }

        $this->assertSame(1, StudentNote::count());
        $this->assertSame(0, AuditLog::whereIn('action', ['student.placement_report', 'student.eligibility_report'])->count());
    }
}
