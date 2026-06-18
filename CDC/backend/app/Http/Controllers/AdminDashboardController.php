<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Inf;
use App\Models\Jnf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $jnfDraftReviewMarked = Jnf::query()
            ->where('status', 'under_review')
            ->whereDoesntHave('statusHistories', function ($history): void {
                $history->where('new_status', 'submitted');
            })
            ->count();

        $infDraftReviewMarked = Inf::query()
            ->where('status', 'under_review')
            ->whereDoesntHave('statusHistories', function ($history): void {
                $history->where('new_status', 'submitted');
            })
            ->count();

        $jnfCounts = Jnf::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $infCounts = Inf::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $jnfTotal = (int) array_sum($jnfCounts->all());
        $infTotal = (int) array_sum($infCounts->all());
        $jnfSubmitted = (int) ($jnfCounts['submitted'] ?? 0);
        $jnfUnderReviewRaw = (int) ($jnfCounts['under_review'] ?? 0);
        $jnfUnderReview = max(0, $jnfUnderReviewRaw - $jnfDraftReviewMarked);
        $jnfAccepted = (int) ($jnfCounts['accepted'] ?? 0);
        $jnfRejected = (int) ($jnfCounts['rejected'] ?? 0);
        $jnfDraft = (int) ($jnfCounts['draft'] ?? 0) + $jnfDraftReviewMarked;
        $infSubmitted = (int) ($infCounts['submitted'] ?? 0);
        $infUnderReviewRaw = (int) ($infCounts['under_review'] ?? 0);
        $infUnderReview = max(0, $infUnderReviewRaw - $infDraftReviewMarked);
        $infAccepted = (int) ($infCounts['accepted'] ?? 0);
        $infRejected = (int) ($infCounts['rejected'] ?? 0);
        $infDraft = (int) ($infCounts['draft'] ?? 0) + $infDraftReviewMarked;

        return response()->json([
            'stats' => [
                'companies_total' => Company::count(),
                'companies_with_submissions' => Company::where(function (Builder $query): void {
                    $query->whereHas('jnfs')->orWhereHas('infs');
                })->count(),
                'jnf_total' => $jnfTotal,
                'jnf_submitted' => $jnfSubmitted,
                'jnf_under_review' => $jnfUnderReview,
                'jnf_accepted' => $jnfAccepted,
                'jnf_rejected' => $jnfRejected,
                'jnf_draft' => $jnfDraft,
                'inf_total' => $infTotal,
                'inf_submitted' => $infSubmitted,
                'inf_under_review' => $infUnderReview,
                'inf_accepted' => $infAccepted,
                'inf_rejected' => $infRejected,
                'inf_draft' => $infDraft,
                'pending_reviews' => $jnfSubmitted + $jnfUnderReview + $infSubmitted + $infUnderReview,
            ],
            'recent_submissions' => [
                'jnfs' => Jnf::with('company:id,name,logo_path')
                    ->select(['id', 'job_title', 'status', 'updated_at', 'company_id', 'edit_access_requested_at', 'form_data'])
                    ->where('status', '!=', 'draft')
                    ->where(function ($query): void {
                        $query
                            ->where('status', '!=', 'under_review')
                            ->orWhereHas('statusHistories', function ($history): void {
                                $history->where('new_status', 'submitted');
                            });
                    })
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(function ($jnf) {
                        $formData = is_array($jnf->form_data) ? $jnf->form_data : [];
                        $batch = $formData['graduatingBatch'] ?? null;
                        if (!$batch && isset($formData['eligibility'][0]['graduatingBatches'][0])) {
                            $batch = $formData['eligibility'][0]['graduatingBatches'][0];
                        }
                        
                        return [
                            'id' => $jnf->id,
                            'job_title' => $jnf->job_title,
                            'status' => $jnf->status,
                            'updated_at' => $jnf->updated_at,
                            'company' => $jnf->company,
                            'edit_access_requested_at' => $jnf->edit_access_requested_at,
                            'graduating_batch' => $batch ?: 'Unknown Batch',
                        ];
                    }),
                'infs' => Inf::with('company:id,name,logo_path')
                    ->select(['id', 'internship_title', 'status', 'updated_at', 'company_id', 'edit_access_requested_at', 'form_data'])
                    ->where('status', '!=', 'draft')
                    ->where(function ($query): void {
                        $query
                            ->where('status', '!=', 'under_review')
                            ->orWhereHas('statusHistories', function ($history): void {
                                $history->where('new_status', 'submitted');
                            });
                    })
                    ->latest()
                    ->limit(5)
                    ->get()
                    ->map(function ($inf) {
                        $formData = is_array($inf->form_data) ? $inf->form_data : [];
                        $batch = $formData['graduatingBatch'] ?? null;
                        if (!$batch && isset($formData['eligibility'][0]['graduatingBatches'][0])) {
                            $batch = $formData['eligibility'][0]['graduatingBatches'][0];
                        }

                        return [
                            'id' => $inf->id,
                            'internship_title' => $inf->internship_title,
                            'status' => $inf->status,
                            'updated_at' => $inf->updated_at,
                            'company' => $inf->company,
                            'edit_access_requested_at' => $inf->edit_access_requested_at,
                            'graduating_batch' => $batch ?: 'Unknown Batch',
                        ];
                    }),
            ],
        ]);
    }
}