<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\PostingRound;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Reconcile Ineligible Students" (owner decision B2-11, Superset parity fix H1): re-check a stage's current pool
 * against the job profile's CURRENT eligibility, blocks and debarments — only through EligibilityService::check() —
 * and, when the admin explicitly confirms, mark the chosen students as not selected in that stage.
 *
 * This is the one place where D103(d) ("applicants keep their applications when eligibility changes") is overridden,
 * and only by this explicit admin action.
 */
class ReconcileService
{
    public function __construct(
        private readonly PipelineService $pipeline,
        private readonly EligibilityService $eligibility
    ) {
    }

    /**
     * Pool members who are no longer eligible, with the student-facing reasons. Offer holders of this job profile and
     * students already decided in this stage by a published row are left out (they cannot be rejected here).
     *
     * @return Collection<int, array{application: Application, reasons: list<string>}>
     */
    public function ineligible(PostingRound $round): Collection
    {
        $posting = $round->jobPosting;
        $this->eligibility->flush();

        $pool = $this->pipeline->pool($round)
            ->load(['studentProfile.user', 'studentProfile.cycleEnrollments', 'offer', 'roundResults' => fn ($q) => $q->where('posting_round_id', $round->id)]);

        return $pool
            ->filter(fn (Application $a) => $this->rejectable($a, $round) === null)
            ->map(function (Application $a) use ($posting) {
                $check = $this->eligibility->check($a->studentProfile, $posting);

                return $check['eligible'] ? null : ['application' => $a, 'reasons' => $check['reasons']];
            })
            ->filter()
            ->sortBy(fn (array $row) => $row['application']->studentProfile->roll_no)
            ->values();
    }

    /** Why an application cannot be rejected by reconcile, or null when it can. */
    public function rejectable(Application $application, PostingRound $round): ?string
    {
        if ($application->status !== 'applied') {
            return 'The application is not live.';
        }
        if ($application->offer) {
            return 'The student has an offer on this job profile.';
        }
        $row = $application->relationLoaded('roundResults')
            ? $application->roundResults->firstWhere('posting_round_id', $round->id)
            : $application->roundResults()->where('posting_round_id', $round->id)->first();
        if ($row && $row->isPublished()) {
            return $row->result === 'rejected' ? 'Already marked not selected in this stage.' : 'A published decision in this stage cannot be changed here.';
        }

        return null;
    }

    /**
     * Write a PUBLISHED "rejected" row in this stage for each chosen application that is in the pool, still
     * ineligible and rejectable. Returns the written row ids (mails are dispatched by these ids, D92/F-008) and what
     * was skipped.
     *
     * @param  list<int>  $applicationIds
     * @return array{rows: list<int>, rejected: list<array{application_id: int, roll_no: string, reasons: list<string>}>, skipped: list<array{application_id: int, reason: string}>}
     */
    public function reject(PostingRound $round, array $applicationIds, User $admin): array
    {
        $candidates = $this->ineligible($round)->keyBy(fn (array $row) => $row['application']->id);
        $now = now()->startOfSecond();
        $rows = [];
        $rejected = [];
        $skipped = [];

        DB::transaction(function () use ($round, $applicationIds, $candidates, $admin, $now, &$rows, &$rejected, &$skipped): void {
            foreach (array_values(array_unique(array_map('intval', $applicationIds))) as $id) {
                $candidate = $candidates->get($id);
                if (! $candidate) {
                    $skipped[] = ['application_id' => $id, 'reason' => 'Not in this stage\'s pool, still eligible, or already decided.'];

                    continue;
                }

                $existing = ApplicationRoundResult::query()
                    ->where('application_id', $id)
                    ->where('posting_round_id', $round->id)
                    ->lockForUpdate()
                    ->first();
                if ($existing && $existing->isPublished()) {
                    $skipped[] = ['application_id' => $id, 'reason' => 'A published decision in this stage cannot be changed here.'];

                    continue;
                }

                $attributes = [
                    'result' => 'rejected',
                    'published_at' => $now,
                    'decided_by' => $admin->id,
                    'remark' => mb_substr('No longer eligible: '.implode(' ', $candidate['reasons']), 0, 255),
                ];
                $row = $existing
                    ? tap($existing)->update($attributes)
                    : ApplicationRoundResult::create($attributes + ['application_id' => $id, 'posting_round_id' => $round->id]);

                $rows[] = $row->id;
                $rejected[] = [
                    'application_id' => $id,
                    'roll_no' => $candidate['application']->studentProfile->roll_no,
                    'reasons' => $candidate['reasons'],
                ];
            }
        });

        return ['rows' => $rows, 'rejected' => $rejected, 'skipped' => $skipped];
    }
}
