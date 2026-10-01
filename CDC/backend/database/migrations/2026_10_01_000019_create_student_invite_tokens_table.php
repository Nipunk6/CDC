<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Set-password tokens of student invitations (E1). A broker of their own so they last 7 days while
 * forgot-password links keep their 60 minutes (owner decision, QA F-011).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_invite_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_invite_tokens');
    }
};
