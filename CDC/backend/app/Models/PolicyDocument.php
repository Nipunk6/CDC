<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PolicyDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'url',
        'is_visible_jnf',
        'is_visible_inf',
    ];

    protected $casts = [
        'is_visible_jnf' => 'boolean',
        'is_visible_inf' => 'boolean',
    ];
}
