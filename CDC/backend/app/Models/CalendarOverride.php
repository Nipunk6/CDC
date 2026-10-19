<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's change to an automatic calendar item, seen only by them. Served by CalendarOverrideController.
 */
class CalendarOverride extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'item_key',
        'type',
        'hidden',
        'title',
        'starts_at',
        'label',
        'done_at',
        'note',
        'link',
        'remind_minutes',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'hidden' => 'boolean',
            'starts_at' => 'datetime',
            'done_at' => 'datetime',
            'reminded_at' => 'datetime',
            'remind_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
