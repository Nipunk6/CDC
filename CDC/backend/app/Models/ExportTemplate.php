<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named Excel column layout (Superset "Excel Templates", S3). `columns` is the ordered list of {key, label, cycle_id?};
 * keys come from App\Support\ExportFieldCatalogue.
 */
class ExportTemplate extends Model
{
    public const TYPES = ['STUDENT_LIST'];

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'type', 'columns', 'created_by'];

    protected function casts(): array
    {
        return ['columns' => 'array'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
