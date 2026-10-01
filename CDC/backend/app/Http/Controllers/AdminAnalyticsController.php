<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\CycleEnrollment;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * Placement analytics (spec Q9 / M10.1). Lives beside the Phase 1 AdminDashboardController so `/admin/dashboard`
 * is untouched. "Placed" = has at least one offer other than `ppo_offered`; the placement percentage's
 * denominator is ALL enrolled students (spec Q9.2). Salary and stipend figures count accepted offers only — a PPO
 * offered but not accepted is left out (owner decision, QA 2026-10-01). Drives are counted per posting, since one
 * company can float several.
 */
class AdminAnalyticsController extends Controller
{
    private const NOT_PLACING = ['ppo_offered'];

    public function overview(): JsonResponse
    {
        $cycles = PlacementCycle::query()->withCount('enrollments')->orderByDesc('starts_on')->get();

        return response()->json([
            'cycles' => $cycles->map(function (PlacementCycle $cycle) {
                // Only students still enrolled, so the overview agrees with the cycle page (D88).
                $placed = Offer::query()->where('placement_cycle_id', $cycle->id)->whereNotIn('offer_type', self::NOT_PLACING)
                    ->whereIn('student_profile_id', CycleEnrollment::query()->where('placement_cycle_id', $cycle->id)->select('student_profile_id'))
                    ->distinct()->count('student_profile_id');
                $enrolled = (int) $cycle->enrollments_count;

                return [
                    'id' => $cycle->id,
                    'name' => $cycle->name,
                    'type' => $cycle->type,
                    'status' => $cycle->status,
                    'enrolled' => $enrolled,
                    'placed' => $placed,
                    'placed_percent' => $enrolled ? round($placed * 100 / $enrolled, 1) : 0,
                    'offers' => Offer::query()->where('placement_cycle_id', $cycle->id)->count(),
                    'postings' => JobPosting::query()->where('placement_cycle_id', $cycle->id)->where('status', '!=', 'cancelled')->count(),
                ];
            }),
        ]);
    }

    public function cycle(PlacementCycle $placementCycle): JsonResponse
    {
        $cycleId = $placementCycle->id;

        $students = StudentProfile::query()
            ->whereIn('id', CycleEnrollment::query()->where('placement_cycle_id', $cycleId)->select('student_profile_id'))
            ->get(['id', 'programme', 'branch', 'graduating_batch', 'gender']);

        $offers = Offer::query()->with('company:id,name')->where('placement_cycle_id', $cycleId)->get();
        $placedIds = $offers->reject(fn (Offer $o) => in_array($o->offer_type, self::NOT_PLACING, true))->pluck('student_profile_id')->unique()->flip();

        $postings = JobPosting::query()->where('placement_cycle_id', $cycleId)->where('status', '!=', 'cancelled')->get(['id', 'status', 'postable_type', 'postable_id']);

        $enrolled = $students->count();
        $placed = $students->filter(fn ($s) => $placedIds->has($s->id))->count();

        // Money statistics: accepted offers in INR only; offers in other currencies are counted separately (D88).
        $accepted = $offers->reject(fn (Offer $o) => in_array($o->offer_type, self::NOT_PLACING, true));
        $inr = $accepted->where('currency', 'INR');
        $ctcs = $inr->pluck('ctc_annual')->filter()->map(fn ($v) => (int) $v)->sort()->values();
        $stipends = $inr->pluck('stipend_monthly')->filter()->map(fn ($v) => (int) $v)->sort()->values();

        $applied = Application::query()
            ->whereIn('job_posting_id', $postings->pluck('id'))
            ->whereNotNull('applied_at')
            ->pluck('applied_at');

        return response()->json([
            'cycle' => $placementCycle->only(['id', 'name', 'type', 'status', 'starts_on', 'ends_on']),
            'totals' => [
                'enrolled' => $enrolled,
                'placed' => $placed,
                'unplaced' => $enrolled - $placed,
                'placed_percent' => $enrolled ? round($placed * 100 / $enrolled, 1) : 0,
                'offers' => $offers->count(),
                'applications' => $applied->count(),
                'drives_completed' => $postings->where('status', 'completed')->count(),
                'drives_ongoing' => $postings->whereIn('status', ['open', 'in_process'])->count(),
            ],
            'by_programme' => $this->groupPlaced($students, $placedIds, fn ($s) => $s->programme),
            'by_batch' => $this->groupPlaced($students, $placedIds, fn ($s) => (string) $s->graduating_batch),
            'by_gender' => $this->groupPlaced($students, $placedIds, fn ($s) => ucfirst((string) $s->gender)),
            'branch_table' => $this->branchTable($students, $placedIds, $offers),
            'offers_by_type' => $offers->groupBy('offer_type')->map(fn ($g, $type) => [
                'type' => $type,
                'label' => Offer::LABELS[$type] ?? $type,
                'count' => $g->count(),
            ])->values(),
            'ctc' => $this->stats($ctcs),
            'non_inr_offers' => $accepted->where('currency', '!=', 'INR')->groupBy('currency')->map->count(),
            'stipend' => $this->stats($stipends),
            'top_recruiters' => $offers->groupBy('company_id')->map(fn ($g) => [
                'company' => $g->first()->company?->name,
                'offers' => $g->count(),
                'best_ctc' => $g->whereNotIn('offer_type', self::NOT_PLACING)->where('currency', 'INR')->max('ctc_annual'),
            ])->sortByDesc('offers')->take(10)->values(),
            'applications_over_time' => $this->daily($applied),
        ]);
    }

    /**
     * @return list<array{key: string, enrolled: int, placed: int, placed_percent: float}>
     */
    private function groupPlaced(Collection $students, Collection $placedIds, callable $key): array
    {
        return $students->groupBy($key)->map(function ($group, $name) use ($placedIds) {
            $placed = $group->filter(fn ($s) => $placedIds->has($s->id))->count();

            return [
                'key' => (string) $name,
                'enrolled' => $group->count(),
                'placed' => $placed,
                'placed_percent' => round($placed * 100 / max(1, $group->count()), 1),
            ];
        })->sortByDesc('enrolled')->values()->all();
    }

    private function branchTable(Collection $students, Collection $placedIds, Collection $offers): array
    {
        $offersByStudent = $offers->groupBy('student_profile_id');

        return $students->groupBy(fn ($s) => $s->programme.'||'.$s->branch)->map(function ($group, $key) use ($placedIds, $offersByStudent) {
            [$programme, $branch] = explode('||', $key, 2);
            $placed = $group->filter(fn ($s) => $placedIds->has($s->id))->count();
            $branchOffers = $group->flatMap(fn ($s) => $offersByStudent->get($s->id, collect()));
            $ctcs = $branchOffers->whereNotIn('offer_type', self::NOT_PLACING)->where('currency', 'INR')->pluck('ctc_annual')->filter();

            return [
                'programme' => $programme,
                'branch' => $branch,
                'enrolled' => $group->count(),
                'placed' => $placed,
                'placed_percent' => round($placed * 100 / max(1, $group->count()), 1),
                'offers' => $branchOffers->count(),
                'average_ctc' => $ctcs->isEmpty() ? null : (int) round($ctcs->avg()),
                'highest_ctc' => $ctcs->max(),
            ];
        })->sortByDesc('enrolled')->values()->all();
    }

    /**
     * Applications per IST day with empty days filled in, so the chart's x axis is continuous.
     *
     * @return list<array{date: string, count: int}>
     */
    private function daily(Collection $timestamps): array
    {
        $counts = $timestamps->countBy(fn ($at) => $at->timezone('Asia/Kolkata')->format('Y-m-d'));
        if ($counts->isEmpty()) {
            return [];
        }

        $day = \Carbon\CarbonImmutable::parse($counts->keys()->min());
        $last = \Carbon\CarbonImmutable::parse($counts->keys()->max());
        $series = [];
        while ($day <= $last && count($series) < 400) {
            $key = $day->format('Y-m-d');
            $series[] = ['date' => $key, 'count' => (int) ($counts[$key] ?? 0)];
            $day = $day->addDay();
        }

        return $series;
    }

    /**
     * @param  Collection<int, int>  $values  sorted ascending
     */
    private function stats(Collection $values): array
    {
        $count = $values->count();
        if ($count === 0) {
            return ['count' => 0, 'highest' => null, 'average' => null, 'median' => null, 'lowest' => null];
        }

        $middle = intdiv($count, 2);
        $median = $count % 2 ? $values[$middle] : (int) round(($values[$middle - 1] + $values[$middle]) / 2);

        return [
            'count' => $count,
            'highest' => $values->last(),
            'average' => (int) round($values->avg()),
            'median' => $median,
            'lowest' => $values->first(),
        ];
    }
}
