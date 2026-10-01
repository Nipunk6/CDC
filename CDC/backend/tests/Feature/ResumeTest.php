<?php

namespace Tests\Feature;

use App\Mail\ResumeReviewedMail;
use App\Models\AuditLog;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResumeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function pdf(string $name = 'cv.pdf', int $kb = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, 'application/pdf');
    }

    private function upload(int $slot = 1, ?UploadedFile $file = null, string $label = 'Software')
    {
        return $this->post('/api/student/resumes', [
            'slot' => $slot,
            'label' => $label,
            'file' => $file ?? $this->pdf(),
        ], ['Accept' => 'application/json']);
    }

    public function test_student_uploads_into_slot_and_file_is_private(): void
    {
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);

        $this->upload()->assertCreated()->assertJsonPath('resume.status', 'pending')->assertJsonMissingPath('resume.file_path');

        $resume = Resume::sole();
        $this->assertStringStartsWith("resumes/{$student->roll_no}/1_", $resume->file_path);
        Storage::disk('local')->assertExists($resume->file_path);
        Storage::disk('public')->assertMissing($resume->file_path);

        $this->get("/api/student/resumes/{$resume->id}/file")->assertOk();
    }

    public function test_oversize_and_non_pdf_are_rejected(): void
    {
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);

        $this->upload(1, $this->pdf('big.pdf', 2049))->assertStatus(422);
        $this->upload(1, UploadedFile::fake()->create('cv.docx', 10, 'application/msword'))->assertStatus(422);
        $this->upload(9)->assertStatus(422);
        $this->upload(1, $this->pdf('ok.pdf', 2048))->assertCreated();
    }

    public function test_reupload_resets_to_pending_and_deletes_old_file(): void
    {
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);

        $this->upload()->assertCreated();
        $resume = Resume::sole();
        $oldPath = $resume->file_path;
        $resume->update(['status' => 'rejected', 'admin_remark' => 'Fix typos']);

        $this->upload(1, null, 'Software v2')->assertOk()->assertJsonPath('resume.status', 'pending');

        $resume->refresh();
        $this->assertNull($resume->admin_remark);
        $this->assertSame('Software v2', $resume->label);
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertSame(1, Resume::count());
    }

    public function test_students_cannot_touch_each_others_resumes(): void
    {
        $owner = StudentProfile::factory()->create();
        Sanctum::actingAs($owner->user);
        $this->upload()->assertCreated();
        $resume = Resume::sole();

        $other = StudentProfile::factory()->create();
        Sanctum::actingAs($other->user);

        $this->get("/api/student/resumes/{$resume->id}/file")->assertNotFound();
        $this->deleteJson("/api/student/resumes/{$resume->id}")->assertNotFound();
        $this->assertSame(1, Resume::count());
    }

    public function test_admin_approves_and_rejects_with_audit_and_mail(): void
    {
        Mail::fake();
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);
        $this->upload()->assertCreated();
        $resume = Resume::sole();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/resumes?status=pending')->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('resumes.0.student_profile.roll_no', $student->roll_no);

        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected'])->assertStatus(422);
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected', 'admin_remark' => 'Add CGPA'])->assertOk();
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved'])->assertOk()->assertJsonPath('resume.status', 'approved');

        Mail::assertQueued(ResumeReviewedMail::class, 2);
        $this->assertSame(1, AuditLog::where('action', 'resume.reject')->count());
        $this->assertSame(1, AuditLog::where('action', 'resume.approve')->count());
    }

    public function test_signed_url_streams_without_login_and_rejects_tampering(): void
    {
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);
        $this->upload()->assertCreated();
        $this->upload(2)->assertCreated();
        [$resume, $other] = Resume::orderBy('slot')->get()->all();

        $url = $resume->signedUrl();
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertOk();
        $this->get($url.'x')->assertForbidden();
        $this->get(str_replace("signed/{$resume->id}", "signed/{$other->id}", $url))->assertForbidden();

        $this->travel(31)->days();
        $this->get($url)->assertForbidden();
    }

    public function test_approving_a_version_the_admin_did_not_see_is_refused(): void
    {
        $student = StudentProfile::factory()->create();
        Sanctum::actingAs($student->user);
        $this->upload()->assertCreated();
        $seen = Resume::sole()->updated_at->toIso8601String();

        $this->travel(1)->minutes();
        $this->upload(1, null, 'Replaced')->assertOk();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $resume = Resume::sole();
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved', 'expected_updated_at' => $seen])->assertStatus(409);
        $this->assertSame('pending', $resume->fresh()->status);

        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved', 'expected_updated_at' => $resume->updated_at->toIso8601String()])->assertOk();
    }
}
