<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Who changed what (spec A1.6 / M10.3).
 */
class AdminAuditLogController extends Controller
{
    public const PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = AuditLog::query()->with('user:id,name,email')->latest('id');

        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }
        if (! empty($validated['action'])) {
            $query->where('action', 'like', $validated['action'].'%');
        }
        // Dates picked by admins are Indian dates.
        if (! empty($validated['from'])) {
            $query->where('created_at', '>=', \Carbon\Carbon::parse($validated['from'], 'Asia/Kolkata')->startOfDay()->utc());
        }
        if (! empty($validated['to'])) {
            $query->where('created_at', '<=', \Carbon\Carbon::parse($validated['to'], 'Asia/Kolkata')->endOfDay()->utc());
        }

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'audit_logs' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'admins' => User::query()->where('role', 'admin')->orderBy('name')->get(['id', 'name', 'email']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }
}
