<?php

namespace App\Models;

use App\Services\AudienceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Notice board entry (Superset parity S7.1). Students in any of its audience groups see it once published.
 * Companies never see notices (B3).
 */
class Notice extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'body',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'job_posting_id',
        'published_at',
        'emailed_at',
        'created_by',
    ];

    /**
     * The private file path is never serialised; attachments stream through authenticated routes.
     *
     * @var list<string>
     */
    protected $hidden = ['attachment_path'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'emailed_at' => 'datetime',
            'attachment_size' => 'integer',
        ];
    }

    public function audiences(): HasMany
    {
        return $this->hasMany(NoticeAudience::class);
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NoticeRead::class);
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
     * Active students in any of this notice's audience groups.
     */
    public function audienceQuery(): Builder
    {
        return app(AudienceService::class)->query($this->audiences->map(fn (NoticeAudience $a) => $a->toGroup())->all());
    }

    public function isVisibleTo(StudentProfile $student): bool
    {
        return $this->published_at !== null && $this->audienceQuery()->whereKey($student->id)->exists();
    }

    /**
     * Published notices addressed to this student, filtered in SQL (the same rule as isVisibleTo, M3).
     */
    public function scopeVisibleTo(Builder $query, StudentProfile $student): void
    {
        $audiences = app(AudienceService::class);
        $query->whereNotNull('notices.published_at')
            ->whereHas('audiences', fn (Builder $rows) => $audiences->whereIncludes($rows, $student));
    }
}
