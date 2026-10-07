<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S4.5: admin-only internal notes about a student ("Write notes about this student").
 * Never serialised into any student or company payload.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['student_profile_id', 'created_at'], 'student_notes_student_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_notes');
    }
};
