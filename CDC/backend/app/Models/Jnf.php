<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Jnf extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'job_title',
        'job_description',
        'job_location',
        'ctc_min',
        'ctc_max',
        'vacancies',
        'application_deadline',
        'status',
        'admin_remarks',
        'edit_access_requested_at',
        'edit_access_requested_reason',
        'form_data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'application_deadline' => 'date',
            'ctc_min' => 'integer',
            'ctc_max' => 'integer',
            'vacancies' => 'integer',
            'edit_access_requested_at' => 'datetime',
            'form_data' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function statusHistories(): MorphMany
    {
        return $this->morphMany(FormStatusHistory::class, 'form');
    }
}
