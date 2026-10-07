<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInfRequest;
use App\Http\Requests\StoreJnfRequest;
use App\Models\Company;
use App\Models\FormStatusHistory;
use App\Models\Inf;
use App\Models\Jnf;
use App\Services\AuditService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Add New Job" (S6.1, B2-1): the CDC fills the company JNF/INF wizard on a company's behalf,
 * picking or creating the company first. The endpoints mirror the company wizard endpoints
 * (profile, policy documents, autosave, store, update) but take the company from the URL.
 * A submitted form is accepted straight away (the CDC is the reviewer) and keeps the same
 * form_data contract, so floating, eligibility, presenters and exports treat it like any
 * accepted company form. No company mail is sent: the company may have no portal login.
 */
class AdminFormBuilderController extends Controller
{
    /** Per form type: model, the scalar columns the wizard sends, and the response key. */
    private const TYPES = [
        'jnf' => ['model' => Jnf::class, 'key' => 'jnf', 'label' => 'JNF', 'title' => 'job_title'],
        'inf' => ['model' => Inf::class, 'key' => 'inf', 'label' => 'INF', 'title' => 'internship_title'],
    ];

    public function __construct(private readonly AuditService $audit)
    {
    }

    /** Company picker: id, name and HR email by name, optionally filtered (first 50). */
    public function companies(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:255']]);

        $query = Company::query()->select(['id', 'name', 'hr_email'])->orderBy('name');

        if (! empty($validated['search'])) {
            $term = $validated['search'];
            \App\Support\Like::whereContains($query, ['name', 'hr_email'], $term); // literal % and _ (fix L28)
        }

        return response()->json([
            'companies' => $query->limit(50)->get()->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name, 'hr_email' => $c->hr_email]),
        ]);
    }

    /** "+ Add a new company": a company record without a login user. */
    public function storeCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'hr_name' => ['required', 'string', 'max:255'],
            'hr_email' => ['required', 'email', 'max:255', 'unique:companies,hr_email'],
            'website' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
        ], [
            'hr_email.unique' => 'A company with this HR email already exists. Pick it from the list instead.',
        ]);

        $company = Company::create([
            'name' => trim($validated['name']),
            'hr_name' => trim($validated['hr_name']),
            'hr_email' => strtolower(trim($validated['hr_email'])),
            'website' => $validated['website'] ?? null,
            'sector' => $validated['sector'] ?? null,
            'industry' => $validated['sector'] ?? null,
        ]);

        $this->audit->log($request, 'company.admin_create', $company, null, $company->only(['name', 'hr_name', 'hr_email', 'website', 'sector']));

        return response()->json([
            'message' => 'Company added.',
            'company' => ['id' => $company->id, 'name' => $company->name, 'hr_email' => $company->hr_email],
        ], 201);
    }

    /** Same shape as GET /company/profile, which the wizard reads to pre-fill the company step. */
    public function profile(Company $company): JsonResponse
    {
        return response()->json(['company' => $company]);
    }

    /** Same as GET /company/policy-documents (the declaration step's documents). */
    public function policyDocuments(Request $request, Company $company): JsonResponse
    {
        return app(PolicyDocumentController::class)->getForCompany($request);
    }

    public function autosaveJnf(Request $request, Company $company): JsonResponse
    {
        return $this->autosave($request, $company, 'jnf', [
            'job_title' => ['required', 'string', 'max:255'],
            'job_description' => ['required', 'string', 'max:5000'],
            'job_location' => ['nullable', 'string', 'max:255'],
            'ctc_min' => ['nullable', 'integer', 'min:0'],
            'ctc_max' => ['nullable', 'integer', 'min:0', 'gte:ctc_min'],
        ]);
    }

    public function autosaveInf(Request $request, Company $company): JsonResponse
    {
        return $this->autosave($request, $company, 'inf', [
            'internship_title' => ['required', 'string', 'max:255'],
            'internship_description' => ['required', 'string', 'max:5000'],
            'internship_location' => ['nullable', 'string', 'max:255'],
            'stipend' => ['nullable', 'integer', 'min:0'],
            'internship_duration_weeks' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    public function storeJnf(StoreJnfRequest $request, Company $company): JsonResponse
    {
        return $this->submit($request, $company, 'jnf', null);
    }

    public function updateJnf(StoreJnfRequest $request, Company $company, Jnf $jnf): JsonResponse
    {
        return $this->submit($request, $company, 'jnf', $jnf);
    }

    public function storeInf(StoreInfRequest $request, Company $company): JsonResponse
    {
        return $this->submit($request, $company, 'inf', null);
    }

    public function updateInf(StoreInfRequest $request, Company $company, Inf $inf): JsonResponse
    {
        return $this->submit($request, $company, 'inf', $inf);
    }

    /**
     * Mirrors Company{Jnf,Inf}Controller::autosave: the form stays a draft until it is submitted.
     *
     * @param  array<string, list<string>>  $typeRules
     */
    private function autosave(Request $request, Company $company, string $type, array $typeRules): JsonResponse
    {
        $config = self::TYPES[$type];
        /** @var class-string<Jnf|Inf> $model */
        $model = $config['model'];

        $validated = $request->validate(['id' => ['nullable', 'integer', 'exists:'.$type.'s,id']] + $typeRules + [
            'vacancies' => ['nullable', 'integer', 'min:1'],
            'application_deadline' => ['nullable', 'date'],
            'form_data' => ['nullable', 'string'],
        ]);

        $id = $validated['id'] ?? null;
        unset($validated['id']);

        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $validated['form_data'] = json_decode($validated['form_data'], true);
        }

        if ($id !== null) {
            $form = $model::where('id', $id)->where('company_id', $company->id)->first();

            if (! $form) {
                return response()->json(['message' => $config['label'].' not found.'], 404);
            }

            if (! in_array((string) $form->status, ['draft', 'under_review'], true)) {
                return response()->json(['message' => 'This '.$config['label'].' cannot be edited in its current status.'], 422);
            }

            $before = ['title' => $form->{$config['title']} ?? null, 'updated_at' => $form->updated_at?->toIso8601String()];
            $form->update(array_merge($validated, ['status' => (string) $form->status]));
            // Every admin write is audited, autosaves included (one small row; the form_data itself is not copied).
            $this->audit->log($request, 'form.admin_draft', $form, $before, ['title' => $form->{$config['title']} ?? null, 'company_id' => $company->id]);

            return response()->json([
                'message' => $config['label'].' draft auto-saved.',
                $config['key'] => $form->fresh(),
            ]);
        }

        $form = $model::create(array_merge($validated, ['company_id' => $company->id, 'status' => 'draft']));

        FormStatusHistory::create([
            'form_type' => $model,
            'form_id' => $form->id,
            'old_status' => null,
            'new_status' => 'draft',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Draft created by the CDC.',
        ]);
        $this->audit->log($request, 'form.admin_draft', $form, null, ['title' => $form->{$config['title']} ?? null, 'company_id' => $company->id, 'status' => 'draft']);

        return response()->json([
            'message' => $config['label'].' draft auto-saved.',
            $config['key'] => $form,
        ], 201);
    }

    /**
     * The wizard's final submit: validated by the company FormRequest, then accepted directly.
     */
    private function submit(FormRequest $request, Company $company, string $type, Jnf|Inf|null $form): JsonResponse
    {
        $config = self::TYPES[$type];
        /** @var class-string<Jnf|Inf> $model */
        $model = $config['model'];

        if ($form !== null) {
            if ((int) $form->company_id !== (int) $company->id) {
                return response()->json(['message' => $config['label'].' not found.'], 404);
            }

            if (! in_array((string) $form->status, ['draft', 'under_review'], true)) {
                return response()->json(['message' => 'This '.$config['label'].' cannot be edited in its current status.'], 422);
            }
        }

        $validated = $request->validated();
        unset($validated['status']);

        if (isset($validated['form_data']) && is_string($validated['form_data'])) {
            $decoded = json_decode($validated['form_data'], true);
            $validated['form_data'] = is_array($decoded) ? $decoded : null;
        }

        // Same rule as the company wizard: the joining month (YYYY-MM) must be in the future.
        if (! empty($validated['form_data']['joiningMonth'])) {
            $joiningDate = new \DateTime($validated['form_data']['joiningMonth'].'-01');
            if ($joiningDate <= new \DateTime()) {
                return response()->json([
                    'message' => 'Date of joining must be in the future.',
                    'errors' => ['joiningMonth' => ['Date of joining must be a future date.']],
                ], 422);
            }
        }

        $oldStatus = $form ? (string) $form->status : null;
        $payload = array_merge($validated, ['status' => 'accepted']);

        if ($form === null) {
            $form = $model::create($payload + ['company_id' => $company->id]);
        } else {
            $form->update($payload);
        }

        FormStatusHistory::create([
            'form_type' => $model,
            'form_id' => $form->id,
            'old_status' => $oldStatus,
            'new_status' => 'accepted',
            'changed_by' => $request->user()?->id,
            'remarks' => 'Created by the CDC.',
        ]);

        $this->audit->log($request, 'form.admin_create', $form, $oldStatus ? ['status' => $oldStatus] : null, [
            'form_type' => $type,
            'form_id' => $form->id,
            'company_id' => $company->id,
            'company' => $company->name,
            'title' => $form->{$config['title']},
            'status' => 'accepted',
        ]);

        return response()->json([
            'message' => $config['label'].' created and accepted. Open it for applications next.',
            $config['key'] => $form->fresh(),
        ], $oldStatus === null ? 201 : 200);
    }
}
