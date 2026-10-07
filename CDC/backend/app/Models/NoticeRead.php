<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NoticeRead extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = ['notice_id', 'student_profile_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
