<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The admin's verification of one student-changeable profile field. Written only by StudentVerificationService.
 */
class StudentFieldVerification extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'student_profile_id',
        'field',
        'status',
        'remark',
        'changed_at',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
