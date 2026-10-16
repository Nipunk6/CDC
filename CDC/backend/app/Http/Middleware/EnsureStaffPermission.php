<?php

namespace App\Http\Middleware;

use App\Services\StaffAccessService;
use App\Support\StaffPermissions;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff roles: every admin route needs the permission + level its StaffPermissions rule names. Super admins pass
 * every rule; a route with no rule is for super admins only (fail closed). Runs after `role:admin`.
 */
class EnsureStaffPermission
{
    public function __construct(private readonly StaffAccessService $access)
    {
    }

    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $this->access->flush();
        $user = $request->user();
        if (! $user || $user->is_super_admin) {
            return $next($request);
        }

        $route = $request->route();
        $uri = $route?->uri() ?? '';

        // An invited staff member must fill in their own details before anything else.
        if ($user->profile_completed_at === null && ! str_starts_with($uri, 'api/admin/me/')) {
            return response()->json(['message' => 'Complete your profile first.', 'profile_incomplete' => true], 403);
        }

        $rule = $route ? StaffPermissions::ruleFor($request->method(), $uri) : null;

        if ($rule === null || $rule['permission'] === StaffPermissions::SUPER) {
            return response()->json(['message' => 'Only a super admin can do this.'], 403);
        }

        if (! $this->access->can($user, $rule['permission'], $rule['level'])) {
            $labels = array_map(fn ($key) => StaffPermissions::all()[$key]['label'] ?? $key, (array) $rule['permission']);

            return response()->json([
                'message' => sprintf('You do not have permission for this (%s: %s needed). Ask a super admin for access.', implode(' or ', $labels), self::levelLabel($rule['level'])),
                'permission' => $rule['permission'],
                'level' => $rule['level'],
            ], 403);
        }

        // Department limit: a single student record (or one hanging off a student) outside the person's departments
        // does not exist for them.
        if ($this->access->departments($user) !== null) {
            $student = $this->studentOf($route);
            if ($student !== false && ! $this->access->seesStudent($user, $student)) {
                return response()->json(['message' => 'Not found.'], 404);
            }
        }

        return $next($request);
    }

    /** The student a route is about, null if it names one that has none, false if the route names no student. */
    private function studentOf($route): \App\Models\StudentProfile|null|false
    {
        foreach (['studentProfile', 'resume', 'branchChangeRequest'] as $name) {
            $value = $route?->parameter($name);
            if ($value === null) {
                continue;
            }
            if ($value instanceof \App\Models\StudentProfile) {
                return $value;
            }
            if (is_object($value) && method_exists($value, 'studentProfile')) {
                return $value->studentProfile;
            }
        }

        return false;
    }

    public static function levelLabel(string $level): string
    {
        return match ($level) {
            'read' => 'Read',
            'verify' => 'Read and verify',
            'write' => 'Read and write',
            default => 'None',
        };
    }
}
