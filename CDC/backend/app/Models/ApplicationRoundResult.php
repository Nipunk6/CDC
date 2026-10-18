<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationRoundResult extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'application_id',
        'posting_round_id',
        'attendance',
        'result',
        'is_addendum',
        'published_at',
        'published_result',
        'previously_published_at',
        'decided_by',
        'remark',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_addendum' => 'boolean',
            'published_at' => 'datetime',
            'previously_published_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function postingRound(): BelongsTo
    {
        return $this->belongsTo(PostingRound::class);
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * The decision the student and company currently see: the published result, or — while a change to it waits as a
     * draft — the decision that was published before (owner decision 2026-10-18). Null when nothing is published.
     */
    public function visibleResult(): ?string
    {
        return $this->isPublished() ? $this->result : $this->published_result;
    }
}
