<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Models\ShortlistProposal;
use App\Services\PipelineService;
use App\Services\SpreadsheetImportService;
use App\Services\CompanyProposalUploadService;
use App\Services\SpocService;
use App\Services\StakeholderNotifier;
use App\Support\ApplicantOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;
use Illuminate\Support\Str;

/**
 * Company view of its OWN floated forms (spec M6.4 / Q10.x). Only PUBLISHED round outcomes are ever shown,
 * and admin-only flags (unverified resume, placed elsewhere) never leave the admin API.
 */
class CompanyPipelineController extends Controller
{
    public function __construct(
        private readonly PipelineService $pipeline,
        private readonly StakeholderNotifier $stakeholders
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $postings = $this->ownPostings($request)
            ->with(['postable', 'placementCycle:id,name,type,status', 'rounds'])
            ->withCount(['applications as applicant_count' => fn ($q) => $q->where('status', 'applied')])
            ->orderByDesc('floated_at')
            ->get();

        return response()->json([
            'postings' => $postings->map(fn (JobPosting $p) => $this->summary($p)),
        ]);
    }

    public function show(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);
        $jobPosting->load(['postable', 'placementCycle:id,name,type,status', 'rounds']);
        $jobPosting->loadCount(['applications as applicant_count' => fn ($q) => $q->where('status', 'applied')]);

        return response()->json([
            'posting' => $this->summary($jobPosting) + [
                'rounds' => $jobPosting->rounds->map(fn (PostingRound $r) => $r->only(['id', 'name', 'round_type', 'sort_order', 'scheduled_at', 'status', 'is_final']) + [
                    'published_selected' => $r->results()->whereNotNull('published_at')->where('result', 'selected')->count(),
                    'published_waitlisted' => $r->results()->whereNotNull('published_at')->where('result', 'waitlisted')->count(),
                    // Attendance of students whose result in this stage is published (P3-D53); drafts stay hidden.
                    'attendance' => $this->attendanceSummary($r),
                ]),
                'questions' => $jobPosting->questions()->get(['id', 'question', 'qtype', 'options', 'required']),
                // Student SPOCs of this job profile (P3-D52): name, phone and email only.
                'spocs' => SpocService::companyFacing($jobPosting),
            ],
        ]);
    }

    /**
     * Applicants with the Q10.2 field set; contact details only when the admin enabled sharing.
     */
    public function applicants(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);

        $share = $jobPosting->share_contact_details;
        $applications = $jobPosting->applications()
            ->where('status', 'applied')
            ->with(['studentProfile', 'resume', 'roundResults' => fn ($q) => $q->where(fn ($v) => $v->whereNotNull('published_at')->orWhereNotNull('published_result'))])
            ->orderBy('applied_at')
            ->get();

        return response()->json([
            'share_contact_details' => $share,
            'applicants' => $applications->map(function (Application $a) use ($share) {
                $s = $a->studentProfile;

                return [
                    'application_id' => $a->id,
                    'roll_no' => $s->roll_no,
                    'full_name' => $s->full_name,
                    'programme' => $s->programme,
                    'branch' => $s->branch,
                    'graduating_batch' => $s->graduating_batch,
                    'current_cgpa' => $s->current_cgpa,
                    'ongoing_backlogs' => $s->ongoing_backlogs,
                    'total_backlogs' => $s->total_backlogs,
                    'tenth_percent' => $s->tenth_percent,
                    'twelfth_percent' => $s->twelfth_percent,
                    'answers' => $a->answers ?? [],
                    'resume_url' => $a->resume?->signedUrl(),
                    'rounds' => $a->roundResults->mapWithKeys(fn (ApplicationRoundResult $r) => [
                        $r->posting_round_id => ['result' => $r->visibleResult(), 'attendance' => $r->attendance],
                    ]),
                ] + ($share ? [
                    'phone' => $s->phone,
                    'personal_email' => $s->personal_email,
                    'institute_email' => $s->institute_email,
                ] : []);
            }),
        ]);
    }

    /**
     * Same export as admins, restricted to the Q10.2 fields (contact only when shared), any time (spec Q8.2).
     */
    public function export(Request $request, JobPosting $jobPosting, \App\Services\ExportService $exports): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizePosting($request, $jobPosting);
        $order = ApplicantOrder::fromRequest($request); // P3-D54: the order chosen on screen

        return $exports->applicantsWorkbook($jobPosting, 'company', fn ($applications) => ApplicantOrder::byCgpa($applications, $order));
    }

    /**
     * One stage's students as Excel: shortlisted, not selected, or everyone with a decision there. Only PUBLISHED
     * decisions count, like everything else the company sees.
     */
    public function exportStage(Request $request, JobPosting $jobPosting, PostingRound $postingRound, \App\Services\ExportService $exports): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizePosting($request, $jobPosting);
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');
        $decision = $request->validate(['decision' => ['nullable', 'in:shortlisted,rejected,all']])['decision'] ?? 'all';
        $result = ['shortlisted' => 'selected', 'rejected' => 'rejected'][$decision] ?? null;

        $order = ApplicantOrder::fromRequest($request); // P3-D54: the order chosen on screen

        $narrow = fn ($applications) => ApplicantOrder::byCgpa($applications->filter(function (Application $a) use ($postingRound, $result) {
            $visible = $a->roundResults->first(fn (ApplicationRoundResult $r) => $r->posting_round_id === $postingRound->id)?->visibleResult();

            return $visible !== null && ($result === null || $visible === $result);
        }), $order);
        $label = ['shortlisted' => $postingRound->is_final ? 'selected' : 'shortlisted', 'rejected' => 'not selected', 'all' => 'all students'][$decision];

        return $exports->applicantsWorkbook($jobPosting, 'company', $narrow, $postingRound->name.' '.$label);
    }

    /** "Download Final Offers": the job profile's offers (selected students) for the company. */
    public function exportOffers(Request $request, JobPosting $jobPosting, \App\Services\ExportService $exports): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizePosting($request, $jobPosting);

        return $exports->companyOffersWorkbook($jobPosting);
    }

    /**
     * The link the CDC mails with "Send Applicant List" (S6.11): signed and time-limited, the same company-safe
     * export the company downloads from its portal. Cancelled job profiles are not served.
     */
    public function signedExport(JobPosting $jobPosting, \App\Services\ExportService $exports): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_if($jobPosting->status === 'cancelled', 404, 'Job profile not found.');

        return $exports->applicantsWorkbook($jobPosting, 'company');
    }

    public function proposals(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);

        return response()->json([
            'proposals' => $jobPosting->proposals()
                ->with('postingRound:id,name')
                ->latest('id')
                ->get(['id', 'job_posting_id', 'posting_round_id', 'kind', 'payload', 'source_file_name', 'status', 'admin_remark', 'decided_at', 'created_at']),
        ]);
    }

    /**
     * Propose a shortlist / waitlist / addendum / replacement for a round. Lands as `pending` for the CDC.
     */
    public function storeProposal(Request $request, JobPosting $jobPosting, PostingRound $postingRound): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');

        $validated = $request->validate([
            'kind' => ['required', 'in:shortlist,waitlist,addendum,replacement_request'],
            'entries' => ['required', 'array', 'min:1', 'max:3000'],
            'entries.*.roll_no' => ['required', 'string', 'max:30'],
        ]);

        if ($refused = $this->proposalRefusal($jobPosting, $validated['kind'])) {
            return $refused;
        }

        // One entry per roll number (case-insensitive).
        $unique = [];
        foreach ($validated['entries'] as $entry) {
            $unique[strtoupper(trim($entry['roll_no']))] ??= $entry;
        }
        $validated['entries'] = array_values($unique);

        [$found, $unknown] = $this->pipeline->resolveApplicants($jobPosting, array_column($validated['entries'], 'roll_no'), forCompany: true);

        if ($unknown !== []) {
            return response()->json([
                'message' => 'Some roll numbers are not applicants of this job profile. Remove them and try again.',
                'errors' => $unknown,
            ], 422);
        }

        $proposal = $this->createProposal($request, $jobPosting, $postingRound, $validated['kind'], array_map(fn ($e) => strtoupper(trim($e['roll_no'])), $validated['entries']));

        return response()->json([
            'message' => 'Proposal sent to the CDC. Results become visible to students only after the CDC publishes them.',
            'proposal' => $proposal,
        ], 201);
    }

    /** P3-D56: the stage's decision sheet (its students, a Decision column with a Shortlisted / On Hold dropdown). */
    public function decisionSheet(Request $request, JobPosting $jobPosting, PostingRound $postingRound, \App\Services\ExportService $exports, CompanyProposalUploadService $uploads): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorizePosting($request, $jobPosting);
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');

        return $exports->decisionSheetWorkbook($jobPosting, $postingRound, $uploads->pool($postingRound));
    }

    /**
     * P3-D56: upload the filled decision sheet. Shortlisted rows become a Shortlist proposal and On Hold rows an On Hold
     * proposal (each only when it has rows); bad rows are skipped and reported. `dry_run` only reports.
     */
    public function uploadDecisions(Request $request, JobPosting $jobPosting, PostingRound $postingRound, CompanyProposalUploadService $uploads): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');
        $request->validate(['file' => array_merge(['required'], SpreadsheetImportService::UPLOAD_RULES), 'dry_run' => ['nullable', 'boolean']]);

        if ($refused = $this->proposalRefusal($jobPosting, 'shortlist')) {
            return $refused;
        }

        try {
            $report = $uploads->readDecisionSheet($request->file('file'), $jobPosting, $postingRound);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $counts = ['shortlist' => count($report['shortlist']), 'waitlist' => count($report['waitlist'])];
        $answer = ['shortlisted' => $counts['shortlist'], 'on_hold' => $counts['waitlist'], 'blank' => $report['blank'], 'errors' => $report['errors']];
        if ($counts['shortlist'] + $counts['waitlist'] === 0) {
            return response()->json(['message' => 'Nothing to send: no row is marked Shortlisted or On Hold for a student of this stage.'] + $answer, 422);
        }

        if ($request->boolean('dry_run')) {
            return response()->json(['message' => sprintf('Preview: %d shortlisted, %d on hold, %d left blank, %d row(s) skipped. Nothing is sent yet.',
                $counts['shortlist'], $counts['waitlist'], $report['blank'], count($report['errors'])), 'dry_run' => true] + $answer);
        }

        $source = $this->keepSourceFile($request, $jobPosting);
        $proposals = [];
        $sent = [];
        foreach (['shortlist' => 'a shortlist of %d', 'waitlist' => 'an On Hold list of %d'] as $kind => $label) {
            if ($report[$kind] !== []) {
                $proposals[] = $this->createProposal($request, $jobPosting, $postingRound, $kind, $report[$kind], $source);
                $sent[] = sprintf($label, $counts[$kind]);
            }
        }

        return response()->json([
            'message' => sprintf('Sent to the CDC: %s. %d row(s) skipped. Results become visible to students only after the CDC publishes them.', implode(' and ', $sent), count($report['errors'])),
            'proposals' => $proposals,
        ] + $answer, 201);
    }

    /** P3-D56: a roll-number list (first column) as a proposal of the chosen type; bad rows are skipped and reported. */
    public function uploadRollList(Request $request, JobPosting $jobPosting, PostingRound $postingRound, CompanyProposalUploadService $uploads): JsonResponse
    {
        $this->authorizePosting($request, $jobPosting);
        abort_if($postingRound->job_posting_id !== $jobPosting->id, 404, 'Stage not found.');
        $validated = $request->validate([
            'kind' => ['required', 'in:shortlist,waitlist,addendum,replacement_request'],
            'file' => array_merge(['required'], SpreadsheetImportService::UPLOAD_RULES),
            'dry_run' => ['nullable', 'boolean'],
        ]);

        if ($refused = $this->proposalRefusal($jobPosting, $validated['kind'])) {
            return $refused;
        }

        try {
            $report = $uploads->readRollList($request->file('file'), $jobPosting);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
        $answer = ['students' => count($report['rolls']), 'errors' => $report['errors']];
        if ($report['rolls'] === []) {
            return response()->json(['message' => 'Nothing to send: no roll number in the file is an applicant of this job profile.'] + $answer, 422);
        }
        if ($request->boolean('dry_run')) {
            return response()->json(['message' => sprintf('Preview: %d student(s), %d row(s) skipped. Nothing is sent yet.', count($report['rolls']), count($report['errors'])), 'dry_run' => true] + $answer);
        }

        $proposal = $this->createProposal($request, $jobPosting, $postingRound, $validated['kind'], $report['rolls'], $this->keepSourceFile($request, $jobPosting));

        return response()->json([
            'message' => sprintf('Proposal of %d student(s) sent to the CDC. %d row(s) skipped.', count($report['rolls']), count($report['errors'])),
            'proposal' => $proposal,
        ] + $answer, 201);
    }

    /** The same refusals for every way of proposing. */
    private function proposalRefusal(JobPosting $jobPosting, string $kind): ?JsonResponse
    {
        // A completed drive still takes replacement requests and addenda (E9 invites them; QA F-010).
        if ($jobPosting->status === 'cancelled'
            || ($jobPosting->status === 'completed' && ! in_array($kind, ['addendum', 'replacement_request'], true))) {
            return response()->json(['message' => 'This job profile is '.$jobPosting->status.'.'.($jobPosting->status === 'completed' ? ' You can still send a replacement request or an addendum.' : '')], 422);
        }
        if ($jobPosting->acceptsApplications()) {
            return response()->json(['message' => 'Applications are still open. You can propose candidates once the application window closes.'], 422);
        }

        return null;
    }

    /** @return array{name: string, path: string} the uploaded file, kept for the CDC (private disk) */
    private function keepSourceFile(Request $request, JobPosting $jobPosting): array
    {
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension() ?: 'xlsx');
        $name = mb_substr((string) $file->getClientOriginalName(), 0, 200) ?: "upload.{$extension}";
        $path = $file->storeAs("proposal-uploads/{$jobPosting->id}", Str::uuid().'.'.$extension, 'local');

        return ['name' => $name, 'path' => $path];
    }

    /**
     * @param  list<string>  $rolls
     * @param  array{name: string, path: string}|null  $source
     */
    private function createProposal(Request $request, JobPosting $jobPosting, PostingRound $postingRound, string $kind, array $rolls, ?array $source = null): ShortlistProposal
    {
        $payload = array_values(array_map(fn ($roll) => ['roll_no' => $roll], $rolls));

        $proposal = ShortlistProposal::create([
            'job_posting_id' => $jobPosting->id,
            'posting_round_id' => $postingRound->id,
            'proposed_by' => $request->user()->id,
            'kind' => $kind,
            'payload' => $payload,
            'source_file_name' => $source['name'] ?? null,
            'source_file_path' => $source['path'] ?? null,
            'status' => 'pending',
        ]);

        $company = $jobPosting->company()?->name ?? 'A company';
        $this->stakeholders->notifyAdmins(
            "{$company} proposed a {$this->kindLabel($kind)}",
            sprintf('%s submitted a %s of %d candidate(s) for %s — %s.', $company, $this->kindLabel($kind), count($payload), $jobPosting->title(), $postingRound->name)
                .($source ? " (uploaded from {$source['name']})" : ''),
            [],
            "/admin/postings/{$jobPosting->id}",
            ['job_posting_id' => $jobPosting->id, 'kind' => 'shortlist_proposal'],
            // Only the admins who approve or reject proposals, not every staff member (e.g. Dept FICs).
            'proposals',
            'verify'
        );

        return $proposal;
    }

    private function kindLabel(string $kind): string
    {
        return ['shortlist' => 'shortlist', 'waitlist' => 'waitlist', 'addendum' => 'addendum', 'replacement_request' => 'replacement request'][$kind] ?? $kind;
    }

    private function ownPostings(Request $request): Builder
    {
        $companyId = $request->user()->company_id;

        return JobPosting::query()
            ->where('status', '!=', 'cancelled')
            ->where(function (Builder $q) use ($companyId): void {
                $q->where(fn (Builder $j) => $j->where('postable_type', Jnf::class)->whereIn('postable_id', Jnf::query()->where('company_id', $companyId)->select('id')))
                    ->orWhere(fn (Builder $i) => $i->where('postable_type', Inf::class)->whereIn('postable_id', Inf::query()->where('company_id', $companyId)->select('id')));
            });
    }

    /**
     * Appeared / absent counts of one stage, over rows the company may see (a published result, or the previously
     * published one while a change waits as a draft) of live applications only.
     *
     * @return array{appeared: int, absent: int}
     */
    private function attendanceSummary(PostingRound $round): array
    {
        $counts = $round->results()
            ->where(fn ($q) => $q->whereNotNull('published_at')->orWhereNotNull('published_result'))
            ->whereNotNull('attendance')
            ->whereHas('application', fn ($q) => $q->where('status', 'applied'))
            ->selectRaw('attendance, count(*) as total')
            ->groupBy('attendance')
            ->pluck('total', 'attendance');

        return ['appeared' => (int) ($counts['yes'] ?? 0), 'absent' => (int) ($counts['no'] ?? 0)];
    }

    private function authorizePosting(Request $request, JobPosting $posting): void
    {
        // 404, not 403, so other companies' posting ids cannot be probed (Phase 1 pattern).
        abort_unless($this->ownPostings($request)->whereKey($posting->id)->exists(), 404, 'Job profile not found.');
    }

    private function summary(JobPosting $posting): array
    {
        return [
            'id' => $posting->id,
            'form_type' => $posting->formType(),
            'form_id' => $posting->postable_id,
            'type' => $posting->postingType(),
            'title' => $posting->title(),
            'placement_cycle' => $posting->placementCycle ? $posting->placementCycle->only(['id', 'name', 'type']) : null,
            'status' => $posting->status,
            'application_deadline' => $posting->application_deadline,
            'deadline_passed' => $posting->deadlinePassed(),
            'share_contact_details' => $posting->share_contact_details,
            'applicant_count' => (int) ($posting->applicant_count ?? 0),
            'rounds_count' => $posting->relationLoaded('rounds') ? $posting->rounds->count() : null,
            // For the shared status label (fix L17): Closed For Applications until a stage is published.
            'any_stage_published' => $posting->relationLoaded('rounds')
                ? $posting->rounds->contains(fn ($r) => $r->status === 'completed')
                : $posting->rounds()->where('status', 'completed')->exists(),
        ];
    }
}
