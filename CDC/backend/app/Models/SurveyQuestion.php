<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyQuestion extends Model
{
    /** Toolbar controls (labels in the admin builder). */
    public const TYPES = ['mcq_single', 'mcq_multi', 'text', 'yes_no', 'dropdown', 'date', 'rating', 'static_text', 'rich_text', 'file', 'sequence'];

    /** Controls that take options. */
    public const WITH_OPTIONS = ['mcq_single', 'mcq_multi', 'dropdown', 'sequence'];

    /** Display-only controls (no answer). */
    public const DISPLAY_ONLY = ['static_text'];

    /**
     * @var list<string>
     */
    protected $fillable = ['survey_id', 'qtype', 'question', 'help_text', 'options', 'settings', 'required', 'sort_order'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'settings' => 'array',
            'required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function isAnswerable(): bool
    {
        return ! in_array($this->qtype, self::DISPLAY_ONLY, true);
    }

    public function ratingMax(): int
    {
        return max(2, min(10, (int) ($this->settings['max'] ?? 5)));
    }
}
