<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\StudentProfile;
use App\Services\EligibilityService;
use App\Support\PostingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class StudentPostingController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private readonly EligibilityService $eligibility)
    {
    }

    /**
     * Every non-cancelled posting in the student's active cycles that is open to their branch, with their own
     * eligibility (spec Q3.2; branch rule: owner decision 2026-10-01).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:fulltime,internship'],
            'eligibility' => ['nullable', 'in:eligible,ineligible'],
            'applied' => ['nullable', 'in:yes,no'],
            'status' => ['nullable', 'in:open,closed'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $student = $this->student($request);

        $applications = $student->applications()->get()->keyBy('job_posting_id');

        $postings = $this->visibleQuery($student)
            ->with(['postable.company:id,name,logo_path,sector', 'placementCycle:id,name,type,status'])
            ->orderByDesc('floated_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (JobPosting $posting) => $this->shownTo($student, $posting, $applications->has($posting->id)));

        // Eligibility is computed per posting in PHP, so filtering and paging happen on the collection.
        $items = $postings->map(function (JobPosting $posting) use ($student, $applications) {
            return PostingPresenter::card($posting, $student->programme) + [
                'eligibility' => $this->eligibility->check($student, $posting),
                'application' => PostingPresenter::application($applications->get($posting->id)),
            ];
        })->filter(function (array $item) use ($validated) {
            if (! empty($validated['type']) && $item['type'] !== $validated['type']) {
                return false;
            }
            if (! empty($validated['eligibility']) && $item['eligibility']['eligible'] !== ($validated['eligibility'] === 'eligible')) {
                return false;
            }
            $applied = $item['application'] !== null && $item['application']['status'] === 'applied';
            if (! empty($validated['applied']) && $applied !== ($validated['applied'] === 'yes')) {
                return false;
            }
            if (! empty($validated['status']) && $item['accepts_applications'] !== ($validated['status'] === 'open')) {
                return false;
            }
            $search = strtolower(trim((string) ($validated['search'] ?? '')));
            if ($search !== '' && ! str_contains(strtolower($item['title'].' '.$item['company']['name']), $search)) {
                return false;
            }

            return true;
        })->values();

        $page = (int) ($validated['page'] ?? 1);
        $paginator = new LengthAwarePaginator($items->forPage($page, self::PER_PAGE)->values(), $items->count(), self::PER_PAGE, $page);

        return response()->json([
            'postings' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $student = $this->student($request);

        $application = $student->applications()->where('job_posting_id', $jobPosting->id)->first();

        if (! $this->visibleQuery($student)->whereKey($jobPosting->id)->exists() || ! $this->shownTo($student, $jobPosting, $application !== null)) {
            return response()->json(['message' => 'Posting not found.'], 404);
        }

        $jobPosting->load(['postable.company:id,name,logo_path,sector,website', 'placementCycle:id,name,type,status', 'rounds', 'questions']);

        return response()->json([
            'posting' => PostingPresenter::card($jobPosting, $student->programme) + [
                'form_type' => $jobPosting->formType(),
                'form_data' => PostingPresenter::formData($jobPosting),
                'company_website' => $jobPosting->company()?->website,
                'rounds' => $jobPosting->rounds->map->only(['id', 'name', 'round_type', 'sort_order', 'scheduled_at', 'status', 'is_final'])->values(),
                'questions' => $jobPosting->questions->map->only(['id', 'question', 'qtype', 'options', 'required'])->values(),
                'eligibility' => $this->eligibility->check($student, $jobPosting),
                'application' => PostingPresenter::application($application),
                'trail' => PostingPresenter::trail($jobPosting, $application),
            ],
        ]);
    }

    /** Postings floated into cycles the student is actively enrolled in (cancelled ones hidden). */
    private function visibleQuery(StudentProfile $student): Builder
    {
        $cycleIds = $student->cycleEnrollments()->where('status', 'active')->pluck('placement_cycle_id');

        return JobPosting::query()
            ->whereIn('placement_cycle_id', $cycleIds)
            ->where('status', '!=', 'cancelled');
    }

    /** A drive that leaves the student's branch out stays hidden — unless they already applied (branch rules can change later). */
    private function shownTo(StudentProfile $student, JobPosting $posting, bool $hasApplication): bool
    {
        return $hasApplication || $this->eligibility->offersBranch($student, $posting);
    }

    private function student(Request $request): StudentProfile
    {
        $student = $request->user()->studentProfile?->load(['user', 'cycleEnrollments']);
        abort_if(! $student, 404, 'Student profile not found.');

        return $student;
    }
}
