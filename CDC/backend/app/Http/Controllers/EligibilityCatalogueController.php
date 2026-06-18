<?php

namespace App\Http\Controllers;

use App\Models\ProgrammeBranch;
use Illuminate\Http\JsonResponse;

class EligibilityCatalogueController extends Controller
{
    public function programmeBranches(): JsonResponse
    {
        $groups = ProgrammeBranch::query()
            ->where('is_custom', true)
            ->where('is_active', true)
            ->orderBy('programme_name')
            ->orderBy('branch_name')
            ->get(['programme_name', 'branch_name'])
            ->groupBy('programme_name')
            ->map(fn ($rows, $programme) => [
                'programme' => $programme,
                'branches' => $rows->pluck('branch_name')->values(),
            ])
            ->values();

        $branchStates = ProgrammeBranch::query()
            ->where('is_custom', false)
            ->orderBy('programme_name')
            ->orderBy('branch_name')
            ->get(['programme_name', 'branch_name', 'is_active'])
            ->groupBy('programme_name')
            ->map(fn ($rows, $programme) => [
                'programme' => $programme,
                'branches' => $rows->map(fn ($row) => [
                    'branch_name' => $row->branch_name,
                    'is_active' => $row->is_active,
                ])->values(),
            ])
            ->values();

        return response()->json([
            'programme_branches' => $groups,
            'branch_states' => $branchStates,
        ]);
    }
}
