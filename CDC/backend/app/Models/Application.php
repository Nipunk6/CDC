<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Application extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_posting_id',
        'student_profile_id',
        'resume_id',
        'status',
        'used_unverified_resume',
        'placed_elsewhere_flag',
        'answers',
        'applied_at',
        'withdrawn_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_unverified_resume' => 'boolean',
            'placed_elsewhere_flag' => 'boolean',
            'answers' => 'array',
            'applied_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function resume(): BelongsTo
    {
        return $this->belongsTo(Resume::class);
    }

    public function roundResults(): HasMany
    {
        return $this->hasMany(ApplicationRoundResult::class);
    }

    public function offer(): HasOne
    {
        return $this->hasOne(Offer::class);
    }
}
