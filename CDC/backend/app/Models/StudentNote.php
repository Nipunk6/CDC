<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin-only internal note about a student (Superset parity S4.5). Served only by AdminStudentNoteController.
 */
class StudentNote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_profile_id',
        'author_id',
        'body',
    ];

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
