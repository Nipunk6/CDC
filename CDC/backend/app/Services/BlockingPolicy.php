<?php

namespace App\Services;

use App\Models\Application;
use App\Models\CycleEnrollment;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * Default block created by each offer type — owner decision 2026-10-01 (QA F-004, D91), replacing spec B4's
 * "this cycle only" for the blocking types. Admins can override at announcement and unblock anyone at any time.
 *
 * | offer_type              | Meaning                                         | Default block                                           |
 * |-------------------------|-------------------------------------------------|---------------------------------------------------------|
 * | intern                  | Internship selection                            | internships — the whole internship cycle(s); FT open    |
 * | intern_performance_ppo  | Intern + performance-based PPO                  | internships — the whole internship cycle(s); FT open    |
 * | ppo_offered             | PPO offered but not accepted                    | no block                                                |
 * | intern_ppo              | PPO accepted after internship                   | completely — every cycle the student is enrolled in     |
 * | fulltime                | Full-time selection                             | completely — every cycle the student is enrolled in     |
 * | intern_fulltime         | Intern + Full-time combined offer               | completely — every cycle the student is enrolled in     |
 *
 * The stored `block_scope` keeps its two values: `all` = "completely", `internships_only` = "internship opportunities".
 */
final class BlockingPolicy
{
    private const MATRIX = [
        'intern' => 'internships_only',
        'intern_ppo' => 'all',
        'ppo_offered' => null,
        'fulltime' => 'all',
        'intern_fulltime' => 'all',
        'intern_performance_ppo' => 'internships_only',
    ];

    /**
     * @return array{scope: string}|null null = no block
     */
    public function suggest(string $offerType): ?array
    {
        $scope = self::MATRIX[$offerType] ?? null;

        return $scope === null ? null : ['scope' => $scope];
    }

    /** Default offer type for a posting: its float-time category (JNF → fulltime, INF → intern when not set). */
    public function defaultOfferType(JobPosting|string $posting): string
    {
        if ($posting instanceof JobPosting) {
            return $posting->offerType();
        }

        return $posting === 'internship' ? 'intern' : 'fulltime';
    }

    /**
     * The block rows an offer creates: one per placement cycle it reaches, each carrying the chosen scope.
     *  - `all` (completely): the offer's cycle + every other open cycle the student is enrolled in.
     *  - `internships_only`: the offer's cycle + every other open INTERNSHIP cycle the student is enrolled in.
     *    An internship cycle only ever holds internships (the float API refuses a JNF there), so this blocks the
     *    whole internship cycle while full-time cycles stay open.
     *
     * @return list<array{placement_cycle_id: int, scope: string}>
     */
    public function targets(StudentProfile $student, PlacementCycle $offerCycle, string $scope): array
    {
        $others = PlacementCycle::query()
            ->where('status', 'open')
            ->where('id', '!=', $offerCycle->id)
            ->whereIn('id', CycleEnrollment::query()->where('student_profile_id', $student->id)->select('placement_cycle_id'))
            ->when($scope !== 'all', fn ($q) => $q->where('type', 'internship'))
            ->orderBy('id')
            ->pluck('id');

        return collect([$offerCycle->id])->merge($others)
            ->map(fn (int $cycleId) => ['placement_cycle_id' => $cycleId, 'scope' => $scope])
            ->all();
    }

    /**
     * Students enrolled into a cycle AFTER their offer was announced bring the offer's block with them, so a
     * "completely" block also covers a cycle opened later (and an internships-only one a later internship cycle).
     * A cycle where the admin already lifted that offer's block is left alone.
     *
     * @param  list<int>  $studentIds
     * @return Collection<int, PlacementBlock> the rows created
     */
    public function carryForward(PlacementCycle $cycle, array $studentIds, ?int $adminId): Collection
    {
        $created = collect();
        if ($studentIds === []) {
            return $created;
        }

        PlacementBlock::query()
            ->whereIn('student_profile_id', $studentIds)
            ->where('reason', 'offer')
            ->where('active', true)
            ->where('placement_cycle_id', '!=', $cycle->id)
            ->when($cycle->type !== 'internship', fn ($q) => $q->where('scope', 'all'))
            ->orderBy('id')
            ->get(['student_profile_id', 'offer_id', 'scope'])
            ->unique(fn (PlacementBlock $block) => $block->student_profile_id.'-'.$block->offer_id)
            ->each(function (PlacementBlock $block) use ($cycle, $adminId, $created): void {
                $row = PlacementBlock::query()->firstOrCreate(
                    ['student_profile_id' => $block->student_profile_id, 'placement_cycle_id' => $cycle->id, 'reason' => 'offer', 'offer_id' => $block->offer_id],
                    ['scope' => $block->scope, 'active' => true, 'blocked_by' => $adminId]
                );
                if ($row->wasRecentlyCreated) {
                    $created->push($row);
                }
            });

        return $created;
    }

    /**
     * Why this student must not be given an offer for this posting, or null when an offer is allowed (owner rule,
     * QA F-027 / F-035): they already hold an offer in the posting's placement cycle, or an active block in that cycle
     * applies to the posting — a debarment or an "all" block always, an internships-only block for an internship
     * posting (the same rule EligibilityService uses for applying). Admins lift the block or revoke the offer first.
     */
    public function offerRefusal(StudentProfile $student, JobPosting $posting, ?int $exceptApplicationId = null): ?string
    {
        $held = Offer::query()
            ->where('student_profile_id', $student->id)
            ->where('placement_cycle_id', $posting->placement_cycle_id)
            ->when($exceptApplicationId, fn ($q) => $q->where('application_id', '!=', $exceptApplicationId))
            ->with('company')
            ->orderBy('id')
            ->first();

        if ($held) {
            return sprintf(
                '%s already holds an offer in this placement cycle (%s). Revoke that offer first if this is intended.',
                $student->roll_no,
                $held->company?->name ?? 'another company'
            );
        }

        $block = PlacementBlock::query()
            ->where('student_profile_id', $student->id)
            ->where('placement_cycle_id', $posting->placement_cycle_id)
            ->where('active', true)
            ->orderBy('id')
            ->get()
            ->first(fn (PlacementBlock $b) => $b->reason === 'debarred'
                || $b->scope === 'all'
                || ($b->scope === 'internships_only' && $posting->postingType() === 'internship'));

        if ($block) {
            $why = match ($block->reason) {
                'debarred' => 'debarred',
                'offer' => 'an earlier offer',
                default => 'blocked by the CDC',
            };

            return sprintf('%s is blocked in this placement cycle (%s). Lift the block first if this is intended.', $student->roll_no, $why);
        }

        return null;
    }

    /**
     * Spec B3/Q3.7: the student's other live applications in a cycle a new block covers get the placed-elsewhere flag.
     */
    public function flagLiveApplications(int $studentId, int $cycleId, string $scope, ?int $exceptApplicationId = null): int
    {
        $others = Application::query()
            ->where('student_profile_id', $studentId)
            ->when($exceptApplicationId, fn ($q) => $q->where('id', '!=', $exceptApplicationId))
            ->where('status', 'applied')
            ->whereHas('jobPosting', function ($q) use ($cycleId) {
                $q->where('placement_cycle_id', $cycleId)->whereIn('status', ['open', 'in_process', 'completed']);
            })
            // Still in the running there: no offer and never published as not selected (a waitlist counts, D84).
            ->whereDoesntHave('offer')
            ->whereDoesntHave('roundResults', fn ($r) => $r->whereNotNull('published_at')->where('result', 'rejected'))
            ->with('jobPosting')
            ->get()
            ->filter(fn (Application $other) => $scope === 'all' || $other->jobPosting->postingType() === 'internship');

        foreach ($others as $other) {
            $other->update(['placed_elsewhere_flag' => true]);
        }

        return $others->count();
    }

    /** Sentence for the offer mail. */
    public function describe(string $scope): string
    {
        return $scope === 'all'
            ? 'As per CDC policy you are now blocked from all further placement and internship opportunities in the placements you are registered for.'
            : 'As per CDC policy you are now blocked from further internship opportunities; full-time opportunities remain open to you.';
    }
}
