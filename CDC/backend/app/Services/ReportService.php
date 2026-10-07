<?php

namespace App\Services;

use App\Models\ApplicationRoundResult;
use App\Models\CycleEnrollment;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * Placement reports (Superset parity S8.5, "Important Links" of a placement): row sets for Excel downloads.
 * "Placed" follows D80: at least one offer other than `ppo_offered` in the placement; the denominator is every
 * student enrolled in it.
 */
class ReportService
{
    public const REPORTS = [
        'job_offers' => 'List : Job Offers',
        'students_placed' => 'List : Students Placed',
        'students_not_placed' => 'List : Students Not Placed',
        'placement_matrix' => 'Placement Matrix Report',
        'absentees' => 'Placement Absentees Report',
        'job_profiles' => 'Download Job Profiles List',
    ];

    private const NOT_PLACING = ['ppo_offered'];

    /**
     * @return array{title: string, headers: list<string>, rows: iterable<int, list<mixed>>}
     */
    public function build(PlacementCycle $cycle, string $report): array
    {
        return match ($report) {
            'job_offers' => $this->jobOffers($cycle),
            'students_placed' => $this->students($cycle, true),
            'students_not_placed' => $this->students($cycle, false),
            'placement_matrix' => $this->matrix($cycle),
            'absentees' => $this->absentees($cycle),
            'job_profiles' => $this->jobProfiles($cycle),
        };
    }

    private function jobOffers(PlacementCycle $cycle): array
    {
        $offers = Offer::query()
            ->with(['studentProfile', 'company:id,name', 'jobPosting.postable'])
            ->where('placement_cycle_id', $cycle->id)
            ->orderBy('announced_at')
            ->get();

        return [
            'title' => self::REPORTS['job_offers'],
            'headers' => ['S.No.', 'Roll No', 'Name', 'Programme', 'Branch', 'Company', 'Job Profile', 'Offer Type', 'CTC offered', 'CTC Currency', 'CTC Interval', 'Announced On (IST)'],
            'rows' => $offers->values()->map(fn (Offer $o, int $i) => [
                $i + 1, $o->studentProfile?->roll_no, $o->studentProfile?->full_name, $o->studentProfile?->programme, $o->studentProfile?->branch,
                $o->company?->name, $o->jobPosting?->title(), Offer::LABELS[$o->offer_type] ?? $o->offer_type,
                $o->ctc_annual ?? $o->stipend_monthly, $o->currency, $o->ctc_annual !== null ? 'YEAR' : ($o->stipend_monthly !== null ? 'MONTH' : null),
                $o->announced_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
            ]),
        ];
    }

    private function students(PlacementCycle $cycle, bool $placed): array
    {
        $enrollments = CycleEnrollment::query()->with('studentProfile')->where('placement_cycle_id', $cycle->id)->get();
        $offers = Offer::query()->with('company:id,name')->where('placement_cycle_id', $cycle->id)->get()->groupBy('student_profile_id');
        $placedIds = $offers->map(fn (Collection $group) => $group->reject(fn (Offer $o) => in_array($o->offer_type, self::NOT_PLACING, true))->isNotEmpty())->filter()->keys()->flip();

        $selected = $enrollments
            ->filter(fn (CycleEnrollment $e) => $e->studentProfile && $placedIds->has($e->student_profile_id) === $placed)
            ->sortBy(fn (CycleEnrollment $e) => $e->studentProfile->roll_no)
            ->values();

        $headers = ['S.No.', 'Roll No', 'Name', 'Programme', 'Branch', 'Passout Batch', 'CGPA', 'Email ID', 'Mobile No.', 'Enrolment'];
        if ($placed) {
            $headers = array_merge($headers, ['Offers', 'Best CTC (annual, INR)']);
        } else {
            $headers[] = 'PPO offered (not accepted)';
        }

        return [
            'title' => self::REPORTS[$placed ? 'students_placed' : 'students_not_placed'].sprintf(' (%d of %d enrolled)', $selected->count(), $enrollments->count()),
            'headers' => $headers,
            'rows' => $selected->map(function (CycleEnrollment $e, int $i) use ($offers, $placed) {
                $s = $e->studentProfile;
                $mine = $offers->get($s->id, collect());
                $row = [
                    $i + 1, $s->roll_no, $s->full_name, $s->programme, $s->branch, $s->graduating_batch,
                    $s->current_cgpa === null ? null : (float) $s->current_cgpa, $s->institute_email, $s->phone,
                    $e->status === 'active' ? 'Enrolled' : ucfirst($e->status),
                ];
                if ($placed) {
                    $row[] = $mine->map(fn (Offer $o) => ($o->company?->name ?? '').' ('.(Offer::LABELS[$o->offer_type] ?? $o->offer_type).')')->implode('; ');
                    $row[] = $mine->where('currency', 'INR')->reject(fn (Offer $o) => in_array($o->offer_type, self::NOT_PLACING, true))->max('ctc_annual');
                } else {
                    $row[] = $mine->contains('offer_type', 'ppo_offered') ? 'Yes' : '';
                }

                return $row;
            }),
        ];
    }

    /** Branch × company: how many offers each company made to each programme/branch. */
    private function matrix(PlacementCycle $cycle): array
    {
        // Placing offers only (D80): a PPO offered but not accepted is not counted (fix L13).
        $offers = Offer::query()->with(['studentProfile:id,programme,branch', 'company:id,name'])->where('placement_cycle_id', $cycle->id)
            ->whereNotIn('offer_type', self::NOT_PLACING)->get();
        $companies = $offers->map(fn (Offer $o) => $o->company?->name ?? '—')->unique()->sort()->values();
        $enrolled = StudentProfile::query()
            ->whereIn('id', CycleEnrollment::query()->where('placement_cycle_id', $cycle->id)->select('student_profile_id'))
            ->get(['programme', 'branch'])
            ->groupBy(fn ($s) => $s->programme.'|'.$s->branch)
            ->map->count();

        $byBranch = $offers->groupBy(fn (Offer $o) => ($o->studentProfile?->programme ?? '—').'|'.($o->studentProfile?->branch ?? '—'));
        $keys = $enrolled->keys()->merge($byBranch->keys())->unique()->sort()->values();

        return [
            'title' => self::REPORTS['placement_matrix'].' (placing offers; PPO offered but not accepted excluded)',
            'headers' => array_merge(['Programme', 'Branch', 'Enrolled'], $companies->all(), ['Total Offers']),
            'rows' => $keys->map(function (string $key) use ($byBranch, $companies, $enrolled) {
                [$programme, $branch] = explode('|', $key, 2);
                $group = $byBranch->get($key, collect());
                $counts = $companies->map(fn ($c) => $group->filter(fn (Offer $o) => ($o->company?->name ?? '—') === $c)->count() ?: null)->all();

                return array_merge([$programme, $branch, (int) ($enrolled[$key] ?? 0)], $counts, [$group->count()]);
            }),
        ];
    }

    /** Every attendance marked "no" in the placement's stages. */
    private function absentees(PlacementCycle $cycle): array
    {
        $rows = ApplicationRoundResult::query()
            ->with(['application.studentProfile', 'application.jobPosting.postable', 'postingRound'])
            ->where('attendance', 'no')
            ->whereHas('application.jobPosting', fn ($q) => $q->where('placement_cycle_id', $cycle->id))
            ->get()
            ->sortBy(fn ($r) => $r->application?->studentProfile?->roll_no)
            ->values();

        return [
            'title' => self::REPORTS['absentees'],
            'headers' => ['S.No.', 'Roll No', 'Name', 'Programme', 'Branch', 'Company', 'Job Profile', 'Stage', 'Stage Date (IST)'],
            'rows' => $rows->map(fn ($r, int $i) => [
                $i + 1, $r->application?->studentProfile?->roll_no, $r->application?->studentProfile?->full_name,
                $r->application?->studentProfile?->programme, $r->application?->studentProfile?->branch,
                $r->application?->jobPosting?->company()?->name, $r->application?->jobPosting?->title(), $r->postingRound?->name,
                $r->postingRound?->scheduled_at?->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
            ]),
        ];
    }

    private function jobProfiles(PlacementCycle $cycle): array
    {
        $postings = JobPosting::query()
            ->with(['postable.company:id,name'])
            ->withCount(['applications as applied_count' => fn ($q) => $q->where('status', 'applied')])
            ->where('placement_cycle_id', $cycle->id)
            ->orderBy('application_deadline')
            ->get();
        $offers = Offer::query()->where('placement_cycle_id', $cycle->id)->selectRaw('job_posting_id, count(*) as total')->groupBy('job_posting_id')->pluck('total', 'job_posting_id');

        return [
            'title' => 'Job Profiles List',
            'headers' => ['S.No.', 'Company', 'Profile', 'Type', 'Offer Category', 'Date of Visit', 'Deadline (IST)', 'Status', 'Applicants', 'Offers'],
            'rows' => $postings->values()->map(fn (JobPosting $p, int $i) => [
                $i + 1, $p->company()?->name, $p->title(), $p->postingType() === 'internship' ? 'Internship' : 'Full Time',
                Offer::LABELS[$p->offerType()] ?? $p->offerType(), $p->visit_date?->toDateString(),
                $p->application_deadline?->timezone('Asia/Kolkata')->format('Y-m-d H:i'),
                match ($p->status) { 'open' => 'Accepting Applications', 'in_process' => 'In Process', default => ucfirst($p->status) },
                (int) $p->applied_count, (int) ($offers[$p->id] ?? 0),
            ]),
        ];
    }
}
