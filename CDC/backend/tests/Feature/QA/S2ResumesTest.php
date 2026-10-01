<?php

namespace Tests\Feature\QA;

use App\Mail\ResumeReviewedMail;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * QA acceptance — Section 2 (Resumes): T2.1–T2.7.
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S2ResumesTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const MB = 1024 * 1024;

    /** 1×1 transparent PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private array $tmp = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach ($this->tmp as $path) {
            @unlink($path);
        }
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    /** A real (not faked-MIME) uploaded file, so MIME sniffing runs on the bytes. */
    private function realFile(string $clientName, string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'qares');
        file_put_contents($path, $bytes);
        $this->tmp[] = $path;

        return new UploadedFile($path, $clientName, null, null, true);
    }

    private function pdfBytes(int $size = 50 * 1024): string
    {
        $head = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            ."3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n";
        $tail = "\n%%EOF\n";

        return $head.str_repeat(' ', max(0, $size - strlen($head) - strlen($tail))).$tail;
    }

    private function pdf(string $name = 'cv.pdf', int $size = 50 * 1024): UploadedFile
    {
        return $this->realFile($name, $this->pdfBytes($size));
    }

    private function upload(int|string $slot, ?UploadedFile $file = null, string $label = 'Software')
    {
        return $this->post('/api/student/resumes', [
            'slot' => $slot,
            'label' => $label,
            'file' => $file ?? $this->pdf(),
        ], ['Accept' => 'application/json']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function as(User $user): void
    {
        // A fresh instance each time so no relation cached by an earlier request leaks into the next one.
        Sanctum::actingAs(User::find($user->id));
    }

    private function cycle(): PlacementCycle
    {
        return PlacementCycle::create([
            'name' => 'FT 2026-27', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    private function posting(PlacementCycle $cycle, ?Company $company = null): JobPosting
    {
        $company ??= Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => uniqid('hr', true).'@acme.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted',
            'form_data' => [
                'jobTitle' => 'SDE',
                'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '6.0', 'backlogsAllowed' => true]]]],
                'genderFilter' => 'all',
                'graduatingBatch' => '2027',
            ],
        ]);

        return JobPosting::create([
            'postable_type' => Jnf::class, 'postable_id' => $jnf->id, 'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(5), 'status' => 'open', 'floated_at' => now(),
        ]);
    }

    private function enrolledStudent(PlacementCycle $cycle): StudentProfile
    {
        $student = StudentProfile::factory()->create();
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        return $student;
    }

    private function resumeRow(StudentProfile $student, int $slot, string $status = 'pending', string $label = 'Main'): Resume
    {
        return $student->resumes()->create(['slot' => $slot, 'label' => $label, 'file_path' => "resumes/{$student->roll_no}/{$slot}_x.pdf", 'file_size' => 10, 'status' => $status]);
    }

    // ------------------------------------------------------------------ T2.1

    public function test_T2_1_pdf_only_max_2mb_with_real_mime_sniffing(): void
    {
        $student = StudentProfile::factory()->create();
        $this->as($student->user);

        $this->upload(1, $this->pdf('cv.pdf', (int) (1.9 * self::MB)))->assertCreated();
        $this->upload(2, $this->pdf('exact.pdf', 2 * self::MB))->assertCreated();
        $this->upload(3, $this->pdf('big.pdf', 2 * self::MB + 1))->assertStatus(422)->assertJsonValidationErrors(['file'])
            ->assertJsonPath('errors.file.0', 'The resume must be 2 MB or smaller.');

        $this->upload(3, $this->realFile('cv.docx', "PK\x03\x04".str_repeat("\0", 200)))->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->upload(3, UploadedFile::fake()->create('cv.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))->assertStatus(422);
        $this->upload(3, $this->realFile('photo.pdf', base64_decode(self::PNG)))->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->upload(3, $this->realFile('notes.pdf', "just some text, not a pdf\n"))->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->upload(4, $this->pdf('CV.PDF'))->assertCreated();

        $this->assertEqualsCanonicalizing([1, 2, 4], Resume::pluck('slot')->all());
        $this->assertCount(3, Storage::disk('local')->allFiles(), 'rejected uploads must not leave files behind');
        $this->assertSame((int) (1.9 * self::MB), Resume::where('slot', 1)->value('file_size'));
    }

    // ------------------------------------------------------------------ T2.1b

    public function test_T2_1b_max_eight_slots(): void
    {
        $student = StudentProfile::factory()->create();
        $this->as($student->user);

        for ($slot = 1; $slot <= 8; $slot++) {
            $this->upload($slot, null, "Slot {$slot}")->assertCreated();
        }
        foreach ([9, 0, -1, 'abc'] as $bad) {
            $this->upload($bad)->assertStatus(422)->assertJsonValidationErrors(['slot']);
        }

        $this->getJson('/api/student/resumes')->assertOk()->assertJsonCount(8, 'resumes')->assertJsonPath('max_slots', 8);
        $this->assertSame(8, Resume::count());
    }

    // ------------------------------------------------------------------ T2.2

    public function test_T2_2_reject_requires_remark_reupload_resets_and_e7_on_both_decisions(): void
    {
        Mail::fake();
        $student = StudentProfile::factory()->create();
        $admin = $this->admin();
        $this->as($student->user);
        $this->upload(1)->assertCreated();
        $resume = Resume::sole();
        $firstPath = $resume->file_path;

        $this->as($admin);
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected'])->assertStatus(422)->assertJsonValidationErrors(['admin_remark']);
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected', 'admin_remark' => ''])->assertStatus(422);
        $this->assertSame('pending', $resume->fresh()->status);
        Mail::assertNothingQueued();

        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected', 'admin_remark' => 'Add your CGPA'])->assertOk();
        Mail::assertQueued(ResumeReviewedMail::class, fn ($m) => $m->hasTo($student->user->email) && $m->approved === false && $m->remark === 'Add your CGPA');
        $this->assertStringContainsString('Add your CGPA', Mail::queued(ResumeReviewedMail::class)->first()->render());

        $this->as($student->user);
        $this->getJson('/api/student/resumes')->assertOk()
            ->assertJsonPath('resumes.0.status', 'rejected')
            ->assertJsonPath('resumes.0.admin_remark', 'Add your CGPA');

        $this->upload(1, null, 'Software v2')->assertOk()->assertJsonPath('resume.status', 'pending');
        $resume->refresh();
        $this->assertSame('pending', $resume->status);
        $this->assertNull($resume->admin_remark);
        $this->assertNull($resume->reviewed_by);
        $this->assertNotSame($firstPath, $resume->file_path);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($resume->file_path);

        $this->as($admin);
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved'])->assertOk();
        Mail::assertQueued(ResumeReviewedMail::class, fn ($m) => $m->approved === true && $m->hasTo($student->user->email));
        Mail::assertQueued(ResumeReviewedMail::class, 2);

        // Re-upload after approval also resets to pending and removes the approved file.
        $approvedPath = $resume->fresh()->file_path;
        $this->as($student->user);
        $this->upload(1, null, 'Software v3')->assertOk()->assertJsonPath('resume.status', 'pending');
        Storage::disk('local')->assertMissing($approvedPath);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    // ------------------------------------------------------------------ T2.4

    public function test_T2_4_labels_validated_and_visible_to_admin_and_student(): void
    {
        $student = StudentProfile::factory()->create();
        $this->as($student->user);

        foreach (['', '   ', str_repeat('a', 200), str_repeat('b', 61)] as $bad) {
            $this->upload(1, null, $bad)->assertStatus(422)->assertJsonValidationErrors(['label']);
        }
        $this->assertSame(0, Resume::count());

        $sixty = str_repeat('c', 60);
        $this->upload(1, null, 'Data Science')->assertCreated();
        $this->upload(2, null, $sixty)->assertCreated();
        $resume = Resume::where('slot', 1)->sole();

        foreach (['', str_repeat('a', 200)] as $bad) {
            $this->patchJson("/api/student/resumes/{$resume->id}", ['label' => $bad])->assertStatus(422)->assertJsonValidationErrors(['label']);
        }
        $this->patchJson("/api/student/resumes/{$resume->id}", ['label' => 'Analytics'])->assertOk();

        $this->assertEqualsCanonicalizing(['Analytics', $sixty], collect($this->getJson('/api/student/resumes')->json('resumes'))->pluck('label')->all());

        $this->as($this->admin());
        $this->assertEqualsCanonicalizing(['Analytics', $sixty], collect($this->getJson('/api/admin/resumes?status=pending')->assertOk()->json('resumes'))->pluck('label')->all());
    }

    // ------------------------------------------------------------------ T2.5

    private function lockFixture(): array
    {
        Mail::fake();
        $cycle = $this->cycle();
        $student = $this->enrolledStudent($cycle);
        $posting = $this->posting($cycle);
        $this->as($student->user);
        $this->upload(1, null, 'Spare')->assertCreated();
        $this->upload(2, null, 'Applied')->assertCreated();
        $two = Resume::where('slot', 2)->sole();
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $two->id])->assertCreated();

        return [$student, $posting, $two];
    }

    private function assertLocked(Resume $resume): void
    {
        $path = $resume->fresh()->file_path;
        $files = count(Storage::disk('local')->allFiles());

        $this->upload($resume->slot, null, 'Replacement')->assertStatus(422);
        $this->deleteJson("/api/student/resumes/{$resume->id}")->assertStatus(422);

        $this->assertSame($path, $resume->fresh()->file_path);
        Storage::disk('local')->assertExists($path);
        $this->assertCount($files, Storage::disk('local')->allFiles(), 'a refused replacement must not leave an orphan file');
        $this->assertTrue(collect($this->getJson('/api/student/resumes')->json('resumes'))->firstWhere('id', $resume->id)['is_locked']);
    }

    public function test_T2_5_resume_locked_while_open_or_in_process_unlocked_after_completion(): void
    {
        [$student, $posting, $two] = $this->lockFixture();

        $this->assertLocked($two);
        $spare = Resume::where('slot', 1)->sole();
        $this->assertFalse(collect($this->getJson('/api/student/resumes')->json('resumes'))->firstWhere('id', $spare->id)['is_locked']);
        $this->deleteJson("/api/student/resumes/{$spare->id}")->assertOk();

        $this->as($this->admin());
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        $this->assertSame('in_process', $posting->fresh()->status);
        $this->as($student->user);
        $this->assertLocked($two);

        $posting->update(['status' => 'completed']);
        $oldPath = $two->fresh()->file_path;
        $this->upload(2, null, 'Replaced after completion')->assertOk()->assertJsonPath('resume.status', 'pending');
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertFalse($two->fresh()->isLocked());
    }

    public function test_T2_5_withdrawn_application_does_not_lock(): void
    {
        [$student, $posting, $two] = $this->lockFixture();
        $this->assertLocked($two);

        $this->postJson('/api/student/applications/'.Application::sole()->id.'/withdraw')->assertOk();
        $this->assertFalse($two->fresh()->isLocked());

        $this->upload(2, null, 'Replaced after withdrawal')->assertOk()->assertJsonPath('resume.status', 'pending');

        // A cancelled posting does not lock either.
        $posting->update(['status' => 'cancelled']);
        $this->assertFalse($two->fresh()->isLocked());
    }

    /**
     * NEEDS-OWNER-DECISION: B5 implies an unlocked resume may be deleted, but C9's `resume_id restrictOnDelete`
     * makes deleting a resume that any application row references impossible; D50 refuses it with a message.
     * This records the implemented interpretation (replace allowed, delete refused) after completion/withdrawal.
     */
    public function test_T2_5_delete_after_completion_or_withdrawal_records_d50_behaviour(): void
    {
        [$student, $posting, $two] = $this->lockFixture();

        $this->postJson('/api/student/applications/'.Application::sole()->id.'/withdraw')->assertOk();
        $this->deleteJson("/api/student/resumes/{$two->id}")->assertStatus(422)
            ->assertJsonPath('message', 'This resume is part of a past application record and cannot be deleted. You can replace it with a new file instead.');

        $posting->update(['status' => 'completed']);
        $this->deleteJson("/api/student/resumes/{$two->id}")->assertStatus(422);
        $this->assertNotNull($two->fresh());
    }

    // ------------------------------------------------------------------ T2.6

    public function test_T2_6_admin_queue_paginated_and_one_audit_row_per_decision(): void
    {
        Mail::fake();
        $students = StudentProfile::factory()->count(55)->create();
        foreach ($students as $i => $student) {
            $this->resumeRow($student, 1, 'pending', "Resume {$i}");
        }
        $this->resumeRow($students[0], 2, 'approved', 'Already ok');
        $this->resumeRow($students[1], 2, 'rejected', 'Already bad');

        $admin = $this->admin();
        $this->as($admin);

        $page1 = $this->getJson('/api/admin/resumes?status=pending')->assertOk()
            ->assertJsonPath('meta.total', 55)->assertJsonPath('meta.per_page', 50)
            ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(50, 'resumes');
        $this->assertTrue(collect($page1->json('resumes'))->every(fn ($r) => $r['status'] === 'pending' && ! empty($r['student_profile']['roll_no'])));
        $this->assertTrue(collect($page1->json('resumes'))->every(fn ($r) => ! array_key_exists('file_path', $r)));
        $this->getJson('/api/admin/resumes?status=pending&page=2')->assertOk()->assertJsonCount(5, 'resumes');

        $ids = collect($page1->json('resumes'))->pluck('id')->take(4)->values();
        $this->patchJson("/api/admin/resumes/{$ids[0]}", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/admin/resumes/{$ids[1]}", ['status' => 'approved'])->assertOk();
        $this->patchJson("/api/admin/resumes/{$ids[2]}", ['status' => 'rejected'])->assertStatus(422); // no remark → not a decision
        $this->patchJson("/api/admin/resumes/{$ids[2]}", ['status' => 'rejected', 'admin_remark' => 'Blurry'])->assertOk();
        $this->patchJson("/api/admin/resumes/{$ids[3]}", ['status' => 'rejected', 'admin_remark' => 'One page only'])->assertOk();

        $logs = AuditLog::whereIn('action', ['resume.approve', 'resume.reject'])->get();
        $this->assertCount(4, $logs);
        $this->assertSame(2, $logs->where('action', 'resume.approve')->count());
        $this->assertSame(2, $logs->where('action', 'resume.reject')->count());
        $this->assertTrue($logs->every(fn ($l) => $l->user_id === $admin->id && $l->subject_type === Resume::class));
        $this->assertEqualsCanonicalizing($ids->all(), $logs->pluck('subject_id')->all());
        $this->assertSame('pending', $logs->firstWhere('subject_id', $ids[2])->before['status']);
        $this->assertSame('Blurry', $logs->firstWhere('subject_id', $ids[2])->after['admin_remark']);

        $this->getJson('/api/admin/resumes?status=pending')->assertJsonPath('meta.total', 51);
    }

    // ------------------------------------------------------------------ T2.7

    public function test_T2_7_guests_get_401_on_resume_endpoints(): void
    {
        $student = StudentProfile::factory()->create();
        $resume = $this->resumeRow($student, 1);

        $this->getJson("/api/student/resumes/{$resume->id}/file")->assertUnauthorized();
        $this->getJson("/api/admin/resumes/{$resume->id}/file")->assertUnauthorized();
        $this->getJson('/api/student/resumes')->assertUnauthorized();
        $this->getJson('/api/admin/resumes')->assertUnauthorized();
        $this->patchJson("/api/student/resumes/{$resume->id}", ['label' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/student/resumes/{$resume->id}")->assertUnauthorized();
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved'])->assertUnauthorized();
        $this->assertSame('pending', $resume->fresh()->status);
    }

    public function test_T2_7_student_cannot_reach_another_students_resume(): void
    {
        $owner = StudentProfile::factory()->create();
        $this->as($owner->user);
        $this->upload(1, null, 'Owner CV')->assertCreated();
        $resume = Resume::sole();

        $intruder = StudentProfile::factory()->create();
        $this->as($intruder->user);
        $this->assertContains($this->get("/api/student/resumes/{$resume->id}/file", ['Accept' => 'application/json'])->status(), [403, 404]);
        $this->assertContains($this->patchJson("/api/student/resumes/{$resume->id}", ['label' => 'Pwned'])->status(), [403, 404]);
        $this->assertContains($this->deleteJson("/api/student/resumes/{$resume->id}")->status(), [403, 404]);
        $this->getJson("/api/admin/resumes/{$resume->id}/file")->assertForbidden();
        $this->getJson('/api/admin/resumes')->assertForbidden();
        $this->assertSame([], $this->getJson('/api/student/resumes')->json('resumes'));

        // Uploading into "slot 1" as the intruder creates the intruder's own slot, never touches the owner's.
        $this->upload(1, null, 'Mine')->assertCreated();
        $this->assertSame('Owner CV', $resume->fresh()->label);
        Storage::disk('local')->assertExists($resume->fresh()->file_path);
    }

    public function test_T2_7_company_only_via_signed_urls(): void
    {
        Mail::fake();
        $cycle = $this->cycle();
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.test']);
        $companyUser = User::factory()->create(['role' => 'company', 'company_id' => $company->id]);
        $otherCompanyUser = User::factory()->create(['role' => 'company', 'company_id' => Company::create(['name' => 'Other', 'hr_name' => 'HR', 'hr_email' => 'hr@other.test'])->id]);
        $posting = $this->posting($cycle, $company);
        $student = $this->enrolledStudent($cycle);
        $this->as($student->user);
        $this->upload(1, $this->pdf('cv.pdf', 4096))->assertCreated();
        $resume = Resume::sole();
        $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $resume->id])->assertCreated();

        $this->as($companyUser);
        $this->getJson("/api/admin/resumes/{$resume->id}/file")->assertForbidden();
        $this->getJson("/api/student/resumes/{$resume->id}/file")->assertForbidden();
        $this->getJson('/api/admin/resumes')->assertForbidden();
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved'])->assertForbidden();

        $applicants = $this->getJson("/api/company/postings/{$posting->id}/applicants")->assertOk();
        $this->assertStringNotContainsString($resume->file_path, $applicants->getContent());
        $this->assertArrayNotHasKey('used_unverified_resume', $applicants->json('applicants.0'));
        $url = $applicants->json('applicants.0.resume_url');
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        $this->as($otherCompanyUser);
        $this->getJson("/api/company/postings/{$posting->id}/applicants")->assertNotFound();

        // The signed link streams without any login.
        $this->app['auth']->forgetGuards();
        $response = $this->get($url)->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertSame(Storage::disk('local')->get($resume->file_path), $response->streamedContent());
    }

    public function test_T2_7_signed_url_tamper_swap_and_expiry(): void
    {
        $student = StudentProfile::factory()->create();
        $this->as($student->user);
        $this->upload(1)->assertCreated();
        $this->upload(2)->assertCreated();
        [$resume, $other] = Resume::orderBy('slot')->get()->all();

        $url = $resume->signedUrl();
        $this->app['auth']->forgetGuards();

        $this->get($url)->assertOk();

        // Flip one character of the signature.
        $tampered = preg_replace_callback('/signature=([0-9a-f])/', fn ($m) => 'signature='.($m[1] === 'a' ? 'b' : 'a'), $url);
        $this->assertNotSame($url, $tampered);
        $this->get($tampered)->assertForbidden();
        $this->get($url.'0')->assertForbidden();

        // Extend the expiry without re-signing.
        parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
        $this->get(str_replace('expires='.$q['expires'], 'expires='.($q['expires'] + 86400 * 365), $url))->assertForbidden();

        // Swap the resume id but keep the signature.
        $this->get(str_replace("signed/{$resume->id}?", "signed/{$other->id}?", $url))->assertForbidden();
        $this->assertContains($this->get(str_replace("signed/{$resume->id}?", 'signed/999999?', $url))->status(), [403, 404]);

        // The private disk's own /storage route never serves a resume without a signature.
        $this->assertContains($this->get('/storage/'.$resume->file_path)->status(), [403, 404]);

        // Short-lived admin preview link and the 30-day company link both expire.
        $preview = $resume->previewUrl();
        $this->travel(31)->minutes();
        $this->get($preview)->assertForbidden();
        $this->get($url)->assertOk();
        $this->travel(31)->days();
        $this->get($url)->assertForbidden();
    }
}
