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

    /**
     * The default-block matrix, for showing the rules (offer type => `all`, `internships_only` or null).
     *
     * @return array<string, string|null>
     */
    public function matrix(): array
    {
        return self::MATRIX;
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

    /** Most offers one student can hold in a full-time (placement) cycle (owner decision 2026-10-18). */
    public const MAX_PLACEMENT_CYCLE_OFFERS = 2;

    /**
     * Why this student must not be given an offer of `$offerType` (default: the posting's category) for this posting,
     * or null when an offer is allowed (owner rule, QA F-027 / F-035): they already hold an offer in the posting's
     * placement cycle, or an active block in that cycle applies to the posting — a debarment or an "all" block always,
     * an internships-only block for an internship posting (the same rule EligibilityService uses for applying).
     *
     * One exception (owner decision 2026-10-18): in a full-time cycle, a student whose only offer there is an
     * Intern + performance-based PPO can get one more offer, a plain Full-Time one — at most two offers in the cycle.
     * Admins lift the block or revoke the offer first.
     */
    public function offerRefusal(StudentProfile $student, JobPosting $posting, ?int $exceptApplicationId = null, ?string $offerType = null): ?string
    {
        $held = Offer::query()
            ->where('student_profile_id', $student->id)
            ->where('placement_cycle_id', $posting->placement_cycle_id)
            ->when($exceptApplicationId, fn ($q) => $q->where('application_id', '!=', $exceptApplicationId))
            ->with('company')
            ->orderBy('id')
            ->get();

        if ($held->isNotEmpty() && ! $this->allowsSecondOffer($posting, $held, $offerType ?? $posting->offerType())) {
            $first = $held->first();

            if ($held->count() >= self::MAX_PLACEMENT_CYCLE_OFFERS) {
                return sprintf(
                    '%s already holds %d offers in this placement cycle, the most allowed. Revoke one first if this is intended.',
                    $student->roll_no,
                    $held->count()
                );
            }

            if ($first->offer_type === 'intern_performance_ppo' && $posting->placementCycle?->type === 'fulltime') {
                return sprintf(
                    '%s holds an Intern + performance-based PPO offer in this placement cycle (%s), so the only further offer allowed is a Full-Time one.',
                    $student->roll_no,
                    $first->company?->name ?? 'another company'
                );
            }

            return sprintf(
                '%s already holds an offer in this placement cycle (%s). Revoke that offer first if this is intended.',
                $student->roll_no,
                $first->company?->name ?? 'another company'
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
     * Why an announced offer's type cannot change to `$newType`, or null: when the student holds another offer in the
     * same cycle, the two must stay the one allowed pair (Intern + performance-based PPO and Full-Time, full-time cycle).
     */
    public function offerTypeChangeRefusal(Offer $offer, string $newType): ?string
    {
        $others = Offer::query()
            ->where('student_profile_id', $offer->student_profile_id)
            ->where('placement_cycle_id', $offer->placement_cycle_id)
            ->whereKeyNot($offer->id)
            ->get();

        if ($others->isEmpty()) {
            return null;
        }

        $types = [$others->first()->offer_type, $newType];
        sort($types);
        $allowed = $others->count() === 1
            && $offer->placementCycle?->type === 'fulltime'
            && $types === ['fulltime', 'intern_performance_ppo'];

        return $allowed ? null : 'This student holds two offers in this placement cycle; they must stay an Intern + performance-based PPO and a Full-Time offer.';
    }

    /**
     * The one second-offer case: a full-time cycle, the only offer held there is an Intern + performance-based PPO,
     * and the new offer is a plain Full-Time one.
     *
     * @param  Collection<int, Offer>  $held
     */
    private function allowsSecondOffer(JobPosting $posting, Collection $held, string $offerType): bool
    {
        return $posting->placementCycle?->type === 'fulltime'
            && $held->count() === 1
            && $held->first()->offer_type === 'intern_performance_ppo'
            && $offerType === 'fulltime';
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

    /**
     * After blocks were lifted in some cycles: a live application keeps its placed-elsewhere flag only if an active
     * block in its cycle still covers it ("all" covers everything, "internships_only" covers internship postings).
     *
     * @param  list<int>  $cycleIds
     */
    public function reconcileFlags(int $studentId, array $cycleIds): int
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

    /** Sentence for the offer mail. */
    public function describe(string $scope): string
    {
        return $scope === 'all'
            ? 'As per CDC policy you are now blocked from all further placement and internship opportunities in the placements you are registered for.'
            : 'As per CDC policy you are now blocked from further internship opportunities; full-time opportunities remain open to you.';
    }
}
