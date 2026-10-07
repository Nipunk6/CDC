<?php

namespace App\Services;

use App\Jobs\DeleteBroadcastAttachment;
use App\Mail\BroadcastMail;
use App\Models\EmailLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

/**
 * Sends one admin-written BroadcastMail to every student in an audience query, in BCC batches through
 * MailDispatchService::sendBulk (D89). Used by notices, stage emails and survey announcements (Superset parity S7).
 */
class BroadcastService
{
    public function __construct(private readonly MailDispatchService $mail)
    {
    }

    /**
     * @param  array{job_posting_id?: int|null, kind?: string|null}  $context  written to email_logs (Communication Log)
     * @param  bool  $deleteAttachmentAfter  the mailable's attachment is a file made for this send only: delete it once
     *                                       every batch has been sent (L23)
     * @return int students mailed
     */
    public function toAudience(Builder $audience, BroadcastMail $mailable, string $template, array $context = [], bool $deleteAttachmentAfter = false): int
    {
        $count = 0;
        $firstLogId = (int) EmailLog::query()->max('id');
        $subject = $mailable->envelope()->subject;

        $audience->with('user')->chunkById($this->mail->batchSize(), function ($students) use ($mailable, $template, $context, $subject, &$count): void {
            $users = $students->pluck('user')->filter();
            $count += $users->count();
            $this->mail->sendBulk($users, $mailable, $subject, $template, $context);
        }, 'student_profiles.id', 'id');

        if ($deleteAttachmentAfter && $mailable->attachmentPath) {
            $this->deleteAfterSend($mailable->attachmentPath, $firstLogId, (string) $subject);
        }

        return $count;
    }

    /**
     * Sent inline (mail mode "sync"): every batch is done, delete now. Queued: a job deletes the file after the
     * batches have left the queue, so no queued batch ever loses its attachment.
     */
    private function deleteAfterSend(string $path, int $firstLogId, string $subject): void
    {
        if ($this->mail->mode() === 'sync') {
            Storage::disk('local')->delete($path);

            return;
        }

        DeleteBroadcastAttachment::dispatch($path, $firstLogId, (int) EmailLog::query()->max('id'), $subject);
    }
}
