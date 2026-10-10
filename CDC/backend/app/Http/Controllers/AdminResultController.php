<?php

namespace App\Http\Controllers;

use App\Mail\OfferMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\Offer;
use App\Models\PlacementBlock;
use App\Models\PostingRound;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\BlockingPolicy;
use App\Services\MailDispatchService;
use App\Services\PipelineService;
use App\Services\PortalNotificationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Final results → offers → blocks (spec B4 / M7.2): the announcement console.
 */
class AdminResultController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly BlockingPolicy $policy,
        private readonly PipelineService $pipeline,
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
    ) {
    }

    public function prepare(JobPosting $jobPosting): JsonResponse
    {
        $final = $this->finalRound($jobPosting);
        if (! $final) {
            return response()->json(['message' => 'This job profile has no final stage.'], 422);
        }

        $rows = $final->results()
            ->whereIn('result', ['selected', 'waitlisted'])
            ->whereHas('application', fn ($q) => $q->where('status', 'applied'))
            ->with(['application.studentProfile', 'application.offer'])
            ->get();

        $defaultType = $this->policy->defaultOfferType($jobPosting);
        $isInf = $jobPosting->formType() === 'inf';
        $cycleId = $jobPosting->placement_cycle_id;

        $activeBlocks = PlacementBlock::query()
            ->where('placement_cycle_id', $cycleId)
            ->where('active', true)
            ->whereIn('student_profile_id', $rows->pluck('application.student_profile_id'))
            ->get()
            ->groupBy('student_profile_id');

        $present = function (ApplicationRoundResult $row) use ($jobPosting, $defaultType, $isInf, $activeBlocks) {
            $student = $row->application->studentProfile;
            $comp = $jobPosting->compensationFor($student->programme);

            return [
                'application_id' => $row->application_id,
                'student' => $student->only(['id', 'roll_no', 'full_name', 'programme', 'branch', 'current_cgpa']),
                'result' => $row->result,
                'published' => $row->isPublished(),
                'placed_elsewhere_flag' => $row->application->placed_elsewhere_flag,
                'used_unverified_resume' => (bool) $row->application->used_unverified_resume, // QA F-022
                'active_blocks' => ($activeBlocks[$student->id] ?? collect())->map(fn (PlacementBlock $b) => $b->message())->values(),
                // Why this candidate cannot be offered right now (QA F-035); the console disables their row.
                'offer_refusal' => $row->application->offer ? null : $this->policy->offerRefusal($student, $jobPosting, $row->application_id),
                'offer' => $row->application->offer,
                'suggested' => [
                    'offer_type' => $defaultType,
                    'ctc_annual' => $isInf ? null : $comp['ctc_annual'],
                    'stipend_monthly' => $isInf ? $comp['stipend_monthly'] : null,
                    'currency' => $comp['currency'],
                    'block' => $this->policy->suggest($defaultType),
                ],
            ];
        };

        $selected = $rows->where('result', 'selected')->values();
        $waitlisted = $rows->where('result', 'waitlisted')->sortBy(fn ($r) => $r->application->studentProfile->roll_no)->values();
        $vacancies = (int) ($jobPosting->postable?->vacancies ?? 0);

        // Regrets a publish would send now: pool members with no final-round decision yet, plus unpublished
        // rejected/pending rows (D84).
        $decided = $final->results()->whereIn('result', ['selected', 'waitlisted', 'rejected'])->pluck('application_id')->all();
        $regrets = $this->pipeline->pool($final)->filter(fn ($a) => ! in_array($a->id, $decided, true))->count()
            + $final->results()->whereNull('published_at')->where('result', 'rejected')->whereHas('application', fn ($q) => $q->where('status', 'applied'))->count();

        return response()->json([
            'posting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title(),
                'company' => $jobPosting->company()?->name,
                'type' => $jobPosting->postingType(),
                'form_type' => $jobPosting->formType(),
                'offer_type' => $jobPosting->offerType(),
                'offer_label' => Offer::LABELS[$jobPosting->offerType()] ?? $jobPosting->offerType(),
                'status' => $jobPosting->status,
                'vacancies' => $vacancies ?: null,
                'accepts_applications' => $jobPosting->acceptsApplications(),
            ],
            'final_round' => $final->only(['id', 'name', 'status']),
            'selected' => $selected->map($present),
            'waitlisted' => $waitlisted->map($present),
            // Places still open against the form's vacancies; the admin picks ANY waitlisted candidates (no ranks, D90).
            'open_places' => $vacancies > 0 ? max(0, min($waitlisted->count(), $vacancies - $selected->count())) : 0,
            'regret_estimate' => $regrets,
            'currency' => (string) ($jobPosting->formData()['currency'] ?? 'INR'),
            'blocked_reason' => $this->orderProblem($jobPosting, $final),
            'offer_types' => collect(Offer::LABELS)->map(fn ($label, $type) => [
                'value' => $type,
                'label' => $label,
                'block' => $this->policy->suggest($type),
            ])->values(),
        ]);
    }

    /**
     * Publish the final round: offers + blocks + placed-elsewhere flags + E5 mails (spec M7.2).
     */
    public function publish(Request $request, JobPosting $jobPosting): JsonResponse
    {
        $validated = $request->validate([
            'selections' => ['present', 'array'],
            'selections.*.application_id' => ['required', 'integer', 'distinct'],
            'selections.*.offer_type' => ['required', Rule::in(Offer::TYPES)],
            'selections.*.ctc_annual' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'selections.*.stipend_monthly' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'selections.*.block' => ['required', 'boolean'],
            'selections.*.block_scope' => ['nullable', 'required_if:selections.*.block,true', 'in:all,internships_only'],
            'reject_remaining' => ['nullable', 'boolean'],
        ]);

        if ($jobPosting->status === 'cancelled') {
            return response()->json(['message' => 'This job profile is cancelled.'], 422);
        }
        if ($jobPosting->acceptsApplications()) {
            return response()->json(['message' => 'Close applications before announcing results.'], 422);
        }

        $final = $this->finalRound($jobPosting);
        if (! $final) {
            return response()->json(['message' => 'This job profile has no final stage.'], 422);
        }
        if ($problem = $this->orderProblem($jobPosting, $final)) {
            return response()->json(['message' => $problem], 422);
        }

        $applications = $jobPosting->applications()
            ->whereIn('id', array_column($validated['selections'], 'application_id'))
            ->with(['studentProfile.user', 'offer'])
            ->get()
            ->keyBy('id');

        foreach ($validated['selections'] as $selection) {
            $application = $applications->get($selection['application_id']);
            if (! $application || $application->status !== 'applied') {
                return response()->json(['message' => "Application #{$selection['application_id']} is not a live application of this job profile."], 422);
            }
            if ($application->offer) {
                return response()->json(['message' => "{$application->studentProfile->roll_no} already has an offer for this job profile."], 422);
            }
        }

        // Offers only for the final round's pool or people already decided in it (no skipping earlier rejections, D84).
        $poolIds = $this->pipeline->pool($final)->pluck('id')->all();
        $finalRows = $final->results()->get()->keyBy('application_id');
        $finalIds = $finalRows->keys()->all();
        foreach ($applications as $application) {
            if (! in_array($application->id, $poolIds, true) && ! in_array($application->id, $finalIds, true)) {
                return response()->json(['message' => "{$application->studentProfile->roll_no} did not reach the final stage. Use Re-add on the stage where they were not selected."], 422);
            }
            // QA F-027: a rejection already published in the final stage is undone only through Re-add (company told, audited).
            $finalRow = $finalRows->get($application->id);
            if ($finalRow && $finalRow->result === 'rejected' && $finalRow->isPublished()) {
                return response()->json(['message' => "{$application->studentProfile->roll_no} was not selected in the final stage. Use Re-add on that stage first."], 422);
            }
            // QA F-035 (owner rule): never a second offer in the cycle, never an offer through an active block.
            if ($refusal = $this->policy->offerRefusal($application->studentProfile, $jobPosting, $application->id)) {
                return response()->json(['message' => $refusal], 422);
            }
        }

        $unpublished = $final->results()->whereNull('published_at')->where('result', '!=', 'pending')->count();
        $undecided = count(array_diff($poolIds, $finalIds));
        if ($validated['selections'] === [] && $unpublished === 0 && ($undecided === 0 || ! ($validated['reject_remaining'] ?? true))) {
            return response()->json(['message' => 'There is nothing new to publish.'], 422);
        }

        $company = $jobPosting->company();
        $now = now()->startOfSecond();
        $admin = $request->user();
        $rejectRemaining = (bool) ($validated['reject_remaining'] ?? true);

        [$offers, $blocks, $flagged, $published, $blockScopes] = DB::transaction(function () use ($validated, $applications, $final, $jobPosting, $company, $now, $admin, $rejectRemaining) {
            $offers = collect();
            $blocks = collect();
            $flagged = 0;
            $blockScopes = [];

            // Same check again under a row lock, so two announcements at the same moment cannot both offer one student.
            StudentProfile::query()->whereIn('id', $applications->pluck('student_profile_id'))->lockForUpdate()->get();
            foreach ($applications as $application) {
                if ($refusal = $this->policy->offerRefusal($application->studentProfile, $jobPosting, $application->id)) {
                    throw new HttpResponseException(response()->json(['message' => $refusal], 422));
                }
            }

            foreach ($validated['selections'] as $selection) {
                /** @var Application $application */
                $application = $applications->get($selection['application_id']);

                ApplicationRoundResult::query()->updateOrCreate(
                    ['application_id' => $application->id, 'posting_round_id' => $final->id],
                    ['result' => 'selected', 'published_at' => $now, 'decided_by' => $admin->id]
                );

                $offer = Offer::create([
                    'application_id' => $application->id,
                    'student_profile_id' => $application->student_profile_id,
                    'company_id' => $company->id,
                    'job_posting_id' => $jobPosting->id,
                    'placement_cycle_id' => $jobPosting->placement_cycle_id,
                    'offer_type' => $selection['offer_type'],
                    'ctc_annual' => $selection['ctc_annual'] ?? null,
                    'stipend_monthly' => $selection['stipend_monthly'] ?? null,
                    'currency' => (string) ($jobPosting->formData()['currency'] ?? 'INR'),
                    'announced_by' => $admin->id,
                    'announced_at' => $now,
                ]);
                $offers->push($offer);

                if ($selection['block']) {
                    $scope = $selection['block_scope'] ?? ($this->policy->suggest($selection['offer_type'])['scope'] ?? 'all');
                    $blockScopes[$offer->id] = $scope;
                    // Owner decision (QA F-004): "completely" reaches every cycle the student is enrolled in;
                    // internship offers reach the internship cycle(s) only.
                    foreach ($this->policy->targets($application->studentProfile, $jobPosting->placementCycle, $scope) as $target) {
                        $block = PlacementBlock::create([
                            'student_profile_id' => $application->student_profile_id,
                            'placement_cycle_id' => $target['placement_cycle_id'],
                            'scope' => $target['scope'],
                            'reason' => 'offer',
                            'offer_id' => $offer->id,
                            'active' => true,
                            'blocked_by' => $admin->id,
                        ]);
                        $blocks->push($block);
                        $flagged += $this->policy->flagLiveApplications($application->student_profile_id, $target['placement_cycle_id'], $target['scope'], $application->id);
                    }
                }
            }

            // Everyone else in the final round: publish their drafts; the rest of the pool is not selected.
            if ($rejectRemaining) {
                $existing = $final->results()->get()->keyBy('application_id');
                foreach ($this->pipeline->pool($final) as $candidate) {
                    $row = $existing->get($candidate->id);
                    if (! $row) {
                        ApplicationRoundResult::create([
                            'application_id' => $candidate->id,
                            'posting_round_id' => $final->id,
                            'result' => 'rejected',
                            'decided_by' => $admin->id,
                        ]);
                    } elseif ($row->result === 'pending' && ! $row->isPublished()) {
                        $row->update(['result' => 'rejected', 'decided_by' => $admin->id]);
                    }
                }
            }

            $selectedIds = array_column($validated['selections'], 'application_id');
            $published = $final->results()
                ->whereNull('published_at')
                ->where('result', '!=', 'pending')
                ->whereNotIn('application_id', $selectedIds)
                ->whereHas('application', fn ($q) => $q->where('status', 'applied'))
                ->get();
            foreach ($published as $row) {
                // A final-round "selected" draft without an offer is announced as not selected.
                if ($row->result === 'selected') {
                    $row->result = 'rejected';
                }
                $row->published_at = $now;
                $row->save();
            }

            $final->update(['status' => 'completed']);
            $jobPosting->update(['status' => 'completed']);

            return [$offers, $blocks, $flagged, $published, $blockScopes];
        });

        foreach ($offers as $offer) {
            $this->audit->log($request, 'offer.create', $offer, null, $offer->only(['application_id', 'student_profile_id', 'offer_type', 'ctc_annual', 'stipend_monthly']));
        }
        foreach ($blocks as $block) {
            $this->audit->log($request, 'block.create', $block, null, $block->only(['student_profile_id', 'placement_cycle_id', 'scope', 'reason', 'offer_id']));
        }
        $this->audit->log($request, 'result.publish', $jobPosting, null, [
            'offers' => $offers->count(),
            'blocks' => $blocks->count(),
            'placed_elsewhere_flagged' => $flagged,
            'regrets' => $published->where('result', 'rejected')->count(),
            'waitlisted' => $published->where('result', 'waitlisted')->count(),
        ]);

        $this->sendOfferMails($jobPosting, $offers, $blockScopes);
        $this->pipeline->dispatchResultMails($final, $published->pluck('id'), false);

        return response()->json([
            'message' => sprintf(
                '%d offer(s) announced · %d block(s) · %d regret mail(s) · %d other application(s) flagged as placed elsewhere.',
                $offers->count(),
                $blocks->count(),
                $published->where('result', 'rejected')->count(),
                $flagged
            ),
            'offers' => $offers->count(),
            'blocks' => $blocks->count(),
            'regrets' => $published->where('result', 'rejected')->count(),
            'flagged' => $flagged,
        ]);
    }

    /**
     * @param  Collection<int, Offer>  $offers
     * @param  array<int, string>  $blockScopes  offer id → chosen block scope
     */
    private function sendOfferMails(JobPosting $posting, Collection $offers, array $blockScopes): void
    {
        $company = $posting->company()?->name ?? 'the company';
        $title = $posting->title();

        foreach ($offers as $offer) {
            $student = $offer->studentProfile()->with('user')->first();
            $compensation = collect([
                $offer->ctc_annual ? $offer->currency.' '.number_format($offer->ctc_annual).' per year' : null,
                $offer->stipend_monthly ? $offer->currency.' '.number_format($offer->stipend_monthly).' per month' : null,
            ])->filter()->implode(' · ') ?: null;
            $blockNote = isset($blockScopes[$offer->id]) ? $this->policy->describe($blockScopes[$offer->id]) : null;

            $this->notifications->createInAppNotification(
                $student->user,
                "Offer from {$company}",
                "Congratulations! You have been selected for {$title} at {$company} (".Offer::LABELS[$offer->offer_type].').',
                'success'
            );

            $mailable = new OfferMail($student->full_name, $company, $title, Offer::LABELS[$offer->offer_type], $compensation, $blockNote);
            $this->mail->send($student->user, $mailable, $mailable->envelope()->subject, 'emails.offer', ['job_posting_id' => $posting->id, 'kind' => 'offer']);
        }
    }

    /**
     * Results are announced only when every round before the final is published and no round comes after it.
     */
    private function orderProblem(JobPosting $posting, PostingRound $final): ?string
    {
        $rounds = $posting->rounds()->get();

        if ($rounds->last()?->id !== $final->id) {
            return 'The final stage must be the last stage. Reorder the stages or mark the last stage as final.';
        }

        $pending = $rounds->first(fn (PostingRound $r) => $r->sort_order < $final->sort_order && $r->status !== 'completed');

        return $pending ? "Publish \"{$pending->name}\" from the Pipeline tab before announcing final results." : null;
    }

    private function finalRound(JobPosting $posting): ?PostingRound
    {
        return $posting->rounds()->where('is_final', true)->first() ?? $posting->rounds()->get()->last();
    }
}
