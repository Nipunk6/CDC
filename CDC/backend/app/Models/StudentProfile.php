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
        // S4.6 academic extras (CDC-entered only; never in SELF_EDITABLE)
        'current_semester',
        'course_start_date',
        'course_end_date',
        'lateral_entry',
        'tenth_board',
        'tenth_passing_year',
        'twelfth_board',
        'twelfth_passing_year',
        'previous_degree',
        'previous_degree_score',
        'previous_degree_score_type',
    ];

    /** S4.6: the CDC-entered academic extras, in display/import order. */
    public const ACADEMIC_EXTRAS = [
        'current_semester', 'course_start_date', 'course_end_date', 'lateral_entry', 'tenth_board', 'tenth_passing_year',
        'twelfth_board', 'twelfth_passing_year', 'previous_degree', 'previous_degree_score', 'previous_degree_score_type',
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
            'current_semester' => 'integer',
            'course_start_date' => 'date:Y-m-d',
            'course_end_date' => 'date:Y-m-d',
            'lateral_entry' => 'boolean',
            'tenth_passing_year' => 'integer',
            'twelfth_passing_year' => 'integer',
            'previous_degree_score' => 'decimal:2',
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

    /** CDC-assigned Student Categories (S8.4). */
    public function studentCategories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(StudentCategory::class, 'student_category_student')->withPivot('assigned_by')->withTimestamps();
    }

    public function branchChangeRequests(): HasMany
    {
        return $this->hasMany(BranchChangeRequest::class);
    }

    /** Admin-only internal notes (S4.5): never load this into a student or company payload. */
    public function adminNotes(): HasMany
    {
        return $this->hasMany(StudentNote::class);
    }
}
