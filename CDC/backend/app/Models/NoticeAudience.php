<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeAudience extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['notice_id', 'audience_type', 'audience_filter'];

    protected function casts(): array
    {
        return ['audience_filter' => 'array'];
    }

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    /** @return array{audience_type: string, audience_filter: array<string, mixed>|null} */
    public function toGroup(): array
    {
        return ['audience_type' => $this->audience_type, 'audience_filter' => $this->audience_filter];
    }
}
