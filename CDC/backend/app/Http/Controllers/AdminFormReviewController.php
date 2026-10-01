<?php

namespace App\Http\Controllers;

use App\Mail\FormEditedByAdminMail;
use App\Mail\FormStatusChangedMail;
use App\Models\FormStatusHistory;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PortalNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFormReviewController extends Controller
{
    public function __construct(
        private readonly PortalNotificationService $notificationService,
        private readonly AuditService $audit
    )
    {
    }

    public function jnfQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:submitted,under_review,accepted,rejected,draft,all'],
        ]);

        $query = Jnf::with('company:id,name,hr_name,hr_email,logo_path')->latest();

        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            if ($validated['status'] === 'draft') {
                $query->where(function ($nested): void {
                    $nested
                        ->where('status', 'draft')
                        ->orWhere(function ($inner): void {
                            $inner
                                ->where('status', 'under_review')
                                ->whereDoesntHave('statusHistories', function ($history): void {
                                    $history->where('new_status', 'submitted');
                                });
                        });
                })->with([
                    'statusHistories' => function ($history): void {
                        $history
                            ->select(['id', 'form_id', 'form_type', 'new_status', 'created_at'])
                            ->latest();
                    },
                ]);
            } else {
                $query->where('status', $validated['status']);
            }
        } elseif (empty($validated['status'])) {
            $query->whereIn('status', ['submitted', 'under_review']);
        } elseif ($validated['status'] === 'all') {
            $query->with([
                'statusHistories' => function ($history): void {
                    $history
                        ->select(['id', 'form_id', 'form_type', 'new_status', 'created_at'])
                        ->latest();
                },
            ]);
        }

        $jnfs = $query->get()->map(function (Jnf $jnf) use ($validated) {
            $formData = is_array($jnf->form_data) ? $jnf->form_data : [];
            $batch = $formData['graduatingBatch'] ?? null;
            if (!$batch && isset($formData['eligibility'][0]['graduatingBatches'][0])) {
                $batch = $formData['eligibility'][0]['graduatingBatches'][0];
            }

            if (in_array(($validated['status'] ?? null), ['draft', 'all'], true)) {
                $isDraftReviewMarked = $this->isDraftReviewMarked($jnf->statusHistories, (string) $jnf->status);

                if ($isDraftReviewMarked) {
                    $jnf->setAttribute('status', 'draft');
                }

                $jnf->setAttribute('review_marked', $isDraftReviewMarked);
                $jnf->unsetRelation('statusHistories');
            }

            // Expose a lightweight response array rather than full model, or simply append the attribute.
            // Since the frontend expects certain fields, let's append it to the model.
            $jnf->setAttribute('graduating_batch', $batch ?: 'Unknown Batch');
            
            // Optionally, unset form_data to keep response lightweight as it was before
            $jnf->makeHidden(['form_data']);
            
            return $jnf;
        });

        return response()->json([
            'jnfs' => $jnfs,
        ]);
    }

    public function infQueue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:submitted,under_review,accepted,rejected,draft,all'],
        ]);

        $query = Inf::with('company:id,name,hr_name,hr_email,logo_path')->latest();

        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            if ($validated['status'] === 'draft') {
                $query->where(function ($nested): void {
                    $nested
                        ->where('status', 'draft')
                        ->orWhere(function ($inner): void {
                            $inner
                                ->where('status', 'under_review')
                                ->whereDoesntHave('statusHistories', function ($history): void {
                                    $history->where('new_status', 'submitted');
                                });
                        });
                })->with([
                    'statusHistories' => function ($history): void {
                        $history
                            ->select(['id', 'form_id', 'form_type', 'new_status', 'created_at'])
                            ->latest();
                    },
                ]);
            } else {
                $query->where('status', $validated['status']);
            }
        } elseif (empty($validated['status'])) {
            $query->whereIn('status', ['submitted', 'under_review']);
        } elseif ($validated['status'] === 'all') {
            $query->with([
                'statusHistories' => function ($history): void {
                    $history
                        ->select(['id', 'form_id', 'form_type', 'new_status', 'created_at'])
                        ->latest();
                },
            ]);
        }

        $infs = $query->get()->map(function (Inf $inf) use ($validated) {
            $formData = is_array($inf->form_data) ? $inf->form_data : [];
            $batch = $formData['graduatingBatch'] ?? null;
            if (!$batch && isset($formData['eligibility'][0]['graduatingBatches'][0])) {
                $batch = $formData['eligibility'][0]['graduatingBatches'][0];
            }

            if (in_array(($validated['status'] ?? null), ['draft', 'all'], true)) {
                $isDraftReviewMarked = $this->isDraftReviewMarked($inf->statusHistories, (string) $inf->status);

                if ($isDraftReviewMarked) {
                    $inf->setAttribute('status', 'draft');
                }

                $inf->setAttribute('review_marked', $isDraftReviewMarked);
                $inf->unsetRelation('statusHistories');
            }

            $inf->setAttribute('graduating_batch', $batch ?: 'Unknown Batch');
            $inf->makeHidden(['form_data']);

            return $inf;
        });

        return response()->json([
            'infs' => $infs,
        ]);
    }

    public function showJnf(Request $request, Jnf $jnf): JsonResponse
    {
        $statusHistory = $jnf->statusHistories()->with('changedBy:id,name,email,role')->latest()->get();
        $isDraftReviewMarked = $this->isDraftReviewMarked($statusHistory, (string) $jnf->status);
        $canEditLatestRemark = $this->canEditLatestUnderReviewRemark($jnf, Jnf::class);
        $latestEditableRemarkId = $this->latestEditableUnderReviewRemarkIdForAdmin($request, $jnf, Jnf::class, $canEditLatestRemark);

        if ($isDraftReviewMarked) {
            $jnf->setAttribute('status', 'draft');
        }

        $companyEmail = $jnf->company?->hr_email;
        $statusHistory->each(function (FormStatusHistory $entry) use ($companyEmail): void {
            $entry->setAttribute('author_email', $this->resolveHistoryAuthorEmail($entry, $companyEmail));
        });

        $reviewEntry = $statusHistory->first(function (FormStatusHistory $entry): bool {
            return $entry->new_status === 'under_review' && $entry->changedBy !== null;
        });

        return response()->json([
            'jnf' => $jnf->load('company'),
            'review_marked' => $isDraftReviewMarked,
            'reviewed_by_email' => $reviewEntry?->changedBy?->email,
            'can_edit_latest_remark' => $canEditLatestRemark,
            'latest_editable_remark_id' => $latestEditableRemarkId,
            'status_history' => $statusHistory,
        ]);
    }

    public function showInf(Request $request, Inf $inf): JsonResponse
    {
        $statusHistory = $inf->statusHistories()->with('changedBy:id,name,email,role')->latest()->get();
        $isDraftReviewMarked = $this->isDraftReviewMarked($statusHistory, (string) $inf->status);
        $canEditLatestRemark = $this->canEditLatestUnderReviewRemark($inf, Inf::class);
        $latestEditableRemarkId = $this->latestEditableUnderReviewRemarkIdForAdmin($request, $inf, Inf::class, $canEditLatestRemark);

        if ($isDraftReviewMarked) {
            $inf->setAttribute('status', 'draft');
        }

        $companyEmail = $inf->company?->hr_email;
        $statusHistory->each(function (FormStatusHistory $entry) use ($companyEmail): void {
            $entry->setAttribute('author_email', $this->resolveHistoryAuthorEmail($entry, $companyEmail));
        });

        $reviewEntry = $statusHistory->first(function (FormStatusHistory $entry): bool {
            return $entry->new_status === 'under_review' && $entry->changedBy !== null;
        });

        return response()->json([
            'inf' => $inf->load('company'),
            'review_marked' => $isDraftReviewMarked,
            'reviewed_by_email' => $reviewEntry?->changedBy?->email,
            'can_edit_latest_remark' => $canEditLatestRemark,
            'latest_editable_remark_id' => $latestEditableRemarkId,
            'status_history' => $statusHistory,
        ]);
    }

    public function downloadJnfCsv(Jnf $jnf): StreamedResponse|JsonResponse
    {
        if ((string) $jnf->status !== 'accepted') {
            return response()->json([
                'message' => 'CSV download is available only for accepted JNFs.',
            ], 422);
        }

        $jnf->loadMissing('company:id,name,hr_name,hr_email,logo_path');
        $formData = is_array($jnf->form_data) ? $jnf->form_data : [];
        $companyProfile = is_array($formData['companyProfile'] ?? null) ? $formData['companyProfile'] : [];
        $salaryComponents = is_array($formData['salaryComponents'] ?? null) ? $formData['salaryComponents'] : [];

        $headers = [
            'jnf_id',
            'status',
            'company_name',
            'company_hr_name',
            'company_hr_email',
            'profile_company_name',
            'job_title',
            'job_designation',
            'job_location',
            'work_mode',
            'expected_hires',
            'minimum_hires',
            'joining_month',
            'skills',
            'registration_link',
            'job_description',
            'min_cgpa',
            'backlogs_allowed',
            'gender_filter',
            'slp_requirement',
            'min_tenth_percent',
            'min_twelfth_percent',
            'branch_backlog_caps',
            'graduating_batch',
            'eligible_branches',
            'ctc_min',
            'ctc_max',
            'programme_salaries_breakup',
            'joining_bonus',
            'retention_bonus',
            'performance_bonus',
            'esops_stock_options',
            'vesting_period',
            'stocks_rsus',
            'relocation_allowance',
            'medical_allowance',
            'deductions',
            'bond_amount',
            'bond_duration',
            'detailed_ctc_breakup',
            'selection_rounds',
            'admin_remarks',
            'form_submitted_at',
            'form_accepted_at',
        ];

        $eligibleBranches = $this->flattenSelectedBranches($formData['eligibility'] ?? []);
        $eligibleBranchesStr = implode('; ', $eligibleBranches);

        $progSalaries = is_array($formData['programmeSalaries'] ?? null) ? $formData['programmeSalaries'] : [];
        $progSalariesParts = [];
        foreach ($progSalaries as $ps) {
            if (is_array($ps) && ($ps['enabled'] ?? false) === true) {
                $prog = $ps['programme'] ?? 'Unknown';
                $ctc = $ps['ctcAnnual'] ?? '';
                $base = $ps['baseSalary'] ?? '';
                $take = $ps['takeHome'] ?? '';
                $progSalariesParts[] = "$prog: CTC $ctc, Base $base, Take-home $take";
            }
        }
        $programmeSalariesStr = implode('; ', $progSalariesParts);

        $rounds = is_array($formData['selectionRounds'] ?? null) ? $formData['selectionRounds'] : [];
        $activeRounds = [];
        $roundTypeNames = [
            'ppt' => 'Pre-Placement Talk',
            'resume' => 'Resume Shortlisting',
            'written_test' => 'Written Test',
            'aptitude_test' => 'Aptitude Test',
            'technical_test' => 'Technical Test',
            'group_discussion' => 'Group Discussion',
            'hr_interview' => 'HR Interview',
            'technical_interview' => 'Technical Interview',
            'psychometric' => 'Psychometric Test',
            'medical' => 'Medical Test',
            'other' => 'Other',
        ];
        foreach ($rounds as $r) {
            if (is_array($r) && ($r['enabled'] ?? false) === true) {
                $type = $r['type'] ?? 'unknown';
                $typeName = $roundTypeNames[$type] ?? ucfirst(str_replace('_', ' ', $type));
                $mode = $r['mode'] ?? '';
                $duration = $r['duration'] ?? '';
                $details = $r['details'] ?? $r['description'] ?? '';
                $infra = $r['infraRequirement'] ?? '';

                $roundDetails = $typeName;
                $subParts = [];
                if ($mode) $subParts[] = "Mode: $mode";
                if ($duration) $subParts[] = "Duration: $duration";
                if ($details) $subParts[] = "Details: " . trim(strip_tags(html_entity_decode($details, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($infra) $subParts[] = "Infra: $infra";
                
                if (!empty($subParts)) {
                    $roundDetails .= " (" . implode(', ', $subParts) . ")";
                }
                $activeRounds[] = $roundDetails;
            }
        }
        $selectionRoundsStr = implode('; ', $activeRounds);

        $submittedEntry = $jnf->statusHistories()
            ->where('new_status', 'review_pending')
            ->orderBy('created_at', 'asc')
            ->first();
        $submittedAt = $submittedEntry 
            ? $submittedEntry->created_at 
            : ($jnf->statusHistories()->orderBy('created_at', 'asc')->first()?->created_at ?? $jnf->created_at);

        $acceptedEntry = $jnf->statusHistories()
            ->where('new_status', 'accepted')
            ->orderBy('created_at', 'desc')
            ->first();
        $acceptedAt = $acceptedEntry ? $acceptedEntry->created_at : $jnf->updated_at;

        $row = [
            (string) $jnf->id,
            (string) $jnf->status,
            (string) ($jnf->company?->name ?? ''),
            (string) ($jnf->company?->hr_name ?? ''),
            (string) ($jnf->company?->hr_email ?? ''),
            (string) ($companyProfile['name'] ?? ''),
            (string) ($formData['jobTitle'] ?? $jnf->job_title ?? ''),
            (string) ($formData['jobDesignation'] ?? ''),
            (string) ($formData['jobLocation'] ?? $jnf->job_location ?? ''),
            (string) ($formData['workMode'] ?? ''),
            (string) ($formData['expectedHires'] ?? $jnf->vacancies ?? ''),
            (string) ($formData['minimumHires'] ?? ''),
            (string) ($formData['joiningMonth'] ?? ''),
            $this->csvValue($formData['skills'] ?? []),
            (string) ($formData['registrationLink'] ?? ''),
            $this->cleanHtmlField($formData['jobDescription'] ?? $jnf->job_description ?? ''),
            (string) ($formData['globalCgpa'] ?? '7.0'),
            (isset($formData['globalBacklogs']) ? ((bool) $formData['globalBacklogs'] ? 'Yes' : 'No') : 'No'),
            (string) ($formData['genderFilter'] ?? 'all'),
            (string) ($formData['slpRequirement'] ?? ''),
            (string) ($formData['minTenthPercent'] ?? ''),
            (string) ($formData['minTwelfthPercent'] ?? ''),
            implode('; ', $this->flattenBacklogCaps($formData['eligibility'] ?? [])),
            (string) ($formData['graduatingBatch'] ?? $jnf->graduating_batch ?? ''),
            $eligibleBranchesStr,
            (string) ($jnf->ctc_min ?? ''),
            (string) ($jnf->ctc_max ?? ''),
            $programmeSalariesStr,
            (string) ($salaryComponents['joiningBonus'] ?? ''),
            (string) ($salaryComponents['retentionBonus'] ?? ''),
            (string) ($salaryComponents['performanceBonus'] ?? ''),
            (string) ($salaryComponents['esops'] ?? ''),
            (string) ($salaryComponents['vestPeriod'] ?? ''),
            (string) ($salaryComponents['stocks'] ?? ''),
            (string) ($salaryComponents['relocationAllowance'] ?? $salaryComponents['relocationBonus'] ?? ''),
            (string) ($salaryComponents['medicalAllowance'] ?? ''),
            (string) ($salaryComponents['deductions'] ?? ''),
            (string) ($salaryComponents['bondAmount'] ?? ''),
            (string) ($salaryComponents['bondDuration'] ?? $salaryComponents['bondYears'] ?? ''),
            $this->cleanHtmlField($salaryComponents['ctcBreakup'] ?? ''),
            $selectionRoundsStr,
            (string) ($jnf->admin_remarks ?? ''),
            $this->formatIstTime($submittedAt),
            $this->formatIstTime($acceptedAt),
        ];

        return $this->streamCsvDownload(
            fileName: sprintf('accepted-jnf-%d.csv', $jnf->id),
            headers: $headers,
            row: $row,
        );
    }

    public function downloadInfCsv(Inf $inf): StreamedResponse|JsonResponse
    {
        if ((string) $inf->status !== 'accepted') {
            return response()->json([
                'message' => 'CSV download is available only for accepted INFs.',
            ], 422);
        }

        $inf->loadMissing('company:id,name,hr_name,hr_email,logo_path');
        $formData = is_array($inf->form_data) ? $inf->form_data : [];
        $companyProfile = is_array($formData['companyProfile'] ?? null) ? $formData['companyProfile'] : [];

        $headers = [
            'inf_id',
            'status',
            'company_name',
            'company_hr_name',
            'company_hr_email',
            'profile_company_name',
            'internship_title',
            'internship_designation',
            'internship_location',
            'work_mode',
            'expected_hires',
            'duration_weeks',
            'joining_month',
            'skills',
            'registration_link',
            'internship_description',
            'min_cgpa',
            'backlogs_allowed',
            'gender_filter',
            'slp_requirement',
            'min_tenth_percent',
            'min_twelfth_percent',
            'branch_backlog_caps',
            'graduating_batch',
            'eligible_branches',
            'stipend',
            'programme_stipends_breakup',
            'ppo_provision',
            'ppo_ctc',
            'selection_rounds',
            'admin_remarks',
            'form_submitted_at',
            'form_accepted_at',
        ];

        $eligibleBranches = $this->flattenSelectedBranches($formData['eligibility'] ?? []);
        $eligibleBranchesStr = implode('; ', $eligibleBranches);

        $progStipends = is_array($formData['programmeStipends'] ?? null) ? $formData['programmeStipends'] : [];
        $progStipendsParts = [];
        foreach ($progStipends as $ps) {
            if (is_array($ps) && ($ps['enabled'] ?? false) === true) {
                $prog = $ps['programme'] ?? 'Unknown';
                $stipend = $ps['baseStipend'] ?? $ps['stipend'] ?? '';
                $hra = $ps['hra'] ?? '';
                $other = $ps['otherAllowances'] ?? $ps['otherPerks'] ?? '';
                $progStipendsParts[] = "$prog: Stipend $stipend, HRA $hra, Other Allowances $other";
            }
        }
        $programmeStipendsStr = implode('; ', $progStipendsParts);

        $rounds = is_array($formData['selectionRounds'] ?? null) ? $formData['selectionRounds'] : [];
        $activeRounds = [];
        $roundTypeNames = [
            'ppt' => 'Pre-Placement Talk',
            'resume' => 'Resume Shortlisting',
            'written_test' => 'Written Test',
            'aptitude_test' => 'Aptitude Test',
            'technical_test' => 'Technical Test',
            'group_discussion' => 'Group Discussion',
            'hr_interview' => 'HR Interview',
            'technical_interview' => 'Technical Interview',
            'psychometric' => 'Psychometric Test',
            'medical' => 'Medical Test',
            'other' => 'Other',
        ];
        foreach ($rounds as $r) {
            if (is_array($r) && ($r['enabled'] ?? false) === true) {
                $type = $r['type'] ?? 'unknown';
                $typeName = $roundTypeNames[$type] ?? ucfirst(str_replace('_', ' ', $type));
                $mode = $r['mode'] ?? '';
                $duration = $r['duration'] ?? '';
                $details = $r['details'] ?? $r['description'] ?? '';
                $infra = $r['infraRequirement'] ?? '';

                $roundDetails = $typeName;
                $subParts = [];
                if ($mode) $subParts[] = "Mode: $mode";
                if ($duration) $subParts[] = "Duration: $duration";
                if ($details) $subParts[] = "Details: " . trim(strip_tags(html_entity_decode($details, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($infra) $subParts[] = "Infra: $infra";
                
                if (!empty($subParts)) {
                    $roundDetails .= " (" . implode(', ', $subParts) . ")";
                }
                $activeRounds[] = $roundDetails;
            }
        }
        $selectionRoundsStr = implode('; ', $activeRounds);

        $submittedEntry = $inf->statusHistories()
            ->where('new_status', 'review_pending')
            ->orderBy('created_at', 'asc')
            ->first();
        $submittedAt = $submittedEntry 
            ? $submittedEntry->created_at 
            : ($inf->statusHistories()->orderBy('created_at', 'asc')->first()?->created_at ?? $inf->created_at);

        $acceptedEntry = $inf->statusHistories()
            ->where('new_status', 'accepted')
            ->orderBy('created_at', 'desc')
            ->first();
        $acceptedAt = $acceptedEntry ? $acceptedEntry->created_at : $inf->updated_at;

        $row = [
            (string) $inf->id,
            (string) $inf->status,
            (string) ($inf->company?->name ?? ''),
            (string) ($inf->company?->hr_name ?? ''),
            (string) ($inf->company?->hr_email ?? ''),
            (string) ($companyProfile['name'] ?? ''),
            (string) ($formData['internshipTitle'] ?? $inf->internship_title ?? ''),
            (string) ($formData['internshipDesignation'] ?? ''),
            (string) ($formData['internshipLocation'] ?? $inf->internship_location ?? ''),
            (string) ($formData['workMode'] ?? ''),
            (string) ($formData['expectedHires'] ?? $inf->vacancies ?? ''),
            (string) ($formData['duration'] ?? $inf->internship_duration_weeks ?? ''),
            (string) ($formData['joiningMonth'] ?? ''),
            $this->csvValue($formData['skills'] ?? []),
            (string) ($formData['registrationLink'] ?? ''),
            $this->cleanHtmlField($formData['internshipDescription'] ?? $inf->internship_description ?? ''),
            (string) ($formData['globalCgpa'] ?? '7.0'),
            (isset($formData['globalBacklogs']) ? ((bool) $formData['globalBacklogs'] ? 'Yes' : 'No') : 'No'),
            (string) ($formData['genderFilter'] ?? 'all'),
            (string) ($formData['slpRequirement'] ?? ''),
            (string) ($formData['minTenthPercent'] ?? ''),
            (string) ($formData['minTwelfthPercent'] ?? ''),
            implode('; ', $this->flattenBacklogCaps($formData['eligibility'] ?? [])),
            (string) ($formData['graduatingBatch'] ?? $inf->graduating_batch ?? ''),
            $eligibleBranchesStr,
            (string) ($inf->stipend ?? ''),
            $programmeStipendsStr,
            (isset($formData['ppoProvision']) ? ((bool) $formData['ppoProvision'] ? 'Yes' : 'No') : 'No'),
            (string) ($formData['ppoCtc'] ?? ''),
            $selectionRoundsStr,
            (string) ($inf->admin_remarks ?? ''),
            $this->formatIstTime($submittedAt),
            $this->formatIstTime($acceptedAt),
        ];

        return $this->streamCsvDownload(
            fileName: sprintf('accepted-inf-%d.csv', $inf->id),
            headers: $headers,
            row: $row,
        );
    }

    public function addJnfNote(Request $request, Jnf $jnf): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        if (! $this->isDraftItem($jnf)) {
            return response()->json(['message' => 'Notes can only be added to draft items.'], 422);
        }

        FormStatusHistory::create([
            'form_type' => Jnf::class,
            'form_id' => $jnf->getKey(),
            'old_status' => (string) $jnf->status,
            // Keep draft forms in draft status, but mark review context through history.
            'new_status' => 'under_review',
            'changed_by' => $request->user()?->id,
            'remarks' => 'NOTE: ' . trim($validated['note']),
        ]);
        $this->audit->log($request, 'form.note', $jnf, null, ['note' => trim($validated['note'])]);

        $this->notifyAdminsForCompanyFormAction(
            request: $request,
            title: 'Admin Action: JNF Draft Note',
            message: sprintf(
                'Draft note added for JNF "%s" (%s).',
                $jnf->job_title,
                $jnf->company?->name ?? 'Unknown Company'
            )
        );

        return response()->json(['message' => 'Draft note added successfully.']);
    }

    public function addInfNote(Request $request, Inf $inf): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        if (! $this->isDraftItem($inf)) {
            return response()->json(['message' => 'Notes can only be added to draft items.'], 422);
        }

        FormStatusHistory::create([
            'form_type' => Inf::class,
            'form_id' => $inf->getKey(),
            'old_status' => (string) $inf->status,
            // Keep draft forms in draft status, but mark review context through history.
            'new_status' => 'under_review',
            'changed_by' => $request->user()?->id,
            'remarks' => 'NOTE: ' . trim($validated['note']),
        ]);
        $this->audit->log($request, 'form.note', $inf, null, ['note' => trim($validated['note'])]);

        $this->notifyAdminsForCompanyFormAction(
            request: $request,
            title: 'Admin Action: INF Draft Note',
            message: sprintf(
                'Draft note added for INF "%s" (%s).',
                $inf->internship_title,
                $inf->company?->name ?? 'Unknown Company'
            )
        );

        return response()->json(['message' => 'Draft note added successfully.']);
    }

    public function updateLatestJnfRemark(Request $request, Jnf $jnf): JsonResponse
    {
        $validated = $request->validate([
            'remark' => ['required', 'string', 'max:2000'],
        ]);

        $updatedRemark = $this->updateLatestRemarkForAdmin(
            request: $request,
            form: $jnf,
            formType: Jnf::class,
            remark: trim($validated['remark'])
        );

        if ($updatedRemark === null) {
            return response()->json([
                'message' => 'No editable remark found for your account on this JNF.',
            ], 422);
        }

        $this->notifyAdminsForCompanyFormAction(
            request: $request,
            title: 'Admin Action: JNF Review Remark',
            message: sprintf(
                'Latest review remark updated for JNF "%s" (%s).',
                $jnf->job_title,
                $jnf->company?->name ?? 'Unknown Company'
            )
        );

        return response()->json([
            'message' => 'Latest remark updated successfully.',
            'remark' => $updatedRemark,
            'jnf' => $jnf->fresh(),
        ]);
    }

    public function updateLatestInfRemark(Request $request, Inf $inf): JsonResponse
    {
        $validated = $request->validate([
            'remark' => ['required', 'string', 'max:2000'],
        ]);

        $updatedRemark = $this->updateLatestRemarkForAdmin(
            request: $request,
            form: $inf,
            formType: Inf::class,
            remark: trim($validated['remark'])
        );

        if ($updatedRemark === null) {
            return response()->json([
                'message' => 'No editable remark found for your account on this INF.',
            ], 422);
        }

        $this->notifyAdminsForCompanyFormAction(
            request: $request,
            title: 'Admin Action: INF Review Remark',
            message: sprintf(
                'Latest review remark updated for INF "%s" (%s).',
                $inf->internship_title,
                $inf->company?->name ?? 'Unknown Company'
            )
        );

        return response()->json([
            'message' => 'Latest remark updated successfully.',
            'remark' => $updatedRemark,
            'inf' => $inf->fresh(),
        ]);
    }

    public function updateJnfStatus(Request $request, Jnf $jnf): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,under_review,accepted,rejected'],
            'admin_remarks' => ['nullable', 'string'],
        ]);

        // Phase 2 (D66): a form that is live on the student job board must stay accepted.
        if ($validated['status'] !== 'accepted' && ($blocked = $this->floatedFormGuard($jnf))) {
            return $blocked;
        }

        $isDraftReviewAction = $validated['status'] === 'under_review' && $jnf->status === 'draft';

        if (in_array($validated['status'], ['under_review', 'rejected'], true) && ! $isDraftReviewAction && blank($validated['admin_remarks'] ?? null)) {
            return response()->json([
                'message' => 'Admin remarks are required for review or rejection.',
            ], 422);
        }

        $jnfHistory = $jnf->statusHistories()->latest()->get(['new_status', 'created_at']);
        $jnfDraftReviewMarked = $this->isDraftReviewMarked($jnfHistory, (string) $jnf->status);

        if ($validated['status'] === 'draft' && $jnf->status !== 'draft' && ! $jnfDraftReviewMarked) {
            return response()->json(['message' => 'Only draft items can remove review mark.'], 422);
        }

        $changed = $this->transitionFormStatus(
            request: $request,
            form: $jnf,
            formType: Jnf::class,
            title: 'JNF',
            subject: $jnf->job_title,
            status: $validated['status'],
            remarks: $validated['admin_remarks'] ?? null,
        );

        if ($changed) {
            $this->notifyAdminsForCompanyFormAction(
                request: $request,
                title: 'Admin Action: JNF Status',
                message: sprintf(
                    'Status set to %s for JNF "%s" (%s).',
                    $validated['status'],
                    $jnf->job_title,
                    $jnf->company?->name ?? 'Unknown Company'
                )
            );
        }

        return response()->json([
            'message' => 'JNF status updated successfully.',
            'jnf' => $jnf->fresh(),
        ]);
    }

    public function updateInfStatus(Request $request, Inf $inf): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,under_review,accepted,rejected'],
            'admin_remarks' => ['nullable', 'string'],
        ]);

        // Phase 2 (D66): a form that is live on the student job board must stay accepted.
        if ($validated['status'] !== 'accepted' && ($blocked = $this->floatedFormGuard($inf))) {
            return $blocked;
        }

        $isDraftReviewAction = $validated['status'] === 'under_review' && $inf->status === 'draft';

        if (in_array($validated['status'], ['under_review', 'rejected'], true) && ! $isDraftReviewAction && blank($validated['admin_remarks'] ?? null)) {
            return response()->json([
                'message' => 'Admin remarks are required for review or rejection.',
            ], 422);
        }

        $infHistory = $inf->statusHistories()->latest()->get(['new_status', 'created_at']);
        $infDraftReviewMarked = $this->isDraftReviewMarked($infHistory, (string) $inf->status);

        if ($validated['status'] === 'draft' && $inf->status !== 'draft' && ! $infDraftReviewMarked) {
            return response()->json(['message' => 'Only draft items can remove review mark.'], 422);
        }

        $changed = $this->transitionFormStatus(
            request: $request,
            form: $inf,
            formType: Inf::class,
            title: 'INF',
            subject: $inf->internship_title,
            status: $validated['status'],
            remarks: $validated['admin_remarks'] ?? null,
        );

        if ($changed) {
            $this->notifyAdminsForCompanyFormAction(
                request: $request,
                title: 'Admin Action: INF Status',
                message: sprintf(
                    'Status set to %s for INF "%s" (%s).',
                    $validated['status'],
                    $inf->internship_title,
                    $inf->company?->name ?? 'Unknown Company'
                )
            );
        }

        return response()->json([
            'message' => 'INF status updated successfully.',
            'inf' => $inf->fresh(),
        ]);
    }

    private function transitionFormStatus(
        Request $request,
        Model $form,
        string $formType,
        string $title,
        string $subject,
        string $status,
        ?string $remarks
    ): bool {
        $oldStatus = (string) $form->getAttribute('status');
        $statusHistory = $form->statusHistories()->latest()->get(['new_status', 'created_at']);
        $currentDraftReviewMarked = $this->isDraftReviewMarked($statusHistory, $oldStatus);

        $isDraftMarkedForReview = $oldStatus === 'draft' && $status === 'under_review';
        $isDraftReviewUnmarked = $status === 'draft' && $currentDraftReviewMarked;
        $storedStatus = $oldStatus === 'draft' ? 'draft' : $status;
        $effectiveRemarks = ($isDraftMarkedForReview || $isDraftReviewUnmarked) ? null : $remarks;

        if (! $isDraftMarkedForReview && ! $isDraftReviewUnmarked && $oldStatus === $status && $effectiveRemarks === null) {
            return false;
        }

        $form->setAttribute('status', $storedStatus);
        if ($effectiveRemarks !== null) {
            $form->setAttribute('admin_remarks', $effectiveRemarks);
        }
        $form->save();

        $history = FormStatusHistory::create([
            'form_type' => $formType,
            'form_id' => $form->getKey(),
            'old_status' => $oldStatus,
            'new_status' => $isDraftReviewUnmarked ? 'draft' : $status,
            'changed_by' => $request->user()?->id,
            'remarks' => $effectiveRemarks
                ?? ($isDraftMarkedForReview
                    ? 'Draft marked for review.'
                    : ($isDraftReviewUnmarked ? 'Draft review mark removed.' : 'Status updated by admin.')),
        ]);
        $this->audit->log($request, 'form.status', $form, ['status' => $oldStatus], ['status' => $history->new_status, 'remarks' => $history->remarks]);

        $company = $form->company;
        if (! $company || $isDraftMarkedForReview || $isDraftReviewUnmarked) {
            return true;
        }

        foreach ($company->users as $user) {
            $notificationType = $status === 'accepted' ? 'success' : ($status === 'rejected' ? 'error' : 'info');

            $this->notificationService->createInAppNotification(
                user: $user,
                title: sprintf('%s Status Updated', $title),
                message: sprintf('%s status changed to %s for "%s".', $title, $status, $subject),
                type: $notificationType
            );

            $this->notificationService->sendLoggedEmail(
                user: $user,
                mailable: new FormStatusChangedMail(
                    formType: $title,
                    title: $subject,
                    status: $status,
                    remarks: $effectiveRemarks,
                ),
                subject: sprintf('%s Status Updated: %s', $title, $subject),
                template: 'form-status-changed'
            );
        }

        // Ensure company receives status updates even if no linked company users exist.
        if ($company->users->isEmpty() && ! empty($company->hr_email)) {
            Mail::to($company->hr_email)->send(new FormStatusChangedMail(
                formType: $title,
                title: $subject,
                status: $status,
                remarks: $effectiveRemarks,
            ));
        }

        return true;
    }

    private function notifyAdminsForCompanyFormAction(Request $request, string $title, string $message): void
    {
        $actorEmail = $request->user()?->email ?? 'unknown-admin';
        $admins = User::query()->where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $this->notificationService->createInAppNotification(
                user: $admin,
                title: $title,
                message: sprintf('%s by %s.', $message, $actorEmail),
                type: 'info'
            );
        }
    }

    private function isDraftReviewMarked(Collection $statusHistory, string $currentStatus): bool
    {
        // Backward compatibility for older records that were persisted as under_review.
        if ($currentStatus === 'under_review') {
            return ! $statusHistory->contains(function (FormStatusHistory $entry): bool {
                return $entry->new_status === 'submitted';
            });
        }

        if ($currentStatus !== 'draft') {
            return false;
        }

        $latest = $statusHistory->first();

        return $latest instanceof FormStatusHistory && $latest->new_status === 'under_review';
    }

    private function isDraftItem(Model $form): bool
    {
        $status = (string) $form->getAttribute('status');

        if ($status === 'draft') {
            return true;
        }

        if ($status !== 'under_review') {
            return false;
        }

        $statusHistory = $form->statusHistories()->latest()->get(['new_status', 'created_at']);

        return $this->isDraftReviewMarked($statusHistory, $status);
    }

    private function resolveHistoryAuthorEmail(FormStatusHistory $entry, ?string $companyEmail = null): ?string
    {
        $changedBy = $entry->changedBy;

        if ($changedBy === null) {
            return $companyEmail;
        }

        if (($changedBy->role ?? null) === 'company') {
            return $changedBy->email ?: $companyEmail;
        }

        return $changedBy->email;
    }

    private function updateLatestRemarkForAdmin(Request $request, Model $form, string $formType, string $remark): ?string
    {
        $adminId = $request->user()?->id;

        if ($adminId === null) {
            return null;
        }

        if (! $this->canEditLatestUnderReviewRemark($form, $formType)) {
            return null;
        }

        $historyEntry = FormStatusHistory::query()
            ->where('form_type', $formType)
            ->where('form_id', $form->getKey())
            ->where('changed_by', $adminId)
            ->where('new_status', 'under_review')
            ->whereNotNull('remarks')
            ->where('remarks', 'not like', 'NOTE:%')
            ->latest()
            ->first();

        if (! $historyEntry) {
            return null;
        }

        $before = ['remark' => $historyEntry->remarks];
        $historyEntry->update([
            'remarks' => $remark,
        ]);

        $form->setAttribute('admin_remarks', $remark);
        $form->save();
        $this->audit->log($request, 'form.remark_edit', $form, $before, ['remark' => $remark]);

        return $remark;
    }

    private function canEditLatestUnderReviewRemark(Model $form, string $formType): bool
    {
        $latestUnderReviewAt = FormStatusHistory::query()
            ->where('form_type', $formType)
            ->where('form_id', $form->getKey())
            ->where('new_status', 'under_review')
            ->latest()
            ->value('created_at');

        if ($latestUnderReviewAt === null) {
            return false;
        }

        $hasResubmittedAfterReview = FormStatusHistory::query()
            ->where('form_type', $formType)
            ->where('form_id', $form->getKey())
            ->where('new_status', 'submitted')
            ->where('created_at', '>', $latestUnderReviewAt)
            ->exists();

        return ! $hasResubmittedAfterReview;
    }

    private function latestEditableUnderReviewRemarkIdForAdmin(
        Request $request,
        Model $form,
        string $formType,
        bool $canEditLatestRemark
    ): ?int {
        if (! $canEditLatestRemark) {
            return null;
        }

        $adminId = $request->user()?->id;

        if ($adminId === null) {
            return null;
        }

        $id = FormStatusHistory::query()
            ->where('form_type', $formType)
            ->where('form_id', $form->getKey())
            ->where('changed_by', $adminId)
            ->where('new_status', 'under_review')
            ->whereNotNull('remarks')
            ->where('remarks', 'not like', 'NOTE:%')
            ->latest()
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function editJnfFormData(Request $request, Jnf $jnf): JsonResponse
    {
        return $this->editFormData($request, $jnf, Jnf::class, 'JNF', $jnf->job_title);
    }

    public function editInfFormData(Request $request, Inf $inf): JsonResponse
    {
        return $this->editFormData($request, $inf, Inf::class, 'INF', $inf->internship_title);
    }

    private function editFormData(Request $request, Model $form, string $formType, string $title, string $subject): JsonResponse
    {
        $status = (string) $form->getAttribute('status');

        $validated = $request->validate([
            'form_data' => ['required', 'array'],
        ]);

        $oldFormData = is_array($form->getAttribute('form_data')) ? $form->getAttribute('form_data') : [];
        $newFormData = $validated['form_data'];

        $changedFields = $this->detectChangedFields($oldFormData, $newFormData, $formType);

        if (empty($changedFields)) {
            return response()->json(['message' => 'No changes detected.']);
        }

        // Update form_data and top-level columns that mirror form_data fields
        $form->setAttribute('form_data', $newFormData);

        if ($formType === Jnf::class) {
            if (isset($newFormData['jobTitle'])) {
                $form->setAttribute('job_title', $newFormData['jobTitle']);
            }
            if (isset($newFormData['jobDescription'])) {
                $form->setAttribute('job_description', $newFormData['jobDescription']);
            }
            if (isset($newFormData['jobLocation'])) {
                $form->setAttribute('job_location', $newFormData['jobLocation']);
            }
            if (isset($newFormData['expectedHires']) && is_numeric($newFormData['expectedHires'])) {
                $form->setAttribute('vacancies', (int) $newFormData['expectedHires']);
            }

            // Sync ctc_min and ctc_max based on enabled programmeSalaries
            if (isset($newFormData['programmeSalaries']) && is_array($newFormData['programmeSalaries'])) {
                $enabledSalaries = collect($newFormData['programmeSalaries'])
                    ->filter(fn ($sal) => is_array($sal) && ($sal['enabled'] ?? false) === true);
                if ($enabledSalaries->isNotEmpty()) {
                    $ctcValues = $enabledSalaries
                        ->map(fn ($sal) => ($sal['ctcAnnual'] !== null && $sal['ctcAnnual'] !== '') ? (float) $sal['ctcAnnual'] : null)
                        ->filter(fn ($val) => $val !== null);
                    if ($ctcValues->isNotEmpty()) {
                        $form->setAttribute('ctc_min', (int) $ctcValues->min());
                        $form->setAttribute('ctc_max', (int) $ctcValues->max());
                    } else {
                        $form->setAttribute('ctc_min', null);
                        $form->setAttribute('ctc_max', null);
                    }
                } else {
                    $form->setAttribute('ctc_min', null);
                    $form->setAttribute('ctc_max', null);
                }
            }
        } else {
            if (isset($newFormData['internshipTitle'])) {
                $form->setAttribute('internship_title', $newFormData['internshipTitle']);
            }
            if (isset($newFormData['internshipDescription'])) {
                $form->setAttribute('internship_description', $newFormData['internshipDescription']);
            }
            if (isset($newFormData['internshipLocation'])) {
                $form->setAttribute('internship_location', $newFormData['internshipLocation']);
            }
            if (isset($newFormData['expectedHires']) && is_numeric($newFormData['expectedHires'])) {
                $form->setAttribute('vacancies', (int) $newFormData['expectedHires']);
            }
            if (isset($newFormData['duration']) && is_numeric($newFormData['duration'])) {
                $form->setAttribute('internship_duration_weeks', (int) $newFormData['duration']);
            }

            // Sync stipend based on enabled programmeStipends
            if (isset($newFormData['programmeStipends']) && is_array($newFormData['programmeStipends'])) {
                $enabledStipends = collect($newFormData['programmeStipends'])
                    ->filter(fn ($st) => is_array($st) && ($st['enabled'] ?? false) === true);
                if ($enabledStipends->isNotEmpty()) {
                    $stipendValues = $enabledStipends
                        ->map(fn ($st) => ($st['baseStipend'] !== null && $st['baseStipend'] !== '') ? (float) $st['baseStipend'] : null)
                        ->filter(fn ($val) => $val !== null);
                    if ($stipendValues->isNotEmpty()) {
                        $form->setAttribute('stipend', (int) $stipendValues->first());
                    } else {
                        $form->setAttribute('stipend', null);
                    }
                } else {
                    $form->setAttribute('stipend', null);
                }
            }
        }

        $form->save();

        $fieldListSummary = implode(', ', array_keys($changedFields));
        $remarks = 'Admin edited form fields: ' . $fieldListSummary;
        $this->audit->log($request, 'form.edit', $form, array_map(fn (array $c) => $c['old'], $changedFields), array_map(fn (array $c) => $c['new'], $changedFields));

        FormStatusHistory::create([
            'form_type' => $formType,
            'form_id' => $form->getKey(),
            'old_status' => $status,
            'new_status' => $status,
            'changed_by' => $request->user()?->id,
            'remarks' => $remarks,
        ]);

        // Notify company
        $company = $form->company;
        if ($company) {
            $mailable = new FormEditedByAdminMail($title, $subject, $changedFields);

            foreach ($company->users as $user) {
                $this->notificationService->createInAppNotification(
                    user: $user,
                    title: sprintf('%s Updated by Admin', $title),
                    message: sprintf('An admin edited your %s "%s". Fields changed: %s', $title, $subject, $fieldListSummary),
                    type: 'info'
                );

                $this->notificationService->sendLoggedEmail(
                    user: $user,
                    mailable: $mailable,
                    subject: sprintf('%s Updated by Admin: %s', $title, $subject),
                    template: 'form-edited-by-admin'
                );
            }

            if ($company->users->isEmpty() && ! empty($company->hr_email)) {
                try {
                    Mail::to($company->hr_email)->send($mailable);
                } catch (\Throwable) {
                    // Silently fail
                }
            }
        }

        // Notify other admins
        $this->notifyAdminsForCompanyFormAction(
            request: $request,
            title: sprintf('Admin Action: %s Edited', $title),
            message: sprintf(
                'Form data edited for %s "%s" (%s). Fields: %s',
                $title,
                $subject,
                $company?->name ?? 'Unknown Company',
                $fieldListSummary
            )
        );

        return response()->json([
            'message' => sprintf('%s form data updated successfully.', $title),
            'changed_fields' => array_keys($changedFields),
        ]);
    }

    /**
     * Refuse to move a floated form out of `accepted` while its posting is live (D66).
     */
    private function floatedFormGuard(Jnf|Inf $form): ?JsonResponse
    {
        $posting = $form->jobPosting()->first();

        if (! $posting || $posting->status === 'cancelled') {
            return null;
        }

        return response()->json([
            'message' => 'This form is live for students as a job posting. Cancel the posting first if it really needs to go back for review.',
        ], 422);
    }

    private function detectChangedFields(array $oldData, array $newData, string $formType): array
    {
        $changed = [];

        // Define JNF-specific and INF-specific keys
        if ($formType === Jnf::class) {
            $scalarKeys = [
                'jobTitle' => 'Job Title',
                'jobDesignation' => 'Job Designation',
                'jobLocation' => 'Job Location',
                'workMode' => 'Work Mode',
                'expectedHires' => 'Expected Hires',
                'minimumHires' => 'Minimum Hires',
                'joiningMonth' => 'Joining Month',
                'jobDescription' => 'Job Description',
                'additionalInfo' => 'Additional Info',
                'registrationLink' => 'Registration Link',
                'globalCgpa' => 'Minimum CGPA',
                'genderFilter' => 'Gender Filter',
                'slpRequirement' => 'SLP Requirement',
                'minTenthPercent' => 'Minimum 10th %',
                'minTwelfthPercent' => 'Minimum 12th %',
                'currency' => 'Currency',
            ];
        } else {
            $scalarKeys = [
                'internshipTitle' => 'Internship Title',
                'internshipDesignation' => 'Internship Designation',
                'internshipLocation' => 'Internship Location',
                'duration' => 'Duration (weeks)',
                'workMode' => 'Work Mode',
                'expectedHires' => 'Expected Hires',
                'joiningMonth' => 'Joining Month',
                'internshipDescription' => 'Internship Description',
                'additionalInfo' => 'Additional Info',
                'registrationLink' => 'Registration Link',
                'globalCgpa' => 'Minimum CGPA',
                'genderFilter' => 'Gender Filter',
                'slpRequirement' => 'SLP Requirement',
                'minTenthPercent' => 'Minimum 10th %',
                'minTwelfthPercent' => 'Minimum 12th %',
                'currency' => 'Currency',
            ];
        }

        foreach ($scalarKeys as $key => $label) {
            $oldVal = (string) ($oldData[$key] ?? '');
            $newVal = (string) ($newData[$key] ?? '');

            if ($oldVal !== $newVal) {
                $changed[$label] = ['old' => $oldVal ?: '—', 'new' => $newVal ?: '—'];
            }
        }

        // Boolean fields (properly casted)
        $oldBacklogs = isset($oldData['globalBacklogs']) ? (bool) $oldData['globalBacklogs'] : false;
        $newBacklogs = isset($newData['globalBacklogs']) ? (bool) $newData['globalBacklogs'] : false;
        if ($oldBacklogs !== $newBacklogs) {
            $changed['Backlogs Allowed'] = [
                'old' => $oldBacklogs ? 'Yes' : 'No',
                'new' => $newBacklogs ? 'Yes' : 'No'
            ];
        }

        if ($formType === Inf::class) {
            $oldPpo = isset($oldData['ppoProvision']) ? (bool) $oldData['ppoProvision'] : false;
            $newPpo = isset($newData['ppoProvision']) ? (bool) $newData['ppoProvision'] : false;
            if ($oldPpo !== $newPpo) {
                $changed['PPO Provision'] = [
                    'old' => $oldPpo ? 'Yes' : 'No',
                    'new' => $newPpo ? 'Yes' : 'No'
                ];
            }

            $oldPpoCtc = (string) ($oldData['ppoCtc'] ?? '');
            $newPpoCtc = (string) ($newData['ppoCtc'] ?? '');
            if ($oldPpoCtc !== $newPpoCtc) {
                $changed['PPO CTC'] = ['old' => $oldPpoCtc ?: '—', 'new' => $newPpoCtc ?: '—'];
            }
        }

        // JNF Salary components scalar fields
        if ($formType === Jnf::class) {
            $salaryComponentKeys = [
                'joiningBonus' => 'Joining Bonus',
                'retentionBonus' => 'Retention Bonus',
                'performanceBonus' => 'Performance/Variable Bonus',
                'esops' => 'ESOPs / Stock Options',
                'vestPeriod' => 'Vesting Period',
                'stocks' => 'Stocks/RSUs',
                'relocationAllowance' => 'Relocation Allowance',
                'relocationBonus' => 'Relocation Bonus (Legacy)',
                'medicalAllowance' => 'Medical Allowance / Insurance',
                'deductions' => 'Deductions (PF, Tax, etc.)',
                'bondAmount' => 'Bond Amount (if any)',
                'bondDuration' => 'Bond Duration',
                'bondYears' => 'Bond (Years) (Legacy)',
                'ctcBreakup' => 'Detailed CTC Breakup',
            ];
            $oldComponents = is_array($oldData['salaryComponents'] ?? null) ? $oldData['salaryComponents'] : [];
            $newComponents = is_array($newData['salaryComponents'] ?? null) ? $newData['salaryComponents'] : [];
            foreach ($salaryComponentKeys as $key => $label) {
                $oldVal = (string) ($oldComponents[$key] ?? '');
                $newVal = (string) ($newComponents[$key] ?? '');
                if ($oldVal !== $newVal) {
                    $changed[$label] = ['old' => $oldVal ?: '—', 'new' => $newVal ?: '—'];
                }
            }
        }

        // Programme Salaries summary (JNF)
        if ($formType === Jnf::class && (isset($newData['programmeSalaries']) || isset($oldData['programmeSalaries']))) {
            $oldSalaries = is_array($oldData['programmeSalaries'] ?? null) ? $oldData['programmeSalaries'] : [];
            $newSalaries = is_array($newData['programmeSalaries'] ?? null) ? $newData['programmeSalaries'] : [];
            $salaryDiffs = [];

            $allProgrammes = array_unique(array_merge(
                collect($oldSalaries)->pluck('programme')->toArray(),
                collect($newSalaries)->pluck('programme')->toArray()
            ));

            foreach ($allProgrammes as $prog) {
                if (empty($prog)) continue;
                $oldSal = collect($oldSalaries)->firstWhere('programme', $prog);
                $newSal = collect($newSalaries)->firstWhere('programme', $prog);

                $oldEnabled = (bool) ($oldSal['enabled'] ?? false);
                $newEnabled = (bool) ($newSal['enabled'] ?? false);

                if ($oldEnabled !== $newEnabled) {
                    $salaryDiffs[] = sprintf('%s: %s → %s', $prog, $oldEnabled ? 'Enabled' : 'Disabled', $newEnabled ? 'Enabled' : 'Disabled');
                } elseif ($newEnabled) {
                    $oldCtc = (string) ($oldSal['ctcAnnual'] ?? '');
                    $newCtc = (string) ($newSal['ctcAnnual'] ?? '');
                    $oldBase = (string) ($oldSal['baseSalary'] ?? '');
                    $newBase = (string) ($newSal['baseSalary'] ?? '');
                    $oldTakeHome = (string) ($oldSal['takeHome'] ?? '');
                    $newTakeHome = (string) ($newSal['takeHome'] ?? '');

                    $subChanges = [];
                    if ($oldCtc !== $newCtc) {
                        $subChanges[] = sprintf('CTC %s → %s', $oldCtc ?: '—', $newCtc ?: '—');
                    }
                    if ($oldBase !== $newBase) {
                        $subChanges[] = sprintf('Base %s → %s', $oldBase ?: '—', $newBase ?: '—');
                    }
                    if ($oldTakeHome !== $newTakeHome) {
                        $subChanges[] = sprintf('Take Home %s → %s', $oldTakeHome ?: '—', $newTakeHome ?: '—');
                    }

                    if (!empty($subChanges)) {
                        $salaryDiffs[] = sprintf('%s (%s)', $prog, implode(', ', $subChanges));
                    }
                }
            }

            if (! empty($salaryDiffs)) {
                $changed['Programme Salaries'] = [
                    'old' => 'Active program salary details updated',
                    'new' => implode('; ', $salaryDiffs),
                ];
            }
        }

        // Programme Stipends summary (INF)
        if ($formType === Inf::class && (isset($newData['programmeStipends']) || isset($oldData['programmeStipends']))) {
            $oldStipends = is_array($oldData['programmeStipends'] ?? null) ? $oldData['programmeStipends'] : [];
            $newStipends = is_array($newData['programmeStipends'] ?? null) ? $newData['programmeStipends'] : [];
            $stipendDiffs = [];

            $allProgrammes = array_unique(array_merge(
                collect($oldStipends)->pluck('programme')->toArray(),
                collect($newStipends)->pluck('programme')->toArray()
            ));

            foreach ($allProgrammes as $prog) {
                if (empty($prog)) continue;
                $oldStip = collect($oldStipends)->firstWhere('programme', $prog);
                $newStip = collect($newStipends)->firstWhere('programme', $prog);

                $oldEnabled = (bool) ($oldStip['enabled'] ?? false);
                $newEnabled = (bool) ($newStip['enabled'] ?? false);

                if ($oldEnabled !== $newEnabled) {
                    $stipendDiffs[] = sprintf('%s: %s → %s', $prog, $oldEnabled ? 'Enabled' : 'Disabled', $newEnabled ? 'Enabled' : 'Disabled');
                } elseif ($newEnabled) {
                    $oldBase = (string) ($oldStip['baseStipend'] ?? $oldStip['stipend'] ?? '');
                    $newBase = (string) ($newStip['baseStipend'] ?? $newStip['stipend'] ?? '');
                    $oldHra = (string) ($oldStip['hra'] ?? '');
                    $newHra = (string) ($newStip['hra'] ?? '');
                    $oldOther = (string) ($oldStip['otherAllowances'] ?? $oldStip['otherPerks'] ?? '');
                    $newOther = (string) ($newStip['otherAllowances'] ?? $newStip['otherPerks'] ?? '');

                    $subChanges = [];
                    if ($oldBase !== $newBase) {
                        $subChanges[] = sprintf('Stipend %s → %s', $oldBase ?: '—', $newBase ?: '—');
                    }
                    if ($oldHra !== $newHra) {
                        $subChanges[] = sprintf('HRA %s → %s', $oldHra ?: '—', $newHra ?: '—');
                    }
                    if ($oldOther !== $newOther) {
                        $subChanges[] = sprintf('Other Allowances %s → %s', $oldOther ?: '—', $newOther ?: '—');
                    }

                    if (!empty($subChanges)) {
                        $stipendDiffs[] = sprintf('%s (%s)', $prog, implode(', ', $subChanges));
                    }
                }
            }

            if (! empty($stipendDiffs)) {
                $changed['Programme Stipends'] = [
                    'old' => 'Active program stipend details updated',
                    'new' => implode('; ', $stipendDiffs),
                ];
            }
        }

        // Selection Process (Rounds) diff (JNF and INF)
        if (isset($newData['selectionRounds']) || isset($oldData['selectionRounds'])) {
            $oldRounds = is_array($oldData['selectionRounds'] ?? null) ? $oldData['selectionRounds'] : [];
            $newRounds = is_array($newData['selectionRounds'] ?? null) ? $newData['selectionRounds'] : [];
            $roundDiffs = [];

            $roundTypeNames = [
                'ppt' => 'Pre-Placement Talk',
                'resume' => 'Resume Shortlisting',
                'written_test' => 'Written Test',
                'aptitude_test' => 'Aptitude Test',
                'technical_test' => 'Technical Test',
                'group_discussion' => 'Group Discussion',
                'hr_interview' => 'HR Interview',
                'technical_interview' => 'Technical Interview',
                'psychometric' => 'Psychometric Test',
                'medical' => 'Medical Test',
                'other' => 'Other',
            ];

            $allTypes = array_unique(array_merge(
                collect($oldRounds)->pluck('type')->toArray(),
                collect($newRounds)->pluck('type')->toArray()
            ));

            foreach ($allTypes as $type) {
                if (empty($type)) continue;
                $oldRound = collect($oldRounds)->firstWhere('type', $type);
                $newRound = collect($newRounds)->firstWhere('type', $type);

                $oldEnabled = (bool) ($oldRound['enabled'] ?? false);
                $newEnabled = (bool) ($newRound['enabled'] ?? false);
                $oldMode = (string) ($oldRound['mode'] ?? '');
                $newMode = (string) ($newRound['mode'] ?? '');

                $typeName = $roundTypeNames[$type] ?? ucfirst(str_replace('_', ' ', $type));

                if ($oldEnabled !== $newEnabled) {
                    $roundDiffs[] = sprintf('%s: %s → %s', $typeName, $oldEnabled ? 'Enabled' : 'Disabled', $newEnabled ? 'Enabled' : 'Disabled');
                } elseif ($newEnabled && $oldMode !== $newMode) {
                    $roundDiffs[] = sprintf('%s Mode: %s → %s', $typeName, $oldMode ?: 'not specified', $newMode ?: 'not specified');
                }
            }

            if (! empty($roundDiffs)) {
                $changed['Selection Process'] = [
                    'old' => 'Rounds updated',
                    'new' => implode('; ', $roundDiffs),
                ];
            }
        }

        // Array fields (skills)
        if (isset($newData['skills']) || isset($oldData['skills'])) {
            $oldSkills = is_array($oldData['skills'] ?? null) ? $oldData['skills'] : [];
            $newSkills = is_array($newData['skills'] ?? null) ? $newData['skills'] : [];
            sort($oldSkills);
            sort($newSkills);

            if ($oldSkills !== $newSkills) {
                $changed['Skills'] = [
                    'old' => implode(', ', $oldSkills) ?: '—',
                    'new' => implode(', ', $newSkills) ?: '—',
                ];
            }
        }

        // Eligibility – summarise selected branch changes
        if (isset($newData['eligibility']) || isset($oldData['eligibility'])) {
            $oldBranches = $this->flattenSelectedBranches($oldData['eligibility'] ?? []);
            $newBranches = $this->flattenSelectedBranches($newData['eligibility'] ?? []);

            $added = array_diff($newBranches, $oldBranches);
            $removed = array_diff($oldBranches, $newBranches);

            if (! empty($added) || ! empty($removed)) {
                $parts = [];
                if (! empty($added)) {
                    $parts[] = 'Added: ' . implode(', ', array_slice($added, 0, 10)) . (count($added) > 10 ? sprintf(' +%d more', count($added) - 10) : '');
                }
                if (! empty($removed)) {
                    $parts[] = 'Removed: ' . implode(', ', array_slice($removed, 0, 10)) . (count($removed) > 10 ? sprintf(' +%d more', count($removed) - 10) : '');
                }

                $changed['Eligible Branches'] = [
                    'old' => count($oldBranches) . ' branches selected',
                    'new' => count($newBranches) . ' branches selected (' . implode('; ', $parts) . ')',
                ];
            }

            // Cut-offs of branches selected both before and after — an edit to only these is a change too (QA F-007).
            $oldRules = $this->flattenBranchRules($oldData['eligibility'] ?? []);
            $ruleDiffs = [];
            foreach (array_intersect_key($this->flattenBranchRules($newData['eligibility'] ?? []), $oldRules) as $branch => $rule) {
                if ($oldRules[$branch] !== $rule) {
                    $ruleDiffs[] = sprintf('%s: %s → %s', $branch, $oldRules[$branch], $rule);
                }
            }

            if ($ruleDiffs !== []) {
                $changed['Branch Cut-offs'] = [
                    'old' => count($ruleDiffs) . ' branch(es) changed',
                    'new' => implode('; ', array_slice($ruleDiffs, 0, 10)) . (count($ruleDiffs) > 10 ? sprintf(' +%d more', count($ruleDiffs) - 10) : ''),
                ];
            }
        }

        return $changed;
    }

    /**
     * Each selected branch's own cut-offs, keyed "Branch (Programme)".
     *
     * @return array<string, string>
     */
    private function flattenBranchRules(mixed $eligibility): array
    {
        $rules = [];

        foreach (is_array($eligibility) ? $eligibility : [] as $programme) {
            foreach (is_array($programme['branches'] ?? null) ? $programme['branches'] : [] as $branch) {
                if (! is_array($branch) || ($branch['selected'] ?? false) !== true) {
                    continue;
                }

                $backlogs = (bool) ($branch['backlogsAllowed'] ?? false);
                $parts = ['CGPA ≥ ' . (trim((string) ($branch['cgpa'] ?? '')) ?: '—'), $backlogs ? 'backlogs allowed' : 'no backlogs'];
                foreach (['maxOngoingBacklogs' => 'ongoing', 'maxTotalBacklogs' => 'total'] as $key => $label) {
                    if ($backlogs && trim((string) ($branch[$key] ?? '')) !== '') {
                        $parts[] = sprintf('%s ≤ %s', $label, trim((string) $branch[$key]));
                    }
                }

                $rules[sprintf('%s (%s)', $branch['branch'] ?? 'Unknown', $programme['programme'] ?? '')] = implode(', ', $parts);
            }
        }

        return $rules;
    }

    /**
     * @return string[]
     */
    /**
     * Phase 2 numeric backlog caps per selected branch, e.g. "Mining Engineering (B.Tech ...): ongoing ≤ 1, total ≤ 2".
     *
     * @return list<string>
     */
    private function flattenBacklogCaps(mixed $eligibility): array
    {
        $caps = [];

        foreach (is_array($eligibility) ? $eligibility : [] as $programme) {
            foreach (is_array($programme['branches'] ?? null) ? $programme['branches'] : [] as $branch) {
                if (! is_array($branch) || ($branch['selected'] ?? false) !== true || ! ($branch['backlogsAllowed'] ?? false)) {
                    continue;
                }

                $parts = [];
                foreach (['maxOngoingBacklogs' => 'ongoing', 'maxTotalBacklogs' => 'total'] as $key => $label) {
                    if (isset($branch[$key]) && trim((string) $branch[$key]) !== '') {
                        $parts[] = sprintf('%s ≤ %s', $label, trim((string) $branch[$key]));
                    }
                }

                if ($parts !== []) {
                    $caps[] = sprintf('%s (%s): %s', $branch['branch'] ?? 'Unknown', $programme['programme'] ?? '', implode(', ', $parts));
                }
            }
        }

        sort($caps);

        return $caps;
    }

    private function flattenSelectedBranches(mixed $eligibility): array
    {
        if (! is_array($eligibility)) {
            return [];
        }

        $selected = [];

        foreach ($eligibility as $programme) {
            if (! is_array($programme)) {
                continue;
            }

            $programmeName = $programme['programme'] ?? '';
            $branches = $programme['branches'] ?? [];

            if (! is_array($branches)) {
                continue;
            }

            foreach ($branches as $branch) {
                if (is_array($branch) && ($branch['selected'] ?? false) === true) {
                    $selected[] = ($branch['branch'] ?? 'Unknown') . ' (' . $programmeName . ')';
                }
            }
        }

        sort($selected);

        return $selected;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string> $row
     */
    private function streamCsvDownload(string $fileName, array $headers, array $row): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $row): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fputcsv($output, $headers);
            fputcsv($output, $row);
            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function csvValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            if ($value === []) {
                return '';
            }

            if (array_is_list($value)) {
                return implode('; ', array_map(fn ($item): string => $this->csvValue($item), $value));
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return '';
    }

    private function cleanHtmlField(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = str_replace(["\xc2\xa0", '&nbsp;'], ' ', $decoded);
        return trim(strip_tags($decoded));
    }

    private function formatIstTime(mixed $date): string
    {
        if ($date === null) {
            return '';
        }
        if (!$date instanceof \Carbon\Carbon) {
            $date = \Carbon\Carbon::parse($date);
        }
        return $date->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s') . ' IST';
    }
}