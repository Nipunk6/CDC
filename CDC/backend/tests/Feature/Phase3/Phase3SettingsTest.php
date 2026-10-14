<?php

namespace Tests\Feature\Phase3;

use App\Models\AuditLog;
use App\Models\PortalSetting;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * P0.3 (S22): the Phase 3 settings exist with the owner's defaults, are stored by the migration, can be changed from
 * Settings with validation and an audit row, and the MIS API connection (with its encrypted, write-only secret) is
 * for super admins only.
 */
#[Group('phase3')]
class Phase3SettingsTest extends TestCase
{
    use RefreshDatabase;

    private const DEFAULTS = [
        'reminders.enabled' => true,
        'reminders.channels' => ['app', 'email'],
        'reminders.daily_from_days' => 3,
        'reminders.final_hours_interval' => 6,
        'reminders.quiet_start' => '23:00',
        'reminders.quiet_end' => '07:00',
        'mail.pool_strategy' => 'round_robin',
        'rules.one_intern_one_placement' => true,
        'rules.ppo_decline_exclusion' => true,
        'login_logs.retention_days' => 180,
        'mis.api.enabled' => false,
        'mis.api.auth_type' => 'none',
    ];

    private function admin(bool $super = false): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_super_admin' => $super]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_defaults_are_seeded_by_the_migration_and_served(): void
    {
        foreach (self::DEFAULTS as $key => $value) {
            $this->assertSame($value, app(SettingsService::class)->get($key), "default of {$key}");
            $this->assertSame($value, PortalSetting::where('key', $key)->first()?->value, "{$key} is stored by the S22 migration");
        }

        $this->admin();
        $settings = $this->getJson('/api/admin/settings')->assertOk()->json('settings');
        foreach (self::DEFAULTS as $key => $value) {
            $this->assertSame($value, $settings[$key], "GET /admin/settings carries {$key}");
        }
        $this->assertSame(1800, $settings['mail_daily_recipient_cap'], 'the total daily cap is the existing P-1 setting');
    }

    public function test_an_admin_changes_reminder_rule_and_retention_settings_with_an_audit_row(): void
    {
        $admin = $this->admin();

        $response = $this->patchJson('/api/admin/settings', [
            'reminders' => ['enabled' => false, 'channels' => ['app'], 'daily_from_days' => 5, 'final_hours_interval' => 4, 'quiet_start' => '22:30', 'quiet_end' => '06:00'],
            'mail' => ['pool_strategy' => 'group_list'],
            'rules' => ['one_intern_one_placement' => false],
            'login_logs' => ['retention_days' => 365],
        ])->assertOk();

        $settings = $response->json('settings');
        $this->assertFalse($settings['reminders.enabled']);
        $this->assertSame(['app'], $settings['reminders.channels']);
        $this->assertSame(5, $settings['reminders.daily_from_days']);
        $this->assertSame(4, $settings['reminders.final_hours_interval']);
        $this->assertSame('22:30', $settings['reminders.quiet_start']);
        $this->assertSame('group_list', $settings['mail.pool_strategy']);
        $this->assertFalse($settings['rules.one_intern_one_placement']);
        $this->assertTrue($settings['rules.ppo_decline_exclusion'], 'a key that was not sent keeps its value');
        $this->assertSame(365, app(SettingsService::class)->get('login_logs.retention_days'));

        $audit = AuditLog::where('action', 'setting.update')->get();
        $this->assertSame(9, $audit->count(), 'one audit row per changed key');
        $this->assertTrue($audit->every(fn (AuditLog $row) => $row->user_id === $admin->id));
    }

    public function test_bad_values_are_refused(): void
    {
        $this->admin();
        foreach ([
            ['reminders' => ['channels' => ['sms']]],
            ['reminders' => ['daily_from_days' => -1]],
            ['reminders' => ['final_hours_interval' => 0]],
            ['reminders' => ['quiet_start' => '25:00']],
            ['mail' => ['pool_strategy' => 'random']],
            ['login_logs' => ['retention_days' => 5]],
            ['rules' => ['ppo_decline_exclusion' => 'sometimes']],
        ] as $payload) {
            $this->patchJson('/api/admin/settings', $payload)->assertStatus(422);
        }
        $this->assertSame(0, AuditLog::count());
    }

    public function test_mis_api_connection_is_for_super_admins_and_its_secret_is_write_only_and_encrypted(): void
    {
        $this->admin();
        $this->patchJson('/api/admin/settings', ['mis' => ['api' => ['base_url' => 'https://mis.example.ac.in/api']]])
            ->assertStatus(403)->assertJsonPath('message', 'Only a super admin can change the MIS API connection.');
        $this->patchJson('/api/admin/settings', ['mis' => ['api' => ['secret' => 'S3cret-Token-Value']]])->assertStatus(403);

        $this->admin(super: true);
        $this->patchJson('/api/admin/settings', ['mis' => ['api' => ['base_url' => 'http://mis.example.ac.in/api']]])->assertStatus(422); // https only
        $response = $this->patchJson('/api/admin/settings', ['mis' => ['api' => [
            'base_url' => 'https://mis.example.ac.in/api', 'auth_type' => 'bearer', 'secret' => 'S3cret-Token-Value',
            'field_map' => ['admission_no' => 'roll_no', 'cgpa' => 'current_cgpa'],
        ]]])->assertOk();

        $this->assertSame('https://mis.example.ac.in/api', $response->json('settings')['mis.api.base_url']);
        $this->assertEquals(['admission_no' => 'roll_no', 'cgpa' => 'current_cgpa'], $response->json('settings')['mis.api.field_map']); // key order is the database's

        // Saving the same map again changes nothing (no second audit row), whatever order the database returns it in.
        $before = AuditLog::count();
        $this->patchJson('/api/admin/settings', ['mis' => ['api' => ['field_map' => ['cgpa' => 'current_cgpa', 'admission_no' => 'roll_no']]]])->assertOk();
        $this->assertSame($before, AuditLog::count());
        $this->assertTrue($response->json('mis_api_secret_set'));

        // The secret never leaves the server and is not readable in the database or the audit log.
        $this->assertStringNotContainsString('S3cret-Token-Value', $response->getContent());
        $this->assertStringNotContainsString('S3cret-Token-Value', $this->getJson('/api/admin/settings')->getContent());
        $this->assertArrayNotHasKey('mis.api.secret', $this->getJson('/api/admin/settings')->json('settings'));
        $this->assertStringNotContainsString('S3cret-Token-Value', (string) DB::table('portal_settings')->where('key', 'mis.api.secret')->value('value'));
        $this->assertStringNotContainsString('S3cret-Token-Value', (string) json_encode(AuditLog::all()->toArray()));
        $this->assertSame('S3cret-Token-Value', app(SettingsService::class)->getSecret('mis.api.secret'));

        // A normal admin can read the connection (not the secret) but still cannot change it.
        $this->admin();
        $view = $this->getJson('/api/admin/settings')->assertOk();
        $this->assertSame('bearer', $view->json('settings')['mis.api.auth_type']);
        $this->assertTrue($view->json('mis_api_secret_set'));

        // Clearing the secret.
        $this->admin(super: true);
        $this->patchJson('/api/admin/settings', ['mis' => ['api' => ['secret' => null]]])->assertOk()->assertJsonPath('mis_api_secret_set', false);
        $this->assertNull(app(SettingsService::class)->getSecret('mis.api.secret'));
    }
}
