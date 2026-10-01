<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProfile extends Model
{
    use HasFactory;

    /** Fields a student may edit about themselves; everything else is admin/system-controlled. */
    public const SELF_EDITABLE = ['personal_email', 'phone', 'home_state', 'linkedin_url', 'github_url'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'roll_no',
        'full_name',
        'institute_email',
        'personal_email',
        'phone',
        'programme',
        'branch',
        'graduating_batch',
        'current_cgpa',
        'ongoing_backlogs',
        'total_backlogs',
        'gender',
        'date_of_birth',
        'tenth_percent',
        'twelfth_percent',
        'category',
        'pwd',
        'home_state',
        'photo_path',
        'linkedin_url',
        'github_url',
    ];

    /**
     * The private storage path is never serialised; photos are streamed through their own endpoint.
     *
     * @var list<string>
     */
    protected $hidden = [
        'photo_path',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'has_photo',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graduating_batch' => 'integer',
            'current_cgpa' => 'decimal:2',
            'ongoing_backlogs' => 'integer',
            'total_backlogs' => 'integer',
            'date_of_birth' => 'date:Y-m-d',
            'tenth_percent' => 'decimal:2',
            'twelfth_percent' => 'decimal:2',
            'pwd' => 'boolean',
        ];
    }

    public function getHasPhotoAttribute(): bool
    {
        return ! empty($this->attributes['photo_path'] ?? null);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cycleEnrollments(): HasMany
    {
        return $this->hasMany(CycleEnrollment::class);
    }

    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function placementBlocks(): HasMany
    {
        return $this->hasMany(PlacementBlock::class);
    }

    public function branchChangeRequests(): HasMany
    {
        return $this->hasMany(BranchChangeRequest::class);
    }
}
