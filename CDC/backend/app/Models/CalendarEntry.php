<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's own calendar entry, shown only to them beside the automatic calendar items. Served by CalendarEntryController.
 */
class CalendarEntry extends Model
{
    public const COLORS = ['purple', 'blue', 'green', 'orange', 'red', 'teal', 'grey'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'starts_at',
        'note',
        'color',
        'done_at',
        'remind_minutes',
        'reminded_at',
    ];

    protected function casts(): array
    {
        return [
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
