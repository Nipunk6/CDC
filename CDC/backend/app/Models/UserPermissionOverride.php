<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A super admin's tick or untick of one permission for one person: `level` replaces the role's level for them.
 * Written only by StaffAccessService.
 */
class UserPermissionOverride extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'permission',
        'level',
        'set_by',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
