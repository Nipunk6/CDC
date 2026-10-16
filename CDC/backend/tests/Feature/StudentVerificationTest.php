<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\PortalNotification;
use App\Models\StudentFieldVerification;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Profile verification: every student-changeable field starts unverified, a student's change applies at once and
 * goes back to unverified, the admin accepts or rejects each field (or verifies all), and the green badge needs every
 * field verified and no pending or rejected resume.
 */
class StudentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private StudentProfile $student;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->student = StudentProfile::factory()->create(['roll_no' => '23XY0001', 'phone' => '9000000001', 'linkedin_url' => null]);
    }

    private function show(): array
    {
        Sanctum::actingAs($this->admin);

        return $this->getJson("/api/admin/students/{$this->student->id}")->assertOk()->json('student');
    }

    private function field(array $student, string $field): array
    {
        return collect($student['verification'])->firstWhere('field', $field);
    }

    private function verifyAll(array $student): void
    {
        $this->postJson("/api/admin/students/{$this->student->id}/verification/verify-all", [
            'fields' => collect($student['verification'])->map(fn ($f) => ['field' => $f['field'], 'changed_at' => $f['changed_at']])->all(),
        ])->assertOk();
    }

    public function test_every_field_starts_unverified_and_the_list_shows_the_student_unverified(): void
    {
        $student = $this->show();

        $this->assertFalse($student['is_verified']);
        $this->assertSame(array_keys(StudentVerificationService::FIELDS), array_column($student['verification'], 'field'));
        $this->assertSame(['unverified'], array_values(array_unique(array_column($student['verification'], 'status'))));
        // An empty field still needs the admin's look.
        $this->assertFalse($this->field($student, 'linkedin_url')['has_value']);

        $row = collect($this->getJson('/api/admin/students')->assertOk()->json('students'))->firstWhere('id', $this->student->id);
        $this->assertFalse($row['is_verified']);
    }

    public function test_verify_all_turns_the_badge_green_once_resumes_are_approved(): void
    {
        $resume = $this->student->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'pending']);

        $this->verifyAll($this->show());
        $student = $this->show();
        $this->assertSame(['verified'], array_values(array_unique(array_column($student['verification'], 'status'))));
        $this->assertFalse($student['is_verified'], 'a pending resume keeps the badge off');

        $resume->update(['status' => 'approved']);
        $this->assertTrue($this->show()['is_verified']);
        $row = collect($this->getJson('/api/admin/students')->json('students'))->firstWhere('id', $this->student->id);
        $this->assertTrue($row['is_verified']);
        $this->assertSame(count(StudentVerificationService::FIELDS), AuditLog::where('action', 'student.field_verify')->count());
    }

    public function test_a_student_change_applies_at_once_and_goes_back_to_unverified(): void
    {
        $this->verifyAll($this->show());
        $this->assertTrue($this->show()['is_verified']);

        Sanctum::actingAs($this->student->user);
        $this->patchJson('/api/student/profile', ['phone' => '9111111111', 'home_state' => $this->student->home_state])->assertOk()
            ->assertJsonPath('student.phone', '9111111111')
            ->assertJsonPath('student.is_verified', false);

        $student = $this->show();
        $this->assertSame('unverified', $this->field($student, 'phone')['status']);
        $this->assertSame('9111111111', $this->field($student, 'phone')['value']);
        // Sending an unchanged value does not reset that field.
        $this->assertSame('verified', $this->field($student, 'home_state')['status']);

        Sanctum::actingAs($this->student->user);
        $this->postJson('/api/student/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg')])->assertOk();
        $this->assertSame('unverified', $this->field($this->show(), 'photo')['status']);
    }

    public function test_reject_needs_a_remark_tells_the_student_and_accepting_later_works(): void
    {
        $phone = $this->field($this->show(), 'phone');
        $url = "/api/admin/students/{$this->student->id}/verification/phone";

        $this->patchJson($url, ['status' => 'rejected', 'expected_changed_at' => $phone['changed_at']])->assertStatus(422)->assertJsonValidationErrors('remark');
        $this->patchJson($url, ['status' => 'rejected', 'remark' => 'Number does not ring', 'expected_changed_at' => $phone['changed_at']])->assertOk();

        $phone = $this->field($this->show(), 'phone');
        $this->assertSame('rejected', $phone['status']);
        $this->assertSame('Number does not ring', $phone['remark']);
        $this->assertSame(1, PortalNotification::where('user_id', $this->student->user_id)->where('title', 'Profile change needs correction')->count());
        $this->assertSame(1, AuditLog::where('action', 'student.field_reject')->count());

        // The student sees the remark on their own profile.
        Sanctum::actingAs($this->student->user);
        $own = collect($this->getJson('/api/student/profile')->assertOk()->json('student.verification'))->firstWhere('field', 'phone');
        $this->assertSame('rejected', $own['status']);
        $this->assertSame('Number does not ring', $own['remark']);

        $this->patchJson($url, ['status' => 'verified', 'expected_changed_at' => $phone['changed_at']])->assertForbidden();
        Sanctum::actingAs($this->admin);
        $this->patchJson($url, ['status' => 'verified', 'expected_changed_at' => $phone['changed_at']])->assertOk();
        $this->assertNull($this->field($this->show(), 'phone')['remark']);
    }

    public function test_a_decision_on_a_value_the_student_changed_since_is_refused(): void
    {
        $seen = $this->field($this->show(), 'phone')['changed_at'];

        Sanctum::actingAs($this->student->user);
        $this->patchJson('/api/student/profile', ['phone' => '9222222222'])->assertOk();

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/students/{$this->student->id}/verification/phone", ['status' => 'verified', 'expected_changed_at' => $seen])->assertStatus(409);
        $this->assertSame('unverified', StudentFieldVerification::where('field', 'phone')->sole()->status);

        $this->postJson("/api/admin/students/{$this->student->id}/verification/verify-all", ['fields' => [['field' => 'phone', 'changed_at' => $seen]]])
            ->assertOk()->assertJsonPath('verified', 0)->assertJsonPath('skipped', 1);
    }

    public function test_an_admin_edit_counts_as_verified_and_unknown_fields_are_refused(): void
    {
        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/students/{$this->student->id}", ['phone' => '9333333333'])->assertOk();
        $this->assertSame('verified', $this->field($this->show(), 'phone')['status']);

        $this->patchJson("/api/admin/students/{$this->student->id}/verification/current_cgpa", ['status' => 'verified'])->assertNotFound();
        $this->postJson("/api/admin/students/{$this->student->id}/verification/verify-all", ['fields' => [['field' => 'current_cgpa']]])
            ->assertStatus(422);
    }
}
