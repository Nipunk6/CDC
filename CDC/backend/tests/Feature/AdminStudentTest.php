<?php

namespace Tests\Feature;

use App\Mail\StudentInvitationMail;
use App\Mail\StudentProfileUpdatedMail;
use App\Models\AuditLog;
use App\Models\BranchChangeRequest;
use App\Models\EmailLog;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminStudentTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'roll_no' => '22je0459',
            'full_name' => 'Aarav Sharma',
            'institute_email' => '22je0459@iitism.ac.in',
            'programme' => self::BTECH,
            'branch' => 'computer science & engineering',
            'graduating_batch' => 2027,
            'gender' => 'male',
            'current_cgpa' => 8.45,
            'ongoing_backlogs' => 0,
            'total_backlogs' => 1,
            'tenth_percent' => 94.2,
            'twelfth_percent' => 91,
        ], $overrides);
    }

    private function csvRow(array $values): string
    {
        return implode(',', array_map(fn ($v) => str_contains((string) $v, ',') ? '"'.$v.'"' : $v, $values));
    }

    public function test_admin_creates_student_with_invitation_and_audit(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/students', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('student.roll_no', '22JE0459')
            ->assertJsonPath('student.branch', 'Computer Science & Engineering');

        $student = StudentProfile::sole();
        $this->assertSame('student', $student->user->role);
        $this->assertSame('22je0459@iitism.ac.in', $student->user->email);
        $this->assertTrue($student->user->is_active);

        Mail::assertQueued(StudentInvitationMail::class, fn ($mail) => $mail->rollNo === '22JE0459'
            && str_contains($mail->setPasswordUrl, '/auth/student/set-password?token='));
        $this->assertSame(1, EmailLog::where('template', 'emails.student-invitation')->where('status', 'queued')->count());

        $log = AuditLog::where('action', 'student.create')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($student->id, $log->subject_id);
    }

    public function test_programme_and_branch_must_be_in_catalogue(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $this->postJson('/api/admin/students', $this->payload(['branch' => 'Astrology']))
            ->assertStatus(422);

        $this->assertSame(0, StudentProfile::count());
    }

    public function test_bulk_import_dry_run_reports_exactly_the_bad_rows_then_imports_the_rest(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $lines = [implode(',', \App\Services\StudentAccountService::IMPORT_COLUMNS)];
        for ($i = 1; $i <= 10; $i++) {
            $roll = sprintf('22JE%04d', $i);
            $lines[] = $this->csvRow([
                $roll, "Student ".chr(64 + $i), strtolower($roll).'@iitism.ac.in', self::BTECH,
                $i === 4 ? 'Not A Branch' : 'Mining Engineering', 2027, $i === 7 ? 'robot' : 'female',
                '7.5', '0', '0', '88', '87', '2004-05-06', '', '', 'GEN', 'no', 'Bihar',
            ]);
        }
        $csv = implode("\n", $lines);

        $dry = $this->post('/api/admin/students/import', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
            'dry_run' => 1,
        ], ['Accept' => 'application/json']);

        $dry->assertOk()->assertJsonPath('valid_rows', 8)->assertJsonPath('created', 0);
        $this->assertCount(2, $dry->json('errors'));
        $this->assertEqualsCanonicalizing([5, 8], array_column($dry->json('errors'), 'row'));
        $this->assertSame(0, StudentProfile::count());

        $real = $this->post('/api/admin/students/import', [
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ], ['Accept' => 'application/json']);

        $real->assertOk()->assertJsonPath('created', 8);
        $this->assertSame(8, User::where('role', 'student')->count());
        $this->assertSame(8, EmailLog::where('template', 'emails.student-invitation')->count());
        Mail::assertQueued(StudentInvitationMail::class, 8);
        $this->assertSame(1, AuditLog::where('action', 'student.import')->count());
    }

    public function test_student_logs_in_with_roll_number_and_suspension_blocks_login(): void
    {
        $student = StudentProfile::factory()->create(['roll_no' => '22JE0100']);
        $student->user->update(['password' => Hash::make('Secret123')]);

        $this->postJson('/api/auth/login', ['roll_no' => '22je0100', 'password' => 'Secret123'])
            ->assertOk()
            ->assertJsonPath('user.role', 'student')
            ->assertJsonPath('user.student_profile.roll_no', '22JE0100');

        $this->actingAsAdmin();
        $this->patchJson("/api/admin/students/{$student->id}/suspend")->assertOk();
        $this->patchJson("/api/admin/students/{$student->id}/suspend")->assertStatus(422);

        $this->postJson('/api/auth/login', ['roll_no' => '22JE0100', 'password' => 'Secret123'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Account suspended. Contact CDC.');

        $this->patchJson("/api/admin/students/{$student->id}/reactivate")->assertOk();
        $this->assertTrue($student->user->fresh()->is_active);
        $this->assertSame(2, AuditLog::whereIn('action', ['student.suspend', 'student.reactivate'])->count());
    }

    public function test_index_is_paginated_and_searchable(): void
    {
        $this->actingAsAdmin();
        StudentProfile::factory()->count(55)->create();
        StudentProfile::factory()->create(['roll_no' => '21MC0001', 'full_name' => 'Zoya Khan']);

        $this->getJson('/api/admin/students')
            ->assertOk()
            ->assertJsonPath('meta.total', 56)
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonCount(50, 'students');

        $this->getJson('/api/admin/students?search=zoya')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('students.0.roll_no', '21MC0001');
    }

    public function test_admin_update_is_audited_with_before_after_and_notifies_student(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $student = StudentProfile::factory()->create(['current_cgpa' => 7.1]);

        $this->patchJson("/api/admin/students/{$student->id}", ['current_cgpa' => 7.9, 'phone' => '9876543210'])
            ->assertOk()
            ->assertJsonPath('student.current_cgpa', '7.90');

        $log = AuditLog::where('action', 'student.update')->sole();
        $this->assertEquals('7.10', $log->before['current_cgpa']);
        $this->assertEquals('7.90', $log->after['current_cgpa']);
        Mail::assertQueued(StudentProfileUpdatedMail::class);
    }

    public function test_student_can_only_edit_personal_fields(): void
    {
        $student = StudentProfile::factory()->create(['current_cgpa' => 6.5]);
        Sanctum::actingAs($student->user);

        $this->patchJson('/api/student/profile', [
            'phone' => '9999999999',
            'current_cgpa' => 9.9,
            'branch' => 'Mining Engineering',
        ])->assertOk();

        $student->refresh();
        $this->assertSame('9999999999', $student->phone);
        $this->assertEquals('6.50', $student->current_cgpa);
        $this->assertSame('Computer Science & Engineering', $student->branch);

        $this->getJson('/api/admin/students')->assertForbidden();
    }

    public function test_student_photo_is_stored_privately_and_streamed(): void
    {
        Storage::fake('local');
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);

        $this->post('/api/student/profile/photo', [
            'photo' => UploadedFile::fake()->image('me.png')->size(500),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('student.has_photo', true);

        $path = $student->fresh()->photo_path;
        $this->assertStringStartsWith("student-photos/{$student->roll_no}/", $path);
        Storage::disk('local')->assertExists($path);
        $this->get('/api/student/profile/photo')->assertOk();

        $this->post('/api/student/profile/photo', [
            'photo' => UploadedFile::fake()->image('big.png')->size(1500),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_branch_change_request_flow(): void
    {
        Mail::fake();
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);

        $this->postJson('/api/student/branch-change', [
            'requested_branch' => 'Mathematics & Computing',
            'reason' => 'Approved by senate after first year.',
        ])->assertCreated();

        $this->postJson('/api/student/branch-change', [
            'requested_branch' => 'Mining Engineering',
            'reason' => 'Second request while first pending.',
        ])->assertStatus(409);

        $request = BranchChangeRequest::sole();
        $this->actingAsAdmin();

        $this->patchJson("/api/admin/branch-changes/{$request->id}", ['status' => 'rejected'])
            ->assertStatus(422);

        $this->patchJson("/api/admin/branch-changes/{$request->id}", ['status' => 'approved'])
            ->assertOk();

        $this->assertSame('Mathematics & Computing', $student->fresh()->branch);
        $this->assertSame(1, AuditLog::where('action', 'branch_change.approve')->count());
        Mail::assertQueued(StudentProfileUpdatedMail::class);

        $this->patchJson("/api/admin/branch-changes/{$request->id}", ['status' => 'approved'])
            ->assertStatus(422);
    }

    public function test_academic_bulk_update_goes_through_the_sync_service(): void
    {
        $this->actingAsAdmin();
        $a = StudentProfile::factory()->create(['roll_no' => '22JE0001', 'current_cgpa' => 7.0]);
        StudentProfile::factory()->create(['roll_no' => '22JE0002', 'current_cgpa' => 8.0]);

        $csv = "roll_no,current_cgpa,ongoing_backlogs,total_backlogs\n22JE0001,7.4,1,2\n22JE0002,8.0,0,0\n22JE9999,9,0,0\n22JE0001,7,0,0";

        $response = $this->post('/api/admin/students/academics/import', [
            'file' => UploadedFile::fake()->createWithContent('acad.csv', $csv),
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('updated', 1)
            ->assertJsonPath('unchanged', 1);
        $this->assertCount(2, $response->json('errors'));

        $a->refresh();
        $this->assertEquals('7.40', $a->current_cgpa);
        $this->assertSame(1, $a->ongoing_backlogs);
        $this->assertSame(1, AuditLog::where('action', 'student.academics_sync')->count());
    }

    public function test_import_template_downloads(): void
    {
        $this->actingAsAdmin();

        $this->get('/api/admin/students/import/template')
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    public function test_admin_can_still_edit_a_student_whose_branch_left_the_catalogue(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $student = StudentProfile::factory()->create(['branch' => 'Retired Branch']);

        $this->patchJson("/api/admin/students/{$student->id}", ['phone' => '9123456789', 'branch' => 'Retired Branch'])
            ->assertOk();
        $this->patchJson("/api/admin/students/{$student->id}", ['branch' => 'Another Unknown'])
            ->assertStatus(422);
    }

    public function test_student_reset_link_points_at_the_student_page(): void
    {
        Mail::fake();
        $student = StudentProfile::factory()->create();

        $this->postJson('/api/auth/forgot-password', ['roll_no' => $student->roll_no])->assertOk();

        Mail::assertSent(\App\Mail\PasswordResetLinkMail::class, fn ($mail) => str_contains($mail->resetUrl, '/auth/student/set-password?'));
    }
}
