<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyHistoryService;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Company History (owner decisions 2026-10-20, P3-D55): which academic years and placements each company came
 * for, its job profiles, applicants and offers, and its HR contacts. Read only (permission: companies, read).
 */
class AdminCompanyHistoryController extends Controller
{
    public function __construct(private readonly CompanyHistoryService $history)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->history->overview($this->filters($request)));
    }

    public function export(Request $request, ExportService $exports): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = collect($this->history->overview($filters)['companies'])->map(fn (array $c) => [
            $c['name'],
            $c['sector'],
            collect($c['visits'])->map(fn ($v) => $v['year'].' ('.collect($v['placements'])->pluck('name')->join(', ').')')->join('; '),
            $c['first_year'],
            $c['last_year'],
            $c['job_profiles'],
            $c['applicants'],
            $c['offers'],
            $c['accepted_not_opened'],
            $c['hr']['name'],
            $c['hr']['designation'],
            $c['hr']['email'],
            $c['hr']['phone'],
        ]);

        $title = 'Company History'.collect([
            $filters['year'] ? 'year '.$filters['year'] : null,
            $filters['type'] ? ($filters['type'] === 'internship' ? 'Internship' : 'Full Time') : null,
            ['not_returned' => 'did not return', 'never' => 'never came'][$filters['status'] ?? ''] ?? null,
        ])->filter()->map(fn ($p) => ' · '.$p)->join('');

        return $exports->tableWorkbook(
            $title,
            ['Company', 'Sector', 'Years and placements', 'First year', 'Last year', 'Job profiles', 'Applicants', 'Offers', 'Accepted, not opened', 'HR name', 'HR designation', 'HR email', 'HR phone'],
            $rows,
            'company-history.xlsx',
            'Company History'
        );
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json($this->history->detail($company));
    }

    /** @return array{year: ?string, type: ?string, status: ?string, q: ?string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'type' => ['nullable', 'in:fulltime,internship'],
            'status' => ['nullable', 'in:came,not_returned,never'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        return ['year' => $validated['year'] ?? null, 'type' => $validated['type'] ?? null, 'status' => $validated['status'] ?? null, 'q' => $validated['q'] ?? null];
    }
}
