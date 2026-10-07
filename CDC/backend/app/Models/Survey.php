<?php

namespace App\Models;

use App\Services\AudienceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Survey form (Superset parity S7.3). Draft → published → archived; a published survey is never hard-deleted (B2-7).
 * `job_posting_id` is context only: answers never change an offer, offer type or block (B2-5).
 */
class Survey extends Model
{
    public const TYPES = ['general', 'ppo_consent', 'feedback'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'survey_type',
        'welcome_text',
        'concluding_text',
        'status',
        'is_public',
        'allow_multiple',
        'allow_edits',
        'deadline_at',
        'job_posting_id',
        'published_at',
        'archived_at',
        'emailed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'allow_multiple' => 'boolean',
            'allow_edits' => 'boolean',
            'deadline_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SurveyQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(SurveyAudience::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who may answer: every active student when public (signed-in students only, B2-6), else the audience groups.
     */
    public function audienceQuery(): Builder
    {
        $groups = $this->is_public
            ? [['audience_type' => 'all', 'audience_filter' => null]]
            : $this->audiences->map(fn (SurveyAudience $a) => $a->toGroup())->all();

        return app(AudienceService::class)->query($groups);
    }

    public function isVisibleTo(StudentProfile $student): bool
    {
        return $this->status === 'published' && $this->audienceQuery()->whereKey($student->id)->exists();
    }

    /**
     * Published surveys this student may answer (public, or one of its audience groups), filtered in SQL (M3).
     */
    public function scopeVisibleTo(Builder $query, StudentProfile $student): void
    {
        $audiences = app(AudienceService::class);
        $query->where('surveys.status', 'published')
            ->where(fn (Builder $q) => $q->where('surveys.is_public', true)
                ->orWhereHas('audiences', fn (Builder $rows) => $audiences->whereIncludes($rows, $student)));
    }

    public function deadlinePassed(): bool
    {
        return $this->deadline_at !== null && $this->deadline_at->isPast();
    }

    public function isOpen(): bool
    {
        return $this->status === 'published' && ! $this->deadlinePassed();
    }
}
