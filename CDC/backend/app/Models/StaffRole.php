<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A staff role built by a super admin from the permission catalogue (App\Support\StaffPermissions).
 * The system role with slug `full_access` grants every permission at its highest level and stores no rows.
 */
class StaffRole extends Model
{
    public const FULL_ACCESS = 'full_access';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'name',
        'description',
        'color',
        'is_system',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(StaffRolePermission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFullAccess(): bool
    {
        return $this->slug === self::FULL_ACCESS;
    }

    public static function fullAccess(): self
    {
        return self::query()->firstOrCreate(
            ['slug' => self::FULL_ACCESS],
            ['name' => 'Full Access', 'description' => 'Everything except managing users and roles. Always includes new permissions.', 'color' => 'primary', 'is_system' => true]
        );
    }
}
