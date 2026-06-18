<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlumniOutreachSubmission extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'country_code',
        'phone_number',
        'phone',
        'graduation_year',
        'programme',
        'department',
        'current_organization',
        'current_designation',
        'city',
        'country',
        'linkedin_url',
        'willing_to_mentor',
        'willing_to_refer',
        'message',
        'general_comments',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'graduation_year' => 'integer',
            'willing_to_mentor' => 'boolean',
            'willing_to_refer' => 'boolean',
        ];
    }
}
