<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProgrammeBranch extends Model
{
    use HasFactory;

    protected $fillable = [
        'programme_name',
        'branch_name',
        'is_custom',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_custom' => 'boolean',
        'is_active' => 'boolean',
    ];
}
