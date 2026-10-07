<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostingRound extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'job_posting_id',
        'name',
        'round_type',
        'sort_order',
        'scheduled_at',
        'venue',
        'status',
        'is_final',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_final' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(ApplicationRoundResult::class);
    }

    public function proposals(): HasMany
    {
        return $this->hasMany(ShortlistProposal::class);
    }
}
