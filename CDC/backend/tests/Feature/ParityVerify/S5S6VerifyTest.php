<?php

namespace Tests\Feature\ParityVerify;

use App\Jobs\SendStudentInvitation;
use App\Mail\PortalNoticeMail;
use App\Mail\PostingFloatedMail;
use App\Mail\StudentInvitationMail;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CycleEnrollment;
use App\Models\EmailLog;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Independent QA probes for Superset parity S5 (student invitations) and S6 (job profile management).
 * A failing probe is a finding; it is kept on purpose.
 */
class S5S6VerifyTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const BRANCH = 'Computer Science & Engineering';

    private User $admin;

    private PlacementCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->cycle = PlacementCycle::create([
            'name' => 'FT', 'type' => 'fulltime', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30', 'status' => 'open',
            'allowed_programmes' => [['programme' => self::BTECH, 'batches' => [2027]]],
        ]);
    }

    // ================================================================== helpers

    private function asAdmin(): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->admin);
    }

    private function as(User $user): void
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);
    }

    private function createStudentViaApi(string $roll): StudentProfile
    {
        $this->asAdmin();
        $this->postJson('/api/admin/students', [
            'roll_no' => $roll, 'full_name' => 'Probe Student', 'institute_email' => strtolower($roll).'@iitism.ac.in',
            'programme' => self::BTECH, 'branch' => self::BRANCH, 'graduating_batch' => 2028, 'gender' => 'male',
        ])->assertCreated();

        return StudentProfile::where('roll_no', strtoupper($roll))->sole();
    }

    private function inviteToken(string $email): ?string
    {
        $url = null;
        foreach (Mail::queued(StudentInvitationMail::class) as $mail) {
            if ($mail->hasTo($email)) {
                $url = $mail->setPasswordUrl;
            }
        }
        if ($url === null) {
            return null;
        }
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['token'] ?? null;
    }

    private function setPassword(string $email, string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/reset-password', [
            'email' => $email, 'token' => $token, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ]);
    }

    private function stateStudent(string $roll, string $state, array $attrs = []): StudentProfile
    {
        $s = StudentProfile::factory()->create(array_merge(['roll_no' => $roll, 'institute_email' => strtolower($roll).'@iitism.ac.in'], $attrs));
        $s->user->forceFill([
            'email' => strtolower($roll).'@iitism.ac.in',
            'invited_at' => now()->subDays(2), 'last_invited_at' => now()->subDays(2), 'invite_count' => 1,
            'activated_at' => $state === 'accepted' ? now()->subDay() : null,
            'invite_revoked_at' => $state === 'revoked' ? now()->subHour() : null,
        ])->save();

        return $s->fresh('user');
    }

    private function import(string $csv, array $extra = [])
    {
        $this->asAdmin();

        return $this->post('/api/admin/students/import', array_merge([
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ], $extra), ['Accept' => 'application/json']);
    }

    private function formData(array $rounds = ['technical_interview', 'hr_interview']): array
    {
        return [
            'jobTitle' => 'SDE', 'internshipTitle' => 'SDE', 'currency' => 'INR',
            'companyProfile' => ['name' => 'Probe Co'],
            'signatory' => ['name' => 'CDC Officer', 'designation' => 'TPO', 'date' => '2026-10-06'],
            'genderFilter' => 'all', 'graduatingBatch' => '2027',
            'eligibility' => [['programme' => self::BTECH, 'branches' => [['branch' => self::BRANCH, 'selected' => true, 'cgpa' => '7.0', 'backlogsAllowed' => true]]]],
            'programmeSalaries' => [['programme' => self::BTECH, 'ctcAnnual' => '2400000', 'enabled' => true]],
            'selectionRounds' => array_map(fn ($t) => ['type' => $t, 'enabled' => true], $rounds),
        ];
    }

    /** A company (with a portal user) and an accepted JNF, opened for applications in the FT cycle. */
    private function floatedPosting(string $name = 'Acme', array $extra = [], bool $companyUser = true): JobPosting
    {
        $company = Company::create(['name' => $name, 'hr_name' => 'HR', 'hr_email' => strtolower($name).'@co.test']);
        if ($companyUser) {
            User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr-'.strtolower($name).'@co.test']);
        }
        $jnf = Jnf::create(['company_id' => $company->id, 'job_title' => 'SDE', 'job_description' => 'x', 'status' => 'accepted', 'vacancies' => 2, 'form_data' => $this->formData()]);
        $this->asAdmin();
        $id = $this->postJson('/api/admin/postings', [
            'form_type' => 'jnf', 'form_id' => $jnf->id, 'placement_cycle_id' => $this->cycle->id,
            'application_deadline' => now()->addDays(3)->toIso8601String(),
        ] + $extra)->assertCreated()->json('posting.id');

        return JobPosting::findOrFail($id);
    }

    /** Enrolled, eligible students with an approved resume. @return list<StudentProfile> */
    private function enrolledStudents(int $n, string $prefix = '22JE'): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $s = StudentProfile::factory()->create(['roll_no' => sprintf('%s%04d', $prefix, $i), 'phone' => '9000000000', 'personal_email' => "p{$i}@gmail.com"]);
            CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $s->id, 'status' => 'active']);
            $s->resumes()->create(['slot' => 1, 'label' => 'CV', 'file_path' => 'resumes/x.pdf', 'file_size' => 1, 'status' => 'approved']);
            $out[] = $s;
        }

        return $out;
    }

    /** @return list<string> header row of a streamed xlsx */
    private function xlsxHeaders(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'probe').'.xlsx';
        file_put_contents($path, $content);
        $sheet = IOFactory::load($path)->getActiveSheet()->toArray(null, false, false, false);
        @unlink($path);

        return array_values(array_filter(array_map('strval', $sheet[0] ?? [])));
    }

    // ================================================================== S5

    public function test_s5_revoke_is_refused_for_an_activated_account_single_and_bulk(): void
    {
        $student = $this->createStudentViaApi('25JE0101');
        $other = $this->createStudentViaApi('25JE0102');
        $token = $this->inviteToken('25je0101@iitism.ac.in');
        $this->assertNotNull($token);
        $this->setPassword('25je0101@iitism.ac.in', $token)->assertOk();
        $this->assertSame('accepted', $student->user->fresh()->invitationStatus());

        $this->asAdmin();
        $this->postJson("/api/admin/students/{$student->id}/revoke-invitation")
            ->assertStatus(422)->assertJsonPath('message', 'This student has already activated their account. Use Suspend instead.');
        $this->postJson('/api/admin/students/invitations/revoke', ['student_ids' => [$other->id, $student->id]])->assertStatus(422);

        // Nothing changed for either student, nothing audited.
        $this->assertNull($student->user->fresh()->invite_revoked_at);
        $this->assertNull($other->user->fresh()->invite_revoked_at);
        $this->assertSame(0, AuditLog::whereIn('action', ['student.invite_revoke', 'student.invite_revoke_bulk'])->count());
        // The activated student can still sign in with their password.
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['roll_no' => '25JE0101', 'password' => 'NewPass123', 'role' => 'student'])->assertOk();
    }

    public function test_s5_revoked_link_and_earlier_forgot_link_cannot_set_a_password_until_resend(): void
    {
        $student = $this->createStudentViaApi('25JE0201');
        $email = '25je0201@iitism.ac.in';
        $inviteToken = $this->inviteToken($email);
        $forgotToken = Password::broker()->createToken($student->user);

        $this->asAdmin();
        $this->postJson("/api/admin/students/{$student->id}/revoke-invitation")->assertOk();
        $this->assertSame('revoked', $student->user->fresh()->invitationStatus());
        $log = AuditLog::where('action', 'student.invite_revoke')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertArrayHasKey('invite_revoked_at', (array) $log->before);
        $this->assertNotNull($log->after['invite_revoked_at'] ?? null);

        $this->setPassword($email, $inviteToken)->assertStatus(422);
        $this->setPassword($email, $forgotToken)->assertStatus(422);
        $this->assertNull($student->user->fresh()->activated_at);

        // Forgot password (by roll number and by email) sends nothing to a revoked student.
        Mail::fake();
        $this->postJson('/api/auth/forgot-password', ['roll_no' => '25JE0201'])->assertOk();
        $this->postJson('/api/auth/forgot-password', ['email' => $email])->assertOk();
        Mail::assertNothingSent();
        Mail::assertNothingQueued();

        // Resend un-revokes and the new link works.
        $this->asAdmin();
        $this->postJson("/api/admin/students/{$student->id}/resend-invitation")->assertOk();
        $this->assertSame('sent', $student->user->fresh()->invitationStatus());
        $this->setPassword($email, $this->inviteToken($email))->assertOk();
        $this->assertSame('accepted', $student->user->fresh()->invitationStatus());
    }

    public function test_s5_bulk_resend_never_mails_accepted_students_in_any_mode(): void
    {
        $accepted = [$this->stateStudent('24JE0001', 'accepted'), $this->stateStudent('24JE0002', 'accepted'), $this->stateStudent('24JE0003', 'accepted')];
        $sent = $this->stateStudent('24JE0004', 'sent');
        $revoked = $this->stateStudent('24JE0005', 'revoked');
        $acceptedEmails = array_map(fn ($s) => $s->user->email, $accepted);
        $this->asAdmin();

        $checkNoAccepted = function () use ($acceptedEmails): void {
            foreach (Mail::queued(StudentInvitationMail::class) as $m) {
                foreach ($acceptedEmails as $e) {
                    $this->assertFalse($m->hasTo($e), "Accepted student {$e} was mailed an invitation.");
                }
            }
        };

        $this->postJson('/api/admin/students/invitations/resend', ['student_ids' => array_merge(array_map(fn ($s) => $s->id, $accepted), [$sent->id])])
            ->assertOk()->assertJsonPath('sent', 1)->assertJsonPath('skipped', 3);
        $checkNoAccepted();

        Mail::fake();
        // Owner decision 2026-10-07 (D126): "all pending" excludes Revoked invitations, so only the Sent student is mailed.
        $this->postJson('/api/admin/students/invitations/resend', ['all_pending' => true])->assertOk()->assertJsonPath('sent', 1)->assertJsonPath('skipped_revoked', 1);
        $checkNoAccepted();

        Mail::fake();
        $this->postJson('/api/admin/students/invitations/resend', ['all_pending' => true, 'invitation_status' => 'accepted'])->assertOk();
        $checkNoAccepted();

        Mail::fake();
        $this->postJson('/api/admin/students/invitations/resend', ['all_pending' => true, 'search' => '24JE0001'])->assertStatus(422);
        Mail::assertNothingQueued();

        foreach ($accepted as $s) {
            $this->postJson("/api/admin/students/{$s->id}/resend-invitation")->assertStatus(422);
            $this->assertSame(1, (int) $s->user->fresh()->invite_count);
        }
        Mail::assertNothingQueued();

        // A queued invitation job for a student who activated in the meantime delivers nothing.
        Mail::fake();
        $sent->user->forceFill(['activated_at' => now()])->save();
        (new SendStudentInvitation($sent->id))->handle(app(\App\Services\StudentAccountService::class));
        Mail::assertNothingQueued();
        $this->assertNotNull($revoked->id);
    }

    public function test_s5_a_real_6000_row_import_creates_or_reports_every_row(): void
    {
        $lines = ['Institute Roll Number (Mandatory),Full Name (Mandatory),Email Address (Mandatory),Programme (Mandatory),Branch (Mandatory),Passout Batch (YYYY),Gender (M/F/O)'];
        $bad = [17 => 'gender', 2500 => 'email', 5999 => 'dup'];
        for ($i = 1; $i <= 6000; $i++) {
            $roll = sprintf('23JE%04d', $i);
            $email = strtolower($roll).'@iitism.ac.in';
            $gender = 'M';
            if (($bad[$i] ?? null) === 'gender') {
                $gender = 'robot';
            }
            if (($bad[$i] ?? null) === 'email') {
                $email = strtolower($roll).'@gmail.com';
            }
            if (($bad[$i] ?? null) === 'dup') {
                $roll = '23JE0001';
            }
            $lines[] = sprintf('%s,Student Name,%s,"%s","%s",2028,%s', $roll, $email, self::BTECH, self::BRANCH, $gender);
        }

        $response = $this->import(implode("\n", $lines))->assertOk();
        $created = $response->json('created');
        $errorRows = collect($response->json('errors'))->pluck('row')->unique()->sort()->values()->all();
        $this->assertSame(5997, $created);
        $this->assertSame([18, 2501, 6000], $errorRows);
        $this->assertSame(6000, $created + count($errorRows));
        $this->assertSame(5997, StudentProfile::count());
        $this->assertSame(5997, AuditLog::where('action', 'student.create')->count());
        $this->assertSame(1, AuditLog::where('action', 'student.import')->count());
        $this->assertSame(5997, User::where('role', 'student')->whereNotNull('invited_at')->where('invite_count', 1)->count());
    }

    public function test_s5_rows_beyond_the_default_10000_cap_are_reported(): void
    {
        $lines = ['roll_no,full_name,institute_email,programme,branch,graduating_batch,gender'];
        for ($i = 1; $i <= 10005; $i++) {
            $lines[] = sprintf('21JE%05d,Student Name,21je%05d@iitism.ac.in,"%s","%s",2028,m', $i, $i, self::BTECH, self::BRANCH);
        }
        $response = $this->import(implode("\n", $lines), ['dry_run' => 1])->assertOk();
        $this->assertSame(10000, $response->json('valid_rows'));
        $this->assertSame(5, $response->json('over_limit_rows'));
        $this->assertSame(10005, $response->json('valid_rows') + count($response->json('errors')));
        $this->assertSame('21JE10001', collect($response->json('errors'))->first()['roll_no']);
    }

    public function test_s5_superset_style_headers_with_bom_hints_and_country_code(): void
    {
        $csv = "\xEF\xBB\xBF".implode("\n", [
            '"Institute Roll Number (Mandatory)","First Name (Mandatory)","Middle Name","Last Name","Mobile Country Code (e.g. 91)","Mobile (10 Digits)","Gender (M/F/O)","Date Of Birth (YYYY-MM-DD)","Email Address (Mandatory)","Personal Email Address","Current Course Name","Current Course Start Date (YYYY-MM-DD)","Xth Score (Percentage)","XIIth Score (Percentage)","Programme","Branch"',
            '"25je0301","Asha","","Rao","+91","9876543210","F","2006-01-02","25je0301@iitism.ac.in","asha@gmail.com","B.Tech","2025-07-25","91.5","89","'.self::BTECH.'","'.self::BRANCH.'"',
        ]);
        $this->import($csv, ['default_batch' => 2029])->assertOk()->assertJsonPath('created', 1)->assertJsonPath('errors', []);
        $s = StudentProfile::where('roll_no', '25JE0301')->sole();
        $this->assertSame('Asha Rao', $s->full_name);
        $this->assertSame('+91 9876543210', $s->phone);
        $this->assertSame('female', $s->gender);
        $this->assertEquals(91.5, (float) $s->tenth_percent);
        $this->assertEquals(89, (float) $s->twelfth_percent);
        $this->assertSame(2029, (int) $s->graduating_batch);
    }

    /** Superset's own sample columns only (no Programme/Branch): every row must at least be reported, not dropped. */
    public function test_s5_superset_only_columns_are_reported_row_by_row(): void
    {
        $csv = implode("\n", [
            '"Institute Roll Number (Mandatory)","First Name (Mandatory)","Middle Name","Last Name","Mobile Country Code (e.g. 91)","Mobile (10 Digits)","Gender (M/F/O)","Date Of Birth (YYYY-MM-DD)","Email Address (Mandatory)","Personal Email Address","Current Course Name"',
            '"25je0401","A","","B","91","9876543210","M","2006-01-02","25je0401@iitism.ac.in","","B.Tech Computer Science & Engineering"',
            '"25je0402","C","","D","91","9876543211","F","2006-01-03","25je0402@iitism.ac.in","","B.Tech Computer Science & Engineering"',
        ]);
        $response = $this->import($csv, ['default_batch' => 2029, 'dry_run' => 1])->assertOk();
        $this->assertSame([2, 3], collect($response->json('errors'))->pluck('row')->unique()->values()->all());
        $this->assertContains('programme', collect($response->json('errors'))->pluck('field')->all());
    }

    public function test_s5_institute_domain_outside_local_testing_rejects_demo_domain_on_create_import_and_edit(): void
    {
        $student = StudentProfile::factory()->create(['roll_no' => '24JE0700', 'institute_email' => '24je0700@iitism.ac.in']);
        $this->asAdmin();
        foreach (['production', 'staging'] as $env) {
            $this->app['env'] = $env;
            try {
                $this->postJson('/api/admin/students', [
                    'roll_no' => '24JE07'.($env === 'production' ? '01' : '02'), 'full_name' => 'X Y', 'institute_email' => "x{$env}@students.cdc-demo.test",
                    'programme' => self::BTECH, 'branch' => self::BRANCH, 'graduating_batch' => 2028, 'gender' => 'male',
                ])->assertStatus(422)->assertJsonValidationErrors('institute_email');
                $this->patchJson("/api/admin/students/{$student->id}", ['institute_email' => "y{$env}@students.cdc-demo.test"])->assertStatus(422);
                $r = $this->import(implode("\n", [
                    'roll_no,full_name,institute_email,programme,branch,graduating_batch,gender',
                    '24JE0703,Imp Ort,24je0703@students.cdc-demo.test,"'.self::BTECH.'","'.self::BRANCH.'",2028,m',
                    '24JE0704,Imp Ort,24je0704@gmail.com,"'.self::BTECH.'","'.self::BRANCH.'",2028,m',
                ]), ['dry_run' => 1])->assertOk();
                $this->assertSame(0, $r->json('valid_rows'), "env {$env}");
            } finally {
                $this->app['env'] = 'testing';
            }
        }
        // Look-alike domains are refused even in testing.
        $this->postJson('/api/admin/students', [
            'roll_no' => '24JE0705', 'full_name' => 'X Y', 'institute_email' => 'x@iitism.ac.in.attacker.test',
            'programme' => self::BTECH, 'branch' => self::BRANCH, 'graduating_batch' => 2028, 'gender' => 'male',
        ])->assertStatus(422);
        $this->postJson('/api/admin/students', [
            'roll_no' => '24JE0706', 'full_name' => 'X Y', 'institute_email' => 'x@fakeiitism.ac.in',
            'programme' => self::BTECH, 'branch' => self::BRANCH, 'graduating_batch' => 2028, 'gender' => 'male',
        ])->assertStatus(422);
    }

    // ================================================================== S6

    public function test_s6_scheduled_open_fires_once_and_e2_mails_once(): void
    {
        $students = $this->enrolledStudents(3);
        $posting = $this->floatedPosting('Sched', ['scheduled_open_at' => now()->addHour()->toIso8601String()]);
        Mail::assertNotQueued(PostingFloatedMail::class);

        // Hidden from students everywhere while scheduled, including its documents and Apply.
        $this->as($students[0]->user);
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();
        $this->assertNotSame(201, $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $students[0]->resumes()->first()->id])->status());
        $this->assertSame(0, Application::count());

        $this->travel(2)->hours();
        $this->artisan('placement:open-scheduled')->assertSuccessful();
        $this->artisan('placement:open-scheduled')->assertSuccessful();
        $this->artisan('placement:open-scheduled')->assertSuccessful();

        Mail::assertQueued(PostingFloatedMail::class, 1);
        $this->assertSame(3, EmailLog::where('job_posting_id', $posting->id)->where('kind', 'opening')->count());
        $this->assertSame(1, AuditLog::where('action', 'posting.scheduled_open')->where('subject_id', $posting->id)->count());

        // A later "Open now" is refused (no longer scheduled), so no second E2.
        $this->asAdmin();
        $this->postJson("/api/admin/postings/{$posting->id}/open-now")->assertStatus(422);
        Mail::assertQueued(PostingFloatedMail::class, 1);
    }

    /** Apply on a hidden (scheduled) job profile should look like its detail (404), not reveal it with a 422. */
    public function test_s6_apply_to_a_scheduled_job_profile_is_404_like_its_detail(): void
    {
        $students = $this->enrolledStudents(1);
        $posting = $this->floatedPosting('Hidden', ['scheduled_open_at' => now()->addHour()->toIso8601String()]);
        $this->as($students[0]->user);
        $this->getJson("/api/student/postings/{$posting->id}")->assertNotFound();
        $response = $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $students[0]->resumes()->first()->id]);
        $this->assertSame(404, $response->status(), 'Apply answered '.$response->status().': '.$response->json('message'));
    }

    public function test_s6_cancelled_scheduled_job_profile_never_opens_or_mails(): void
    {
        $this->enrolledStudents(2);
        $posting = $this->floatedPosting('Canc', ['scheduled_open_at' => now()->addHour()->toIso8601String()]);
        $this->asAdmin();
        $this->patchJson("/api/admin/postings/{$posting->id}/cancel")->assertOk();
        $this->assertTrue(AuditLog::where('action', 'posting.cancel')->where('subject_id', $posting->id)->exists());

        // Display label inputs: a cancelled job profile must not report itself as scheduled (postingStatusLabel
        // checks is_scheduled before status, so it would read "Scheduled to Open").
        $isScheduled = $this->getJson("/api/admin/postings/{$posting->id}")->json('posting.is_scheduled');

        $this->travel(2)->hours();
        $this->artisan('placement:open-scheduled')->assertSuccessful();
        Mail::assertNotQueued(PostingFloatedMail::class);
        $this->assertSame('cancelled', $posting->fresh()->status);

        $this->assertFalse($isScheduled, 'Cancelled job profile still has is_scheduled=true, so the status label shows "Scheduled to Open".');
    }

    public function test_s6_attached_document_access_matrix(): void
    {
        Storage::fake('local');
        $students = $this->enrolledStudents(1);
        $posting = $this->floatedPosting('DocA');
        $other = $this->floatedPosting('DocB');
        $this->asAdmin();
        $docId = $this->post("/api/admin/postings/{$posting->id}/documents", ['title' => 'JD', 'file' => UploadedFile::fake()->create('jd.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->json('document.id');

        // Fake PDF (text renamed .pdf) and > 5 MB are refused; exactly 5 MB is accepted.
        // A real upload (content-sniffed like production; UploadedFile::fake() reports the MIME from the name only).
        $fakePdf = tempnam(sys_get_temp_dir(), 'probe');
        file_put_contents($fakePdf, '<html><script>alert(1)</script></html>');
        $this->post("/api/admin/postings/{$posting->id}/documents", ['title' => 'x', 'file' => new UploadedFile($fakePdf, 'evil.pdf', null, null, true)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/admin/postings/{$posting->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('big.pdf', 5121, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/admin/postings/{$posting->id}/documents", ['title' => 'x', 'file' => UploadedFile::fake()->create('five.pdf', 5120, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated();

        // Stored on the private disk, not the public one.
        $path = \App\Models\PostingDocument::findOrFail($docId)->file_path;
        Storage::disk('local')->assertExists($path);
        $this->assertFalse(Storage::disk('public')->exists($path));

        // Cross-posting id: 404 for admin and student.
        $this->getJson("/api/admin/postings/{$other->id}/documents/{$docId}")->assertNotFound();
        $this->deleteJson("/api/admin/postings/{$other->id}/documents/{$docId}")->assertNotFound();

        // Unauthenticated.
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/student/postings/{$posting->id}/documents/{$docId}")->assertUnauthorized();
        $this->getJson("/api/admin/postings/{$posting->id}/documents/{$docId}")->assertUnauthorized();

        // Company users (even the owning company) cannot use the student or admin route.
        $companyUser = User::where('role', 'company')->where('company_id', $posting->company()->id)->sole();
        $this->as($companyUser);
        $this->getJson("/api/student/postings/{$posting->id}/documents/{$docId}")->assertForbidden();
        $this->getJson("/api/admin/postings/{$posting->id}/documents/{$docId}")->assertForbidden();

        // Enrolled eligible student: OK; through the wrong posting: 404.
        $this->as($students[0]->user);
        $this->get("/api/student/postings/{$posting->id}/documents/{$docId}")->assertOk();
        $this->getJson("/api/student/postings/{$other->id}/documents/{$docId}")->assertNotFound();

        // Student not enrolled in the cycle, and an enrolled student of a branch the job profile leaves out: 404.
        $outsider = StudentProfile::factory()->create(['roll_no' => '22JE0901']);
        $this->as($outsider->user);
        $this->getJson("/api/student/postings/{$posting->id}/documents/{$docId}")->assertNotFound();
        $otherBranch = StudentProfile::factory()->create(['roll_no' => '22JE0902', 'branch' => 'Electrical Engineering']);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $otherBranch->id, 'status' => 'active']);
        $this->as($otherBranch->user);
        $this->getJson("/api/student/postings/{$posting->id}/documents/{$docId}")->assertNotFound();

        // Cancelled job profile: students lose access.
        $this->asAdmin();
        $this->patchJson("/api/admin/postings/{$posting->id}/cancel")->assertOk();
        $this->as($students[0]->user);
        $this->getJson("/api/student/postings/{$posting->id}/documents/{$docId}")->assertNotFound();
    }

    public function test_s6_every_job_profile_mail_row_carries_job_posting_id(): void
    {
        Storage::fake('local');
        $students = $this->enrolledStudents(3);
        $posting = $this->floatedPosting('Mailco');              // E2
        $companyUser = User::where('role', 'company')->where('company_id', $posting->company()->id)->sole();

        foreach ($students as $s) {                              // E3
            $this->as($s->user);
            $this->postJson("/api/student/postings/{$posting->id}/apply", ['resume_id' => $s->resumes()->first()->id])->assertCreated();
        }

        $this->asAdmin();
        $this->patchJson("/api/admin/postings/{$posting->id}/close")->assertOk();
        $rounds = $posting->rounds()->get();
        $r1 = $rounds[0];
        $final = $rounds->firstWhere('is_final', true);
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r1->id}/results", ['roll_nos' => ['22JE0001', '22JE0002'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r1->id}/publish", ['reject_remaining' => true])->assertOk(); // E4 (+ company notice)

        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$r1->id}/email", [
            'subject' => 'Test venue', 'message' => 'Report to NLHC at 9.', 'results' => ['selected'],
        ])->assertOk(); // S7 stage email

        // Company proposal for the final stage → E10 notice to admins about this job profile.
        $this->as($companyUser);
        $this->postJson("/api/company/postings/{$posting->id}/rounds/{$final->id}/proposals", ['kind' => 'shortlist', 'entries' => [['roll_no' => '22JE0001']]])->assertCreated();

        $this->asAdmin();
        $this->postJson("/api/admin/postings/{$posting->id}/rounds/{$final->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $app = Application::where('job_posting_id', $posting->id)->whereHas('studentProfile', fn ($q) => $q->where('roll_no', '22JE0001'))->sole();
        $this->postJson("/api/admin/postings/{$posting->id}/results/publish", ['selections' => [
            ['application_id' => $app->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'block' => false],
        ]])->assertOk(); // E5 + regrets + company notice
        $this->postJson("/api/admin/postings/{$posting->id}/send-applicant-list")->assertOk();

        $kinds = EmailLog::whereNotNull('job_posting_id')->pluck('kind')->unique()->values()->all();
        foreach (['opening', 'application_receipt', 'stage_result', 'stage_email', 'offer', 'applicant_list'] as $kind) {
            $this->assertContains($kind, $kinds, "No email_logs row of kind {$kind}");
        }
        $this->assertSame(0, EmailLog::whereNotNull('job_posting_id')->whereNull('kind')->count(), 'Rows with a job_posting_id but no kind');

        $untagged = EmailLog::whereNull('job_posting_id')->get(['recipient_email', 'subject', 'template'])
            ->map(fn ($l) => "{$l->template} | {$l->subject} → {$l->recipient_email}")->all();
        $this->assertSame([], $untagged, "Job-profile mails without job_posting_id:\n".implode("\n", $untagged));
    }

    public function test_s6_admin_created_job_profile_behaves_like_a_company_jnf(): void
    {
        $students = $this->enrolledStudents(2);
        $low = StudentProfile::factory()->create(['roll_no' => '22JE0050', 'current_cgpa' => 6.5]);
        CycleEnrollment::create(['placement_cycle_id' => $this->cycle->id, 'student_profile_id' => $low->id, 'status' => 'active']);
        $this->asAdmin();

        $companyId = $this->postJson('/api/admin/form-builder/companies', ['name' => 'Offline Co', 'hr_name' => 'HR', 'hr_email' => 'hr@offline.test'])
            ->assertCreated()->json('company.id');
        $this->assertSame(0, User::where('company_id', $companyId)->count());
        $this->assertTrue(AuditLog::where('action', 'company.admin_create')->exists());

        $jnfId = $this->postJson("/api/admin/form-builder/{$companyId}/jnfs", [
            'job_title' => 'SDE', 'job_description' => 'Build.', 'job_location' => 'Pune',
            'form_data' => json_encode($this->formData()), 'status' => 'submitted',
        ])->assertCreated()->assertJsonPath('jnf.status', 'accepted')->json('jnf.id');
        Mail::assertNothingQueued(); // no mail on admin create

        // Twin company JNF with identical form_data.
        $twinCompany = Company::create(['name' => 'Twin', 'hr_name' => 'HR', 'hr_email' => 'hr@twin.test']);
        $twin = Jnf::create(['company_id' => $twinCompany->id, 'job_title' => 'SDE', 'job_description' => 'Build.', 'status' => 'accepted', 'form_data' => $this->formData()]);

        $ids = [];
        foreach ([$jnfId, $twin->id] as $formId) {
            $ids[] = $this->postJson('/api/admin/postings', [
                'form_type' => 'jnf', 'form_id' => $formId, 'placement_cycle_id' => $this->cycle->id,
                'application_deadline' => now()->addDays(3)->toIso8601String(),
            ])->assertCreated()->json('posting.id');
        }
        [$adminPosting, $twinPosting] = [JobPosting::findOrFail($ids[0]), JobPosting::findOrFail($ids[1])];

        $a = $this->getJson("/api/admin/postings/{$adminPosting->id}")->json('posting');
        $b = $this->getJson("/api/admin/postings/{$twinPosting->id}")->json('posting');
        $this->assertSame($b['eligibility_snapshot'], $a['eligibility_snapshot']);
        $this->assertSame($b['stats']['eligible'], $a['stats']['eligible']);
        $this->assertSame(2, $a['stats']['eligible']);
        $this->assertSame($b['compensation'], $a['compensation']);
        $this->assertSame(collect($b['rounds'])->pluck('round_type')->all(), collect($a['rounds'])->pluck('round_type')->all());
        $this->assertSame(1, EmailLog::where('job_posting_id', $adminPosting->id)->where('kind', 'opening')->distinct('message_ref')->count('message_ref'));

        // Student side: listed, eligible, can apply; the low-CGPA student is not eligible.
        $this->as($students[0]->user);
        $this->getJson("/api/student/postings/{$adminPosting->id}")->assertOk()->assertJsonPath('posting.eligibility.eligible', true);
        $this->postJson("/api/student/postings/{$adminPosting->id}/apply", ['resume_id' => $students[0]->resumes()->first()->id])->assertCreated();
        $this->as($low->user);
        $this->getJson("/api/student/postings/{$adminPosting->id}")->assertOk()->assertJsonPath('posting.eligibility.eligible', false);

        // Results and exports work as for any job profile.
        $this->asAdmin();
        $this->patchJson("/api/admin/postings/{$adminPosting->id}/close")->assertOk();
        $final = $adminPosting->rounds()->where('is_final', true)->sole();
        $first = $adminPosting->rounds()->orderBy('sort_order')->first() ?? $adminPosting->rounds()->first();
        $this->postJson("/api/admin/postings/{$adminPosting->id}/rounds/{$first->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $this->postJson("/api/admin/postings/{$adminPosting->id}/rounds/{$first->id}/publish", ['reject_remaining' => true])->assertOk();
        $this->postJson("/api/admin/postings/{$adminPosting->id}/rounds/{$final->id}/results", ['roll_nos' => ['22JE0001'], 'result' => 'selected'])->assertOk();
        $app = Application::where('job_posting_id', $adminPosting->id)->sole();
        $this->postJson("/api/admin/postings/{$adminPosting->id}/results/publish", ['selections' => [
            ['application_id' => $app->id, 'offer_type' => 'fulltime', 'ctc_annual' => 2400000, 'block' => false],
        ]])->assertOk()->assertJsonPath('offers', 1);
        $this->get("/api/admin/postings/{$adminPosting->id}/export")->assertOk();
        $this->assertSame('completed', $adminPosting->fresh()->status);
    }

    public function test_s6_send_applicant_list_export_is_company_safe_and_respects_contact_sharing(): void
    {
        $students = $this->enrolledStudents(3);
        $posting = $this->floatedPosting('Safe');
        foreach ($students as $i => $s) {
            Application::create([
                'job_posting_id' => $posting->id, 'student_profile_id' => $s->id, 'resume_id' => $s->resumes()->first()->id,
                'status' => $i === 2 ? 'withdrawn' : 'applied', 'applied_at' => now(), 'used_unverified_resume' => true, 'placed_elsewhere_flag' => true,
            ]);
        }
        $this->asAdmin();

        $fetch = function () use ($posting): array {
            Mail::fake();
            $this->asAdmin();
            $this->postJson("/api/admin/postings/{$posting->id}/send-applicant-list")->assertOk();
            $url = null;
            Mail::assertQueued(PortalNoticeMail::class, function ($m) use (&$url) {
                $url = $m->actionUrl;

                return true;
            });
            $this->app['auth']->forgetGuards();
            $response = $this->get(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))->assertOk();

            return [$this->xlsxHeaders($response->streamedContent()), $response->streamedContent()];
        };

        [$headers, $content] = $fetch();
        foreach (['Unverified Resume Flag', 'Placed Elsewhere Flag', 'Application Status', 'Gender', 'Date of Birth', 'Category', 'PwD', 'Phone', 'Personal Email', 'Institute Email', 'Offer CTC (annual)'] as $forbidden) {
            $this->assertNotContains($forbidden, $headers, "Company export (share off) contains {$forbidden}");
        }
        $path = tempnam(sys_get_temp_dir(), 'probe').'.xlsx';
        file_put_contents($path, $content);
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, false, false, false);
        @unlink($path);
        $rolls = array_filter(array_map(fn ($r) => $r[0] ?? null, array_slice($rows, 1)));
        $this->assertEqualsCanonicalizing(['22JE0001', '22JE0002'], array_values($rolls), 'Withdrawn applicants must not be in the company list');

        $this->asAdmin();
        $this->patchJson("/api/admin/postings/{$posting->id}", ['share_contact_details' => true])->assertOk();
        [$headers] = $fetch();
        $this->assertContains('Phone', $headers);
        $this->assertNotContains('Unverified Resume Flag', $headers);
        $this->assertNotContains('Placed Elsewhere Flag', $headers);

        // Audited and logged each time; not a gate (company portal export still works without it).
        $this->assertSame(2, AuditLog::where('action', 'posting.send_applicant_list')->count());
        $companyUser = User::where('role', 'company')->where('company_id', $posting->company()->id)->sole();
        $this->as($companyUser);
        $this->get("/api/company/postings/{$posting->id}/export")->assertOk();

        // A company without portal users: refused with a message, nothing logged.
        $this->asAdmin();
        $loginless = $this->floatedPosting('Nologin', [], false);
        $this->postJson("/api/admin/postings/{$loginless->id}/send-applicant-list")->assertStatus(422);
    }

    public function test_s5_s6_new_admin_routes_refuse_students_companies_and_guests(): void
    {
        Storage::fake('local');
        $student = $this->enrolledStudents(1)[0];
        $posting = $this->floatedPosting('Perm');
        $companyUser = User::where('role', 'company')->where('company_id', $posting->company()->id)->sole();
        $company = $posting->company();

        $routes = [
            ['GET', '/api/admin/students/invitations'],
            ['POST', '/api/admin/students/invitations/resend'],
            ['POST', '/api/admin/students/invitations/revoke'],
            ['POST', "/api/admin/students/{$student->id}/resend-invitation"],
            ['POST', "/api/admin/students/{$student->id}/revoke-invitation"],
            ['POST', '/api/admin/students/import'],
            ['GET', '/api/admin/students/import/template'],
            ['POST', "/api/admin/postings/{$posting->id}/open-now"],
            ['GET', "/api/admin/postings/{$posting->id}/documents"],
            ['POST', "/api/admin/postings/{$posting->id}/documents"],
            ['GET', "/api/admin/postings/{$posting->id}/documents/1"],
            ['DELETE', "/api/admin/postings/{$posting->id}/documents/1"],
            ['GET', "/api/admin/postings/{$posting->id}/activity"],
            ['GET', "/api/admin/postings/{$posting->id}/communications"],
            ['POST', "/api/admin/postings/{$posting->id}/send-applicant-list"],
            ['GET', '/api/admin/form-builder/companies'],
            ['POST', '/api/admin/form-builder/companies'],
            ['GET', "/api/admin/form-builder/{$company->id}/profile"],
            ['GET', "/api/admin/form-builder/{$company->id}/policy-documents"],
            ['POST', "/api/admin/form-builder/{$company->id}/jnfs/autosave"],
            ['POST', "/api/admin/form-builder/{$company->id}/jnfs"],
            ['POST', "/api/admin/form-builder/{$company->id}/infs/autosave"],
            ['POST', "/api/admin/form-builder/{$company->id}/infs"],
        ];

        foreach ([$student->user, $companyUser] as $user) {
            $this->as($user);
            foreach ($routes as [$method, $uri]) {
                $status = $this->json($method, $uri)->status();
                $this->assertContains($status, [403, 404], "{$user->role} got {$status} on {$method} {$uri}");
            }
        }
        $this->app['auth']->forgetGuards();
        foreach ($routes as [$method, $uri]) {
            $this->assertSame(401, $this->json($method, $uri)->status(), "guest on {$method} {$uri}");
        }
        $this->assertSame(0, AuditLog::count() - AuditLog::where('action', 'posting.float')->count() - AuditLog::where('action', 'posting.notify')->count(), 'A refused call wrote an audit row');
        Mail::assertNotQueued(StudentInvitationMail::class);
    }
}
