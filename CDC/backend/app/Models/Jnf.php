<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * True once an admin has floated this form into a placement cycle (job_postings row exists).
     * The table is created in Phase 2 M5; until then no form can be floated.
     */
    public function isFloated(): bool
    {
        if (! Schema::hasTable('job_postings')) {
            return false;
        }

        return DB::table('job_postings')
            ->where('postable_type', self::class)
            ->where('postable_id', $this->id)
            ->exists();
    }
}
