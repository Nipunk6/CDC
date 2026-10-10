<?php

namespace Tests\Feature\Phase3;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\LoginLog;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * P0.4 / P0.5 (N4, OD-19): every login attempt of every role is recorded with the real client IP; a forged
 * forwarded address is ignored and flagged; no password is ever stored and unknown identifiers are hashed; old rows
 * are purged; super admins see everything, other admins their own rows and students' rows; a student sees their last
 * login.
 */
#[Group('phase3')]
class LoginLogTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Right-Pass-123';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function user(string $role, string $email, array $extra = []): User
    {
        return User::factory()->create(['role' => $role, 'email' => $email, 'password' => Hash::make(self::PASSWORD)] + $extra);
    }

    private function student(string $roll): StudentProfile
    {
        $student = StudentProfile::factory()->create(['roll_no' => $roll]);
        $student->user->update(['password' => Hash::make(self::PASSWORD)]);

        return $student;
    }

    private function login(array $credentials, string $ip = '203.0.113.10', array $headers = [])
    {
        $this->app['auth']->forgetGuards();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeaders(['User-Agent' => 'QA-Browser/1.0'] + $headers)
            ->postJson('/api/auth/login', $credentials);
    }

    /** The three headers the Next.js server sends (SEC-008), signed with $secret. */
    private function signed(string $ip, string $secret): array
    {
        $ts = (string) now()->timestamp;

        return ['X-CDC-Client-IP' => $ip, 'X-CDC-Client-IP-Ts' => $ts, 'X-CDC-Client-IP-Sig' => hash_hmac('sha256', $ip.'|'.$ts, $secret)];
    }

    public function test_every_role_gets_one_row_per_successful_login_with_the_request_ip(): void
    {
        $student = $this->student('24JE0101');
        $company = Company::create(['name' => 'Acme', 'hr_name' => 'HR', 'hr_email' => 'hr@acme.qa.test']);
        $this->user('company', 'hr@acme.qa.test', ['company_id' => $company->id]);
        $this->user('admin', 'admin@cdc.qa.test');
        $this->user('mentor', 'mentor@cdc.qa.test');

        $this->login(['roll_no' => '24je0101', 'password' => self::PASSWORD], '203.0.113.11')->assertOk();
        $this->login(['email' => 'hr@acme.qa.test', 'password' => self::PASSWORD], '203.0.113.12')->assertOk();
        $this->login(['email' => 'admin@cdc.qa.test', 'password' => self::PASSWORD], '203.0.113.13')->assertOk();
        $this->login(['email' => 'mentor@cdc.qa.test', 'password' => self::PASSWORD], '203.0.113.14')->assertOk();

        $rows = LoginLog::orderBy('id')->get();
        $this->assertCount(4, $rows);
        $this->assertSame(['student', 'company', 'admin', 'mentor'], $rows->pluck('role')->all());
        $this->assertSame(['203.0.113.11', '203.0.113.12', '203.0.113.13', '203.0.113.14'], $rows->pluck('ip')->all());
        $this->assertSame(['24JE0101', 'hr@acme.qa.test', 'admin@cdc.qa.test', 'mentor@cdc.qa.test'], $rows->pluck('identifier')->all());
        $this->assertSame($student->user_id, $rows[0]->user_id);
        $this->assertTrue($rows->every(fn (LoginLog $row) => $row->success === true && $row->failure_reason === null && $row->ip_source === 'direct'));
        $this->assertSame('QA-Browser/1.0', $rows[0]->user_agent);
    }

    public function test_failures_are_recorded_without_the_password_and_unknown_identifiers_are_hashed(): void
    {
        $admin = $this->user('admin', 'admin@cdc.qa.test');
        $this->user('company', 'frozen@co.qa.test', ['is_active' => false]);

        $this->login(['email' => 'admin@cdc.qa.test', 'password' => 'Wrong-Guess-777'])->assertStatus(422);
        $this->login(['email' => 'nobody@nowhere.qa.test', 'password' => 'Wrong-Guess-777'])->assertStatus(422);
        $this->login(['roll_no' => '99ZZ9999', 'password' => 'Wrong-Guess-777'])->assertStatus(422);
        $this->login(['email' => 'frozen@co.qa.test', 'password' => self::PASSWORD])->assertStatus(403);

        $rows = LoginLog::orderBy('id')->get();
        $this->assertCount(4, $rows);
        $this->assertTrue($rows->every(fn (LoginLog $row) => $row->success === false));
        $this->assertSame(['wrong_password', 'unknown_account', 'unknown_account', 'suspended'], $rows->pluck('failure_reason')->all());
        $this->assertSame($admin->id, $rows[0]->user_id);
        $this->assertNull($rows[1]->user_id);
        $this->assertNull($rows[1]->role);
        $this->assertSame('sha256:'.hash('sha256', 'nobody@nowhere.qa.test'), $rows[1]->identifier);
        $this->assertSame('sha256:'.hash('sha256', '99zz9999'), $rows[2]->identifier);

        $dump = json_encode(LoginLog::all()->toArray());
        $this->assertStringNotContainsString('Wrong-Guess-777', $dump);
        $this->assertStringNotContainsString(self::PASSWORD, $dump);
        $this->assertStringNotContainsString('nobody@nowhere', $dump);
    }

    public function test_a_throttled_attempt_is_recorded_too(): void
    {
        $this->freezeTime(); // the backoff after the fifth failure is one second
        $this->user('admin', 'admin@cdc.qa.test');
        for ($i = 0; $i < 6; $i++) {
            $this->login(['email' => 'admin@cdc.qa.test', 'password' => 'Wrong-Guess-777']);
        }
        $this->login(['email' => 'admin@cdc.qa.test', 'password' => self::PASSWORD])->assertStatus(429);

        $this->assertSame('throttled', LoginLog::latest('id')->first()->failure_reason);
        $this->assertSame(LoginLog::count(), 7, 'one row per attempt');
    }

    public function test_the_signed_forwarded_ip_is_used_and_a_forged_one_is_ignored_and_flagged(): void
    {
        config(['services.internal_proxy.secret' => 'test-proxy-secret']);
        $this->user('admin', 'admin@cdc.qa.test');

        // Signed by the Next.js server: the forwarded address and browser are recorded.
        $this->login(['email' => 'admin@cdc.qa.test', 'password' => self::PASSWORD], '10.0.0.5', $this->signed('198.51.100.23', 'test-proxy-secret') + ['X-CDC-Client-User-Agent' => 'Real-Browser/9'])->assertOk();
        // Forged: the right headers, the wrong secret.
        $this->login(['email' => 'admin@cdc.qa.test', 'password' => self::PASSWORD], '10.0.0.6', $this->signed('198.51.100.99', 'guessed-secret') + ['X-CDC-Client-User-Agent' => 'Forged/1'])->assertOk();
        // Forged: only the address header.
        $this->login(['email' => 'admin@cdc.qa.test', 'password' => self::PASSWORD], '10.0.0.7', ['X-CDC-Client-IP' => '198.51.100.77', 'X-Forwarded-For' => '198.51.100.77'])->assertOk();

        [$signed, $forged, $bare] = LoginLog::orderBy('id')->get()->all();
        $this->assertSame(['198.51.100.23', 'signed', 'Real-Browser/9'], [$signed->ip, $signed->ip_source, $signed->user_agent]);
        $this->assertSame(['10.0.0.6', 'invalid_signature', 'QA-Browser/1.0'], [$forged->ip, $forged->ip_source, $forged->user_agent]);
        $this->assertSame('10.0.0.7', $bare->ip);
        $this->assertSame('invalid_signature', $bare->ip_source, 'a forwarded address without a signature is flagged, never trusted');
    }

    public function test_purge_removes_rows_older_than_the_retention_setting_and_is_scheduled(): void
    {
        $admin = $this->user('admin', 'admin@cdc.qa.test');
        app(SettingsService::class)->set('login_logs.retention_days', 90, $admin);
        $row = fn (int $daysAgo) => LoginLog::create(['identifier' => 'admin@cdc.qa.test', 'user_id' => $admin->id, 'role' => 'admin', 'ip' => '203.0.113.1', 'ip_source' => 'direct', 'success' => true, 'created_at' => now()->subDays($daysAgo)]);
        $old = $row(91);
        $edge = $row(89);
        $fresh = $row(0);

        $this->artisan('login-logs:purge')->expectsOutputToContain('1 login record(s) older than 90 days deleted.')->assertSuccessful();

        $this->assertNull(LoginLog::find($old->id));
        $this->assertNotNull(LoginLog::find($edge->id));
        $this->assertNotNull(LoginLog::find($fresh->id));

        $scheduled = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command);
        $this->assertTrue($scheduled->contains(fn (?string $command) => str_contains((string) $command, 'login-logs:purge')), 'login-logs:purge is on the scheduler');
    }

    public function test_super_admins_see_everything_other_admins_their_own_and_students_rows(): void
    {
        $super = $this->user('admin', 'super@cdc.qa.test', ['is_super_admin' => true, 'name' => 'Super Admin']);
        $adminA = $this->user('admin', 'a@cdc.qa.test', ['name' => 'Admin A']);
        $adminB = $this->user('admin', 'b@cdc.qa.test', ['name' => 'Admin B']);
        $company = $this->user('company', 'hr@acme.qa.test');
        $student = $this->student('24JE0101');

        $this->login(['email' => 'a@cdc.qa.test', 'password' => self::PASSWORD], '203.0.113.21');
        $this->login(['email' => 'b@cdc.qa.test', 'password' => self::PASSWORD], '203.0.113.22');
        $this->login(['email' => 'hr@acme.qa.test', 'password' => self::PASSWORD], '203.0.113.23');
        $this->login(['roll_no' => '24JE0101', 'password' => self::PASSWORD], '198.51.100.40');
        $this->login(['roll_no' => '24JE0101', 'password' => 'Wrong-Guess-777'], '198.51.100.41');
        $this->login(['email' => 'ghost@nowhere.qa.test', 'password' => 'Wrong-Guess-777'], '198.51.100.42');

        Sanctum::actingAs($super);
        $all = $this->getJson('/api/admin/login-logs')->assertOk();
        $this->assertSame(6, $all->json('meta.total'));
        $this->assertSame(['current_page', 'last_page', 'per_page', 'total'], array_keys($all->json('meta')));
        $first = $all->json('login_logs.0');
        $this->assertSame(['id', 'created_at', 'success', 'failure_reason', 'ip', 'ip_source', 'user_agent', 'role', 'identifier', 'user'], array_keys($first));
        $this->assertNull($first['identifier'], 'a hashed identifier is not shown');
        $this->assertSame('unknown_account', $first['failure_reason']);

        // Filters.
        $this->assertSame(2, $this->getJson('/api/admin/login-logs?role=student')->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/login-logs?outcome=failure')->json('meta.total'));
        $this->assertSame(3, $this->getJson('/api/admin/login-logs?ip=198.51.100')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/login-logs?user_id='.$company->id)->json('meta.total'));
        $this->assertSame(2, $this->getJson('/api/admin/login-logs?q=24JE0101')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/login-logs?q=Admin B')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/admin/login-logs?role=unknown')->json('meta.total'));
        $this->assertSame(6, $this->getJson('/api/admin/login-logs?from='.now('Asia/Kolkata')->toDateString().'&to='.now('Asia/Kolkata')->toDateString())->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/admin/login-logs?to='.now('Asia/Kolkata')->subDay()->toDateString())->json('meta.total'));

        // A normal admin: own rows and students' rows only, whatever filter is sent.
        Sanctum::actingAs($adminA);
        $mine = $this->getJson('/api/admin/login-logs')->assertOk();
        $this->assertSame(3, $mine->json('meta.total'));
        $this->assertEqualsCanonicalizing([$adminA->id, $student->user_id, $student->user_id], array_column(array_column($mine->json('login_logs'), 'user'), 'id'));
        $this->assertSame(0, $this->getJson('/api/admin/login-logs?user_id='.$adminB->id)->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/admin/login-logs?role=company')->json('meta.total'));
        $this->assertSame(0, $this->getJson('/api/admin/login-logs?role=unknown')->json('meta.total'));

        // Nobody else.
        foreach ([$company, $student->user, $this->user('mentor', 'mentor@cdc.qa.test')] as $other) {
            Sanctum::actingAs($other);
            $this->getJson('/api/admin/login-logs')->assertForbidden();
        }
    }

    public function test_export_is_for_super_admins_and_is_audited(): void
    {
        $super = $this->user('admin', 'super@cdc.qa.test', ['is_super_admin' => true]);
        $admin = $this->user('admin', 'a@cdc.qa.test');
        $this->login(['email' => 'a@cdc.qa.test', 'password' => self::PASSWORD]);
        $this->login(['email' => '=cmd@evil.qa.test', 'password' => 'Wrong-Guess-777']);

        Sanctum::actingAs($admin);
        $this->get('/api/admin/login-logs/export')->assertForbidden();
        $this->assertSame(0, AuditLog::where('action', 'login_logs.view')->count());

        Sanctum::actingAs($super);
        $response = $this->get('/api/admin/login-logs/export?outcome=success')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('Content-Type'));

        $audit = AuditLog::where('action', 'login_logs.view')->sole();
        $this->assertSame($super->id, $audit->user_id);
        $this->assertSame(1, $audit->after['rows']);
        $this->assertSame('success', $audit->after['filters']['outcome']);
    }

    public function test_a_student_sees_their_last_login_on_the_profile(): void
    {
        $student = $this->student('24JE0101');
        $this->travelTo(now()->subDay());
        $this->login(['roll_no' => '24JE0101', 'password' => self::PASSWORD], '198.51.100.40')->assertOk();
        $this->travelBack();
        $this->login(['roll_no' => '24JE0101', 'password' => 'Wrong-Guess-777'], '198.51.100.66');
        $token = $this->login(['roll_no' => '24JE0101', 'password' => self::PASSWORD], '198.51.100.41')->assertOk()->json('token');

        $this->app['auth']->forgetGuards();
        $profile = $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/student/profile')->assertOk();

        $this->assertSame('198.51.100.41', $profile->json('student.last_login.ip'));
        $this->assertSame('198.51.100.40', $profile->json('student.previous_login.ip'));
        $this->assertNotNull($profile->json('student.last_login.at'));
        $this->assertStringNotContainsString('198.51.100.66', $profile->getContent(), 'failed attempts are not shown as logins');
    }
}
