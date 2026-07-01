<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJnfRequest;
use App\Mail\CompanyFormSubmissionConfirmationMail;
use App\Mail\EditAccessRequestedMail;
use App\Mail\FormSubmittedMail;
use App\Models\EmailLog;
use App\Models\FormStatusHistory;
use App\Models\Jnf;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class CompanyJnfController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $items = $company->jnfs()
            ->select(['id', 'job_title', 'status', 'updated_at', 'created_at', 'company_id', 'edit_access_requested_at', 'form_data'])
            ->with(['statusHistories:id,form_id,form_type,new_status'])
            ->latest()
            ->get();

        $items->each(function (Jnf $item): void {
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

        return response()->json(['jnfs' => $items]);
    }

    public function show(Request $request, Jnf $jnf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $jnf->company_id !== $company->id) {
            return response()->json(['message' => 'JNF not found.'], 404);
        }

        $this->hydrateLegacyFormDataIfMissing($jnf);
        $jnf->refresh();
        $jnf->loadMissing('company:id,name,logo_path');

        $statusHistory = $jnf->statusHistories()->latest()->get();
        $this->applyCompanyVisibleStatus($jnf, $statusHistory);

        return response()->json([
            'jnf' => $jnf,
            'status_history' => $statusHistory,
        ]);
    }

    public function store(StoreJnfRequest $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $status = $request->input('status', 'draft');
        $validated = $request->validated();

        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $decoded = json_decode($validated['form_data'], true);
            $validated['form_data'] = is_array($decoded) ? $decoded : null;
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

        $jnf = Jnf::create($data);

        FormStatusHistory::create([
            'form_type' => Jnf::class,
            'form_id' => $jnf->id,
            'old_status' => null,
            'new_status' => $status,
            'changed_by' => $request->user()?->id,
            'remarks' => $status === 'draft' ? 'Auto-saved draft created.' : 'Form created.',
        ]);

        if ($status === 'submitted') {
            $this->sendSubmissionEmailAndNotify($request, $jnf);
        }

        return response()->json([
            'message' => $status === 'draft' ? 'JNF draft saved.' : 'JNF created successfully.',
            'jnf' => $jnf,
        ], 201);
    }

    public function update(StoreJnfRequest $request, Jnf $jnf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $jnf->company_id !== $company->id) {
            return response()->json(['message' => 'JNF not found.'], 404);
        }

        if (! in_array((string) $jnf->status, ['draft', 'under_review'], true)) {
            return response()->json([
                'message' => 'This JNF cannot be edited in its current status.',
            ], 422);
        }

        $oldStatus = (string) $jnf->status;
        $newStatus = (string) $request->input('status', $oldStatus);
        $validated = $request->validated();

        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $decoded = json_decode($validated['form_data'], true);
            $validated['form_data'] = is_array($decoded) ? $decoded : null;
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

        $jnf->fill($payload);
        $hasFormChanges = $jnf->isDirty(array_keys($validated));
        $jnf->update($payload);

        if ($oldStatus !== $newStatus || $hasFormChanges) {
            $remarks = $oldStatus !== $newStatus
                ? ($hasFormChanges ? 'Form updated by company and status changed.' : 'Status updated by company.')
                : 'Form updated by company.';

            FormStatusHistory::create([
                'form_type' => Jnf::class,
                'form_id' => $jnf->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $request->user()?->id,
                'remarks' => $remarks,
            ]);
        }

        if ($oldStatus !== 'submitted' && $newStatus === 'submitted') {
            $this->sendSubmissionEmailAndNotify($request, $jnf);
        }

        return response()->json([
            'message' => $newStatus === 'draft' ? 'JNF draft auto-saved.' : 'JNF updated successfully.',
            'jnf' => $jnf->fresh(),
        ]);
    }

    public function destroy(Request $request, Jnf $jnf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $jnf->company_id !== $company->id) {
            return response()->json(['message' => 'JNF not found.'], 404);
        }

        $jnf->delete();

        return response()->json(['message' => 'JNF deleted successfully.']);
    }

    public function autosave(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:jnfs,id'],
            'job_title' => ['required', 'string', 'max:255'],
            'job_description' => ['required', 'string', 'max:5000'],
            'job_location' => ['nullable', 'string', 'max:255'],
            'ctc_min' => ['nullable', 'integer', 'min:0'],
            'ctc_max' => ['nullable', 'integer', 'min:0', 'gte:ctc_min'],
            'vacancies' => ['nullable', 'integer', 'min:1'],
            'application_deadline' => ['nullable', 'date'],
            'admin_remarks' => ['nullable', 'string', 'max:2000'],
            'form_data' => ['nullable', 'string'],
        ]);

        $id = $validated['id'] ?? null;
        unset($validated['id']);

        // Parse form_data if it's a JSON string
        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $validated['form_data'] = json_decode($validated['form_data'], true);
        }

        if ($id !== null) {
            $jnf = Jnf::where('id', $id)->where('company_id', $company->id)->first();

            if (! $jnf) {
                return response()->json(['message' => 'JNF not found.'], 404);
            }

            if (! in_array((string) $jnf->status, ['draft', 'under_review'], true)) {
                return response()->json([
                    'message' => 'This JNF cannot be edited in its current status.',
                ], 422);
            }

            $preservedStatus = (string) $jnf->status;
            $jnf->update(array_merge($validated, ['status' => $preservedStatus]));

            return response()->json([
                'message' => $preservedStatus === 'draft' ? 'JNF draft auto-saved.' : 'JNF auto-saved.',
                'jnf' => $jnf->fresh(),
            ]);
        }

        $jnf = Jnf::create(array_merge($validated, [
            'company_id' => $company->id,
            'status' => 'draft',
        ]));

        FormStatusHistory::create([
            'form_type' => Jnf::class,
            'form_id' => $jnf->id,
            'old_status' => null,
            'new_status' => 'draft',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Auto-saved draft created.',
        ]);

        return response()->json([
            'message' => 'JNF draft auto-saved.',
            'jnf' => $jnf,
        ], 201);
    }

    public function duplicate(Request $request, Jnf $jnf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $jnf->company_id !== $company->id) {
            return response()->json(['message' => 'JNF not found.'], 404);
        }

        // Create a copy with "Copy of" prefix
        $formData = $jnf->form_data;
        if ($formData) {
            if (is_string($formData)) {
                $formData = json_decode($formData, true);
            }
            if (is_array($formData)) {
                // Update title in form_data
                $formData['jobTitle'] = $formData['jobTitle'] ?? $jnf->job_title;
                
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

        $newJnf = Jnf::create([
            'company_id' => $company->id,
            'job_title' => $jnf->job_title,
            'job_description' => $jnf->job_description,
            'job_location' => $jnf->job_location,
            'ctc_min' => $jnf->ctc_min,
            'ctc_max' => $jnf->ctc_max,
            'vacancies' => $jnf->vacancies,
            'application_deadline' => $jnf->application_deadline,
            'form_data' => $formData,
            'status' => 'draft',
        ]);

        FormStatusHistory::create([
            'form_type' => Jnf::class,
            'form_id' => $newJnf->id,
            'old_status' => null,
            'new_status' => 'draft',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Duplicated from JNF #' . $jnf->id,
        ]);

        return response()->json([
            'message' => 'JNF duplicated successfully.',
            'id' => $newJnf->id,
            'jnf' => $newJnf,
        ], 201);
    }

    public function requestEditAccess(Request $request, Jnf $jnf): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company || $jnf->company_id !== $company->id) {
            return response()->json(['message' => 'JNF not found.'], 404);
        }

        if ($jnf->status !== 'submitted') {
            return response()->json(['message' => 'Edit access can only be requested for submitted JNFs.'], 422);
        }

        if ($jnf->edit_access_requested_at !== null) {
            return response()->json(['message' => 'Edit access has already been requested for this JNF.'], 409);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $jnf->update([
            'edit_access_requested_at' => now(),
            'edit_access_requested_reason' => trim($validated['reason']),
        ]);

        FormStatusHistory::create([
            'form_type' => Jnf::class,
            'form_id' => $jnf->id,
            'old_status' => 'submitted',
            'new_status' => 'submitted',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Edit access requested: ' . trim($validated['reason']),
        ]);

        $this->sendEditAccessRequestEmailAndNotify($jnf, $company->name, trim($validated['reason']));

        return response()->json([
            'message' => 'Edit access request submitted successfully.',
            'jnf' => $jnf->fresh(),
        ]);
    }

    private function sendEditAccessRequestEmailAndNotify(Jnf $jnf, string $companyName, string $reason): void
    {
        $admins = User::where('role', 'admin')->get();
        $reviewUrl = rtrim((string) config('app.frontend_url'), '/') . '/admin/jnfs/' . $jnf->id;

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new EditAccessRequestedMail(
                    'JNF',
                    $companyName,
                    $jnf->job_title,
                    $reason,
                    $reviewUrl
                ));

                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('JNF Edit Access Request: %s', $jnf->job_title),
                    'template' => 'edit-access-requested',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('JNF Edit Access Request: %s', $jnf->job_title),
                    'template' => 'edit-access-requested',
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);
            }

            PortalNotification::create([
                'user_id' => $admin->id,
                'title' => 'JNF Edit Access Request',
                'message' => sprintf('%s requested edit access for JNF: %s', $companyName, $jnf->job_title),
                'type' => 'warning',
            ]);
        }
    }

    private function sendSubmissionEmailAndNotify(Request $request, Jnf $jnf): void
    {
        $company = $request->user()?->company;

        if (! $company) {
            return;
        }

        $admins = User::where('role', 'admin')->get();
        $summary = $this->buildSubmissionSummary($jnf);
        $reviewUrl = rtrim((string) config('app.frontend_url'), '/') . '/admin/jnfs/' . $jnf->id;

        foreach ($admins as $admin) {
            try {
                Mail::to($admin->email)->send(new FormSubmittedMail(
                    'JNF',
                    $company->name,
                    $jnf->job_title,
                    $summary['role'],
                    $summary['location'],
                    $summary['compensation'],
                    $summary['eligibility'],
                    $reviewUrl
                ));

                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('JNF Submitted: %s', $jnf->job_title),
                    'template' => 'form-submitted',
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                EmailLog::create([
                    'user_id' => $admin->id,
                    'recipient_email' => $admin->email,
                    'subject' => sprintf('JNF Submitted: %s', $jnf->job_title),
                    'template' => 'form-submitted',
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);
            }

            PortalNotification::create([
                'user_id' => $admin->id,
                'title' => 'New JNF Submission',
                'message' => sprintf('%s submitted a JNF: %s', $company->name, $jnf->job_title),
                'type' => 'info',
            ]);
        }

        $companyFormUrl = rtrim((string) config('app.frontend_url'), '/') . '/company/jnf/' . $jnf->id;
        $this->sendCompanySubmissionConfirmation(
            request: $request,
            formType: 'JNF',
            formTitle: $jnf->job_title,
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
    private function buildSubmissionSummary(Jnf $jnf): array
    {
        $formData = is_array($jnf->form_data) ? $jnf->form_data : [];

        $role = $this->nullableString($formData['jobDesignation'] ?? null) ?? $this->nullableString($jnf->job_title);
        $location = $this->nullableString($formData['jobLocation'] ?? null) ?? $this->nullableString($jnf->job_location);

        $ctcMin = is_numeric($jnf->ctc_min) ? (int) $jnf->ctc_min : null;
        $ctcMax = is_numeric($jnf->ctc_max) ? (int) $jnf->ctc_max : null;

        if ($ctcMin !== null && $ctcMax !== null && $ctcMin !== $ctcMax) {
            $compensation = sprintf('INR %s - %s CTC', number_format($ctcMin), number_format($ctcMax));
        } elseif ($ctcMin !== null) {
            $compensation = sprintf('INR %s CTC', number_format($ctcMin));
        } else {
            $compensation = null;
        }

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

    private function hydrateLegacyFormDataIfMissing(Jnf $jnf): void
    {
        if (is_array($jnf->form_data) && count($jnf->form_data) > 0) {
            return;
        }

        $jnf->form_data = [
            'jobTitle' => $jnf->job_title ?? '',
            'jobDescription' => $jnf->job_description ?? '',
            'jobLocation' => $jnf->job_location ?? '',
            'expectedHires' => $jnf->vacancies !== null ? (string) $jnf->vacancies : '',
        ];

        $jnf->save();
    }

    private function applyCompanyVisibleStatus(Jnf $jnf, ?Collection $statusHistory = null): void
    {
        if ($jnf->status !== 'under_review') {
            return;
        }

        $history = $statusHistory;

        if ($history === null) {
            $history = $jnf->relationLoaded('statusHistories')
                ? $jnf->statusHistories
                : $jnf->statusHistories()->get(['new_status']);
        }

        $hasSubmitted = $history->contains(function (FormStatusHistory $entry): bool {
            return $entry->new_status === 'submitted';
        });

        if (! $hasSubmitted) {
            $jnf->setAttribute('status', 'draft');
        }
    }
}
