<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\CampusEvent;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PostingRound;
use App\Services\EligibilityService;
use App\Support\PostingPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the student home page needs in one call (spec Q9 / M10.2).
 */
class StudentDashboardController extends Controller
{
    public function __construct(private readonly EligibilityService $eligibility)
    {
    }

    /** Applications still in the running: applied and never published as not selected. */
    public static function liveApplications(\App\Models\StudentProfile $student)
    {
        return $student->applications()
            ->where('status', 'applied')
            ->whereDoesntHave('roundResults', fn ($r) => $r->whereNotNull('published_at')->where('result', 'rejected'))
            ->get(['id', 'job_posting_id']);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $student = $request->user()->studentProfile?->load(['user', 'cycleEnrollments']);
        abort_if(! $student, 404, 'Student profile not found.');

        $resumes = $student->resumes()->get(['id', 'label', 'status']);
        $applications = $student->applications()
            ->where('status', 'applied')
            ->with(['jobPosting.postable.company:id,name,logo_path', 'jobPosting.placementCycle:id,name,type,status', 'jobPosting.rounds', 'offer'])
            ->latest('applied_at')
            ->get();

        // Nudge: eligible, still open, not applied — closing soonest first.
        $cycleIds = $student->cycleEnrollments->where('status', 'active')->pluck('placement_cycle_id');
        $appliedIds = $student->applications()->pluck('job_posting_id')->all();
        $nudges = JobPosting::query()
            ->with(['postable.company:id,name,logo_path', 'placementCycle:id,name,type,status'])
            ->whereIn('placement_cycle_id', $cycleIds)
            ->where('status', 'open')
            ->where('application_deadline', '>', now())
            ->whereNotIn('id', $appliedIds ?: [0])
            ->orderBy('application_deadline')
            ->get()
            ->filter(fn (JobPosting $p) => $p->acceptsApplications() && $this->eligibility->check($student, $p)['eligible'])
            ->map(fn (JobPosting $p) => PostingPresenter::card($p, $student->programme))
            ->values();

        $horizon = now()->addDays(14);
        $upcoming = collect()
            ->merge(CampusEvent::query()->whereNotNull('published_at')->whereBetween('starts_at', [now(), $horizon])->get()
                ->filter(fn (CampusEvent $e) => $e->isVisibleTo($student))
                ->map(fn (CampusEvent $e) => ['type' => 'event', 'at' => $e->starts_at, 'title' => $e->title, 'link' => '/student/events']))
            ->merge(JobPosting::query()->whereIn('placement_cycle_id', $cycleIds)->where('status', 'open')->whereBetween('application_deadline', [now(), $horizon])->get()
                ->filter(fn (JobPosting $p) => in_array($p->id, $appliedIds) || $this->eligibility->offersBranch($student, $p))
                ->map(fn (JobPosting $p) => ['type' => 'deadline', 'at' => $p->application_deadline, 'title' => 'Apply by: '.$p->title(), 'link' => "/student/postings/{$p->id}"]))
            ->merge(PostingRound::query()->whereIn('job_posting_id', $this->liveApplications($student)->pluck('job_posting_id'))->where('status', '!=', 'completed')->whereHas('jobPosting', fn ($p) => $p->whereIn('status', ['open', 'in_process']))->whereBetween('scheduled_at', [now(), $horizon])->with('jobPosting.postable')->get()
                ->map(fn (PostingRound $r) => ['type' => 'round', 'at' => $r->scheduled_at, 'title' => $r->name.' — '.$r->jobPosting->title(), 'link' => "/student/postings/{$r->job_posting_id}"]))
            ->sortBy('at')
            ->values()
            ->take(8);

        $offers = Offer::query()->with('company:id,name')->where('student_profile_id', $student->id)->latest('announced_at')->get();

        return response()->json([
            'student' => $student->only(['roll_no', 'full_name', 'programme', 'branch', 'graduating_batch', 'current_cgpa']),
            'resumes' => [
                'total' => $resumes->count(),
                'approved' => $resumes->where('status', 'approved')->count(),
                'pending' => $resumes->where('status', 'pending')->count(),
                'rejected' => $resumes->where('status', 'rejected')->count(),
            ],
            'unverified_applications' => $applications->where('used_unverified_resume', true)->count(),
            'applications' => $applications->take(5)->map(fn (Application $a) => PostingPresenter::application($a) + [
                'posting' => PostingPresenter::card($a->jobPosting, $student->programme),
                'trail' => PostingPresenter::trail($a->jobPosting, $a),
                'offer' => $a->offer ? $a->offer->only(['offer_type', 'ctc_annual', 'stipend_monthly', 'currency']) + ['label' => Offer::LABELS[$a->offer->offer_type] ?? $a->offer->offer_type] : null,
            ])->values(),
            // In progress = still in the running: posting live and not published as rejected in any round.
            'active_applications' => $applications->filter(fn (Application $a) => in_array($a->jobPosting->status, ['open', 'in_process'], true)
                && ! $a->roundResults()->whereNotNull('published_at')->where('result', 'rejected')->exists())->count(),
            'nudges' => $nudges->take(6)->values(),
            'nudges_total' => $nudges->count(),
            'upcoming' => $upcoming,
            'offers' => $offers->map(fn (Offer $o) => $o->only(['offer_type', 'ctc_annual', 'stipend_monthly', 'currency', 'announced_at']) + [
                'company' => $o->company?->name,
                'label' => Offer::LABELS[$o->offer_type] ?? $o->offer_type,
            ]),
            'active_blocks' => $student->placementBlocks()->where('active', true)->with(['offer:id,offer_type', 'placementCycle:id,name'])->get()
                ->map(fn ($b) => $b->message().($b->placementCycle ? " ({$b->placementCycle->name})" : '')),
        ]);
    }
}
