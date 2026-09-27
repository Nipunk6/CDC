<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public const SUSPENDED_MESSAGE = 'Account suspended. Contact CDC.';

    /**
     * Reject every authenticated request from a suspended user (users.is_active = false).
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            return response()->json([
                'message' => self::SUSPENDED_MESSAGE,
            ], 403);
        }

        return $next($request);
    }
}
