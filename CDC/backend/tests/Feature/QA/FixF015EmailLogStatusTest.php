<?php

namespace Tests\Feature\QA;

use App\Mail\StudentInvitationMail;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\MailDispatchService;
use Illuminate\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

/**
 * Regression for QA F-015: an email queued through MailDispatchService must not stay "queued" in email_logs forever —
 * the worker marks it sent, or failed when the job gives up.
 */
#[Group('qa')]
class FixF015EmailLogStatusTest extends TestCase
{
    use RefreshDatabase;

    private function mail(): StudentInvitationMail
    {
        return new StudentInvitationMail(name: 'Asha', rollNo: '22JE0001', setPasswordUrl: 'https://portal.test/set', loginUrl: 'https://portal.test/login');
    }

    public function test_queued_personal_and_bulk_mails_are_marked_sent_by_the_worker(): void
    {
        // Real (array) mailer + sync queue: the "worker" runs inside queue().
        $users = User::factory()->count(3)->create();
        $service = app(MailDispatchService::class);
        $this->assertSame('queued', $service->mode());

        $service->send($users[0], $this->mail(), 'Invite', 'student-invitation');
        $service->sendBulk($users, $this->mail(), 'Bulk', 'student-invitation');

        $this->assertSame(4, EmailLog::count());
        $this->assertSame(0, EmailLog::where('status', '!=', 'sent')->count(), 'every row was marked sent');
        $this->assertSame(0, EmailLog::whereNull('sent_at')->count());
        $this->assertSame(2, EmailLog::distinct()->count('message_ref'), 'one reference per message (the BCC batch shares one)');
    }

    public function test_a_queued_mail_whose_job_fails_is_marked_failed(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->create();
        app(MailDispatchService::class)->send($user, $this->mail(), 'Invite', 'student-invitation');
        $log = EmailLog::sole();
        $this->assertSame('queued', $log->status, 'still on the queue');

        // The worker gives up on that job.
        $payload = json_decode(DB::table('jobs')->value('payload'), true);
        $this->assertSame(SendQueuedMailable::class, $payload['data']['commandName']);
        event(new JobFailed('database', new SyncJob(Container::getInstance(), json_encode($payload), 'database', 'default'), new RuntimeException('SMTP down')));

        $this->assertSame(['failed', 'SMTP down'], [$log->fresh()->status, $log->fresh()->error_message]);
    }
}
