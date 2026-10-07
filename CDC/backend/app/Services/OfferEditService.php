<?php

namespace App\Services;

use App\Mail\PortalNoticeMail;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Editing and revoking an announced offer (Superset parity S2, owner decision B2-8). A change of offer type re-runs
 * BlockingPolicy: the offer's active blocks are lifted and the new type's blocks created, and placed-elsewhere flags
 * follow. A block the admin lifted by hand never comes back unless the admin asks for it (D91, L7). Every write is
 * audited by the caller with before/after values.
 */
class OfferEditService
{
    /** Blocks follow the new offer type, but a block the admin lifted by hand stays lifted (the API default). */
    public const BLOCKS_DEFAULT = 'default';

    /** Blocks follow the new offer type and the blocks the admin lifted by hand come back (`reapply_blocking: true`). */
    public const BLOCKS_REAPPLY = 'reapply';

    /** Blocks stay exactly as they are (`reapply_blocking: false`). */
    public const BLOCKS_KEEP = 'keep';

    public function __construct(
        private readonly BlockingPolicy $policy,
        private readonly PortalNotificationService $notifications,
        private readonly MailDispatchService $mail
    ) {
    }

    /**
     * What a change of offer type would do to blocks, without writing anything. `restorable` lists the blocks the admin
     * lifted by hand that only BLOCKS_REAPPLY would bring back.
     *
     * @return array{current_scope: string|null, new_scope: string|null, changes_blocks: bool, had_lifted_blocks: bool, lift: list<array{cycle: string, scope: string}>, create: list<array{cycle: string, scope: string}>, restorable: list<array{cycle: string, scope: string}>, summary: string}
     */
    public function preview(Offer $offer, string $newType, string $mode = self::BLOCKS_DEFAULT): array
    {
        $plan = $this->plan($offer, $newType, $mode);
        $cycles = PlacementCycle::query()->pluck('name', 'id');
        $named = fn (array $targets) => array_map(fn (array $t) => ['cycle' => $cycles[$t['placement_cycle_id']] ?? '—', 'scope' => $t['scope']], $targets);
        $lift = $plan['lift']->map(fn (PlacementBlock $b) => ['cycle' => $b->placementCycle?->name ?? '—', 'scope' => $b->scope])->values()->all();
        $create = $named($plan['create']);

        return [
            'current_scope' => $plan['current_scope'],
            'new_scope' => $plan['new_scope'],
            'changes_blocks' => $plan['changes'],
            // The admin lifted this offer's block by hand earlier: it does not come back unless the admin asks (D91).
            'had_lifted_blocks' => $plan['hand_lifted'] !== [],
            'lift' => $lift,
            'create' => $create,
            'restorable' => $named($plan['restorable']),
            'summary' => $this->summary($plan['changes'], $lift, $create, $plan['new_scope']),
        ];
    }

    /**
     * @param  array{offer_type?: string, ctc_annual?: int|null, stipend_monthly?: int|null, currency?: string}  $changes
     * @param  string  $mode  one of the BLOCKS_* constants
     * @return array{offer: Offer, before: array<string, mixed>, after: array<string, mixed>, lifted: Collection<int, PlacementBlock>, created: Collection<int, PlacementBlock>, flags_cleared: int, flags_set: int}
     */
    public function update(Offer $offer, array $changes, string $mode, User $admin): array
    {
        return DB::transaction(function () use ($offer, $changes, $mode, $admin) {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            $before = $offer->only(['offer_type', 'ctc_annual', 'stipend_monthly', 'currency']);
            $plan = $this->plan($offer, $changes['offer_type'] ?? $offer->offer_type, $mode);

            $offer->fill($changes)->save();

            $lifted = collect();
            $created = collect();
            $cleared = 0;
            $set = 0;

            if ($plan['changes']) {
                $lifted = $this->liftBlocks($plan['lift'], $admin);
                foreach ($plan['create'] as $target) {
                    $block = PlacementBlock::create([
                        'student_profile_id' => $offer->student_profile_id,
                        'placement_cycle_id' => $target['placement_cycle_id'],
                        'scope' => $target['scope'],
                        'reason' => 'offer',
                        'offer_id' => $offer->id,
                        'active' => true,
                        'blocked_by' => $admin->id,
                    ]);
                    $created->push($block);
                    $set += $this->policy->flagLiveApplications($offer->student_profile_id, $target['placement_cycle_id'], $target['scope'], $offer->application_id);
                }
                $cleared = $this->reconcileFlags($offer->student_profile_id, $lifted->pluck('placement_cycle_id')->unique()->all());
            }

            return [
                'offer' => $offer,
                'before' => $before,
                'after' => $offer->only(['offer_type', 'ctc_annual', 'stipend_monthly', 'currency']),
                'lifted' => $lifted,
                'created' => $created,
                'flags_cleared' => $cleared,
                'flags_set' => $set,
            ];
        });
    }

    /**
     * Revoke an offer: its blocks are lifted, placed-elsewhere flags that no longer have a reason are cleared, and the
     * offer row is removed (the audit log keeps it). The final-stage result stays as published.
     *
     * @return array{before: array<string, mixed>, lifted: Collection<int, PlacementBlock>, flags_cleared: int}
     */
    public function revoke(Offer $offer, User $admin): array
    {
        return DB::transaction(function () use ($offer, $admin) {
            $offer = Offer::query()->lockForUpdate()->findOrFail($offer->id);
            $before = $offer->only(['id', 'application_id', 'student_profile_id', 'job_posting_id', 'placement_cycle_id', 'offer_type', 'ctc_annual', 'stipend_monthly', 'currency', 'announced_at']);

            $lifted = $this->liftBlocks($this->activeBlocks($offer), $admin);
            $studentId = $offer->student_profile_id;
            $offer->delete();
            $cleared = $this->reconcileFlags($studentId, $lifted->pluck('placement_cycle_id')->unique()->all());

            return ['before' => $before, 'lifted' => $lifted, 'flags_cleared' => $cleared];
        });
    }

    /** In-app notice plus a personal mail to the student about a changed or revoked offer. */
    public function notifyStudent(Offer|array $offer, string $headline, array $lines, string $type = 'info'): void
    {
        $studentId = is_array($offer) ? $offer['student_profile_id'] : $offer->student_profile_id;
        $student = \App\Models\StudentProfile::query()->with('user')->find($studentId);
        if (! $student?->user) {
            return;
        }

        $this->notifications->createInAppNotification($student->user, $headline, implode(' ', $lines), $type);
        $mailable = new PortalNoticeMail($headline, "Dear {$student->full_name},", $headline, $lines);
        $this->mail->send($student->user, $mailable, $headline, 'emails.portal-notice', [
            'job_posting_id' => is_array($offer) ? ($offer['job_posting_id'] ?? null) : $offer->job_posting_id,
            'kind' => 'offer_update',
        ]);
    }

    public static function compensationText(?int $ctc, ?int $stipend, string $currency): string
    {
        return collect([
            $ctc ? $currency.' '.number_format($ctc).' per year' : null,
            $stipend ? $currency.' '.number_format($stipend).' per month' : null,
        ])->filter()->implode(' · ') ?: 'not specified';
    }

    /**
     * @return Collection<int, PlacementBlock>
     */
    private function activeBlocks(Offer $offer): Collection
    {
        return PlacementBlock::query()->with('placementCycle:id,name')->where('offer_id', $offer->id)->where('active', true)->orderBy('id')->get();
    }

    /**
     * What a change of offer type does to blocks. A different scope lifts the offer's active blocks and creates the new
     * type's blocks in every cycle BlockingPolicy::targets() reaches, except a cycle where the admin lifted the offer's
     * block by hand: that block comes back only with BLOCKS_REAPPLY (D91). With the same scope the active blocks stay;
     * BLOCKS_REAPPLY then only restores the hand-lifted ones. BLOCKS_KEEP and an unchanged type touch nothing.
     *
     * @return array{current_scope: string|null, new_scope: string|null, changes: bool, lift: Collection<int, PlacementBlock>, create: list<array{placement_cycle_id: int, scope: string}>, restorable: list<array{placement_cycle_id: int, scope: string}>, hand_lifted: list<int>}
     */
    private function plan(Offer $offer, string $newType, string $mode): array
    {
        $active = $this->activeBlocks($offer);
        $currentScope = $active->first()?->scope;
        $newScope = $this->policy->suggest($newType)['scope'] ?? null;
        $handLifted = $this->handLiftedCycles($offer);
        $lift = collect();
        $create = [];
        $restorable = [];

        if ($mode !== self::BLOCKS_KEEP && $newType !== $offer->offer_type) {
            $targets = $newScope === null ? [] : $this->policy->targets($offer->studentProfile, $offer->placementCycle, $newScope);
            $wasLifted = fn (array $t) => in_array($t['placement_cycle_id'], $handLifted, true);
            if ($currentScope !== $newScope) {
                $lift = $active;
                $create = array_values(array_filter($targets, fn (array $t) => ! $wasLifted($t)));
                $restorable = array_values(array_filter($targets, $wasLifted));
            } else {
                $covered = $active->pluck('placement_cycle_id')->all();
                $restorable = array_values(array_filter($targets, fn (array $t) => $wasLifted($t) && ! in_array($t['placement_cycle_id'], $covered)));
            }
            if ($mode === self::BLOCKS_REAPPLY) {
                $create = array_merge($create, $restorable);
            }
        }

        return [
            'current_scope' => $currentScope,
            'new_scope' => $newScope,
            'changes' => $lift->isNotEmpty() || $create !== [],
            'lift' => $lift,
            'create' => $create,
            'restorable' => $restorable,
            'hand_lifted' => $handLifted,
        ];
    }

    /**
     * Cycles where the admin lifted this offer's block by hand (Placement Blocks → lift): the offer's latest block in
     * that cycle is inactive and was not lifted by an offer edit, whose `block.remove` audit rows carry `via`.
     *
     * @return list<int>
     */
    private function handLiftedCycles(Offer $offer): array
    {
        $lifted = PlacementBlock::query()->where('offer_id', $offer->id)->orderBy('id')->get()
            ->keyBy('placement_cycle_id') // the latest block per cycle
            ->reject(fn (PlacementBlock $b) => $b->active);
        if ($lifted->isEmpty()) {
            return [];
        }

        $byOfferEdit = AuditLog::query()
            ->where('action', 'block.remove')
            ->where('subject_type', PlacementBlock::class)
            ->whereIn('subject_id', $lifted->pluck('id'))
            ->get(['subject_id', 'after'])
            ->filter(fn (AuditLog $log) => isset($log->after['via']))
            ->map(fn (AuditLog $log) => (int) $log->subject_id)
            ->all();

        return $lifted->reject(fn (PlacementBlock $b) => in_array($b->id, $byOfferEdit, true))
            ->map(fn (PlacementBlock $b) => (int) $b->placement_cycle_id)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, PlacementBlock>  $blocks
     * @return Collection<int, PlacementBlock>
     */
    private function liftBlocks(Collection $blocks, User $admin): Collection
    {
        foreach ($blocks as $block) {
            $block->update(['active' => false, 'unblocked_by' => $admin->id, 'unblocked_at' => now()]);
        }

        return $blocks;
    }

    /**
     * After blocks were lifted in some cycles: a live application keeps its placed-elsewhere flag only if an active
     * block in its cycle still covers it ("all" covers everything, "internships_only" covers internship postings).
     *
     * @param  list<int>  $cycleIds
     */
    private function reconcileFlags(int $studentId, array $cycleIds): int
    {
        $cleared = 0;
        foreach ($cycleIds as $cycleId) {
            $scopes = PlacementBlock::query()
                ->where('student_profile_id', $studentId)
                ->where('placement_cycle_id', $cycleId)
                ->where('active', true)
                ->pluck('scope')
                ->unique();
            if ($scopes->contains('all')) {
                continue;
            }

            Application::query()
                ->with('jobPosting')
                ->where('student_profile_id', $studentId)
                ->where('placed_elsewhere_flag', true)
                ->whereHas('jobPosting', fn ($q) => $q->where('placement_cycle_id', $cycleId))
                ->get()
                ->reject(fn (Application $a) => $scopes->contains('internships_only') && $a->jobPosting->postingType() === 'internship')
                ->each(function (Application $a) use (&$cleared): void {
                    $a->update(['placed_elsewhere_flag' => false]);
                    $cleared++;
                });
        }

        return $cleared;
    }

    /**
     * @param  list<array{cycle: string, scope: string}>  $lift
     * @param  list<array{cycle: string, scope: string}>  $create
     */
    private function summary(bool $changes, array $lift, array $create, ?string $newScope): string
    {
        if (! $changes) {
            return 'Blocks stay as they are.';
        }

        $label = fn (string $scope) => $scope === 'all' ? 'completely' : 'from internships';
        $parts = [];
        if ($lift !== []) {
            $parts[] = 'Lifts '.count($lift).' block(s) ('.collect($lift)->pluck('cycle')->implode(', ').').';
        }
        if ($newScope === null) {
            $parts[] = 'The new offer type does not block; placed-elsewhere flags that no longer apply are cleared.';
        } elseif ($create !== []) {
            $parts[] = 'Blocks the student '.$label($newScope).' in '.collect($create)->pluck('cycle')->implode(', ').'.';
        }

        return implode(' ', $parts);
    }
}
