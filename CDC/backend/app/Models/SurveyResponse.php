<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyResponse extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['survey_id', 'student_profile_id', 'single_key', 'answers', 'submitted_at'];

    /** Internal duplicate guard (L21); never sent to a browser. */
    protected $hidden = ['single_key'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * The unique key of a student's only response when "Allow multiple submission" is off (L21).
     */
    public static function singleKey(int $surveyId, int $studentProfileId): string
    {
        return $surveyId.':'.$studentProfileId;
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }
}
