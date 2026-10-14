<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInfRequest;
use App\Mail\CompanyFormSubmissionConfirmationMail;
use App\Mail\EditAccessRequestedMail;
use App\Mail\FormSubmittedMail;
use App\Models\EmailLog;
use App\Models\FormStatusHistory;
use App\Models\Inf;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class CompanyInfController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $items = $company->infs()
            ->select(['id', 'internship_title', 'status', 'updated_at', 'created_at', 'company_id', 'edit_access_requested_at', 'form_data'])
            ->with(['statusHistories:id,form_id,form_type,new_status'])
            ->latest()
            ->get();

        $items->each(function (Inf $item): void {
            $this->applyCompanyVisibleStatus($item);
            $item->unsetRelation('statusHistories');

            $batch = 'Unknown Batch';
            if (is_array($item->form_data) && isset($item->form_data['graduatingBatch'])) {
                $batch = $item->form_data['graduatingBatch'];
            } elseif (is_array($item->form_data) && isset($item->form_data['eligibility']) && is_array($item->form_data['eligibility']) && count($item->form_data['eligibility']) > 0) {
                $batch = (string)($item->form_data['eligibility'][0]['batch'] ?? 'Unknown Batch');
            }
            $item->graduating_batch = $batch;
            unset($item->form_data);
        });

        return response()->json(['infs' => $items]);
    }

    public function show(Request $request, Inf $inf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $inf->company_id !== $company->id) {
            return response()->json(['message' => 'INF not found.'], 404);
        }

        $this->hydrateLegacyFormDataIfMissing($inf);
        $inf->refresh();
        $inf->loadMissing('company:id,name,logo_path');

        $statusHistory = $inf->statusHistories()->latest()->get();
        $this->applyCompanyVisibleStatus($inf, $statusHistory);

        return response()->json([
            'inf' => $inf,
            'status_history' => $statusHistory,
        ]);
    }

    public function store(StoreInfRequest $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $status = $request->input('status') ?? 'draft'; // F-040: an explicit null/blank status means the default
        $validated = $request->validated();
        unset($validated['admin_remarks']); // companies never write admin remarks

        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $decoded = json_decode($validated['form_data'], true);
            $validated['form_data'] = \App\Models\JobPosting::withoutAdminOnlyKeys(is_array($decoded) ? $decoded : null); // companies never set admin-only eligibility keys (fix M2)
        }

        // Validate joiningMonth is in the future
        if (isset($validated['form_data']['joiningMonth']) && !empty($validated['form_data']['joiningMonth'])) {
            $joiningMonth = $validated['form_data']['joiningMonth'];
            // joiningMonth is in YYYY-MM format, ensure it's after current month
            $currentDate = new \DateTime();
            $joiningDate = new \DateTime($joiningMonth . '-01');
            if ($joiningDate <= $currentDate) {
                return response()->json([
                    'message' => 'Date of joining must be in the future.',
                    'errors' => ['joiningMonth' => ['Date of joining must be a future date.']],
                ], 422);
            }
        }

        $data = array_merge($validated, [
            'company_id' => $company->id,
            'status' => $status,
        ]);

        $inf = Inf::create($data);

        FormStatusHistory::create([
            'form_type' => Inf::class,
            'form_id' => $inf->id,
            'old_status' => null,
            'new_status' => $status,
            'changed_by' => $request->user()?->id,
            'remarks' => $status === 'draft' ? 'Auto-saved draft created.' : 'Form created.',
        ]);

        if ($status === 'submitted') {
            $this->sendSubmissionEmailAndNotify($request, $inf);
        }

        return response()->json([
            'message' => $status === 'draft' ? 'INF draft saved.' : 'INF created successfully.',
            'inf' => $inf,
        ], 201);
    }

    public function update(StoreInfRequest $request, Inf $inf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $inf->company_id !== $company->id) {
            return response()->json(['message' => 'INF not found.'], 404);
        }

        if (! in_array((string) $inf->status, ['draft', 'under_review'], true)) {
            return response()->json([
                'message' => 'This INF cannot be edited in its current status.',
            ], 422);
        }

        $oldStatus = (string) $inf->status;
        $newStatus = (string) ($request->input('status') ?? $oldStatus); // F-040: null/blank keeps the current status
        $validated = $request->validated();
        unset($validated['admin_remarks']); // companies never write admin remarks

        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $decoded = json_decode($validated['form_data'], true);
            $validated['form_data'] = \App\Models\JobPosting::withoutAdminOnlyKeys(is_array($decoded) ? $decoded : null); // companies never set admin-only eligibility keys (fix M2)
        }

        // Validate joiningMonth is in the future
        if (isset($validated['form_data']['joiningMonth']) && !empty($validated['form_data']['joiningMonth'])) {
            $joiningMonth = $validated['form_data']['joiningMonth'];
            // joiningMonth is in YYYY-MM format, ensure it's after current month
            $currentDate = new \DateTime();
            $joiningDate = new \DateTime($joiningMonth . '-01');
            if ($joiningDate <= $currentDate) {
                return response()->json([
                    'message' => 'Date of joining must be in the future.',
                    'errors' => ['joiningMonth' => ['Date of joining must be a future date.']],
                ], 422);
            }
        }

        $payload = array_merge($validated, ['status' => $newStatus]);

        // Clear edit access request when form is resubmitted
        if ($newStatus === 'submitted') {
            $payload['edit_access_requested_at'] = null;
            $payload['edit_access_requested_reason'] = null;
        }

        $inf->fill($payload);
        $hasFormChanges = $inf->isDirty(array_keys($validated));
        $inf->update($payload);

        if ($oldStatus !== $newStatus || $hasFormChanges) {
            $remarks = $oldStatus !== $newStatus
                ? ($hasFormChanges ? 'Form updated by company and status changed.' : 'Status updated by company.')
                : 'Form updated by company.';

            FormStatusHistory::create([
                'form_type' => Inf::class,
                'form_id' => $inf->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $request->user()?->id,
                'remarks' => $remarks,
            ]);
        }

        if ($oldStatus !== 'submitted' && $newStatus === 'submitted') {
            $this->sendSubmissionEmailAndNotify($request, $inf);
        }

        return response()->json([
            'message' => $newStatus === 'draft' ? 'INF draft auto-saved.' : 'INF updated successfully.',
            'inf' => $inf->fresh(),
        ]);
    }

    public function destroy(Request $request, Inf $inf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $inf->company_id !== $company->id) {
            return response()->json(['message' => 'INF not found.'], 404);
        }

        $deletableStatuses = ['draft', 'submitted', 'under_review', 'accepted', 'rejected'];
        $isDraft = (string) $inf->status === 'draft';
        $isUnfloatedForm = in_array((string) $inf->status, $deletableStatuses, true) && ! $inf->isFloated();

        if (! $isDraft && ! $isUnfloatedForm) {
            return response()->json([
                'message' => 'This INF has been opened for applications and cannot be deleted.',
            ], 422);
        }

        $inf->delete();

        return response()->json(['message' => 'INF deleted successfully.']);
    }

    public function autosave(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $validated = $request->validate([
            // SEC-022: no `exists` rule — an unknown id and another company's id both get "INF not found." below.
            'id' => ['nullable', 'integer'],
            'internship_title' => ['required', 'string', 'max:255'],
            'internship_description' => ['required', 'string', 'max:5000'],
            'internship_location' => ['nullable', 'string', 'max:255'],
            'stipend' => ['nullable', 'integer', 'min:0'],
            'internship_duration_weeks' => ['nullable', 'integer', 'min:1'],
            'vacancies' => ['nullable', 'integer', 'min:1'],
            'application_deadline' => ['nullable', 'date'],
            'form_data' => ['nullable', 'string'],
        ]);

        $id = $validated['id'] ?? null;
        unset($validated['id']);

        // Parse form_data if it's a JSON string
        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $validated['form_data'] = \App\Models\JobPosting::withoutAdminOnlyKeys(json_decode($validated['form_data'], true) ?: null); // fix M2
        }

        if ($id !== null) {
            $inf = Inf::where('id', $id)->where('company_id', $company->id)->first();

            if (! $inf) {
                return response()->json(['message' => 'INF not found.'], 404);
            }

            if (! in_array((string) $inf->status, ['draft', 'under_review'], true)) {
                return response()->json([
                    'message' => 'This INF cannot be edited in its current status.',
                ], 422);
            }

            $preservedStatus = (string) $inf->status;
            $inf->update(array_merge($validated, ['status' => $preservedStatus]));

            return response()->json([
                'message' => $preservedStatus === 'draft' ? 'INF draft auto-saved.' : 'INF auto-saved.',
                'inf' => $inf->fresh(),
            ]);
        }

        $inf = Inf::create(array_merge($validated, [
            'company_id' => $company->id,
            'status' => 'draft',
        ]));

        FormStatusHistory::create([
            'form_type' => Inf::class,
            'form_id' => $inf->id,
            'old_status' => null,
            'new_status' => 'draft',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Auto-saved draft created.',
        ]);

        return response()->json([
            'message' => 'INF draft auto-saved.',
            'inf' => $inf,
        ], 201);
    }

    public function duplicate(Request $request, Inf $inf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $inf->company_id !== $company->id) {
            return response()->json(['message' => 'INF not found.'], 404);
        }

        // Create a copy with "Copy of" prefix
        $formData = $inf->form_data;
        if ($formData) {
            if (is_string($formData)) {
                $formData = json_decode($formData, true);
            }
            if (is_array($formData)) {
                // Update title in form_data
                $formData['internshipTitle'] = $formData['internshipTitle'] ?? $inf->internship_title;
                
                // Clear graduating batch
                $formData['graduatingBatch'] = '';
                if (isset($formData['eligibility']) && is_array($formData['eligibility'])) {
                    foreach ($formData['eligibility'] as $k => $prog) {
                        $formData['eligibility'][$k]['graduatingBatch'] = '';
                        $formData['eligibility'][$k]['graduatingBatches'] = [];
                    }
                }

                // Reset declarations to false
                $formData['declarations'] = [
                    'aipc' => false,
                    'shortlistCriteria' => false,
                    'infoVerified' => false,
                    'consentLogo' => false,
                    'confirmAccuracy' => false,
                    'resultsViaCdc' => false,
                ];

                // Reset signatory to empty
                $formData['signatory'] = [
                    'name' => '',
                    'designation' => '',
                    'date' => '',
                ];
            }
        }

        $newInf = Inf::create([
            'company_id' => $company->id,
            'internship_title' => $inf->internship_title,
            'internship_description' => $inf->internship_description,
            'internship_location' => $inf->internship_location,
            'stipend' => $inf->stipend,
            'internship_duration_weeks' => $inf->internship_duration_weeks,
            'vacancies' => $inf->vacancies,
            'application_deadline' => $inf->application_deadline,
            'form_data' => $formData,
            'status' => 'draft',
        ]);

        FormStatusHistory::create([
            'form_type' => Inf::class,
            'form_id' => $newInf->id,
            'old_status' => null,
            'new_status' => 'draft',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Duplicated from INF #' . $inf->id,
        ]);

        return response()->json([
            'message' => 'INF duplicated successfully.',
            'id' => $newInf->id,
            'inf' => $newInf,
        ], 201);
    }

    public function requestEditAccess(Request $request, Inf $inf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $inf->company_id !== $company->id) {
            return response()->json(['message' => 'INF not found.'], 404);
        }

        if ($inf->status !== 'submitted') {
            return response()->json(['message' => 'Edit access can only be requested for submitted INFs.'], 422);
        }

        if ($inf->edit_access_requested_at !== null) {
            return response()->json(['message' => 'Edit access has already been requested for this INF.'], 409);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $inf->update([
            'edit_access_requested_at' => now(),
            'edit_access_requested_reason' => trim($validated['reason']),
        ]);

        FormStatusHistory::create([
            'form_type' => Inf::class,
            'form_id' => $inf->id,
            'old_status' => 'submitted',
            'new_status' => 'submitted',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Edit access requested: ' . trim($validated['reason']),
        ]);

        $this->sendEditAccessRequestEmailAndNotify($inf, $company->name, trim($validated['reason']));

        return response()->json([
            'message' => 'Edit access request submitted successfully.',
            'inf' => $inf->fresh(),
        ]);
    }

    private function sendEditAccessRequestEmailAndNotify(Inf $inf, string $companyName, string $reason): void
    {
        $admins = User::where('role', 'admin')->get();
        $reviewUrl = rtrim((string) config('app.frontend_url'), '/') . '/admin/infs/' . $inf->id;

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new EditAccessRequestedMail(
                    'INF',
                    $companyName,
                    $inf->internship_title,
                    $reason,
                    $reviewUrl
                ));

                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('INF Edit Access Request: %s', $inf->internship_title),
                    'template' => 'edit-access-requested',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('INF Edit Access Request: %s', $inf->internship_title),
                    'template' => 'edit-access-requested',
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);
            }

            PortalNotification::create([
                'user_id' => $admin->id,
                'title' => 'INF Edit Access Request',
                'message' => sprintf('%s requested edit access for INF: %s', $companyName, $inf->internship_title),
                'type' => 'warning',
            ]);
        }
    }

    private function sendSubmissionEmailAndNotify(Request $request, Inf $inf): void
    {
        $company = $request->user()?->company;

        if (! $company) {
            return;
        }

        $admins = User::where('role', 'admin')->get();
        $summary = $this->buildSubmissionSummary($inf);
        $reviewUrl = rtrim((string) config('app.frontend_url'), '/') . '/admin/infs/' . $inf->id;

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new FormSubmittedMail(
                    'INF',
                    $company->name,
                    $inf->internship_title,
                    $summary['role'],
                    $summary['location'],
                    $summary['compensation'],
                    $summary['eligibility'],
                    $reviewUrl
                ));

                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('INF Submitted: %s', $inf->internship_title),
                    'template' => 'form-submitted',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('INF Submitted: %s', $inf->internship_title),
                    'template' => 'form-submitted',
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);
            }

            PortalNotification::create([
                'user_id' => $admin->id,
                'title' => 'New INF Submission',
                'message' => sprintf('%s submitted an INF: %s', $company->name, $inf->internship_title),
                'type' => 'info',
            ]);
        }

        $companyFormUrl = rtrim((string) config('app.frontend_url'), '/') . '/company/inf/' . $inf->id;
        $this->sendCompanySubmissionConfirmation(
            request: $request,
            formType: 'INF',
            formTitle: $inf->internship_title,
            formUrl: $companyFormUrl,
        );
    }

    private function sendCompanySubmissionConfirmation(
        Request $request,
        string $formType,
        string $formTitle,
        ?string $formUrl = null,
    ): void {
        $company = $request->user()?->company;

        if (! $company) {
            return;
        }

        $recipientMap = [];

        $companyUsers = $company->users()
            ->select(['id', 'email'])
            ->get();

        foreach ($companyUsers as $companyUser) {
            $email = strtolower(trim((string) $companyUser->email));

            if ($email === '') {
                continue;
            }

            $recipientMap[$email] = [
                'user_id' => $companyUser->id,
                'email' => $email,
            ];
        }

        $hrEmail = strtolower(trim((string) ($company->hr_email ?? '')));
        if ($hrEmail !== '' && ! array_key_exists($hrEmail, $recipientMap)) {
            $recipientMap[$hrEmail] = [
                'user_id' => null,
                'email' => $hrEmail,
            ];
        }

        $subject = sprintf('%s Submission Received: %s', $formType, $formTitle);

        foreach ($recipientMap as $recipient) {
            try {
                Mail::to($recipient['email'])->send(new CompanyFormSubmissionConfirmationMail(
                    formType: $formType,
                    companyName: $company->name,
                    title: $formTitle,
                    formUrl: $formUrl,
                ));

                EmailLog::create([
                    'user_id' => $recipient['user_id'],
                    'recipient_email' => $recipient['email'],
                    'subject' => $subject,
                    'template' => 'company-form-submission-confirmation',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                EmailLog::create([
                    'user_id' => $recipient['user_id'],
                    'recipient_email' => $recipient['email'],
                    'subject' => $subject,
                    'template' => 'company-form-submission-confirmation',
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array{role:?string,location:?string,compensation:?string,eligibility:?string}
     */
    private function buildSubmissionSummary(Inf $inf): array
    {
        $formData = is_array($inf->form_data) ? $inf->form_data : [];

        $role = $this->nullableString($formData['internshipDesignation'] ?? null) ?? $this->nullableString($inf->internship_title);
        $location = $this->nullableString($formData['internshipLocation'] ?? null) ?? $this->nullableString($inf->internship_location);

        $stipend = is_numeric($inf->stipend) ? (int) $inf->stipend : null;
        $compensation = $stipend !== null ? sprintf('INR %s / month stipend', number_format($stipend)) : null;

        $eligibility = $this->buildEligibilitySummary($formData['eligibility'] ?? null);

        return [
            'role' => $role,
            'location' => $location,
            'compensation' => $compensation,
            'eligibility' => $eligibility,
        ];
    }

    private function buildEligibilitySummary(mixed $eligibility): ?string
    {
        if (! is_array($eligibility)) {
            return null;
        }

        $selectedBranches = 0;
        $selectedProgrammes = [];

        foreach ($eligibility as $programme) {
            if (! is_array($programme)) {
                continue;
            }

            $programmeName = $this->nullableString($programme['programme'] ?? null);
            $branches = $programme['branches'] ?? [];

            if (! is_array($branches)) {
                continue;
            }

            foreach ($branches as $branch) {
                if (! is_array($branch)) {
                    continue;
                }

                if (($branch['selected'] ?? false) === true) {
                    $selectedBranches++;
                    if ($programmeName !== null) {
                        $selectedProgrammes[$programmeName] = true;
                    }
                }
            }
        }

        if ($selectedBranches === 0) {
            return null;
        }

        $programmeList = array_slice(array_keys($selectedProgrammes), 0, 2);
        $programmePreview = implode(', ', $programmeList);
        if (count($selectedProgrammes) > 2) {
            $programmePreview .= sprintf(' +%d more', count($selectedProgrammes) - 2);
        }

        if ($programmePreview === '') {
            return sprintf('%d branches selected', $selectedBranches);
        }

        return sprintf('%d branches selected across %s', $selectedBranches, $programmePreview);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function hydrateLegacyFormDataIfMissing(Inf $inf): void
    {
        if (is_array($inf->form_data) && count($inf->form_data) > 0) {
            return;
        }

        $inf->form_data = [
            'internshipTitle' => $inf->internship_title ?? '',
            'internshipDescription' => $inf->internship_description ?? '',
            'internshipLocation' => $inf->internship_location ?? '',
            'duration' => $inf->internship_duration_weeks !== null ? (string) $inf->internship_duration_weeks : '',
            'expectedHires' => $inf->vacancies !== null ? (string) $inf->vacancies : '',
        ];

        $inf->save();
    }

    private function applyCompanyVisibleStatus(Inf $inf, ?Collection $statusHistory = null): void
    {
        if ($inf->status !== 'under_review') {
            return;
        }

        $history = $statusHistory;

        if ($history === null) {
            $history = $inf->relationLoaded('statusHistories')
                ? $inf->statusHistories
                : $inf->statusHistories()->get(['new_status']);
        }

        $hasSubmitted = $history->contains(function (FormStatusHistory $entry): bool {
            return $entry->new_status === 'submitted';
        });

        if (! $hasSubmitted) {
            $inf->setAttribute('status', 'draft');
        }
    }
}
