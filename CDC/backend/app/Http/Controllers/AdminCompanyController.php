<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCompanyController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $query = Company::query()->withCount([
            'jnfs',
            'infs',
            'jnfs as jnfs_submitted_count' => fn (Builder $q) => $q->where('status', 'submitted'),
            'jnfs as jnfs_under_review_count' => fn (Builder $q) => $q->where('status', 'under_review'),
            'jnfs as jnfs_accepted_count' => fn (Builder $q) => $q->where('status', 'accepted'),
            'jnfs as jnfs_rejected_count' => fn (Builder $q) => $q->where('status', 'rejected'),
            'infs as infs_submitted_count' => fn (Builder $q) => $q->where('status', 'submitted'),
            'infs as infs_under_review_count' => fn (Builder $q) => $q->where('status', 'under_review'),
            'infs as infs_accepted_count' => fn (Builder $q) => $q->where('status', 'accepted'),
            'infs as infs_rejected_count' => fn (Builder $q) => $q->where('status', 'rejected'),
        ])->latest();

        if (! empty($validated['q'])) {
            $term = $validated['q'];
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('name', 'like', "%{$term}%")
                    ->orWhere('industry', 'like', "%{$term}%")
                    ->orWhere('hr_name', 'like', "%{$term}%")
                    ->orWhere('hr_email', 'like', "%{$term}%");
            });
        }

        return response()->json([
            'companies' => $query->get(),
        ]);
    }

    public function show(Company $company): JsonResponse
    {
        $company->load('users:id,company_id,name,email,role')->loadCount([
            'jnfs',
            'infs',
            'jnfs as jnf_pending_count' => fn (Builder $q) => $q->whereIn('status', ['submitted', 'under_review']),
            'jnfs as jnf_accepted_count' => fn (Builder $q) => $q->where('status', 'accepted'),
            'jnfs as jnf_rejected_count' => fn (Builder $q) => $q->where('status', 'rejected'),
            'infs as inf_pending_count' => fn (Builder $q) => $q->whereIn('status', ['submitted', 'under_review']),
            'infs as inf_accepted_count' => fn (Builder $q) => $q->where('status', 'accepted'),
            'infs as inf_rejected_count' => fn (Builder $q) => $q->where('status', 'rejected'),
        ]);

        return response()->json([
            'company' => $company,
            'summary' => [
                'jnf_total' => $company->jnfs_count,
                'jnf_pending' => $company->jnf_pending_count,
                'jnf_accepted' => $company->jnf_accepted_count,
                'jnf_rejected' => $company->jnf_rejected_count,
                'inf_total' => $company->infs_count,
                'inf_pending' => $company->inf_pending_count,
                'inf_accepted' => $company->inf_accepted_count,
                'inf_rejected' => $company->inf_rejected_count,
            ],
            'recent_submissions' => [
                'jnfs' => $company->jnfs()->select(['id', 'company_id', 'job_title', 'status', 'created_at'])->latest()->limit(5)->get(),
                'infs' => $company->infs()->select(['id', 'company_id', 'internship_title', 'status', 'created_at'])->latest()->limit(5)->get(),
            ],
        ]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255', 'regex:/^(https?:\/\/)?[\w\-]+(\.[\w\-]+)+[^\s]*$/i'],
            'hr_name' => ['required', 'string', 'max:255', "regex:/^[\\pL\\s'.-]+$/u"],
            'hr_email' => ['required', 'email:rfc,dns', 'max:255'],
            'hr_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $before = $company->only(array_keys($validated));
        $company->update($validated);
        $this->audit->log($request, 'company.update', $company, $before, $company->only(array_keys($validated)));

        return response()->json([
            'message' => 'Company updated successfully.',
            'company' => $company->fresh(),
        ]);
    }
}