<?php

namespace Tests\Feature;

use App\Mail\BroadcastMail;
use App\Mail\PortalNoticeMail;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\MailDispatchService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * P-1.2 QA daily mail-recipient cap. Every address on a message counts (each BCC student and the portal's own To
 * address); once today's cap is used up, mails are deferred to the next IST day (00:05) instead of being dropped.
 */
class MailDailyCapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // 10:00 IST: well inside one IST day.
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00', 'Asia/Kolkata'));
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function cap(int $value): void
    {
        app(SettingsService::class)->set('mail_daily_recipient_cap', $value, $this->admin);
    }

    /** @return Collection<int, User> */
    private function students(int $count): Collection
    {
        return User::factory()->count($count)->create(['role' => 'student']);
    }

    private function bulk(Collection $users): void
    {
        app(MailDispatchService::class)->sendBulk($users, new BroadcastMail('Subject', 'Headline'), 'Subject', 'emails.broadcast');
    }

    private function used(string $date): int
    {
        return (int) DB::table('mail_daily_usage')->where('usage_date', $date)->value('recipients');
    }

    public function test_each_bcc_address_and_the_portal_to_address_count_as_recipients(): void
    {
        $this->cap(1000);
        $this->bulk($this->students(250)); // 3 messages of 100 / 100 / 50 BCC, each with the portal in To

        $this->assertSame(253, $this->used('2026-10-09'));
        $this->assertSame(250, EmailLog::whereNull('scheduled_for')->count());
    }

    public function test_reaching_the_cap_defers_the_rest_to_the_next_ist_day_and_never_drops_mail(): void
    {
        $this->cap(50);
        $users = $this->students(120);
        $this->bulk($users);

        // Batches shrink to 49 BCC so a message (49 + the To) fits the cap: 49 today, 49 tomorrow, 22 the day after.
        $this->assertSame(50, $this->used('2026-10-09'));
        $this->assertSame(50, $this->used('2026-10-10'));
        $this->assertSame(23, $this->used('2026-10-11'));

        $this->assertSame(120, EmailLog::count(), 'every student still gets a log row');
        $this->assertSame(49, EmailLog::whereNull('scheduled_for')->count());
        $this->assertSame(49, EmailLog::where('scheduled_for', Carbon::parse('2026-10-10 00:05:00', 'Asia/Kolkata')->utc())->count());
        $this->assertSame(22, EmailLog::where('scheduled_for', Carbon::parse('2026-10-11 00:05:00', 'Asia/Kolkata')->utc())->count());

        $bcc = collect(Mail::queued(BroadcastMail::class))->flatMap(fn ($m) => collect($m->bcc)->pluck('address'));
        $this->assertSame($users->pluck('email')->sort()->values()->all(), $bcc->sort()->values()->all(), 'nobody is dropped');
    }

    public function test_sync_mode_sends_now_under_the_cap_and_queues_for_tomorrow_over_it(): void
    {
        app(SettingsService::class)->set('mail_mode', 'sync', $this->admin);
        $this->cap(3);
        $mail = app(MailDispatchService::class);
        [$a, $b, $c] = $this->students(3)->all();

        $mail->send($a, new PortalNoticeMail('S', 'Hi', 'H'), 'S', 'emails.portal-notice');
        $mail->send($b, new PortalNoticeMail('S', 'Hi', 'H'), 'S', 'emails.portal-notice');
        $mail->send($c, new PortalNoticeMail('S', 'Hi', 'H'), 'S', 'emails.portal-notice');
        Mail::assertSent(PortalNoticeMail::class, 3);
        Mail::assertNothingQueued();

        $d = $this->students(1)->first();
        $mail->send($d, new PortalNoticeMail('S', 'Hi', 'H'), 'S', 'emails.portal-notice');
        Mail::assertSent(PortalNoticeMail::class, 3);
        Mail::assertQueued(PortalNoticeMail::class, fn ($m) => $m->hasTo($d->email));
        $this->assertSame('queued', EmailLog::where('recipient_email', $d->email)->value('status'));
        $this->assertNotNull(EmailLog::where('recipient_email', $d->email)->value('scheduled_for'));
        $this->assertSame(1, $this->used('2026-10-10'));
    }

    public function test_the_cap_counts_per_ist_day(): void
    {
        $this->cap(2);
        $mail = app(MailDispatchService::class);
        [$a, $b] = $this->students(2)->all();

        // 23:50 IST on the 9th, then 00:10 IST on the 10th (still the 9th in UTC): separate IST days.
        $this->travelTo(Carbon::parse('2026-10-09 23:50:00', 'Asia/Kolkata'));
        $mail->send($a, new PortalNoticeMail('S', 'Hi', 'H'), 'S', 'emails.portal-notice');
        $this->travelTo(Carbon::parse('2026-10-10 00:10:00', 'Asia/Kolkata'));
        $mail->send($b, new PortalNoticeMail('S', 'Hi', 'H'), 'S', 'emails.portal-notice');

        $this->assertSame(1, $this->used('2026-10-09'));
        $this->assertSame(1, $this->used('2026-10-10'));
    }

    public function test_cap_zero_means_unlimited(): void
    {
        $this->cap(0);
        $this->bulk($this->students(150));

        $this->assertSame(0, EmailLog::whereNotNull('scheduled_for')->count());
    }

    public function test_settings_show_todays_usage_and_the_cap_is_editable_and_audited(): void
    {
        $this->cap(100);
        $this->bulk($this->students(30)); // 31 recipients today

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/settings')->assertOk()
            ->assertJsonPath('mail_quota.date', '2026-10-09')
            ->assertJsonPath('mail_quota.cap', 100)
            ->assertJsonPath('mail_quota.used', 31)
            ->assertJsonPath('mail_quota.remaining', 69);

        $this->patchJson('/api/admin/settings', ['mail_daily_recipient_cap' => 1500])->assertOk()
            ->assertJsonPath('mail_quota.cap', 1500);
        $this->patchJson('/api/admin/settings', ['mail_daily_recipient_cap' => -1])->assertStatus(422);
        $this->assertTrue(AuditLog::where('action', 'setting.update')->get()->contains(fn ($l) => ($l->after['key'] ?? null) === 'mail_daily_recipient_cap' && ($l->after['value'] ?? null) === 1500));

        $student = $this->students(1)->first();
        Sanctum::actingAs($student);
        $this->patchJson('/api/admin/settings', ['mail_daily_recipient_cap' => 5])->assertForbidden();
    }
}
