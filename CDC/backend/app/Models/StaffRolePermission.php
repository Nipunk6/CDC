<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission level inside a staff role. Written only by StaffAccessService.
 */
class StaffRolePermission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'staff_role_id',
        'permission',
        'level',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }
}
