<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Announce-only campus event (PPT / workshop / webinar), table `events` (spec C14). Named CampusEvent so it
 * never collides with Laravel's Event facade.
 */
class CampusEvent extends Model
{
    use HasFactory;

    protected $table = 'events';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'event_type',
        'company_id',
        'starts_at',
        'venue',
        'meeting_link',
        'description',
        'audience_type',
        'audience_filter',
        'published_at',
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
            'starts_at' => 'datetime',
            'published_at' => 'datetime',
            'audience_filter' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Students in this event's audience (active accounts only).
     */
    public function audienceQuery(): Builder
    {
        $query = StudentProfile::query()->whereHas('user', fn (Builder $u) => $u->where('is_active', true));
        $filter = $this->audience_filter ?? [];

        if ($this->audience_type === 'branches') {
            $pairs = is_array($filter['branches'] ?? null) ? $filter['branches'] : [];
            if ($pairs === []) {
                return $query->whereRaw('1 = 0');
            }
            $query->where(function (Builder $q) use ($pairs): void {
                foreach ($pairs as $pair) {
                    $q->orWhere(function (Builder $p) use ($pair): void {
                        $p->where('programme', (string) ($pair['programme'] ?? ''));
                        if (! empty($pair['branch'])) {
                            $p->where('branch', (string) $pair['branch']);
                        }
                    });
                }
            });
        } elseif ($this->audience_type === 'posting_applicants') {
            $postingId = (int) ($filter['job_posting_id'] ?? 0);
            $query->whereHas('applications', fn (Builder $a) => $a->where('job_posting_id', $postingId)->where('status', 'applied'));
        }

        return $query;
    }

    public function isVisibleTo(StudentProfile $student): bool
    {
        return $this->published_at !== null && $this->audienceQuery()->whereKey($student->id)->exists();
    }
}
