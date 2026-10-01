<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Resume extends Model
{
    use HasFactory;

    public const MAX_SLOTS = 8;

    public const MAX_KB = 2048;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_profile_id',
        'slot',
        'label',
        'file_path',
        'file_size',
        'status',
        'admin_remark',
        'reviewed_by',
        'reviewed_at',
    ];

    /**
     * The private storage path is never serialised.
     *
     * @var list<string>
     */
    protected $hidden = [
        'file_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'file_size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * Locked = attached to a live application whose posting is still open or in process (spec B5).
     */
    public function isLocked(): bool
    {
        return $this->applications()
            ->where('status', 'applied')
            ->whereHas('jobPosting', fn ($q) => $q->whereIn('status', ['open', 'in_process']))
            ->exists();
    }

    /**
     * No-login link for companies and exports (spec Q8.1).
     */
    public function signedUrl(int $days = 30): string
    {
        return URL::temporarySignedRoute('resumes.signed', now()->addDays($days), ['resume' => $this->id]);
    }

    /**
     * Short-lived variant for the admin preview through the Next.js PDF proxy.
     */
    public function previewUrl(int $minutes = 30): string
    {
        return URL::temporarySignedRoute('resumes.signed', now()->addMinutes($minutes), ['resume' => $this->id]);
    }

    public function downloadName(): string
    {
        $roll = $this->studentProfile?->roll_no ?? 'resume';

        return sprintf('%s_%s.pdf', $roll, preg_replace('/[^A-Za-z0-9_-]+/', '_', (string) $this->label) ?: $this->slot);
    }
}
