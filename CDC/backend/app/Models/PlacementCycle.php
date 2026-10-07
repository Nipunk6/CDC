<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlacementCycle extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'starts_on',
        'ends_on',
        'status',
        'is_draft',
        'allowed_programmes',
        'description',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'allowed_programmes' => 'array',
            'is_draft' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(CycleEnrollment::class);
    }

    /**
     * Placements students may see (S8.3): a Draft placement is hidden from them everywhere until it is published.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<PlacementCycle>  $query
     */
    public function scopeVisibleToStudents(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->where('is_draft', false);
    }

    public function isOpen(): bool
    {
        return (string) $this->status === 'open';
    }
}
