<?php

namespace App\Models;

use App\Services\EligibilityService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementBlock extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_profile_id',
        'placement_cycle_id',
        'scope',
        'reason',
        'offer_id',
        'remark',
        'active',
        'blocked_by',
        'unblocked_by',
        'unblocked_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'unblocked_at' => 'datetime',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function placementCycle(): BelongsTo
    {
        return $this->belongsTo(PlacementCycle::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function unblockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'unblocked_by');
    }

    /** Student-facing sentence, e.g. "Blocked: accepted a Full-Time offer." */
    public function message(): string
    {
        return EligibilityService::blockMessage($this->reason, $this->scope, $this->offer?->offer_type, $this->remark);
    }
}
