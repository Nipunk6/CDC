<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * One student's placement record for the admin student page and its two Excel reports (Superset parity S4.5).
 * Admin only: round results are returned whether published or not, each marked with `published`.
 */
class StudentRecordService
{
    public function __construct(private readonly EligibilityService $eligibility)
    {
    }

    /**
     * Applications with everything the page and the Placement Report need, newest first.
     *
     * @return Collection<int, Application>
     */
    public function applications(StudentProfile $student): Collection
    {
        return $student->applications()
            ->with(['jobPosting.rounds', 'jobPosting.postable.company:id,name', 'jobPosting.placementCycle:id,name', 'roundResults', 'offer'])
            ->latest('applied_at')
            ->get();
    }

    /**
     * Each stage of the application's job profile with this student's result and attendance.
     *
     * @return list<array{round_id: int, name: string, is_final: bool, result: ?string, attendance: ?string, published: bool}>
     */
    public function stages(Application $application): array
    {
        return $application->jobPosting?->rounds->values()->map(function ($round) use ($application) {
            /** @var ApplicationRoundResult|null $row */
            $row = $application->roundResults->firstWhere('posting_round_id', $round->id);

            return [
                'round_id' => $round->id,
                'name' => $round->name,
                'is_final' => (bool) $round->is_final,
                'result' => $row?->result,
                'attendance' => $row?->attendance,
                'published' => (bool) $row?->isPublished(),
            ];
        })->all() ?? [];
    }

    /** Stage result as shown on the page and in the report ("Shortlisted", "On Hold", ...). */
    public static function resultLabel(?string $result, bool $isFinal): ?string
    {
        return match ($result) {
            null => null,
            'selected' => $isFinal ? 'Selected' : 'Shortlisted',
            'waitlisted' => 'On Hold',
            'rejected' => 'Not selected',
            default => 'Pending',
        };
    }

    /** Attendance as Yes / No / — (not marked). */
    public static function attendanceLabel(?string $attendance): string
    {
        return match ($attendance) {
            'yes' => 'Yes',
            'no' => 'No',
            default => '—',
        };
    }

    /**
     * The "Placements" section: one entry per cycle the student is enrolled in (or applied in), with the
     * Enrolled / Placed status (D80: placed = an offer other than ppo_offered in that cycle), that cycle's
     * applications and each stage's attendance.
     *
     * @return list<array<string, mixed>>
     */
    public function placements(StudentProfile $student): array
    {
        $student->loadMissing('cycleEnrollments.placementCycle:id,name,type,status,starts_on,ends_on');
        $applications = $this->applications($student);
        $offers = $student->offers()->with(['company:id,name', 'jobPosting.postable', 'placementCycle:id,name'])->get();

        $cycles = [];
        foreach ($student->cycleEnrollments as $enrollment) {
            if ($enrollment->placementCycle) {
                $cycles[$enrollment->placement_cycle_id] = ['cycle' => $enrollment->placementCycle, 'enrollment' => $enrollment];
            }
        }
        foreach ($applications as $application) {
            $cycle = $application->jobPosting?->placementCycle;
            if ($cycle && ! isset($cycles[$cycle->id])) {
                $cycles[$cycle->id] = ['cycle' => $cycle, 'enrollment' => null];
            }
        }

        $result = [];
        foreach ($cycles as $cycleId => ['cycle' => $cycle, 'enrollment' => $enrollment]) {
            $cycleOffers = $offers->where('placement_cycle_id', $cycleId)->values();
            $placing = $cycleOffers->where('offer_type', '!=', 'ppo_offered')->values();

            if ($placing->isNotEmpty()) {
                $statusLabel = 'Placed ('.$placing->map(fn (Offer $o) => $this->offerRole($o).' at '.($o->company?->name ?? 'company'))->implode(', ').')';
            } elseif (! $enrollment) {
                $statusLabel = 'Not enrolled';
            } else {
                $statusLabel = $enrollment->status === 'active' ? 'Enrolled' : ucfirst((string) $enrollment->status);
            }

            $result[] = [
                'cycle' => [
                    'id' => $cycle->id,
                    'name' => $cycle->name,
                    'type' => $cycle->type ?? null,
                    'status' => $cycle->status ?? null,
                ],
                'enrollment' => $enrollment ? [
                    'id' => $enrollment->id,
                    'status' => $enrollment->status,
                    'created_at' => $enrollment->created_at,
                ] : null,
                'placed' => $placing->isNotEmpty(),
                'status_label' => $statusLabel,
                'offers' => $cycleOffers->map(fn (Offer $o) => [
                    'id' => $o->id,
                    'offer_type' => $o->offer_type,
                    'label' => Offer::LABELS[$o->offer_type] ?? $o->offer_type,
                    'company_name' => $o->company?->name,
                    'role' => $this->offerRole($o),
                ])->all(),
                'applications' => $applications
                    ->filter(fn (Application $a) => $a->jobPosting?->placement_cycle_id === $cycleId)
                    ->values()
                    ->map(fn (Application $a) => [
                        'id' => $a->id,
                        'job_posting_id' => $a->job_posting_id,
                        'title' => $a->jobPosting?->title(),
                        'company_name' => $a->jobPosting?->company()?->name,
                        'posting_status' => $a->jobPosting?->status,
                        'status' => $a->status,
                        'applied_at' => $a->applied_at,
                        'offer_label' => $a->offer ? (Offer::LABELS[$a->offer->offer_type] ?? $a->offer->offer_type) : null,
                        'stages' => $this->stages($a),
                    ])->all(),
            ];
        }

        return $result;
    }

    /**
     * Eligibility of the student for every non-cancelled job profile in the cycles they are enrolled in.
     *
     * @return list<array{posting: JobPosting, eligible: bool, reasons: list<string>}>
     */
    public function eligibility(StudentProfile $student): array
    {
        $student->loadMissing(['cycleEnrollments', 'user']);
        $cycleIds = $student->cycleEnrollments->pluck('placement_cycle_id')->unique()->values();

        return JobPosting::query()
            ->with(['postable.company:id,name', 'placementCycle:id,name'])
            ->whereIn('placement_cycle_id', $cycleIds)
            ->where('status', '!=', 'cancelled')
            ->orderBy('placement_cycle_id')
            ->orderBy('id')
            ->get()
            ->map(fn (JobPosting $posting) => ['posting' => $posting] + $this->eligibility->check($student, $posting))
            ->all();
    }

    private function offerRole(Offer $offer): string
    {
        return $offer->jobPosting?->title() ?? (Offer::LABELS[$offer->offer_type] ?? $offer->offer_type);
    }
}
