<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Single entry point for Phase 2 emails. Honours the admin-controlled
 * `mail_mode` setting: `queued` pushes onto the database queue (production
 * must run `php artisan queue:work`), `sync` sends inline like Phase 1.
 * Broadcasts use `sendBulk()` (one BCC message per batch); personal mails use `send()`.
 */
class MailDispatchService
{
    /** Mailable metadata key carrying the email_logs reference of a queued message (QA F-015). */
    public const LOG_REF = 'cdc_log_ref';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly PortalNotificationService $notifications
    ) {
    }

    public function send(User|string $to, Mailable $mailable, string $subject, string $template): void
    {
        if ($this->mode() === 'sync') {
            if ($to instanceof User) {
                $this->notifications->sendLoggedEmail($to, $mailable, $subject, $template);

                return;
            }

            $this->sendToAddress($to, $mailable, $subject, $template);

            return;
        }

        $email = $to instanceof User ? $to->email : $to;
        $ref = (string) Str::uuid();

        // Logged before the push, so a worker that sends at once still finds the row to mark sent.
        $log = EmailLog::create([
            'user_id' => $to instanceof User ? $to->id : null,
            'recipient_email' => $email,
            'subject' => $subject,
            'template' => $template,
            'message_ref' => $ref,
            'status' => 'queued',
        ]);

        try {
            Mail::to($email)->queue($mailable->metadata(self::LOG_REF, $ref));
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
        }
    }

    /**
     * One message per batch of students, all in BCC (D89): an announcement to 1,000 students is
     * 10 SMTP sends instead of 1,000. Only for mails whose content is identical for every recipient.
     * The To header is the portal's own address so students never see each other's emails.
     *
     * @param  iterable<User>  $users
     */
    public function sendBulk(iterable $users, Mailable $mailable, string $subject, string $template): void
    {
        $recipients = collect($users)->filter(fn ($user) => $user instanceof User && filled($user->email))->unique('email')->values();

        foreach ($recipients->chunk($this->batchSize()) as $batch) {
            $ref = (string) Str::uuid();
            $message = (clone $mailable)->metadata(self::LOG_REF, $ref);
            $pending = Mail::to((string) config('mail.from.address'))->bcc($batch->pluck('email')->all());
            $sync = $this->mode() === 'sync';

            // One log row per student, so the email log still answers "was X told?". Written before the push,
            // so a worker that sends at once still finds the rows to mark sent.
            $now = now();
            EmailLog::insert($batch->map(fn (User $user) => [
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'subject' => $subject,
                'template' => $template,
                'message_ref' => $ref,
                'status' => 'queued',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());

            try {
                if ($sync) {
                    $pending->send($message);
                    EmailLog::where('message_ref', $ref)->where('status', 'queued')->update(['status' => 'sent', 'sent_at' => now()]);
                } else {
                    $pending->queue($message);
                }
            } catch (Throwable $exception) {
                EmailLog::where('message_ref', $ref)->update(['status' => 'failed', 'error_message' => $exception->getMessage()]);
            }
        }
    }

    /**
     * MessageSent listener: a message carrying a log reference marks its rows sent (QA F-015).
     */
    public function markSent(MessageSent $event): void
    {
        $ref = $event->message->getHeaders()->get('X-Metadata-'.self::LOG_REF)?->getBodyAsString();

        if ($ref) {
            EmailLog::where('message_ref', $ref)->where('status', 'queued')->update(['status' => 'sent', 'sent_at' => now()]);
        }
    }

    /**
     * JobFailed listener: a queued mail whose job failed for good marks its rows failed (QA F-015).
     */
    public function markFailed(JobFailed $event): void
    {
        $payload = $event->job->payload();
        if (($payload['data']['commandName'] ?? null) !== SendQueuedMailable::class) {
            return;
        }

        try {
            $key = self::LOG_REF;
            // Mailable::$metadata is protected; read it in the mailable's own scope.
            $ref = (fn () => $this->metadata[$key] ?? null)->call(unserialize($payload['data']['command'])->mailable);
        } catch (Throwable) {
            return;
        }

        if ($ref) {
            EmailLog::where('message_ref', $ref)->where('status', 'queued')
                ->update(['status' => 'failed', 'error_message' => Str::limit($event->exception->getMessage(), 1000)]);
        }
    }

    public function batchSize(): int
    {
        return max(1, (int) config('mail.bulk_batch_size', 100));
    }

    public function mode(): string
    {
        return $this->settings->get('mail_mode') === 'sync' ? 'sync' : 'queued';
    }

    private function sendToAddress(string $email, Mailable $mailable, string $subject, string $template): void
    {
        try {
            Mail::to($email)->send($mailable);

            EmailLog::create([
                'recipient_email' => $email,
                'subject' => $subject,
                'template' => $template,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            EmailLog::create([
                'recipient_email' => $email,
                'subject' => $subject,
                'template' => $template,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
