<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ExportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The login log (Phase 3 N4, OD-19). A super admin sees every attempt; any other admin sees their own attempts and
 * students' attempts. Only a super admin can download it, and each download is audited.
 */
class AdminLoginLogController extends Controller
{
    public const PER_PAGE = 50;

    private const EXPORT_LIMIT = 20000;

    public function __construct(
        private readonly AuditService $audit,
        private readonly ExportService $exports
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $page = $this->query($request->user(), $filters)->paginate(self::PER_PAGE);

        return response()->json([
            'login_logs' => collect($page->items())->map(fn (LoginLog $row) => $this->present($row))->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        if (! $request->user()->is_super_admin) {
            return response()->json(['message' => 'Only a super admin can download the login log.'], 403);
        }

        $filters = $this->filters($request);
        $rows = $this->query($request->user(), $filters)->limit(self::EXPORT_LIMIT)->get();

        $this->audit->log($request, 'login_logs.view', null, null, [
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
            'rows' => $rows->count(),
        ]);

        return $this->exports->tableWorkbook(
            'Login log',
            ['When (IST)', 'Result', 'Reason', 'Role', 'Name', 'Email / Roll No', 'IP address', 'Address source', 'Browser'],
            $rows->map(fn (LoginLog $row) => [
                $row->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i:s A'),
                $row->success ? 'Signed in' : 'Failed',
                $row->failure_reason,
                $row->role ?? 'unknown',
                $row->user?->name,
                $row->identifierIsHashed() ? '(no such account)' : $row->identifier,
                $row->ip,
                $row->ip_source,
                $row->user_agent,
            ]),
            'login-log-'.now()->timezone('Asia/Kolkata')->format('Ymd-His').'.xlsx',
            'Login log'
        );
    }

    /** @return array{role: ?string, outcome: ?string, user_id: ?int, ip: ?string, q: ?string, from: ?string, to: ?string} */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'role' => ['nullable', 'in:admin,company,student,mentor,unknown'],
            'outcome' => ['nullable', 'in:success,failure'],
            'user_id' => ['nullable', 'integer'],
            'ip' => ['nullable', 'string', 'max:45', 'regex:/^[0-9a-fA-F:.]+$/'],
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'ip.regex' => 'Enter an IP address or the start of one.',
        ]);

        return [
            'role' => $validated['role'] ?? null,
            'outcome' => $validated['outcome'] ?? null,
            'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
            'ip' => $validated['ip'] ?? null,
            'q' => isset($validated['q']) ? trim($validated['q']) : null,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $filters */
    private function query(User $admin, array $filters): Builder
    {
        $query = LoginLog::query()->with('user:id,name,email,role')->latest('id');

        // OD-19: anyone but a super admin sees only their own rows and students' rows.
        if (! $admin->is_super_admin) {
            $query->where(fn (Builder $q) => $q->where('role', 'student')->orWhere('user_id', $admin->id));
        }

        if ($filters['role'] === 'unknown') {
            $query->whereNull('role');
        } elseif ($filters['role']) {
            $query->where('role', $filters['role']);
        }
        if ($filters['outcome']) {
            $query->where('success', $filters['outcome'] === 'success');
        }
        if ($filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }
        if ($filters['ip']) {
            $query->where('ip', 'like', $filters['ip'].'%');
        }
        if ($filters['q']) {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q']).'%';
            $query->where(fn (Builder $q) => $q
                ->where(fn (Builder $i) => $i->where('identifier', 'like', $like)->where('identifier', 'not like', LoginLog::HASH_PREFIX.'%'))
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        }
        // Dates picked by admins are Indian dates.
        if ($filters['from']) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'], 'Asia/Kolkata')->startOfDay()->utc());
        }
        if ($filters['to']) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'], 'Asia/Kolkata')->endOfDay()->utc());
        }

        return $query;
    }

    /** @return array<string, mixed> */
    private function present(LoginLog $row): array
    {
        return [
            'id' => $row->id,
            'created_at' => $row->created_at,
            'success' => $row->success,
            'failure_reason' => $row->failure_reason,
            'ip' => $row->ip,
            'ip_source' => $row->ip_source,
            'user_agent' => $row->user_agent,
            'role' => $row->role,
            // What was typed is shown only when it named a real account; otherwise only its hash is stored.
            'identifier' => $row->identifierIsHashed() ? null : $row->identifier,
            'user' => $row->user?->only(['id', 'name', 'email']),
        ];
    }
}
