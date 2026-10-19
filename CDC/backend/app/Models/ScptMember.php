<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student on the SCPT (Student Coordinators for Placement Team) of one placement cycle (P10.1). Only SCPT members of
 * a job profile's cycle can be its SPOC.
 */
class ScptMember extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['placement_cycle_id', 'student_profile_id', 'designation', 'added_by'];

    public function placementCycle(): BelongsTo
    {
        return $this->belongsTo(PlacementCycle::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function spocAssignments(): HasMany
    {
        return $this->hasMany(JobPostingSpoc::class);
    }
}
