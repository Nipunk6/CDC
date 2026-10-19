<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An SCPT member assigned as SPOC (single point of contact) for a job profile (P10.2). A job profile may have several.
 */
class JobPostingSpoc extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['job_posting_id', 'scpt_member_id', 'assigned_by'];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function scptMember(): BelongsTo
    {
        return $this->belongsTo(ScptMember::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
