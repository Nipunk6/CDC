<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    /**
     * Record an admin mutation. Call from every admin write action.
     *
     * @param  string  $action  dot-slug, e.g. student.create, posting.float, round.publish, block.remove, setting.update
     */
    public function log(Request $request, string $action, ?Model $subject, ?array $before, ?array $after): void
    {
        $this->logAs($request->user(), $request->ip(), $action, $subject, $before, $after);
    }

    /**
     * Same as log() for callers that hold the acting admin but no Request (services, jobs).
     */
    public function logAs(?User $actor, ?string $ip, string $action, ?Model $subject, ?array $before, ?array $after): void
    {
        AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip' => $ip,
        ]);
    }
}
