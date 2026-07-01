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
                'form_data',
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
                'form_data',
            ]);

        $jnfItems->each(function (Jnf $jnf): void {
            $this->applyCompanyVisibleStatusToJnf($jnf);
            $jnf->unsetRelation('statusHistories');

            $batch = 'Unknown Batch';
            if (is_array($jnf->form_data) && isset($jnf->form_data['graduatingBatch'])) {
                $batch = $jnf->form_data['graduatingBatch'];
            }
            $jnf->setAttribute('graduating_batch', $batch);
            $jnf->makeHidden(['form_data']);
        });

        $infItems->each(function (Inf $inf): void {
            $this->applyCompanyVisibleStatusToInf($inf);
            $inf->unsetRelation('statusHistories');

            $batch = 'Unknown Batch';
            if (is_array($inf->form_data) && isset($inf->form_data['graduatingBatch'])) {
                $batch = $inf->form_data['graduatingBatch'];
            }
            $inf->setAttribute('graduating_batch', $batch);
            $inf->makeHidden(['form_data']);
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
