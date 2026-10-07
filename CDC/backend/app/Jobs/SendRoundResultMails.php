<?php

namespace App\Jobs;

use App\Models\PostingRound;
use App\Services\MailDispatchService;
use App\Services\PipelineService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * E4 / final-round regrets for exactly the rows one action stamped (by id — a second publish in the same second
 * must not re-mail anyone, QA F-008), one batch at a time (spec B6, D89).
 */
class SendRoundResultMails implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200;

    /** @param  list<int>  $rowIds */
    public function __construct(public int $roundId, public array $rowIds, public bool $withNextRound = true, public string $kind = 'stage_result')
    {
    }

    public function handle(PipelineService $pipeline, MailDispatchService $mail): void
    {
        $round = PostingRound::query()->find($this->roundId);
        if (! $round) {
            return;
        }

        $posting = $round->jobPosting()->with('postable.company')->first();
        $next = $this->withNextRound ? $posting->rounds()->where('sort_order', '>', $round->sort_order)->first() : null;

        $round->results()
            ->whereIn('id', $this->rowIds)
            ->whereNotNull('published_at')
            ->with(['application.studentProfile.user', 'application.offer'])
            ->chunkById($mail->batchSize(), fn ($rows) => $pipeline->notifyResults($posting, $round, $rows, $next, $this->kind));
    }
}
