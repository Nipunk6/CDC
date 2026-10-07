<?php

namespace App\Jobs;

use App\Models\EmailLog;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the attachment file of one broadcast (notice email copy, stage email) once that send's BCC batches have
 * left the queue (L23). Queued batches read the file from the private disk when a worker sends them, so while any
 * email_logs row the send wrote (ids after `afterLogId` up to `upToLogId`, same subject) is still `queued`, the job
 * puts itself back and checks again later. If the batches never finish within three days the job gives up and the
 * file is kept (never deleted while it might still be needed).
 */
class DeleteBroadcastAttachment implements ShouldQueue
{
    use Queueable;

    public const RECHECK_SECONDS = 300;

    public function __construct(
        public string $path,
        public int $afterLogId,
        public int $upToLogId,
        public string $subject
    ) {
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addDays(3);
    }

    public function handle(): void
    {
        if ($this->stillSending()) {
            $this->release(self::RECHECK_SECONDS);

            return;
        }

        Storage::disk('local')->delete($this->path);
    }

    /**
     * True while a batch of this send is still waiting for the queue worker.
     */
    public function stillSending(): bool
    {
        return $this->upToLogId > $this->afterLogId && EmailLog::query()
            ->where('id', '>', $this->afterLogId)
            ->where('id', '<=', $this->upToLogId)
            ->where('subject', $this->subject)
            ->where('status', 'queued')
            ->exists();
    }
}
