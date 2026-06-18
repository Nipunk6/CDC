<?php

namespace App\Http\Controllers;

use App\Mail\FormEditedByAdminMail;
use App\Mail\FormStatusChangedMail;
use App\Models\FormStatusHistory;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\User;
use App\Services\PortalNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminFormReviewController extends Controller
{
    public function __construct(private readonly PortalNotificationService $notificationService)
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
            'admin_remarks',
            'created_at',
            'updated_at',
        ];

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
            (string) ($formData['jobDescription'] ?? $jnf->job_description ?? ''),
            (string) ($jnf->admin_remarks ?? ''),
            (string) $jnf->created_at,
            (string) $jnf->updated_at,
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
            'admin_remarks',
            'created_at',
            'updated_at',
        ];

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
            (string) ($formData['internshipDescription'] ?? $inf->internship_description ?? ''),
            (string) ($inf->admin_remarks ?? ''),
            (string) $inf->created_at,
            (string) $inf->updated_at,
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

        FormStatusHistory::create([
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

        $historyEntry->update([
            'remarks' => $remark,
        ]);

        $form->setAttribute('admin_remarks', $remark);
        $form->save();

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

        $changedFields = $this->detectChangedFields($oldFormData, $newFormData);

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
        }

        $form->save();

        $fieldListSummary = implode(', ', array_keys($changedFields));
        $remarks = 'Admin edited form fields: ' . $fieldListSummary;

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
     * @return array<string, array{old: string, new: string}>
     */
    private function detectChangedFields(array $oldData, array $newData): array
    {
        $changed = [];

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
            'currency' => 'Currency',
            // INF-specific
            'internshipTitle' => 'Internship Title',
            'internshipDesignation' => 'Internship Designation',
            'internshipLocation' => 'Internship Location',
            'duration' => 'Duration (weeks)',
            'internshipDescription' => 'Internship Description',
        ];

        foreach ($scalarKeys as $key => $label) {
            $oldVal = (string) ($oldData[$key] ?? '');
            $newVal = (string) ($newData[$key] ?? '');

            if ($oldVal !== $newVal) {
                $changed[$label] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        // Boolean fields
        $boolKeys = ['globalBacklogs' => 'Backlogs Allowed'];
        foreach ($boolKeys as $key => $label) {
            $oldVal = (bool) ($oldData[$key] ?? false);
            $newVal = (bool) ($newData[$key] ?? false);

            if ($oldVal !== $newVal) {
                $changed[$label] = ['old' => $oldVal ? 'Yes' : 'No', 'new' => $newVal ? 'Yes' : 'No'];
            }
        }

        // Salary component scalar fields
        $salaryComponentKeys = [
            'joiningBonus' => 'Joining Bonus',
            'relocationBonus' => 'Relocation Bonus',
            'retentionBonus' => 'Retention Bonus',
            'esops' => 'ESOPs',
            'bondYears' => 'Bond (Years)',
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

        // Stipend component scalar fields (INF)
        $stipendComponentKeys = [
            'ppoCtc' => 'PPO CTC',
            'ppoProvision' => 'PPO Provision',
        ];
        foreach ($stipendComponentKeys as $key => $label) {
            $oldVal = (string) ($oldData[$key] ?? '');
            $newVal = (string) ($newData[$key] ?? '');
            if ($oldVal !== $newVal) {
                $changed[$label] = ['old' => $oldVal ?: '—', 'new' => $newVal ?: '—'];
            }
        }

        // Programme Salaries summary
        if (isset($newData['programmeSalaries']) || isset($oldData['programmeSalaries'])) {
            $oldSalaries = is_array($oldData['programmeSalaries'] ?? null) ? $oldData['programmeSalaries'] : [];
            $newSalaries = is_array($newData['programmeSalaries'] ?? null) ? $newData['programmeSalaries'] : [];
            $salaryDiffs = [];
            foreach ($newSalaries as $newSal) {
                if (! is_array($newSal) || ! ($newSal['enabled'] ?? false)) {
                    continue;
                }
                $prog = $newSal['programme'] ?? 'Unknown';
                $oldSal = collect($oldSalaries)->firstWhere('programme', $prog);
                $oldCtc = (string) ($oldSal['ctcAnnual'] ?? '');
                $newCtc = (string) ($newSal['ctcAnnual'] ?? '');
                if ($oldCtc !== $newCtc) {
                    $salaryDiffs[] = sprintf('%s: CTC %s → %s', $prog, $oldCtc ?: '—', $newCtc ?: '—');
                }
            }
            if (! empty($salaryDiffs)) {
                $changed['Programme Salaries'] = [
                    'old' => 'See details',
                    'new' => implode('; ', $salaryDiffs),
                ];
            }
        }

        // Programme Stipends summary (INF)
        if (isset($newData['programmeStipends']) || isset($oldData['programmeStipends'])) {
            $oldStipends = is_array($oldData['programmeStipends'] ?? null) ? $oldData['programmeStipends'] : [];
            $newStipends = is_array($newData['programmeStipends'] ?? null) ? $newData['programmeStipends'] : [];
            $stipendDiffs = [];
            foreach ($newStipends as $newStip) {
                if (! is_array($newStip) || ! ($newStip['enabled'] ?? false)) {
                    continue;
                }
                $prog = $newStip['programme'] ?? 'Unknown';
                $oldStip = collect($oldStipends)->firstWhere('programme', $prog);
                $oldAmt = (string) ($oldStip['stipend'] ?? '');
                $newAmt = (string) ($newStip['stipend'] ?? '');
                if ($oldAmt !== $newAmt) {
                    $stipendDiffs[] = sprintf('%s: %s → %s', $prog, $oldAmt ?: '—', $newAmt ?: '—');
                }
            }
            if (! empty($stipendDiffs)) {
                $changed['Programme Stipends'] = [
                    'old' => 'See details',
                    'new' => implode('; ', $stipendDiffs),
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
        }

        return $changed;
    }

    /**
     * @return string[]
     */
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
}