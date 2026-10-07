<?php

namespace App\Http\Controllers;

use App\Models\PlacementCycle;
use App\Services\AuditService;
use App\Services\ExportService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reports (Superset parity S8.5): placement-level Excel lists, admin only, every download audited.
 */
class AdminReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reports,
        private readonly ExportService $exports,
        private readonly AuditService $audit
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'reports' => collect(ReportService::REPORTS)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values(),
            'cycles' => PlacementCycle::query()->orderByDesc('starts_on')->get(['id', 'name', 'type', 'status']),
        ]);
    }

    public function download(Request $request, PlacementCycle $placementCycle, string $report): StreamedResponse
    {
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404, 'Report not found.');

        $built = $this->reports->build($placementCycle, $report);
        $this->audit->log($request, 'report.download', $placementCycle, null, ['report' => $report]);

        return $this->exports->tableWorkbook(
            $built['title'].' — '.$placementCycle->name,
            $built['headers'],
            $built['rows'],
            Str::slug(ReportService::REPORTS[$report].' '.$placementCycle->name).'.xlsx',
            ReportService::REPORTS[$report]
        );
    }
}
