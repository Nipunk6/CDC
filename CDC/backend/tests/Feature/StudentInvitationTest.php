<?php

namespace Tests\Feature;

use App\Jobs\SendStudentInvitation;
use App\Mail\StudentInvitationMail;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\StudentAccountService;
use Database\Factories\StudentProfileFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Superset parity S5: invitation status (Sent / Accepted / Revoked), Send Invitations list, bulk Re - Send and
 * Revoke Invites, header counts, import by header name, large files and the institute email domain (B2-10).
 */
class StudentInvitationTest extends TestCase
{
    use RefreshDatabase;

    private const BTECH = StudentProfileFactory::BTECH;

    private const BRANCH = 'Computer Science & Engineering';

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'roll_no' => '24je0001', 'full_name' => 'Aarav Sharma', 'institute_email' => '24je0001@iitism.ac.in',
            'programme' => self::BTECH, 'branch' => self::BRANCH, 'graduating_batch' => 2028, 'gender' => 'male',
        ], $overrides);
    }

    /** A student in a given invitation state, made directly (not through the API). */
    private function student(string $roll, string $state = 'sent', array $attributes = []): StudentProfile
    {
        $student = StudentProfile::factory()->create(array_merge(['roll_no' => $roll, 'institute_email' => strtolower($roll).'@iitism.ac.in'], $attributes));
        $student->user->forceFill([
            'email' => strtolower($roll).'@iitism.ac.in',
            'invited_at' => now()->subDays(3),
            'last_invited_at' => now()->subDays(3),
            'invite_count' => 1,
            'activated_at' => $state === 'accepted' ? now()->subDay() : null,
            'invite_revoked_at' => $state === 'revoked' ? now()->subHour() : null,
        ])->save();

        return $student->fresh('user');
    }

    private function tokenFromLastInvitation(string $email): string
    {
        $url = null;
        Mail::assertQueued(StudentInvitationMail::class, function (StudentInvitationMail $mail) use ($email, &$url) {
            if ($mail->hasTo($email)) {
                $url = $mail->setPasswordUrl;
            }

            return true;
        });
        parse_str((string) parse_url((string) $url, PHP_URL_QUERY), $query);

        return (string) $query['token'];
    }

    private function setPassword(string $email, string $token)
    {
        return $this->postJson('/api/auth/reset-password', [
            'email' => $email, 'token' => $token, 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ]);
    }

    private function import(string $csv, array $extra = [])
    {
        return $this->post('/api/admin/students/import', array_merge([
            'file' => UploadedFile::fake()->createWithContent('students.csv', $csv),
        ], $extra), ['Accept' => 'application/json']);
    }

    // ------------------------------------------------------------------ statuses

    public function test_status_goes_sent_then_accepted_and_activated_students_cannot_be_resent_or_revoked(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $this->postJson('/api/admin/students', $this->payload())->assertCreated();
        $student = StudentProfile::sole();
        $user = $student->user;
        $this->assertNotNull($user->invited_at);
        $this->assertNotNull($user->last_invited_at);
        $this->assertSame(1, $user->invite_count);
        $this->assertSame('sent', $user->invitationStatus());

        $this->getJson("/api/admin/students/{$student->id}")->assertOk()
            ->assertJsonPath('student.invitation_status', 'sent')
            ->assertJsonPath('student.invite_count', 1);

        $token = $this->tokenFromLastInvitation('24je0001@iitism.ac.in');
        $this->setPassword('24je0001@iitism.ac.in', $token)->assertOk();

        $user->refresh();
        $this->assertNotNull($user->activated_at);
        $this->assertSame('accepted', $user->invitationStatus());

        // A later password change keeps the first activation time.
        $firstActivation = $user->activated_at->toIso8601String();
        $this->travel(1)->days();
        $reset = \Illuminate\Support\Facades\Password::broker()->createToken($user);
        $this->setPassword('24je0001@iitism.ac.in', $reset)->assertOk();
        $this->assertSame($firstActivation, $user->fresh()->activated_at->toIso8601String());

        Mail::fake();
        $this->postJson("/api/admin/students/{$student->id}/resend-invitation")
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'already activated their account'));
        $this->postJson("/api/admin/students/{$student->id}/revoke-invitation")
            ->assertStatus(422)->assertJsonPath('message', 'This student has already activated their account. Use Suspend instead.');
        $this->postJson('/api/admin/students/invitations/revoke', ['student_ids' => [$student->id]])
            ->assertStatus(422)->assertJsonPath('message', 'This student has already activated their account. Use Suspend instead.');
        Mail::assertNothingQueued();
        $this->assertNull($user->fresh()->invite_revoked_at);
    }

    public function test_revoke_kills_the_link_blocks_forgot_password_and_resend_unrevokes(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $this->postJson('/api/admin/students', $this->payload())->assertCreated();
        $student = StudentProfile::sole();
        $email = '24je0001@iitism.ac.in';
        $oldToken = $this->tokenFromLastInvitation($email);
        $this->assertSame(1, DB::table('student_invite_tokens')->where('email', $email)->count());

        $this->postJson("/api/admin/students/{$student->id}/revoke-invitation")->assertOk()
            ->assertJsonPath('invitation.invitation_status', 'revoked');

        $this->assertSame(0, DB::table('student_invite_tokens')->where('email', $email)->count());
        $this->assertSame('revoked', $student->user->fresh()->invitationStatus());
        $log = AuditLog::where('action', 'student.invite_revoke')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($student->id, $log->subject_id);

        // The old link fails, and forgot-password does not hand out another one.
        $this->setPassword($email, $oldToken)->assertStatus(422);
        $this->postJson('/api/auth/forgot-password', ['roll_no' => '24JE0001'])->assertOk();
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $email)->count());
        $this->postJson("/api/admin/students/{$student->id}/revoke-invitation")->assertStatus(422);

        // Resending un-revokes and the new link works.
        Mail::fake();
        $this->postJson("/api/admin/students/{$student->id}/resend-invitation")->assertOk();
        $user = $student->user->fresh();
        $this->assertNull($user->invite_revoked_at);
        $this->assertSame(2, $user->invite_count);
        $this->assertSame('sent', $user->invitationStatus());
        $this->setPassword($email, $this->tokenFromLastInvitation($email))->assertOk();
        $this->assertSame('accepted', $user->fresh()->invitationStatus());
    }

    public function test_a_queued_invitation_is_not_delivered_after_revoke(): void
    {
        Mail::fake();
        $student = $this->student('24JE0100', 'revoked');

        (new SendStudentInvitation($student->id))->handle(app(StudentAccountService::class));

        Mail::assertNothingQueued();
        $this->assertSame(0, DB::table('student_invite_tokens')->count());
    }

    // ------------------------------------------------------------------ bulk resend / revoke

    public function test_bulk_resend_never_mails_accepted_students(): void
    {
        Mail::fake();
        $admin = $this->actingAsAdmin();
        $accepted = $this->student('24JE0001', 'accepted');
        $sent = $this->student('24JE0002', 'sent');
        $revoked = $this->student('24JE0003', 'revoked');

        $response = $this->postJson('/api/admin/students/invitations/resend', ['student_ids' => [$accepted->id, $sent->id, $revoked->id]]);
        $response->assertOk()->assertJsonPath('sent', 2)->assertJsonPath('skipped', 1);

        Mail::assertQueued(StudentInvitationMail::class, 2);
        Mail::assertNotQueued(StudentInvitationMail::class, fn ($m) => $m->hasTo('24je0001@iitism.ac.in'));
        $this->assertNull($revoked->user->fresh()->invite_revoked_at, 'Resending un-revokes.');
        $this->assertSame(2, $sent->user->fresh()->invite_count);
        $this->assertSame(1, $accepted->user->fresh()->invite_count);

        $log = AuditLog::where('action', 'student.invite_resend_bulk')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(2, $log->after['sent_count']);
        $this->assertSame(1, $log->after['skipped_accepted_count']);
        $this->assertEqualsCanonicalizing(['24JE0002', '24JE0003'], $log->after['roll_nos']);

        // "Resend to all pending", narrowed by batch.
        Mail::fake();
        $this->student('24JE0004', 'sent', ['graduating_batch' => 2029]);
        $this->postJson('/api/admin/students/invitations/resend', ['all_pending' => true, 'batches' => [2029]])
            ->assertOk()->assertJsonPath('sent', 1);
        Mail::assertQueued(StudentInvitationMail::class, 1);

        Mail::fake();
        $this->postJson('/api/admin/students/invitations/resend', ['all_pending' => true])->assertOk()->assertJsonPath('sent', 3);
        Mail::assertQueued(StudentInvitationMail::class, 3);
        Mail::assertNotQueued(StudentInvitationMail::class, fn ($m) => $m->hasTo('24je0001@iitism.ac.in'));

        // Only accepted students selected: nothing to send.
        Mail::fake();
        $this->postJson('/api/admin/students/invitations/resend', ['student_ids' => [$accepted->id]])->assertStatus(422);
        Mail::assertNothingQueued();
    }

    public function test_bulk_revoke_is_refused_when_an_activated_student_is_selected(): void
    {
        $admin = $this->actingAsAdmin();
        $accepted = $this->student('24JE0001', 'accepted');
        $a = $this->student('24JE0002');
        $b = $this->student('24JE0003');
        DB::table('student_invite_tokens')->insert(['email' => '24je0002@iitism.ac.in', 'token' => 'x', 'created_at' => now()]);

        $this->postJson('/api/admin/students/invitations/revoke', ['student_ids' => [$accepted->id, $a->id]])
            ->assertStatus(422)->assertJsonPath('activated', ['24JE0001']);
        $this->assertNull($a->user->fresh()->invite_revoked_at);

        $this->postJson('/api/admin/students/invitations/revoke', ['student_ids' => [$a->id, $b->id]])
            ->assertOk()->assertJsonPath('revoked', 2);
        $this->assertSame('revoked', $a->user->fresh()->invitationStatus());
        $this->assertSame(0, DB::table('student_invite_tokens')->count());
        $log = AuditLog::where('action', 'student.invite_revoke_bulk')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(2, $log->after['revoked_count']);
    }

    // ------------------------------------------------------------------ list, counts, filter

    public function test_invitations_list_has_status_counts_filters_and_fields(): void
    {
        $this->actingAsAdmin();
        $this->student('24JE0001', 'accepted');
        $this->student('24JE0002', 'accepted', ['graduating_batch' => 2029]);
        $this->student('24JE0003', 'sent', ['phone' => '9876543210', 'personal_email' => 'me@gmail.com']);
        $this->student('24JE0004', 'revoked');

        $this->getJson('/api/admin/students/invitations')->assertOk()
            ->assertJsonPath('counts', ['sent' => 1, 'accepted' => 2, 'revoked' => 1, 'total' => 4])
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.per_page', 50);

        $row = $this->getJson('/api/admin/students/invitations?invitation_status=sent')->assertOk()
            ->assertJsonPath('meta.total', 1)->json('students.0');
        $this->assertSame('24JE0003', $row['roll_no']);
        $this->assertSame('sent', $row['invitation_status']);
        $this->assertSame('9876543210', $row['phone']);
        $this->assertSame('me@gmail.com', $row['personal_email']);
        foreach (['full_name', 'graduating_batch', 'institute_email', 'gender', 'date_of_birth', 'programme', 'branch', 'invited_at', 'last_invited_at', 'invite_count', 'activated_at', 'invite_revoked_at'] as $key) {
            $this->assertArrayHasKey($key, $row);
        }

        // Batches narrow the chips too; status narrows only the rows.
        $this->getJson('/api/admin/students/invitations?batches[]=2029')->assertOk()
            ->assertJsonPath('counts.accepted', 1)->assertJsonPath('counts.sent', 0)->assertJsonPath('meta.total', 1);
        $this->getJson('/api/admin/students/invitations?invitation_status=revoked')->assertOk()
            ->assertJsonPath('meta.total', 1)->assertJsonPath('students.0.roll_no', '24JE0004')
            ->assertJsonPath('counts.total', 4);
        $this->getJson('/api/admin/students/invitations?search=24je0002')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_students_page_header_counts_and_invitation_status_filter(): void
    {
        $this->actingAsAdmin();
        $this->student('24JE0001', 'accepted');
        $this->student('24JE0002', 'accepted');
        $this->student('24JE0003', 'sent');
        $this->student('24JE0004', 'revoked');

        $this->getJson('/api/admin/students')->assertOk()
            ->assertJsonPath('invitation_summary', ['registered' => 2, 'invited' => 4]);

        $rolls = fn (string $status) => collect($this->getJson("/api/admin/students?invitation_status={$status}")->assertOk()->json('students'))->pluck('roll_no')->all();
        $this->assertSame(['24JE0001', '24JE0002'], $rolls('accepted'));
        $this->assertSame(['24JE0003'], $rolls('sent'));
        $this->assertSame(['24JE0004'], $rolls('revoked'));
        $this->assertSame(['24JE0003', '24JE0004'], $rolls('invited'));
        $this->getJson('/api/admin/students?invitation_status=bogus')->assertStatus(422);
    }

    // ------------------------------------------------------------------ import

    public function test_template_has_human_readable_headers_and_round_trips(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $this->assertSame(array_merge(StudentAccountService::IMPORT_COLUMNS, StudentAccountService::IMPORT_EXTRA_COLUMNS), array_keys(StudentAccountService::IMPORT_HEADERS));

        $response = $this->get('/api/admin/students/import/template')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $response->streamedContent());
        $header = array_values(array_filter(IOFactory::load($path)->getActiveSheet()->toArray()[0]));
        @unlink($path);
        $this->assertSame(array_values(StudentAccountService::IMPORT_HEADERS), $header);
        $this->assertContains('Institute Roll Number (Mandatory)', $header);
        $this->assertContains('Gender (M/F/O)', $header);
        $this->assertContains('Date Of Birth (YYYY-MM-DD)', $header);

        // The new headers in a shuffled order still import by name.
        $h = StudentAccountService::IMPORT_HEADERS;
        $csv = implode("\n", [
            '"'.implode('","', [$h['gender'], $h['branch'], $h['roll_no'], $h['programme'], $h['institute_email'], $h['full_name'], $h['graduating_batch'], $h['date_of_birth'], $h['current_semester']]).'"',
            '"F","'.self::BRANCH.'","24JE0500","'.self::BTECH.'","24je0500@iitism.ac.in","Riya Sen","2028","2005-01-02","5"',
        ]);
        $this->import($csv)->assertOk()->assertJsonPath('created', 1)->assertJsonPath('errors', []);
        $s = StudentProfile::where('roll_no', '24JE0500')->sole();
        $this->assertSame('female', $s->gender);
        $this->assertSame('Riya Sen', $s->full_name);
        $this->assertSame(5, (int) $s->current_semester);
    }

    public function test_import_accepts_superset_sample_headers_and_batch_preselection(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $csv = implode("\n", [
            '"Institute Roll Number (Mandatory)","First Name (Mandatory)","Middle Name","Last Name","Mobile Country Code (e.g. 91)","Mobile (10 Digits)","Gender (M/F/O)","Date Of Birth (YYYY-MM-DD)","Email Address (Mandatory)","Personal Email Address","Current Course Name","Xth Score","XIIth Score","Programme","Branch"',
            '"25je0246","Shreyash","Kumar","Datta","91","9876543210","M","2006-03-04","25JE0246@iitism.ac.in","shreyash@gmail.com","B.Tech CSE","92.4","88","'.self::BTECH.'","'.self::BRANCH.'"',
            '"25je0247","Vitthal","","Shukla","","9876500000","M","","25je0247@iitism.ac.in","","","","","'.self::BTECH.'","'.self::BRANCH.'"',
        ]);

        $this->import($csv, ['default_batch' => 2029])->assertOk()->assertJsonPath('created', 2)->assertJsonPath('errors', []);

        $a = StudentProfile::where('roll_no', '25JE0246')->sole();
        $this->assertSame('Shreyash Kumar Datta', $a->full_name);
        $this->assertSame('+91 9876543210', $a->phone);
        $this->assertSame('25je0246@iitism.ac.in', $a->institute_email);
        $this->assertSame('shreyash@gmail.com', $a->personal_email);
        $this->assertEquals(92.4, (float) $a->tenth_percent);
        $this->assertEquals(88, (float) $a->twelfth_percent);
        $this->assertSame(2029, (int) $a->graduating_batch);
        $this->assertSame('2006-03-04', $a->date_of_birth->format('Y-m-d'));
        $b = StudentProfile::where('roll_no', '25JE0247')->sole();
        $this->assertSame('Vitthal Shukla', $b->full_name);
        $this->assertSame('9876500000', $b->phone);
        $this->assertSame(2029, AuditLog::where('action', 'student.import')->sole()->after['default_batch']);
    }

    public function test_import_still_reads_old_snake_case_headers_in_any_order_and_headerless_files(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $this->import(implode("\n", [
            'full_name,roll_no,branch,programme,gender,graduating_batch,institute_email',
            'Old Header,24JE0601,"'.self::BRANCH.'","'.self::BTECH.'",male,2028,24je0601@iitism.ac.in',
        ]))->assertOk()->assertJsonPath('created', 1);
        $this->assertSame('Old Header', StudentProfile::where('roll_no', '24JE0601')->value('full_name'));

        // No header row: the template order, by position (row 1 is a student).
        $this->import('24JE0602,No Header,24je0602@iitism.ac.in,"'.self::BTECH.'","'.self::BRANCH.'",2028,female')
            ->assertOk()->assertJsonPath('created', 1);
        $this->assertSame('No Header', StudentProfile::where('roll_no', '24JE0602')->value('full_name'));

        // A blank batch with no pre-selection is still an error.
        $this->import(implode("\n", ['roll_no,full_name,institute_email,programme,branch,gender', '24JE0603,No Batch,24je0603@iitism.ac.in,"'.self::BTECH.'","'.self::BRANCH.'",male']), ['dry_run' => 1])
            ->assertOk()->assertJsonPath('valid_rows', 0)->assertJsonPath('errors.0.field', 'graduating_batch');
    }

    public function test_a_6000_row_import_accounts_for_every_row(): void
    {
        $this->actingAsAdmin();
        StudentProfile::factory()->create(['roll_no' => '24JE0007', 'institute_email' => '24je0007@iitism.ac.in']);

        $lines = ['roll_no,full_name,institute_email,programme,branch,graduating_batch,gender'];
        for ($i = 1; $i <= 6000; $i++) {
            $lines[] = sprintf('24JE%04d,Student Name,24je%04d@iitism.ac.in,"%s","%s",2028,%s', $i, $i, self::BTECH, self::BRANCH, $i === 9 ? 'robot' : 'm');
        }

        $response = $this->import(implode("\n", $lines), ['dry_run' => 1])->assertOk();
        $errorRows = collect($response->json('errors'))->pluck('row')->unique()->values()->all();
        $this->assertSame([8, 10], $errorRows, 'Row 8 = roll 24JE0007 already exists, row 10 = bad gender.');
        $this->assertSame(5998, $response->json('valid_rows'));
        $this->assertSame(6000, $response->json('valid_rows') + count($errorRows));
        $this->assertSame(0, $response->json('over_limit_rows'));
    }

    public function test_rows_beyond_the_cap_are_reported_not_dropped(): void
    {
        $this->actingAsAdmin();
        config(['students.import_max_rows' => 50]);

        $lines = ['roll_no,full_name,institute_email,programme,branch,graduating_batch,gender'];
        for ($i = 1; $i <= 60; $i++) {
            $lines[] = sprintf('24JE%04d,Student Name,24je%04d@iitism.ac.in,"%s","%s",2028,m', $i, $i, self::BTECH, self::BRANCH);
        }

        $response = $this->import(implode("\n", $lines), ['dry_run' => 1])->assertOk()
            ->assertJsonPath('valid_rows', 50)->assertJsonPath('over_limit_rows', 10);
        $errors = collect($response->json('errors'));
        $this->assertSame(range(52, 61), $errors->pluck('row')->all());
        $this->assertSame('24JE0051', $errors->first()['roll_no']);
        $this->assertStringContainsString('at most 50', $errors->first()['reason']);
    }

    // ------------------------------------------------------------------ institute email domain (B2-10)

    public function test_institute_email_domain_is_checked_on_create_import_and_edit(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $message = "Use the student's institute email (@iitism.ac.in).";

        $this->postJson('/api/admin/students', $this->payload(['institute_email' => 'aarav@gmail.com']))
            ->assertStatus(422)->assertJsonPath('errors.institute_email.0', $message);
        $this->postJson('/api/admin/students', $this->payload(['institute_email' => '24JE0001@IITISM.AC.IN']))->assertCreated();

        $response = $this->import(implode("\n", [
            'roll_no,full_name,institute_email,programme,branch,graduating_batch,gender',
            '24JE0002,Bad Domain,24je0002@iitism.ac.in.evil.test,"'.self::BTECH.'","'.self::BRANCH.'",2028,m',
        ]), ['dry_run' => 1])->assertOk();
        $this->assertSame('institute_email', $response->json('errors.0.field'));
        $this->assertSame($message, $response->json('errors.0.reason'));

        $student = StudentProfile::sole();
        $this->patchJson("/api/admin/students/{$student->id}", ['institute_email' => 'aarav@yahoo.com'])
            ->assertStatus(422)->assertJsonPath('errors.institute_email.0', $message);

        // A legacy row off the domain stays editable while its email is not changed.
        $legacy = StudentProfile::factory()->create(['roll_no' => '24JE0003', 'institute_email' => 'legacy@old.example']);
        $this->patchJson("/api/admin/students/{$legacy->id}", ['institute_email' => 'legacy@old.example', 'full_name' => 'Legacy Name'])
            ->assertOk();
        $this->assertSame('Legacy Name', $legacy->fresh()->full_name);
    }

    public function test_domain_list_comes_from_config_and_demo_domain_only_in_local_or_testing(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        // Testing environment: the demo seeder's reserved domain is accepted.
        $this->postJson('/api/admin/students', $this->payload(['roll_no' => '24JE0010', 'institute_email' => '24je0010@students.cdc-demo.test']))
            ->assertCreated();

        config(['students.institute_email_domains' => ['iitism.ac.in', 'ism.ac.in']]);
        $this->postJson('/api/admin/students', $this->payload(['roll_no' => '24JE0011', 'institute_email' => '24je0011@ism.ac.in']))
            ->assertCreated();

        $this->app['env'] = 'production';
        try {
            $this->postJson('/api/admin/students', $this->payload(['roll_no' => '24JE0012', 'institute_email' => '24je0012@students.cdc-demo.test']))
                ->assertStatus(422)->assertJsonPath('errors.institute_email.0', "Use the student's institute email (@iitism.ac.in or @ism.ac.in).");
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    // ------------------------------------------------------------------ permissions

    public function test_students_and_companies_cannot_use_invitation_routes(): void
    {
        $target = $this->student('24JE0001');
        $company = \App\Models\Company::create(['name' => 'Acme', 'industry' => 'IT', 'sector' => 'IT', 'website' => 'https://acme.example', 'hr_name' => 'H', 'hr_email' => 'hr@acme.example', 'hr_phone' => '9999999999']);
        $users = [
            User::factory()->create(['role' => 'student']),
            User::factory()->create(['role' => 'company', 'company_id' => $company->id]),
        ];

        foreach ($users as $user) {
            Sanctum::actingAs($user);
            $this->getJson('/api/admin/students/invitations')->assertForbidden();
            $this->postJson('/api/admin/students/invitations/resend', ['student_ids' => [$target->id]])->assertForbidden();
            $this->postJson('/api/admin/students/invitations/revoke', ['student_ids' => [$target->id]])->assertForbidden();
            $this->postJson("/api/admin/students/{$target->id}/revoke-invitation")->assertForbidden();
            $this->postJson("/api/admin/students/{$target->id}/resend-invitation")->assertForbidden();
        }
        $this->assertNull($target->user->fresh()->invite_revoked_at);
    }

    /** D126 (owner, 2026-10-07): "Resend to all pending" never re-invites a revoked student; an explicit selection still does. */
    public function test_resend_all_pending_excludes_revoked_but_explicit_selection_unrevokes(): void
    {
        Mail::fake();
        $this->actingAsAdmin();
        $this->student('24JE0101', 'accepted');
        $sent = $this->student('24JE0102', 'sent');
        $revoked = $this->student('24JE0103', 'revoked');

        $this->postJson('/api/admin/students/invitations/resend', ['all_pending' => true])
            ->assertOk()->assertJsonPath('sent', 1)->assertJsonPath('skipped', 1)->assertJsonPath('skipped_revoked', 1);
        Mail::assertQueued(StudentInvitationMail::class, 1);
        Mail::assertQueued(StudentInvitationMail::class, fn ($m) => $m->hasTo('24je0102@iitism.ac.in'));
        Mail::assertNotQueued(StudentInvitationMail::class, fn ($m) => $m->hasTo('24je0103@iitism.ac.in'));
        $this->assertNotNull($revoked->user->fresh()->invite_revoked_at, 'all_pending must not un-revoke');

        $log = AuditLog::where('action', 'student.invite_resend_bulk')->latest('id')->first();
        $this->assertSame('all_pending', $log->after['mode']);
        $this->assertSame(1, $log->after['skipped_revoked_count']);
        $this->assertSame(['24JE0102'], $log->after['roll_nos']);

        // Only revoked students left in the filter: nothing to send, and the message says why.
        Mail::fake();
        $response = $this->postJson('/api/admin/students/invitations/resend', ['all_pending' => true, 'search' => '24JE0103'])->assertStatus(422);
        $this->assertStringContainsString('revoked', $response->json('message'));
        Mail::assertNothingQueued();

        // Explicit selection re-invites and un-revokes.
        Mail::fake();
        $this->postJson('/api/admin/students/invitations/resend', ['student_ids' => [$revoked->id]])
            ->assertOk()->assertJsonPath('sent', 1)->assertJsonPath('skipped_revoked', 0);
        Mail::assertQueued(StudentInvitationMail::class, fn ($m) => $m->hasTo('24je0103@iitism.ac.in'));
        $this->assertNull($revoked->user->fresh()->invite_revoked_at);
        $this->assertSame(2, $sent->user->fresh()->invite_count, 'the Sent student was re-invited once by all_pending');
    }
}
