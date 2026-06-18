<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasFactory;

    /**
     * The accessors to append to the model's array / JSON form.
     *
     * @var list<string>
     */
    protected $appends = [
        'logo_url',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'industry',
        'sector',
        'website',
        'postal_address',
        'employee_count',
        'logo_path',
        'hr_name',
        'hr_designation',
        'hr_email',
        'hr_phone',
        'hr_alt_phone',
        'head_talent_contact',
        'primary_contact',
        'secondary_contact',
        'category_org_type',
        'date_of_establishment',
        'annual_turnover',
        'linkedin_url',
        'industry_sector_tags',
        'mnc_hq_country_city',
        'nature_of_business',
        'company_description',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'head_talent_contact' => 'array',
        'primary_contact' => 'array',
        'secondary_contact' => 'array',
        'industry_sector_tags' => 'array',
        'date_of_establishment' => 'date',
    ];

    /**
     * Get the full public URL for the company logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function jnfs(): HasMany
    {
        return $this->hasMany(Jnf::class);
    }

    public function infs(): HasMany
    {
        return $this->hasMany(Inf::class);
    }
}
