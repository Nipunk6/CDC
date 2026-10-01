<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    use HasFactory;

    public const TYPES = ['intern', 'intern_ppo', 'ppo_offered', 'fulltime', 'intern_fulltime', 'intern_performance_ppo'];

    public const LABELS = [
        'intern' => 'Internship',
        'intern_ppo' => 'PPO accepted (after internship)',
        'ppo_offered' => 'PPO offered (not accepted)',
        'fulltime' => 'Full-Time',
        'intern_fulltime' => 'Intern + Full-Time',
        'intern_performance_ppo' => 'Intern + performance-based PPO',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'student_profile_id',
        'company_id',
        'job_posting_id',
        'placement_cycle_id',
        'offer_type',
        'ctc_annual',
        'stipend_monthly',
        'currency',
        'announced_by',
        'announced_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ctc_annual' => 'integer',
            'stipend_monthly' => 'integer',
            'announced_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function placementCycle(): BelongsTo
    {
        return $this->belongsTo(PlacementCycle::class);
    }
}
