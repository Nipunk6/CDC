<?php

namespace App\Http\Controllers;

use App\Models\Inf;
use App\Models\Jnf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if (! $company) {
            return response()->json(['message' => 'Company profile not found.'], 404);
        }

        $jnfItems = $company->jnfs()
            ->with(['statusHistories:id,form_id,form_type,new_status'])
            ->latest()
            ->get([
                'id',
                'job_title',
                'status',
                'updated_at',
                'edit_access_requested_at',
                'edit_access_requested_reason',
            ]);
        $infItems = $company->infs()
            ->with(['statusHistories:id,form_id,form_type,new_status'])
            ->latest()
            ->get([
                'id',
                'internship_title',
                'status',
                'updated_at',
                'edit_access_requested_at',
                'edit_access_requested_reason',
            ]);

        $jnfItems->each(function (Jnf $jnf): void {
            $this->applyCompanyVisibleStatusToJnf($jnf);
            $jnf->unsetRelation('statusHistories');
        });

        $infItems->each(function (Inf $inf): void {
            $this->applyCompanyVisibleStatusToInf($inf);
            $inf->unsetRelation('statusHistories');
        });

        return response()->json([
            'company' => [
                'name' => $company->name,
                'logo_url' => $company->logo_url,
            ],
            'stats' => [
                'jnf_total' => $jnfItems->count(),
                'jnf_submitted' => $jnfItems->where('status', 'submitted')->count(),
                'jnf_accepted' => $jnfItems->where('status', 'accepted')->count(),
                'jnf_rejected' => $jnfItems->where('status', 'rejected')->count(),
                'jnf_draft' => $jnfItems->where('status', 'draft')->count(),
                'inf_total' => $infItems->count(),
                'inf_submitted' => $infItems->where('status', 'submitted')->count(),
                'inf_accepted' => $infItems->where('status', 'accepted')->count(),
                'inf_rejected' => $infItems->where('status', 'rejected')->count(),
                'inf_draft' => $infItems->where('status', 'draft')->count(),
            ],
            'recent_jnfs' => $jnfItems->take(10)->values(),
            'recent_infs' => $infItems->take(10)->values(),
        ]);
    }

    private function applyCompanyVisibleStatusToJnf(Jnf $jnf): void
    {
        if ($jnf->status !== 'under_review') {
            return;
        }

        $history = $jnf->statusHistories;
        $hasSubmitted = $history->contains(function ($entry): bool {
            return $entry->new_status === 'submitted';
        });

        if (! $hasSubmitted) {
            $jnf->setAttribute('status', 'draft');
        }
    }

    private function applyCompanyVisibleStatusToInf(Inf $inf): void
    {
        if ($inf->status !== 'under_review') {
            return;
        }

        $history = $inf->statusHistories;
        $hasSubmitted = $history->contains(function ($entry): bool {
            return $entry->new_status === 'submitted';
        });

        if (! $hasSubmitted) {
            $inf->setAttribute('status', 'draft');
        }
    }
}
