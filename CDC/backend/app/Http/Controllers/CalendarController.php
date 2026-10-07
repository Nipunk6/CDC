<?php

namespace App\Http\Controllers;

use App\Models\CampusEvent;
use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use App\Services\EligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Month calendar (spec Q9.3 / M8.2): events + application deadlines + scheduled round dates.
 * Admins see everything; students only what concerns them.
 */
class CalendarController extends Controller
{
    public function admin(Request $request): JsonResponse
    {
        [$from, $to] = $this->month($request);

        $events = CampusEvent::query()->with('company:id,name')->whereBetween('starts_at', [$from, $to])->get();
        $postings = JobPosting::query()->with(['postable.company:id,name'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('application_deadline', [$from, $to])
            ->get();
        $rounds = PostingRound::query()->with('jobPosting.postable.company:id,name')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$from, $to])
            ->whereHas('jobPosting', fn (Builder $p) => $p->where('status', '!=', 'cancelled'))
            ->get();

        // Date of Visit / Process (S6.3), compared as IST calendar days.
        $visits = JobPosting::query()->with(['postable.company:id,name'])
            ->where('status', '!=', 'cancelled')
            ->whereBetween('visit_date', [$from->timezone('Asia/Kolkata')->toDateString(), $to->timezone('Asia/Kolkata')->toDateString()])
            ->get();

        return response()->json(['items' => $this->items($events, $postings, $rounds, 'admin', $visits)]);
    }

    public function student(Request $request, EligibilityService $eligibility): JsonResponse
    {
        [$from, $to] = $this->month($request);
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        $events = CampusEvent::query()->with('company:id,name')
            ->whereNotNull('published_at')
            ->whereBetween('starts_at', [$from, $to])
            ->get()
            ->filter(fn (CampusEvent $e) => $e->isVisibleTo($student));

        $cycleIds = $student->cycleEnrollments()->where('status', 'active')->pluck('placement_cycle_id');
        $appliedPostingIds = StudentDashboardController::liveApplications($student)->pluck('job_posting_id');
        $postings = JobPosting::query()->with(['postable.company:id,name'])
            ->whereIn('placement_cycle_id', $cycleIds)
            ->where('status', '!=', 'cancelled')
            ->released()
            ->whereBetween('application_deadline', [$from, $to])
            ->get()
            // Drives that leave the student's branch out are not theirs to see (owner decision, QA T3.2).
            ->filter(fn (JobPosting $p) => $appliedPostingIds->contains($p->id) || $eligibility->offersBranch($student, $p));

        $rounds = PostingRound::query()->with('jobPosting.postable.company:id,name')
            ->whereIn('job_posting_id', $appliedPostingIds)
            ->whereHas('jobPosting', fn (Builder $p) => $p->whereIn('status', ['open', 'in_process']))
            ->where('status', '!=', 'completed')
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [$from, $to])
            ->get();

        $visits = JobPosting::query()->with(['postable.company:id,name'])
            ->whereIn('placement_cycle_id', $cycleIds)
            ->where('status', '!=', 'cancelled')
            ->released()
            ->whereBetween('visit_date', [$from->timezone('Asia/Kolkata')->toDateString(), $to->timezone('Asia/Kolkata')->toDateString()])
            ->get()
            ->filter(fn (JobPosting $p) => $appliedPostingIds->contains($p->id) || $eligibility->offersBranch($student, $p));

        return response()->json(['items' => $this->items($events, $postings, $rounds, 'student', $visits)]);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function month(Request $request): array
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        // Month boundaries in IST, where every user is.
        $start = CarbonImmutable::createFromFormat('Y-m-d', ($validated['month'] ?? now('Asia/Kolkata')->format('Y-m')).'-01', 'Asia/Kolkata')->startOfDay();

        return [$start->utc(), $start->endOfMonth()->utc()];
    }

    private function items(Collection $events, Collection $postings, Collection $rounds, string $audience, ?Collection $visits = null): array
    {
        $prefix = $audience === 'admin' ? '/admin' : '/student';

        $items = collect()
            ->merge($events->map(fn (CampusEvent $e) => [
                'type' => 'event',
                'at' => $e->starts_at,
                'title' => $e->title,
                'subtitle' => trim(($e->company?->name ? $e->company->name.' · ' : '').($e->venue ?? '')),
                'draft' => $e->published_at === null,
                'link' => $prefix.'/events',
            ]))
            ->merge($postings->map(fn (JobPosting $p) => [
                'type' => 'deadline',
                'at' => $p->application_deadline,
                'title' => 'Apply by: '.$p->title(),
                'subtitle' => $p->company()?->name ?? '',
                'draft' => false,
                'link' => $prefix.'/postings/'.$p->id,
            ]))
            ->merge($rounds->map(fn (PostingRound $r) => [
                'type' => 'round',
                'at' => $r->scheduled_at,
                'title' => $r->name.' — '.$r->jobPosting->title(),
                'subtitle' => trim(($r->jobPosting->company()?->name ?? '').($r->venue ? ' · '.$r->venue : '')),
                'draft' => false,
                'link' => $prefix.'/postings/'.$r->job_posting_id,
            ]))
            ->merge(($visits ?? collect())->map(fn (JobPosting $p) => [
                'type' => 'visit',
                // A date without a time: 10:00 IST so it lands on the right day in every view.
                'at' => \Carbon\Carbon::parse($p->visit_date->toDateString().' 10:00', 'Asia/Kolkata')->utc(),
                'title' => 'Visit: '.$p->title(),
                'subtitle' => $p->company()?->name ?? '',
                'draft' => false,
                'link' => $prefix.'/postings/'.$p->id,
            ]));

        return $items->sortBy('at')->values()->all();
    }
}
