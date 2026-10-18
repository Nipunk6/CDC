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

    /** Form-data keys that differ between the INF and the JNF wizard (INF key => JNF key). */
    private const TYPE_KEYS = [
        'internshipTitle' => 'jobTitle',
        'internshipDesignation' => 'jobDesignation',
        'internshipLocation' => 'jobLocation',
        'internshipDescription' => 'jobDescription',
    ];

    /** Pay and type-only fields that do not carry over when a form is duplicated as the other type. */
    private const TYPE_ONLY = [
        'inf' => ['duration', 'stipendSameForAll', 'programmeStipends', 'ppoProvision', 'ppoCtc'],
        'jnf' => ['salarySameForAll', 'programmeSalaries', 'salaryComponents'],
    ];

    /**
     * "Add New Job" landing page: recently accepted JNFs and INFs of every company, newest first, summarised for a
     * card (company, batch, location, pay, programmes) with their job profile if already opened for applications.
     */
    public function acceptedForms(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $term = trim((string) ($validated['search'] ?? ''));

        $cards = collect();
        foreach (self::TYPES as $type => $config) {
            /** @var class-string<Jnf|Inf> $model */
            $model = $config['model'];
            $query = $model::query()
                ->where('status', 'accepted')
                ->with(['company:id,name,hr_email,logo_path', 'jobPosting:id,postable_type,postable_id,status,placement_cycle_id,offer_type', 'jobPosting.placementCycle:id,name,type'])
                ->latest('updated_at')
                ->orderByDesc('id');

            if ($term !== '') {
                $query->where(function ($q) use ($term, $config): void {
                    \App\Support\Like::whereContains($q, [$config['title']], $term);
                    $q->orWhereHas('company', fn ($c) => \App\Support\Like::whereContains($c, ['name', 'hr_email'], $term));
                });
            }

            foreach ($query->limit(60)->get() as $form) {
                $cards->push($this->card($type, $form));
            }
        }

        return response()->json([
            'forms' => $cards->sortByDesc('updated_at')->values()->take(60),
        ]);
    }

    /**
     * "Duplicate" on the Add New Job page: a copy of any JNF/INF as a new draft of the chosen type for the same
     * company, opened in the wizard. Like the company's own duplicate, the batch, declarations and signatory are
     * cleared so the wizard asks for them again; pay fields only carry over within the same type.
     */
    public function duplicate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_type' => ['required', 'in:jnf,inf'],
            'form_id' => ['required', 'integer'],
            'as' => ['required', 'in:jnf,inf'],
        ]);

        /** @var class-string<Jnf|Inf> $sourceModel */
        $sourceModel = self::TYPES[$validated['form_type']]['model'];
        $source = $sourceModel::query()->with('company:id,name')->find($validated['form_id']);
        if (! $source || ! $source->company) {
            return response()->json(['message' => 'Form not found.'], 404);
        }

        $from = $validated['form_type'];
        $to = $validated['as'];
        $data = $source->form_data;
        $data = is_string($data) ? (json_decode($data, true) ?: []) : (is_array($data) ? $data : []);

        if ($from !== $to) {
            foreach (self::TYPE_KEYS as $infKey => $jnfKey) {
                [$old, $new] = $from === 'inf' ? [$infKey, $jnfKey] : [$jnfKey, $infKey];
                if (array_key_exists($old, $data)) {
                    $data[$new] = $data[$old];
                    unset($data[$old]);
                }
            }
            foreach (self::TYPE_ONLY[$from] as $key) {
                unset($data[$key]);
            }
        }

        unset($data['id']);
        $data['graduatingBatch'] = '';
        foreach (is_array($data['eligibility'] ?? null) ? $data['eligibility'] : [] as $k => $programme) {
            $data['eligibility'][$k]['graduatingBatch'] = '';
            $data['eligibility'][$k]['graduatingBatches'] = [];
        }
        $data['declarations'] = array_fill_keys(['aipc', 'shortlistCriteria', 'infoVerified', 'consentLogo', 'confirmAccuracy', 'resultsViaCdc'], false);
        $data['signatory'] = ['name' => '', 'designation' => '', 'date' => ''];

        $titleKey = $to === 'inf' ? 'internshipTitle' : 'jobTitle';
        $descriptionKey = $to === 'inf' ? 'internshipDescription' : 'jobDescription';
        $title = trim((string) ($data[$titleKey] ?? '')) ?: (string) $source->{self::TYPES[$from]['title']};
        $data[$titleKey] = $title;

        $config = self::TYPES[$to];
        /** @var class-string<Jnf|Inf> $targetModel */
        $targetModel = $config['model'];
        $description = trim(strip_tags((string) ($data[$descriptionKey] ?? ''))) !== '' ? (string) $data[$descriptionKey] : 'Draft in progress.';
        $copy = $targetModel::create([
            'company_id' => $source->company_id,
            $config['title'] => mb_substr($title, 0, 255),
            $to === 'inf' ? 'internship_description' : 'job_description' => mb_substr($description, 0, 5000),
            $to === 'inf' ? 'internship_location' : 'job_location' => $data[$to === 'inf' ? 'internshipLocation' : 'jobLocation'] ?? null,
            'status' => 'draft',
            'form_data' => $data,
        ]);

        $this->audit->log($request, 'form.admin_duplicate', $copy, null, [
            'source_type' => $from,
            'source_id' => $source->id,
            'form_type' => $to,
            'form_id' => $copy->id,
            'company_id' => $source->company_id,
            'company' => $source->company->name,
            'title' => $title,
        ]);

        return response()->json([
            'message' => sprintf('%s duplicated as a new %s draft.', self::TYPES[$from]['label'], $config['label']),
            'form_type' => $to,
            'form' => ['id' => $copy->id, 'title' => $title, 'form_data' => $copy->form_data],
            'company' => ['id' => $source->company->id, 'name' => $source->company->name, 'hr_email' => $source->company->hr_email],
        ], 201);
    }

    /** @return array<string, mixed> */
    private function card(string $type, Jnf|Inf $form): array
    {
        $data = $form->form_data;
        $data = is_string($data) ? (json_decode($data, true) ?: []) : (is_array($data) ? $data : []);
        $eligibility = is_array($data['eligibility'] ?? null) ? $data['eligibility'] : [];

        $programmes = array_values(array_filter($eligibility, fn ($p) => is_array($p) && collect($p['branches'] ?? [])->contains(fn ($b) => ! empty($b['selected']))));
        $batches = collect($programmes)->flatMap(fn ($p) => (array) ($p['graduatingBatches'] ?? [$p['graduatingBatch'] ?? null]))
            ->push($data['graduatingBatch'] ?? null)->filter()->map(fn ($b) => (string) $b)->unique()->sort()->values()->all();

        $pay = null;
        if ($type === 'inf') {
            $row = collect($data['programmeStipends'] ?? [])->first(fn ($r) => ! empty($r['enabled']) && ($r['total'] ?? $r['baseStipend'] ?? '') !== '');
            $amount = $row['total'] ?? $row['baseStipend'] ?? $form->stipend;
            $pay = $amount ? ['label' => 'Stipend', 'amount' => (float) $amount, 'interval' => 'month'] : null;
        } else {
            $row = collect($data['programmeSalaries'] ?? [])->first(fn ($r) => ! empty($r['enabled']) && ($r['ctcAnnual'] ?? '') !== '');
            $amount = $row['ctcAnnual'] ?? $form->ctc_max ?? $form->ctc_min;
            $pay = $amount ? ['label' => 'CTC', 'amount' => (float) $amount, 'interval' => 'year'] : null;
        }

        $posting = $form->jobPosting;

        return [
            'type' => $type,
            'id' => $form->id,
            'title' => $form->{self::TYPES[$type]['title']},
            'designation' => $data[$type === 'inf' ? 'internshipDesignation' : 'jobDesignation'] ?? null,
            'company' => $form->company ? ['id' => $form->company->id, 'name' => $form->company->name, 'hr_email' => $form->company->hr_email, 'logo_url' => $form->company->logo_url] : null,
            'batches' => $batches,
            'location' => $data[$type === 'inf' ? 'internshipLocation' : 'jobLocation'] ?? null,
            'work_mode' => $data['workMode'] ?? null,
            'duration_weeks' => $type === 'inf' ? ($data['duration'] ?? null) : null,
            'expected_hires' => $data['expectedHires'] ?? null,
            'pay' => $pay ? $pay + ['currency' => $data['currency'] ?? 'INR'] : null,
            'ppo_ctc' => $type === 'inf' && ! empty($data['ppoProvision']) && ! empty($data['ppoCtc']) ? (float) $data['ppoCtc'] : null,
            'programmes_count' => count($programmes),
            'branches_count' => collect($programmes)->sum(fn ($p) => collect($p['branches'] ?? [])->filter(fn ($b) => ! empty($b['selected']))->count()),
            'posting' => $posting ? [
                'id' => $posting->id,
                'status' => $posting->status,
                'cycle' => $posting->placementCycle?->name,
                'offer_label' => \App\Models\Offer::LABELS[$posting->offerType()] ?? null,
            ] : null,
            'updated_at' => $form->updated_at,
        ];
    }

    /**
     * "Add Job Profile" for an existing company: its JNFs or INFs, newest first, with the full form data for the
     * read-only preview. Untouched autosave drafts (no title yet) are left out.
     */
    public function forms(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate(['type' => ['required', 'in:jnf,inf']]);
        $config = self::TYPES[$validated['type']];
        /** @var class-string<Jnf|Inf> $model */
        $model = $config['model'];

        $forms = $model::query()
            ->where('company_id', $company->id)
            ->where($config['title'], '!=', 'Untitled '.$config['label'].' Draft')
            ->latest('updated_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', $config['title'], 'status', 'form_data', 'created_at', 'updated_at']);

        return response()->json([
            'company' => ['id' => $company->id, 'name' => $company->name, 'hr_email' => $company->hr_email, 'logo_url' => $company->logo_url],
            'forms' => $forms->map(fn (Jnf|Inf $form) => [
                'id' => $form->id,
                'title' => $form->{$config['title']},
                'status' => $form->status,
                'form_data' => $form->form_data,
                'created_at' => $form->created_at,
                'updated_at' => $form->updated_at,
            ]),
        ]);
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
