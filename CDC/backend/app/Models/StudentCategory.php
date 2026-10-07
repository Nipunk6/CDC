<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A CDC-defined student category (Superset parity S8.4), e.g. "Minor in Data Science". A job profile may require one
 * of several categories through the `allowedStudentCategories` eligibility key (EligibilityService).
 */
class StudentCategory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['title', 'description', 'created_by'];

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(StudentProfile::class, 'student_category_student')->withPivot('assigned_by')->withTimestamps();
    }
}
