<?php

namespace Tests\Feature\QA;

use App\Http\Controllers\AuthController;
use App\Jobs\SendPostingFloatedMails;
use App\Jobs\SendStudentInvitation;
use App\Mail\PostingFloatedMail;
use App\Mail\ResumeReviewedMail;
use App\Mail\StudentInvitationMail;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\PortalSetting;
use App\Models\Resume;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\EligibilityService;
use App\Services\SettingsService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * QA acceptance — Section 0 (Foundation): T0.4a–g, T0.5a–c, T0.6.
 */
#[\PHPUnit\Framework\Attributes\Group('qa')]
class S0FoundationTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    // ------------------------------------------------------------------ helpers

    private function company(string $name = 'QA Co'): Company
    {
        return Company::create([
            'name' => $name,
            'industry' => 'Technology',
            'website' => 'https://qa.example',
            'hr_name' => 'QA HR',
            'hr_email' => uniqid('hr', true).'@qa.example',
            'hr_phone' => '1234567890',
        ]);
    }

    private function companyUser(?Company $company = null): User
    {
        $company ??= $this->company();

        return User::factory()->create(['role' => 'company', 'company_id' => $company->id]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function formData(): array
    {
        return [
            'jobTitle' => 'Software Engineer',
            'internshipTitle' => 'Summer Intern',
            'companyProfile' => ['name' => 'QA Co'],
            'eligibility' => [[
                'programme' => self::BTECH,
                'branches' => [['branch' => 'Computer Science & Engineering', 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => false]],
            ]],
            'genderFilter' => 'all',
            'graduatingBatch' => '2027',
            'selectionRounds' => [['id' => '1', 'type' => 'hr_interview', 'enabled' => true]],
        ];
    }

    private function jnf(int $companyId, string $status, array $extra = []): Jnf
    {
        return Jnf::create(array_merge([
            'company_id' => $companyId,
            'job_title' => 'Engineer',
            'job_description' => 'Build things.',
            'status' => $status,
            'form_data' => $this->formData(),
        ], $extra));
    }

    private function inf(int $companyId, string $status, array $extra = []): Inf
    {
        return Inf::create(array_merge([
            'company_id' => $companyId,
            'internship_title' => 'Intern',
            'internship_description' => 'Learn things.',
            'status' => $status,
            'form_data' => $this->formData(),
        ], $extra));
    }

    private function cycle(string $type = 'fulltime'): PlacementCycle
    {
        return PlacementCycle::create([
            'name' => 'QA '.$type,
            'type' => $type,
            'starts_on' => '2026-07-01',
            'ends_on' => '2027-06-30',
            'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    /** A job_postings row for the form = "floated". */
    private function floatDirectly(Jnf|Inf $form, string $status = 'open'): JobPosting
    {
        return JobPosting::create([
            'postable_type' => $form::class,
            'postable_id' => $form->id,
            'placement_cycle_id' => $this->cycle($form instanceof Inf ? 'internship' : 'fulltime')->id,
            'application_deadline' => now()->addDays(5),
            'status' => $status,
            'floated_at' => now(),
        ]);
    }

    /** Use a real bearer token for the next request (forget any user cached by the guard). */
    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function enrolledStudent(PlacementCycle $cycle, array $attributes = []): StudentProfile
    {
        $student = StudentProfile::factory()->create($attributes);
        CycleEnrollment::create(['placement_cycle_id' => $cycle->id, 'student_profile_id' => $student->id, 'status' => 'active']);

        return $student;
    }

    // ------------------------------------------------------------------ T0.4a

    public function test_T0_4a_public_admin_register_is_removed(): void
    {
        $response = $this->postJson('/api/auth/admin/register', [
            'name' => 'Evil Admin',
            'email' => 'evil@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $this->assertContains($response->status(), [404, 405], 'POST /api/auth/admin/register must be gone (404/405), got '.$response->status());
        $this->assertDatabaseMissing('users', ['email' => 'evil@example.com']);

        foreach (Route::getRoutes() as $route) {
            $this->assertStringNotContainsString('admin/register', $route->uri(), 'A route still exposes admin/register: '.$route->uri());
            $this->assertNotSame('registerAdmin', $route->getActionMethod(), 'A live route still points at registerAdmin: '.$route->uri());
        }

        $this->assertFalse(method_exists(AuthController::class, 'registerAdmin'), 'AuthController::registerAdmin must be deleted (spec M0.3a).');
    }

    // ------------------------------------------------------------------ T0.4b

    public function test_T0_4b_company_cannot_create_jnf_or_inf_with_a_privileged_status(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->companyUser());

        foreach (['accepted', 'under_review', 'rejected'] as $status) {
            $this->postJson('/api/company/jnfs', ['job_title' => 'E', 'job_description' => 'D', 'status' => $status])
                ->assertStatus(422)->assertJsonValidationErrors(['status']);
            $this->postJson('/api/company/infs', ['internship_title' => 'I', 'internship_description' => 'D', 'status' => $status])
                ->assertStatus(422)->assertJsonValidationErrors(['status']);
        }

        $this->assertDatabaseCount('jnfs', 0);
        $this->assertDatabaseCount('infs', 0);
    }

    public function test_T0_4b_company_cannot_put_a_privileged_status_on_existing_forms(): void
    {
        Mail::fake();
        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $draftJnf = $this->jnf($user->company_id, 'draft');
        $reviewJnf = $this->jnf($user->company_id, 'under_review');
        $draftInf = $this->inf($user->company_id, 'draft');
        $reviewInf = $this->inf($user->company_id, 'under_review');

        foreach (['accepted', 'under_review', 'rejected'] as $status) {
            foreach ([$draftJnf, $reviewJnf] as $jnf) {
                $this->putJson("/api/company/jnfs/{$jnf->id}", ['job_title' => 'E2', 'job_description' => 'D2', 'status' => $status])
                    ->assertStatus(422);
            }
            foreach ([$draftInf, $reviewInf] as $inf) {
                $this->putJson("/api/company/infs/{$inf->id}", ['internship_title' => 'I2', 'internship_description' => 'D2', 'status' => $status])
                    ->assertStatus(422);
            }
        }

        $this->assertSame('draft', $draftJnf->fresh()->status);
        $this->assertSame('under_review', $reviewJnf->fresh()->status);
        $this->assertSame('draft', $draftInf->fresh()->status);
        $this->assertSame('under_review', $reviewInf->fresh()->status);
        $this->assertSame('Engineer', $draftJnf->fresh()->job_title, 'A rejected PUT must not partially apply other fields.');
    }

    public function test_T0_4b_autosave_cannot_set_a_privileged_status(): void
    {
        Mail::fake();
        $user = $this->companyUser();
        Sanctum::actingAs($user);

        $jnf = $this->jnf($user->company_id, 'draft');
        $inf = $this->inf($user->company_id, 'draft');

        foreach (['accepted', 'under_review', 'rejected'] as $status) {
            $r = $this->postJson('/api/company/jnfs/autosave', ['id' => $jnf->id, 'job_title' => 'E', 'job_description' => 'D', 'status' => $status]);
            $this->assertContains($r->status(), [200, 422], 'autosave with status must be refused or ignored, got '.$r->status());
            $this->assertSame('draft', $jnf->fresh()->status, "autosave changed JNF status to {$status}");

            $r = $this->postJson('/api/company/infs/autosave', ['id' => $inf->id, 'internship_title' => 'I', 'internship_description' => 'D', 'status' => $status]);
            $this->assertContains($r->status(), [200, 422]);
            $this->assertSame('draft', $inf->fresh()->status, "autosave changed INF status to {$status}");

            // New-record autosave path.
            $r = $this->postJson('/api/company/jnfs/autosave', ['job_title' => 'New', 'job_description' => 'D', 'status' => $status]);
            $this->assertContains($r->status(), [201, 422]);
            if ($r->status() === 201) {
                $this->assertSame('draft', Jnf::find($r->json('jnf.id'))->status);
            }
        }

        $this->assertSame(0, Jnf::whereIn('status', ['accepted', 'under_review', 'rejected'])->count());
        $this->assertSame(0, Inf::whereIn('status', ['accepted', 'under_review', 'rejected'])->count());
    }

    /** Extra probe found while reading the controller: a null/blank status must be a 4xx, never a 500. */
    public function test_T0_4b_extra_blank_status_is_a_validation_error_not_a_server_error(): void
    {
        Mail::fake();
        $user = $this->companyUser();
        Sanctum::actingAs($user);
        $jnf = $this->jnf($user->company_id, 'draft');

        $put = $this->putJson("/api/company/jnfs/{$jnf->id}", ['job_title' => 'E', 'job_description' => 'D', 'status' => null])->status();
        $post = $this->postJson('/api/company/jnfs', ['job_title' => 'E', 'job_description' => 'D', 'status' => ''])->status();
        $putInf = $this->putJson('/api/company/infs/'.$this->inf($user->company_id, 'draft')->id, ['internship_title' => 'I', 'internship_description' => 'D', 'status' => null])->status();

        $this->assertSame('draft', $jnf->fresh()->status);
        $this->assertLessThan(500, $put, "PUT /company/jnfs/{id} with \"status\": null → {$put}; POST with \"status\": \"\" → {$post}; PUT /company/infs/{id} null → {$putInf}");
        $this->assertLessThan(500, $post, "POST /company/jnfs with \"status\": \"\" → {$post}");
        $this->assertLessThan(500, $putInf, "PUT /company/infs/{id} with \"status\": null → {$putInf}");
    }

    // ------------------------------------------------------------------ T0.4c

    public function test_T0_4c_company_cannot_write_admin_remarks(): void
    {
        Mail::fake();
        $user = $this->companyUser();
        Sanctum::actingAs($user);

        // POST
        $created = $this->postJson('/api/company/jnfs', ['job_title' => 'E', 'job_description' => 'D', 'status' => 'draft', 'admin_remarks' => 'hacked']);
        $this->assertContains($created->status(), [201, 422]);
        $this->assertSame(0, Jnf::where('admin_remarks', 'hacked')->count());

        $createdInf = $this->postJson('/api/company/infs', ['internship_title' => 'I', 'internship_description' => 'D', 'status' => 'draft', 'admin_remarks' => 'hacked']);
        $this->assertContains($createdInf->status(), [201, 422]);
        $this->assertSame(0, Inf::where('admin_remarks', 'hacked')->count());

        // PUT on a form the CDC already remarked on (company-editable status).
        $jnf = $this->jnf($user->company_id, 'under_review', ['admin_remarks' => 'Fix CTC']);
        $inf = $this->inf($user->company_id, 'under_review', ['admin_remarks' => 'Fix stipend']);
        $draft = $this->jnf($user->company_id, 'draft');

        $r = $this->putJson("/api/company/jnfs/{$jnf->id}", ['job_title' => 'E', 'job_description' => 'D', 'admin_remarks' => 'hacked']);
        $this->assertContains($r->status(), [200, 422]);
        $r = $this->putJson("/api/company/infs/{$inf->id}", ['internship_title' => 'I', 'internship_description' => 'D', 'admin_remarks' => 'hacked']);
        $this->assertContains($r->status(), [200, 422]);
        $r = $this->putJson("/api/company/jnfs/{$draft->id}", ['job_title' => 'E', 'job_description' => 'D', 'admin_remarks' => 'hacked']);
        $this->assertContains($r->status(), [200, 422]);

        // Autosave
        $this->postJson('/api/company/jnfs/autosave', ['id' => $jnf->id, 'job_title' => 'E', 'job_description' => 'D', 'admin_remarks' => 'hacked']);
        $this->postJson('/api/company/infs/autosave', ['id' => $inf->id, 'internship_title' => 'I', 'internship_description' => 'D', 'admin_remarks' => 'hacked']);
        $this->postJson('/api/company/jnfs/autosave', ['job_title' => 'New', 'job_description' => 'D', 'admin_remarks' => 'hacked']);

        $this->assertSame('Fix CTC', $jnf->fresh()->admin_remarks);
        $this->assertSame('Fix stipend', $inf->fresh()->admin_remarks);
        $this->assertNull($draft->fresh()->admin_remarks);
        $this->assertSame(0, Jnf::where('admin_remarks', 'hacked')->count());
        $this->assertSame(0, Inf::where('admin_remarks', 'hacked')->count());
    }

    // ------------------------------------------------------------------ T0.4d

    public function test_T0_4d_tokens_expire_after_seven_days(): void
    {
        $this->assertSame(60 * 24 * 7, config('sanctum.expiration'));

        foreach ([$this->admin(), $this->companyUser(), StudentProfile::factory()->create()->user] as $user) {
            $fresh = $user->createToken('qa');
            $this->bearer($fresh->plainTextToken)->getJson('/api/auth/user')->assertOk();

            $fresh->accessToken->forceFill(['created_at' => now()->subDays(6)])->save();
            $this->bearer($fresh->plainTextToken)->getJson('/api/auth/user')->assertOk();

            $fresh->accessToken->forceFill(['created_at' => now()->subDays(8)])->save();
            $this->bearer($fresh->plainTextToken)->getJson('/api/auth/user')->assertUnauthorized();
        }
    }

    // ------------------------------------------------------------------ T0.4e

    public function test_T0_4e_logout_revokes_the_token_for_admin_company_and_student(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'a@qa.test', 'password' => Hash::make('Secret123')]);
        $company = User::factory()->create(['role' => 'company', 'company_id' => $this->company()->id, 'email' => 'c@qa.test', 'password' => Hash::make('Secret123')]);
        $student = StudentProfile::factory()->create(['roll_no' => '22JE7001']);
        $student->user->update(['password' => Hash::make('Secret123')]);

        $logins = [
            'admin' => ['email' => 'a@qa.test', 'password' => 'Secret123'],
            'company' => ['email' => 'c@qa.test', 'password' => 'Secret123'],
            'student' => ['roll_no' => '22JE7001', 'password' => 'Secret123'],
        ];

        foreach ($logins as $role => $credentials) {
            $this->app['auth']->forgetGuards();
            $this->flushHeaders();
            $token = $this->postJson('/api/auth/login', $credentials)->assertOk()->assertJsonPath('user.role', $role)->json('token');

            $this->bearer($token)->getJson('/api/auth/user')->assertOk();
            $this->bearer($token)->postJson('/api/auth/logout')->assertOk();
            $this->bearer($token)->getJson('/api/auth/user')->assertUnauthorized();
        }

        $this->assertSame(0, $admin->tokens()->count() + $company->tokens()->count() + $student->user->tokens()->count());
    }

    // ------------------------------------------------------------------ T0.4g

    public function test_T0_4g_floated_forms_are_undeletable_others_follow_m0_3e(): void
    {
        $user = $this->companyUser();
        Sanctum::actingAs($user);
        $cid = $user->company_id;

        foreach (['jnfs' => fn (string $s) => $this->jnf($cid, $s), 'infs' => fn (string $s) => $this->inf($cid, $s)] as $path => $make) {
            // Floated (open, and also after the posting was cancelled) → 422, row kept.
            $floated = $make('accepted');
            $this->floatDirectly($floated);
            $this->deleteJson("/api/company/{$path}/{$floated->id}")->assertStatus(422);
            $this->assertNotNull($floated->fresh(), "{$path}: floated form was deleted");

            $cancelled = $make('accepted');
            $this->floatDirectly($cancelled, 'cancelled');
            $this->deleteJson("/api/company/{$path}/{$cancelled->id}")->assertStatus(422);
            $this->assertNotNull($cancelled->fresh(), "{$path}: form of a cancelled posting was deleted");

            // Not floated → deletable (spec M0.3e: draft, or submitted/under_review/accepted/rejected with no posting).
            foreach (['draft', 'submitted', 'under_review', 'accepted', 'rejected'] as $status) {
                $form = $make($status);
                $this->deleteJson("/api/company/{$path}/{$form->id}")->assertOk();
                $this->assertNull($form->fresh(), "{$path}: {$status} not-floated form should be deletable");
            }
        }

        // Another company's form → 404 and untouched.
        $foreign = $this->jnf($this->company('Other')->id, 'draft');
        $this->deleteJson("/api/company/jnfs/{$foreign->id}")->assertNotFound();
        $this->assertNotNull($foreign->fresh());
    }

    // ------------------------------------------------------------------ T0.5a

    public function test_T0_5a_admin_sets_mail_mode_persisted_and_audited(): void
    {
        $adminA = $this->admin();
        $adminB = $this->admin();

        Sanctum::actingAs($adminA);
        $this->getJson('/api/admin/settings')->assertOk()->assertJsonPath('settings.mail_mode', 'queued');
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertOk()->assertJsonPath('settings.mail_mode', 'sync');

        $this->assertSame('sync', PortalSetting::where('key', 'mail_mode')->sole()->value);
        $this->assertSame('sync', app(SettingsService::class)->get('mail_mode'));
        $logA = AuditLog::where('action', 'setting.update')->sole();
        $this->assertSame($adminA->id, $logA->user_id);
        $this->assertSame('sync', $logA->after['value']);

        Sanctum::actingAs($adminB);
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'queued'])->assertOk();
        $this->assertSame('queued', PortalSetting::where('key', 'mail_mode')->sole()->value);
        $logB = AuditLog::where('action', 'setting.update')->latest('id')->first();
        $this->assertSame($adminB->id, $logB->user_id);
        $this->assertSame(['key' => 'mail_mode', 'value' => 'sync'], $logB->before);

        foreach (['bogus', '', 'SYNC', 1] as $invalid) {
            $this->patchJson('/api/admin/settings', ['mail_mode' => $invalid])->assertStatus(422);
        }
        $this->assertSame('queued', PortalSetting::where('key', 'mail_mode')->sole()->value);
        $this->assertSame(2, AuditLog::where('action', 'setting.update')->count());

        Sanctum::actingAs($this->companyUser());
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertForbidden();
        $this->getJson('/api/admin/settings')->assertForbidden();

        Sanctum::actingAs(StudentProfile::factory()->create()->user);
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertForbidden();

        $this->assertSame('queued', PortalSetting::where('key', 'mail_mode')->sole()->value);
    }

    // ------------------------------------------------------------------ T0.5b

    private function floatAcceptedJnf(User $admin, PlacementCycle $cycle)
    {
        $jnf = $this->jnf($this->company()->id, 'accepted');
        Sanctum::actingAs($admin);

        return $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf',
            'form_id' => $jnf->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => now()->addDays(5)->toIso8601String(),
        ]);
    }

    public function test_T0_5b_queued_mode_pushes_the_float_job_and_queues_mailables(): void
    {
        Mail::fake();
        Queue::fake();
        $admin = $this->admin();
        $cycle = $this->cycle();
        $students = [$this->enrolledStudent($cycle), $this->enrolledStudent($cycle), $this->enrolledStudent($cycle)];
        $this->assertSame('queued', app(SettingsService::class)->get('mail_mode'));

        $this->floatAcceptedJnf($admin, $cycle)->assertCreated();
        $posting = JobPosting::sole();

        // Nothing was sent inside the request: the E2 job is on the queue.
        Queue::assertPushed(SendPostingFloatedMails::class, fn ($job) => $job->jobPostingId === $posting->id);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        $this->assertSame(0, EmailLog::where('template', 'emails.posting-floated')->count());

        // The worker runs the job → mailables are queued (not sent), one email_logs row per student.
        app()->call([new SendPostingFloatedMails($posting->id), 'handle']);

        Mail::assertNothingSent();
        Mail::assertQueued(PostingFloatedMail::class);
        $expected = app(EligibilityService::class)->eligibleStudentsQuery($posting)->count();
        $this->assertSame(3, $expected);
        $this->assertSame($expected, EmailLog::where('template', 'emails.posting-floated')->where('status', 'queued')->count());
        foreach ($students as $student) {
            Mail::assertQueued(PostingFloatedMail::class, fn ($m) => $m->hasBcc($student->user->email));
        }
    }

    public function test_T0_5b_queued_mode_with_sync_queue_driver_queues_single_mails_too(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $student = StudentProfile::factory()->create();
        $resume = $student->resumes()->create(['slot' => 1, 'label' => 'Main', 'file_path' => 'resumes/x.pdf', 'file_size' => 10, 'status' => 'pending']);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'approved'])->assertOk();

        Mail::assertNotSent(ResumeReviewedMail::class);
        Mail::assertQueued(ResumeReviewedMail::class, fn ($m) => $m->hasTo($student->user->email));
        $this->assertDatabaseHas('email_logs', ['user_id' => $student->user->id, 'template' => 'emails.resume-reviewed', 'status' => 'queued']);

        // E1 in queued mode: the invitation job is pushed, nothing sent inline.
        Queue::fake();
        $this->postJson('/api/admin/students', [
            'roll_no' => '24QA0001', 'full_name' => 'Queued Student', 'institute_email' => '24qa0001@iitism.ac.in',
            'programme' => self::BTECH, 'branch' => 'Computer Science & Engineering', 'graduating_batch' => 2027, 'gender' => 'female',
        ])->assertCreated();
        Queue::assertPushed(SendStudentInvitation::class);
        Mail::assertNotSent(StudentInvitationMail::class);
    }

    // ------------------------------------------------------------------ T0.5c

    public function test_T0_5c_sync_mode_sends_inline_and_logs_sent(): void
    {
        Mail::fake();
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->patchJson('/api/admin/settings', ['mail_mode' => 'sync'])->assertOk();

        $student = StudentProfile::factory()->create();
        $resume = $student->resumes()->create(['slot' => 1, 'label' => 'Main', 'file_path' => 'resumes/x.pdf', 'file_size' => 10, 'status' => 'pending']);

        $this->patchJson("/api/admin/resumes/{$resume->id}", ['status' => 'rejected', 'admin_remark' => 'Add CGPA'])->assertOk();

        Mail::assertSent(ResumeReviewedMail::class, fn ($m) => $m->hasTo($student->user->email) && $m->remark === 'Add CGPA');
        Mail::assertNotQueued(ResumeReviewedMail::class);
        $this->assertDatabaseHas('email_logs', ['user_id' => $student->user->id, 'template' => 'emails.resume-reviewed', 'status' => 'sent']);
        $this->assertNotNull(EmailLog::where('template', 'emails.resume-reviewed')->sole()->sent_at);

        // E1 in sync mode is also delivered inside the request.
        $this->postJson('/api/admin/students', [
            'roll_no' => '24QA0002', 'full_name' => 'Sync Student', 'institute_email' => '24qa0002@iitism.ac.in',
            'programme' => self::BTECH, 'branch' => 'Computer Science & Engineering', 'graduating_batch' => 2027, 'gender' => 'male',
        ])->assertCreated();
        Mail::assertSent(StudentInvitationMail::class, fn ($m) => $m->rollNo === '24QA0002');
        Mail::assertNotQueued(StudentInvitationMail::class);
        $this->assertDatabaseHas('email_logs', ['template' => 'emails.student-invitation', 'status' => 'sent', 'recipient_email' => '24qa0002@iitism.ac.in']);
    }

    // ------------------------------------------------------------------ T0.6

    public function test_T0_6_resumes_are_stored_on_the_private_disk_only(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $student = StudentProfile::factory()->create(['roll_no' => '22JE4242']);
        Sanctum::actingAs($student->user);

        $this->post('/api/student/resumes', [
            'slot' => 3,
            'label' => 'Core',
            'file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('resume.file_path');

        $resume = Resume::sole();
        $this->assertMatchesRegularExpression('#^resumes/22JE4242/3_[0-9a-f\-]{36}\.pdf$#', $resume->file_path);
        Storage::disk('local')->assertExists($resume->file_path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Nothing may be written to the public disk for a resume.');
        $this->assertStringStartsWith(storage_path('app/private'), config('filesystems.disks.local.root'));

        // The admin queue does not leak the storage path either.
        Sanctum::actingAs($this->admin());
        $this->assertStringNotContainsString($resume->file_path, $this->getJson('/api/admin/resumes')->assertOk()->getContent());
    }
}
