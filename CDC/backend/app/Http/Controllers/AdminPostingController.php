<?php

namespace App\Http\Controllers;

use App\Jobs\SendPostingFloatedMails;
use App\Models\Application;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementCycle;
use App\Models\PostingQuestion;
use App\Models\PostingRound;
use App\Services\AuditService;
use App\Services\EligibilityService;
use App\Services\MailDispatchService;
use App\Services\PostingEligibilityService;
use App\Support\Ist;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPostingController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly EligibilityService $eligibility,
        private readonly MailDispatchService $mail
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cycle_id' => ['nullable', 'integer', 'exists:placement_cycles,id'],
            'status' => ['nullable', 'in:open,in_process,completed,cancelled'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = JobPosting::query()
            ->with(['postable.company:id,name,logo_path', 'placementCycle:id,name,type,status'])
            ->withCount([
                'applications as applied_count' => fn ($q) => $q->where('status', 'applied'),
                'applications as withdrawn_count' => fn ($q) => $q->where('status', 'withdrawn'),
            ])
            ->orderByDesc('floated_at')
            ->orderByDesc('id');

        if (! empty($validated['cycle_id'])) {
            $query->where('placement_cycle_id', $validated['cycle_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        $query->with('rounds:id,job_posting_id,status');

        $postings = $query->get();
        // Search by company or profile (S6.13); titles live in form_data, so this filters the loaded list.
        $term = mb_strtolower(trim((string) ($validated['search'] ?? '')));
        if ($term !== '') {
            $postings = $postings->filter(fn (JobPosting $p) => str_contains(mb_strtolower(($p->company()?->name ?? '').' '.$p->title()), $term));
        }

        return response()->json([
            'postings' => $postings->values()->map(fn (JobPosting $p) => $this->summary($p)),
        ]);
    }

    /**
     * Which posting (if any) a form was floated as — drives the Float button on the JNF/INF detail pages.
     */
    public function forForm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_type' => ['required', 'in:jnf,inf'],
            'form_id' => ['required', 'integer'],
        ]);

        $posting = JobPosting::query()
            ->where('postable_type', $validated['form_type'] === 'inf' ? Inf::class : Jnf::class)
            ->where('postable_id', $validated['form_id'])
            ->with('placementCycle:id,name,type,status')
            ->first();

        return response()->json([
            'posting' => $posting ? [
                'id' => $posting->id,
                'status' => $posting->status,
                'application_deadline' => $posting->application_deadline,
                'placement_cycle' => $posting->placementCycle,
                'offer_label' => Offer::LABELS[$posting->offerType()] ?? $posting->offerType(),
                'is_scheduled' => $posting->isScheduled(),
                'scheduled_open_at' => $posting->scheduled_open_at,
                'deadline_passed' => $posting->deadlinePassed(),
            ] : null,
        ]);
    }

    public function previewEligibility(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_type' => ['required', 'in:jnf,inf'],
            'form_id' => ['required', 'integer'],
            'cycle_id' => ['required', 'integer', 'exists:placement_cycles,id'],
            'allowed_student_categories' => ['nullable', 'array', 'max:50'],
            'allowed_student_categories.*' => ['integer'],
        ]);

        $form = $this->findForm($validated['form_type'], (int) $validated['form_id']);
        if (! $form) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        $draft = new JobPosting([
            'placement_cycle_id' => (int) $validated['cycle_id'],
            'eligibility_snapshot' => $this->snapshot($form) + array_filter([
                'allowedStudentCategories' => \App\Services\EligibilityService::categoryIds(['allowedStudentCategories' => $validated['allowed_student_categories'] ?? []]),
            ]),
        ]);
        $draft->setRelation('postable', $form);
        $draft->postable_type = $form::class;

        $enrolled = PlacementCycle::query()->findOrFail($validated['cycle_id'])->enrollments()->where('status', 'active')->count();

        $formRounds = is_array($form->form_data['selectionRounds'] ?? null) ? $form->form_data['selectionRounds'] : [];

        return response()->json([
            'eligible_count' => $this->eligibility->eligibleStudentsQuery($draft)->count(),
            'enrolled_count' => $enrolled,
            // "Steps to publish" (S6.10): the stages the job profile will start with.
            'stages' => collect($formRounds)->filter(fn ($r) => is_array($r) && ($r['enabled'] ?? false))->count(),
        ]);
    }

    /**
     * Float an accepted JNF/INF into a cycle (spec M5.3).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_type' => ['required', 'in:jnf,inf'],
            'form_id' => ['required', 'integer'],
            'placement_cycle_id' => ['required', 'integer', 'exists:placement_cycles,id'],
            'application_deadline' => ['required', 'bail', 'date', $this->futureDeadline()],
            'share_contact_details' => ['nullable', 'boolean'],
            'offer_type' => ['nullable', 'string', 'in:'.implode(',', JobPosting::OFFER_CATEGORIES[$request->input('form_type') === 'inf' ? 'inf' : 'jnf'])],
            // "Schedule For Later" (S6.2): open applications at this IST time instead of now.
            'scheduled_open_at' => ['nullable', 'bail', 'date', $this->futureTime('The opening time must be in the future.')],
            'visit_date' => ['nullable', 'date_format:Y-m-d'],
            // Allowed Student Categories (S8.4): at least one is required to be eligible; empty = no restriction.
            'allowed_student_categories' => ['nullable', 'array', 'max:50'],
            'allowed_student_categories.*' => ['integer', 'exists:student_categories,id'],
        ] + $this->questionRules(), [
            'offer_type.in' => $request->input('form_type') === 'inf'
                ? 'An INF is opened for applications as "Internship" or "Intern + performance-based PPO".'
                : 'A JNF is opened for applications as "Full-Time" or "Intern + Full-Time".',
        ]);

        $form = $this->findForm($validated['form_type'], (int) $validated['form_id']);
        if (! $form) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        $scheduledAt = Ist::parseOrNull($validated['scheduled_open_at'] ?? null);
        if ($scheduledAt && Ist::parse($validated['application_deadline'])->lte($scheduledAt)) {
            return response()->json(['message' => 'The application deadline must be after the opening time.', 'errors' => ['application_deadline' => ['The application deadline must be after the opening time.']]], 422);
        }

        if ((string) $form->status !== 'accepted') {
            return response()->json(['message' => 'Only accepted forms can be opened for applications.'], 422);
        }

        if ($form->isFloated()) {
            return response()->json(['message' => 'This form has already been opened for applications.'], 422);
        }

        $cycle = PlacementCycle::query()->findOrFail($validated['placement_cycle_id']);

        if (! $cycle->isOpen()) {
            return response()->json(['message' => 'The selected placement is closed.'], 422);
        }

        // A Draft placement (S8.3) is hidden from students, so nothing can be opened for applications into it yet.
        if ($cycle->is_draft) {
            return response()->json(['message' => 'The selected placement is a draft. Publish the placement before opening job profiles for applications in it.'], 422);
        }

        $expected = $validated['form_type'] === 'inf' ? 'internship' : 'fulltime';
        if ($cycle->type !== $expected) {
            return response()->json([
                'message' => $expected === 'internship'
                    ? 'An INF can only be opened for applications in an internship placement.'
                    : 'A JNF can only be opened for applications in a full-time placement.',
            ], 422);
        }

        try {
            $posting = DB::transaction(function () use ($form, $cycle, $validated, $request, $scheduledAt): JobPosting {
                $posting = JobPosting::create([
                    'postable_type' => $form::class,
                    'postable_id' => $form->id,
                    'placement_cycle_id' => $cycle->id,
                    'application_deadline' => Ist::parse($validated['application_deadline']),
                    'status' => 'open',
                    'share_contact_details' => (bool) ($validated['share_contact_details'] ?? false),
                    'offer_type' => $validated['offer_type'] ?? JobPosting::OFFER_CATEGORIES[$validated['form_type']][0],
                    'floated_by' => $request->user()->id,
                    'floated_at' => $scheduledAt ?? now(),
                    'eligibility_snapshot' => $this->snapshot($form) + array_filter([
                        'allowedStudentCategories' => \App\Services\EligibilityService::categoryIds(['allowedStudentCategories' => $validated['allowed_student_categories'] ?? []]),
                    ]),
                    'scheduled_open_at' => $scheduledAt,
                    'visit_date' => $validated['visit_date'] ?? null,
                ]);

                $this->copyRounds($posting, $form);
                $this->syncQuestions($posting, $validated['questions'] ?? []);

                return $posting;
            });
        } catch (QueryException $exception) {
            // unique(postable_type, postable_id): someone floated it a moment ago.
            return response()->json(['message' => 'This form has already been opened for applications.'], 422);
        }

        $posting->load(['rounds', 'questions', 'postable.company:id,name,logo_path', 'placementCycle:id,name,type,status']);

        $this->audit->log($request, 'posting.float', $posting, null, [
            'form' => $validated['form_type'].'#'.$form->id,
            'placement_cycle_id' => $cycle->id,
            'application_deadline' => $posting->application_deadline->toIso8601String(),
            'share_contact_details' => $posting->share_contact_details,
            'offer_type' => $posting->offerType(),
            'rounds' => $posting->rounds->pluck('name')->all(),
            'questions' => $posting->questions->count(),
            'allowed_student_categories' => \App\Services\EligibilityService::categoryIds($posting->eligibility_snapshot ?? []),
            'scheduled_open_at' => $posting->scheduled_open_at?->toIso8601String(),
            'visit_date' => $posting->visit_date?->toDateString(),
        ]);

        if ($posting->isScheduled()) {
            return response()->json([
                'message' => 'Job profile scheduled. It opens for applications on '.$posting->scheduled_open_at->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST, and eligible students are emailed then.',
                'posting' => $this->detail($posting),
            ], 201);
        }

        $this->dispatchFloatMails($posting);

        return response()->json([
            'message' => 'Job profile opened for applications. Eligible students are being notified.',
            'posting' => $this->detail($posting),
        ], 201);
    }

    public function show(JobPosting $jobPosting): JsonResponse
    {
        $jobPosting->load(['rounds', 'questions', 'postable.company:id,name,logo_path', 'placementCycle:id,name,type,status', 'floatedBy:id,name']);

        return response()->json(['posting' => $this->detail($jobPosting)]);
    }

    /**
     * Deadline, questions (until the deadline) and the contact-sharing toggle.
     */
    public function update(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate([
            'application_deadline' => ['sometimes', 'bail', 'date', $this->futureDeadline()],
            'share_contact_details' => ['sometimes', 'boolean'],
            'offer_type' => ['sometimes', 'string', 'in:'.implode(',', JobPosting::OFFER_CATEGORIES[$jobPosting->formType()])],
            'visit_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'scheduled_open_at' => ['sometimes', 'bail', 'date', $this->futureTime('The opening time must be in the future.')],
        ] + $this->questionRules());

        if (array_key_exists('scheduled_open_at', $validated)) {
            if (! $jobPosting->isScheduled()) {
                return response()->json(['message' => 'This job profile is already open, so its opening time cannot be changed.'], 422);
            }
            $deadline = array_key_exists('application_deadline', $validated) ? Ist::parse($validated['application_deadline']) : $jobPosting->application_deadline;
            if ($deadline->lte(Ist::parse($validated['scheduled_open_at']))) {
                return response()->json(['message' => 'The application deadline must be after the opening time.'], 422);
            }
        }

        if (in_array($jobPosting->status, ['completed', 'cancelled'], true)) {
            return response()->json(['message' => 'This job profile is '.$jobPosting->status.' and can no longer be edited.'], 422);
        }

        if (array_key_exists('questions', $validated) && $jobPosting->deadlinePassed()) {
            return response()->json(['message' => 'Questions cannot be changed after the application deadline.'], 422);
        }

        $before = $this->auditState($jobPosting);

        DB::transaction(function () use ($jobPosting, $validated): void {
            if (array_key_exists('application_deadline', $validated)) {
                $jobPosting->application_deadline = Ist::parse($validated['application_deadline']);
            }
            if (array_key_exists('share_contact_details', $validated)) {
                $jobPosting->share_contact_details = (bool) $validated['share_contact_details'];
            }
            if (array_key_exists('offer_type', $validated)) {
                $jobPosting->offer_type = $validated['offer_type'];
            }
            if (array_key_exists('visit_date', $validated)) {
                $jobPosting->visit_date = $validated['visit_date'];
            }
            if (array_key_exists('scheduled_open_at', $validated)) {
                $jobPosting->scheduled_open_at = Ist::parse($validated['scheduled_open_at']);
                $jobPosting->floated_at = $jobPosting->scheduled_open_at;
            }
            $jobPosting->save();

            if (array_key_exists('questions', $validated)) {
                $this->syncQuestions($jobPosting, $validated['questions'] ?? []);
            }
        });

        $jobPosting->refresh();
        $this->audit->log($request, 'posting.update', $jobPosting, $before, $this->auditState($jobPosting));

        return response()->json([
            'message' => 'Job profile updated.',
            'posting' => $this->detail($jobPosting->load(['rounds', 'questions', 'postable.company:id,name,logo_path', 'placementCycle:id,name,type,status', 'floatedBy:id,name'])),
        ]);
    }

    /**
     * What an eligibility change would do (D103): counts of newly / no-longer eligible students and the current
     * applicants who would no longer be eligible. Criteria come as `?criteria=<json>` (same keys as the PATCH) so a
     * large branch matrix still fits in the URL; plain query keys work too. Writes nothing.
     */
    public function previewEligibilityChange(Request $request, JobPosting $jobPosting, PostingEligibilityService $editor): JsonResponse
    {
        if ($refusal = $editor->refusal($jobPosting)) {
            return response()->json(['message' => $refusal], 422);
        }

        $input = $request->query();
        if ($request->filled('criteria')) {
            $input = json_decode((string) $request->query('criteria'), true);
            if (! is_array($input)) {
                return response()->json(['message' => 'The proposed criteria could not be read.', 'errors' => ['criteria' => ['Send the criteria as a JSON object.']]], 422);
            }
        }

        $jobPosting->load('postable', 'placementCycle:id,name,type,status');
        $criteria = $editor->proposed($jobPosting, $input);

        return response()->json(['preview' => $editor->preview($jobPosting, $criteria)]);
    }

    /**
     * Change a floated drive's eligibility (D103). Updates eligibility_snapshot and the form's form_data together,
     * keeps every existing application, and by default mails E2 to students who became eligible and were never told.
     */
    public function updateEligibility(Request $request, JobPosting $jobPosting, PostingEligibilityService $editor): JsonResponse
    {
        if ($refusal = $editor->refusal($jobPosting)) {
            return response()->json(['message' => $refusal], 422);
        }

        $request->validate([
            'notify_newly_eligible' => ['sometimes', 'nullable', 'boolean'],
            // Reopen applications (or move the deadline) so everyone eligible under the new criteria can apply.
            'applications_open_until' => ['sometimes', 'nullable', 'bail', 'date', $this->futureDeadline()],
        ]);
        $jobPosting->load('postable', 'placementCycle:id,name,type,status');
        $criteria = $editor->proposed($jobPosting, $request->all());
        $notify = $request->has('notify_newly_eligible') ? $request->boolean('notify_newly_eligible') : true;

        $openUntil = $request->filled('applications_open_until') ? Ist::parse((string) $request->input('applications_open_until')) : null;
        if ($openUntil && ($refusal = $editor->reopenRefusal($jobPosting))) {
            return response()->json(['message' => $refusal], 422);
        }

        $result = $editor->apply($jobPosting, $criteria, $request->user(), $request->ip(), $notify, 'posting', $openUntil);
        $jobPosting->refresh();

        $parts = [];
        if ($result['changed']) {
            $parts[] = sprintf('Eligibility updated. %d newly eligible, %d no longer eligible.', $result['newly_eligible'], $result['no_longer_eligible']);
        }
        if ($result['reopened']) {
            $parts[] = 'Applications are open until '.$jobPosting->application_deadline->timezone('Asia/Kolkata')->format('d M Y, h:i A').' IST.';
        }
        if ($result['notified'] > 0) {
            $parts[] = sprintf('%d newly eligible %s being emailed.', $result['notified'], $result['notified'] === 1 ? 'student is' : 'students are');
        }
        $message = $parts === [] ? 'Nothing changed: these are already the job profile\'s criteria.' : implode(' ', $parts);

        return response()->json([
            'message' => $message,
            'result' => $result,
            'posting' => $this->detail($jobPosting->load(['rounds', 'questions', 'postable.company:id,name,logo_path', 'placementCycle:id,name,type,status', 'floatedBy:id,name'])),
        ]);
    }

    /**
     * Stop taking applications and move the drive into its selection process.
     */
    public function close(Request $request, JobPosting $jobPosting): JsonResponse
    {
        return $this->transition($request, $jobPosting, 'in_process', ['open'], 'posting.close', 'Applications closed. The job profile is now in process.');
    }

    /**
     * Open a scheduled job profile right away (S6.2): clears the schedule and sends E2 now.
     */
    public function openNow(Request $request, JobPosting $jobPosting): JsonResponse
    {
        if (! $jobPosting->isScheduled()) {
            return response()->json(['message' => 'This job profile is not scheduled to open later.'], 422);
        }
        if ($jobPosting->status !== 'open') {
            return response()->json(['message' => 'Only a job profile waiting to open can be opened now.'], 422);
        }

        $before = ['scheduled_open_at' => $jobPosting->scheduled_open_at->toIso8601String()];
        $jobPosting->update(['scheduled_open_at' => null, 'floated_at' => now()]);
        $this->audit->log($request, 'posting.open_now', $jobPosting, $before, ['scheduled_open_at' => null]);
        $this->dispatchFloatMails($jobPosting);

        return response()->json([
            'message' => 'Job profile opened for applications. Eligible students are being notified.',
            'posting' => $this->detail($jobPosting->load(['rounds', 'questions', 'postable.company:id,name,logo_path', 'placementCycle:id,name,type,status', 'floatedBy:id,name'])),
        ]);
    }

    public function cancel(Request $request, JobPosting $jobPosting): JsonResponse
    {
        return $this->transition($request, $jobPosting, 'cancelled', ['open', 'in_process'], 'posting.cancel', 'Job profile cancelled.');
    }

    public function reopen(Request $request, JobPosting $jobPosting): JsonResponse
    {
        if ($this->hasAnyResults($jobPosting)) {
            return response()->json(['message' => 'Results have already been entered for this job profile, so it cannot be reopened.'], 422);
        }

        if ($jobPosting->deadlinePassed()) {
            return response()->json(['message' => 'Extend the application deadline before reopening the job profile.'], 422);
        }

        return $this->transition($request, $jobPosting, 'open', ['in_process'], 'posting.reopen', 'Job profile reopened for applications.');
    }

    public function export(Request $request, JobPosting $jobPosting, \App\Services\ExportService $exports, \App\Services\TemplateExports $templates): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate(['template' => ['nullable', 'integer']]);
        $template = \App\Services\TemplateExports::find($request->query('template'));
        $this->audit->log($request, 'posting.export', $jobPosting, null, ['applications' => $jobPosting->applications()->count(), 'template_id' => $template?->id]);

        // "Excel - Custom Template" (S3) or today's Default Template.
        return $template ? $templates->applicants($jobPosting, $template) : $exports->applicantsWorkbook($jobPosting, 'admin');
    }

    /**
     * "Download Eligible List" (Superset parity S3.6, admin only): every currently eligible student with Applied /
     * Not applied, in the default layout or a custom template.
     */
    public function exportEligible(Request $request, JobPosting $jobPosting, \App\Services\TemplateExports $templates): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $request->validate(['template' => ['nullable', 'integer']]);
        $template = \App\Services\TemplateExports::find($request->query('template'));
        $this->audit->log($request, 'posting.eligible_export', $jobPosting, null, ['template_id' => $template?->id]);

        return $templates->eligible($jobPosting, $template);
    }

    /**
     * Applicants of one posting (admin view — includes every flag).
     */
    public function applications(JobPosting $jobPosting): JsonResponse
    {
        $applications = $jobPosting->applications()
            ->with([
                'studentProfile:id,roll_no,full_name,programme,branch,graduating_batch,current_cgpa,ongoing_backlogs,total_backlogs,gender',
                'resume:id,label,status,slot',
            ])
            ->orderBy('applied_at')
            ->get();

        $outIds = \App\Models\ApplicationRoundResult::query()
            ->whereIn('application_id', $applications->pluck('id'))
            ->whereNotNull('published_at')
            ->where('result', 'rejected')
            ->pluck('application_id')
            ->flip();

        return response()->json([
            'applications' => $applications->map(fn (Application $a) => $a->toArray() + [
                'resume_url' => $a->resume?->previewUrl(),
                'out_of_process' => $outIds->has($a->id),
            ]),
        ]);
    }

    /**
     * "Eligible – Applied / Not applied" (req 20, QA F-009): every student currently eligible for the posting and
     * whether they applied. ?status=applied|not_applied narrows the list; ?search matches roll number or name.
     */
    public function eligible(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:all,applied,not_applied'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $applied = Application::query()->where('job_posting_id', $jobPosting->id)->where('status', 'applied')->select('student_profile_id');
        $query = $this->eligibility->eligibleStudentsQuery($jobPosting);
        $eligible = (clone $query)->count();
        $appliedCount = (clone $query)->whereIn('id', $applied)->count();

        match ($validated['status'] ?? 'all') {
            'applied' => $query->whereIn('id', $applied),
            'not_applied' => $query->whereNotIn('id', $applied),
            default => null,
        };
        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            \App\Support\Like::whereContains($query, ['roll_no', 'full_name'], $search); // literal % and _ (fix L28)
        }

        $page = $query->orderBy('roll_no')->paginate(50, ['id', 'roll_no', 'full_name', 'programme', 'branch', 'current_cgpa', 'institute_email']);
        $appliedIds = (clone $applied)->whereIn('student_profile_id', collect($page->items())->pluck('id'))->pluck('student_profile_id')->flip();

        return response()->json([
            'students' => collect($page->items())->map(fn ($student) => $student->only(['id', 'roll_no', 'full_name', 'programme', 'branch', 'current_cgpa', 'institute_email']) + [
                'applied' => $appliedIds->has($student->id),
            ]),
            'counts' => ['eligible' => $eligible, 'applied' => $appliedCount, 'not_applied' => $eligible - $appliedCount],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function storeRound(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate($this->roundRules());

        $round = DB::transaction(function () use ($jobPosting, $validated): PostingRound {
            $round = $jobPosting->rounds()->create([
                'name' => $validated['name'],
                'round_type' => $validated['round_type'],
                'scheduled_at' => Ist::parseOrNull($validated['scheduled_at'] ?? null),
                'venue' => $validated['venue'] ?? null,
                'sort_order' => ((int) $jobPosting->rounds()->max('sort_order')) + 1,
                'status' => 'pending',
                'is_final' => false,
            ]);

            // The final round is always the last one (D84): a round added at the end becomes the final round.
            $this->makeFinal($jobPosting, $round);

            return $round;
        });

        $this->audit->log($request, 'round.create', $jobPosting, null, $round->fresh()->only(['id', 'name', 'round_type', 'sort_order', 'scheduled_at', 'venue', 'is_final']));

        return response()->json(['message' => 'Stage added.', 'rounds' => $jobPosting->rounds()->get()], 201);
    }

    public function updateRound(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);

        $validated = $request->validate(array_map(
            fn (array $rules) => array_merge(['sometimes'], array_diff($rules, ['required'])),
            $this->roundRules()
        ) + ['status' => ['sometimes', 'in:pending,ongoing,completed']]);

        $before = $postingRound->only(['name', 'round_type', 'scheduled_at', 'venue', 'status', 'is_final']);

        DB::transaction(function () use ($jobPosting, $postingRound, $validated): void {
            if (array_key_exists('scheduled_at', $validated)) {
                $validated['scheduled_at'] = Ist::parseOrNull($validated['scheduled_at']);
            }
            $postingRound->fill(array_intersect_key($validated, array_flip(['name', 'round_type', 'scheduled_at', 'venue', 'status'])));
            $postingRound->save();

            if (filter_var($validated['is_final'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                abort_if($jobPosting->rounds()->get()->last()?->id !== $postingRound->id, 422, 'Only the last stage can be the final stage. Reorder the stages first.');
                $this->makeFinal($jobPosting, $postingRound);
            }
        });

        $this->audit->log($request, 'round.update', $postingRound, $before, $postingRound->fresh()->only(['name', 'round_type', 'scheduled_at', 'venue', 'status', 'is_final']));

        return response()->json(['message' => 'Stage updated.', 'rounds' => $jobPosting->rounds()->get()]);
    }

    public function destroyRound(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->assertRoundOf($jobPosting, $postingRound);

        if ($this->roundHasResults($postingRound)) {
            return response()->json(['message' => 'This stage already has results and cannot be removed.'], 422);
        }

        if ($postingRound->proposals()->where('status', 'pending')->exists()) {
            return response()->json(['message' => 'This stage has pending company proposals. Decide them first.'], 422);
        }

        if ($jobPosting->rounds()->count() <= 1) {
            return response()->json(['message' => 'A job profile needs at least one stage.'], 422);
        }

        $before = $postingRound->only(['id', 'name', 'round_type', 'sort_order', 'is_final']);

        DB::transaction(function () use ($jobPosting, $postingRound): void {
            $wasFinal = $postingRound->is_final;
            $postingRound->delete();

            if ($wasFinal) {
                $this->makeFinal($jobPosting, $jobPosting->rounds()->get()->last());
            }
        });

        $this->audit->log($request, 'round.delete', $jobPosting, $before, null);

        return response()->json(['message' => 'Stage removed.', 'rounds' => $jobPosting->rounds()->get()]);
    }

    public function reorderRounds(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate([
            'ordered_round_ids' => ['required', 'array', 'min:1'],
            'ordered_round_ids.*' => ['integer', 'distinct'],
        ]);

        if ($this->hasAnyResults($jobPosting, publishedOnly: true)) {
            return response()->json(['message' => 'Stages cannot be reordered after results have been published.'], 422);
        }

        $ids = $jobPosting->rounds()->pluck('id')->all();
        $ordered = array_map('intval', $validated['ordered_round_ids']);

        if (count($ordered) !== count($ids) || array_diff($ids, $ordered) !== []) {
            return response()->json(['message' => 'Send every stage of this job profile exactly once.'], 422);
        }

        $before = $ids;

        DB::transaction(function () use ($ordered, $jobPosting): void {
            foreach ($ordered as $index => $id) {
                PostingRound::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
            // Keep the final flag on the (new) last round.
            $this->makeFinal($jobPosting, $jobPosting->rounds()->get()->last());
        });

        $this->audit->log($request, 'round.reorder', $jobPosting, ['order' => $before], ['order' => $ordered]);

        return response()->json(['message' => 'Stages reordered.', 'rounds' => $jobPosting->rounds()->get()]);
    }

    // ---------------------------------------------------------------------------------------------

    private function transition(Request $request, JobPosting $posting, string $to, array $from, string $action, string $message): JsonResponse
    {
        if (! in_array($posting->status, $from, true)) {
            return response()->json(['message' => sprintf('A %s job profile cannot be moved to %s.', str_replace('_', ' ', $posting->status), str_replace('_', ' ', $to))], 422);
        }

        $before = ['status' => $posting->status];
        $posting->update(['status' => $to]);
        $this->audit->log($request, $action, $posting, $before, ['status' => $to]);

        return response()->json([
            'message' => $message,
            'posting' => $this->detail($posting->load(['rounds', 'questions', 'postable.company:id,name,logo_path', 'placementCycle:id,name,type,status', 'floatedBy:id,name'])),
        ]);
    }

    private function dispatchFloatMails(JobPosting $posting): void
    {
        if ($this->mail->mode() === 'sync') {
            SendPostingFloatedMails::dispatchSync($posting->id);

            return;
        }

        SendPostingFloatedMails::dispatch($posting->id);
    }

    private function findForm(string $type, int $id): Jnf|Inf|null
    {
        return $type === 'inf' ? Inf::query()->with('company')->find($id) : Jnf::query()->with('company')->find($id);
    }

    /** @return array<string, mixed> */
    private function snapshot(Model $form): array
    {
        $data = is_array($form->form_data) ? $form->form_data : [];

        // Admin-only keys (fix M2) are never copied from the form: the CDC sets them through its own input.
        return JobPosting::withoutAdminOnlyKeys(array_intersect_key($data, array_flip(JobPosting::SNAPSHOT_KEYS)));
    }

    private function copyRounds(JobPosting $posting, Model $form): void
    {
        $data = is_array($form->form_data) ? $form->form_data : [];
        $rounds = array_values(array_filter(
            is_array($data['selectionRounds'] ?? null) ? $data['selectionRounds'] : [],
            static fn ($round) => is_array($round) && ($round['enabled'] ?? false)
        ));

        if ($rounds === []) {
            $rounds = [['type' => 'other', 'description' => 'Selection']];
        }

        foreach ($rounds as $index => $round) {
            $type = array_key_exists($round['type'] ?? '', JobPosting::ROUND_LABELS) ? $round['type'] : 'other';
            $name = $type === 'other'
                ? (trim((string) ($round['description'] ?? '')) ?: 'Custom Stage')
                : JobPosting::ROUND_LABELS[$type];

            $scheduled = null;
            if (! empty($round['date'])) {
                try {
                    // Tentative dates on the form are Indian dates; 10:00 IST, stored in the app timezone.
                    $scheduled = Carbon::parse((string) $round['date'], 'Asia/Kolkata')->setTime(10, 0)->setTimezone(config('app.timezone'));
                } catch (\Throwable) {
                    $scheduled = null;
                }
            }

            $posting->rounds()->create([
                'name' => mb_substr($name, 0, 255),
                'round_type' => $type,
                'sort_order' => $index + 1,
                'scheduled_at' => $scheduled,
                'status' => $index === 0 ? 'ongoing' : 'pending',
                'is_final' => $index === count($rounds) - 1,
            ]);
        }
    }

    /**
     * Replace the posting's questions: rows with an id are updated, rows without are created, others deleted.
     */
    private function syncQuestions(JobPosting $posting, array $questions): void
    {
        $keep = [];

        foreach (array_values($questions) as $index => $question) {
            $attributes = [
                'question' => trim((string) $question['question']),
                'help_text' => trim((string) ($question['help_text'] ?? '')) ?: null,
                'qtype' => $question['qtype'],
                'options' => $question['qtype'] === 'text'
                    ? null
                    : array_values(array_unique(array_filter(array_map(fn ($o) => trim((string) $o), $question['options'] ?? []), fn ($o) => $o !== ''))),
                'required' => (bool) ($question['required'] ?? false),
                'sort_order' => $index + 1,
            ];

            $existing = ! empty($question['id'])
                ? $posting->questions()->whereKey($question['id'])->first()
                : null;

            if ($existing) {
                $existing->update($attributes);
                $keep[] = $existing->id;
            } else {
                $keep[] = $posting->questions()->create($attributes)->id;
            }
        }

        PostingQuestion::query()->where('job_posting_id', $posting->id)->whereNotIn('id', $keep ?: [0])->delete();
    }

    /** @return array<string, array<int, mixed>> */
    private function questionRules(): array
    {
        return [
            'questions' => ['sometimes', 'array', 'max:20'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.question' => ['required', 'string', 'max:1000'],
            'questions.*.help_text' => ['nullable', 'string', 'max:500'],
            'questions.*.qtype' => ['required', 'in:text,mcq_single,mcq_multi'],
            'questions.*.options' => ['nullable', 'array', 'max:20'],
            'questions.*.options.*' => ['nullable', 'string', 'max:255'],
            'questions.*.required' => ['nullable', 'boolean'],
            'questions.*' => [function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_array($value) || ($value['qtype'] ?? 'text') === 'text') {
                    return;
                }
                $options = array_filter(array_map(fn ($o) => trim((string) $o), $value['options'] ?? []), fn ($o) => $o !== '');
                if (count(array_unique($options)) < 2) {
                    $fail('Multiple-choice questions need at least two different options.');
                }
            }],
        ];
    }

    /** A date-time (IST unless it carries an offset, QA F-005) that is still in the future. */
    private function futureDeadline(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (Ist::parse((string) $value)->isPast()) {
                $fail('The application deadline must be in the future.');
            }
        };
    }

    /** A date-time (IST unless it carries an offset) that is still in the future. */
    private function futureTime(string $message): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($message): void {
            if (Ist::parse((string) $value)->isPast()) {
                $fail($message);
            }
        };
    }

    /** @return array<string, array<int, string>> */
    private function roundRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'round_type' => ['required', 'in:'.implode(',', array_keys(JobPosting::ROUND_LABELS))],
            'scheduled_at' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:255'],
            'is_final' => ['nullable', 'boolean'],
        ];
    }

    private function makeFinal(JobPosting $posting, ?PostingRound $round): void
    {
        if (! $round) {
            return;
        }

        $posting->rounds()->where('id', '!=', $round->id)->update(['is_final' => false]);
        $round->forceFill(['is_final' => true])->save();
    }

    private function assertRoundOf(JobPosting $posting, PostingRound $round): void
    {
        abort_if($round->job_posting_id !== $posting->id, 404, 'Stage not found.');
    }

    private function hasAnyResults(JobPosting $posting, bool $publishedOnly = false): bool
    {
        return \App\Models\ApplicationRoundResult::query()
            ->whereIn('posting_round_id', $posting->rounds()->pluck('id'))
            ->when($publishedOnly, fn ($q) => $q->whereNotNull('published_at'))
            ->exists();
    }

    private function roundHasResults(PostingRound $round): bool
    {
        return $round->results()->exists();
    }

    /** @return array<string, mixed> */
    private function auditState(JobPosting $posting): array
    {
        return [
            'application_deadline' => $posting->application_deadline?->toIso8601String(),
            'share_contact_details' => $posting->share_contact_details,
            'offer_type' => $posting->offerType(),
            'visit_date' => $posting->visit_date?->toDateString(),
            'scheduled_open_at' => $posting->scheduled_open_at?->toIso8601String(),
            'questions' => $posting->questions()->get(['id', 'question', 'help_text', 'qtype', 'options', 'required'])->toArray(),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(JobPosting $posting): array
    {
        $company = $posting->company();

        return [
            'id' => $posting->id,
            'form_type' => $posting->formType(),
            'form_id' => $posting->postable_id,
            'type' => $posting->postingType(),
            'title' => $posting->title(),
            'company' => $company ? ['id' => $company->id, 'name' => $company->name, 'logo_url' => $company->logo_url] : null,
            'placement_cycle' => $posting->placementCycle ? $posting->placementCycle->only(['id', 'name', 'type']) : null,
            'status' => $posting->status,
            'application_deadline' => $posting->application_deadline,
            'deadline_passed' => $posting->deadlinePassed(),
            'share_contact_details' => $posting->share_contact_details,
            'offer_type' => $posting->offerType(),
            'offer_label' => Offer::LABELS[$posting->offerType()] ?? $posting->offerType(),
            'floated_at' => $posting->floated_at,
            'visit_date' => $posting->visit_date?->toDateString(),
            'scheduled_open_at' => $posting->scheduled_open_at,
            'is_scheduled' => $posting->isScheduled(),
            'any_stage_published' => $posting->relationLoaded('rounds')
                ? $posting->rounds->contains(fn ($r) => $r->status === 'completed')
                : $posting->rounds()->where('status', 'completed')->exists(),
            'applied_count' => (int) ($posting->applied_count ?? $posting->applications()->where('status', 'applied')->count()),
            'withdrawn_count' => (int) ($posting->withdrawn_count ?? $posting->applications()->where('status', 'withdrawn')->count()),
        ];
    }

    /** @return array<string, mixed> */
    private function detail(JobPosting $posting): array
    {
        $applications = $posting->applications();

        return $this->summary($posting) + [
            'floated_by' => $posting->floatedBy ? $posting->floatedBy->only(['id', 'name']) : null,
            'eligibility_snapshot' => $posting->eligibility_snapshot,
            'compensation' => $posting->compensationFor(),
            'rounds' => $posting->rounds,
            'questions' => $posting->questions,
            'stats' => [
                'eligible' => $this->eligibility->eligibleStudentsQuery($posting)->count(),
                'applied' => (clone $applications)->where('status', 'applied')->count(),
                'withdrawn' => (clone $applications)->where('status', 'withdrawn')->count(),
                'unverified_resume' => (clone $applications)->where('status', 'applied')->where('used_unverified_resume', true)->count(),
                'placed_elsewhere' => (clone $applications)->where('status', 'applied')->where('placed_elsewhere_flag', true)->count(),
            ],
        ];
    }
}
