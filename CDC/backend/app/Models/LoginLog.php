<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One login attempt (Phase 3 S6). Written only by LoginLogService; never updated.
 */
class LoginLog extends Model
{
    public const UPDATED_AT = null;

    public const HASH_PREFIX = 'sha256:';

    protected $fillable = [
        'user_id',
        'identifier',
        'role',
        'ip',
        'ip_source',
        'user_agent',
        'success',
        'failure_reason',
        'created_at',
    ];

    protected $casts = [
        'success' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** True when no account matched and only a hash of what was typed is stored. */
    public function identifierIsHashed(): bool
    {
        return str_starts_with((string) $this->identifier, self::HASH_PREFIX);
    }
}
