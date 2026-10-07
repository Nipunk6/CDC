<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S8.4 "Student Categories for Placement": CDC-defined tags (minors, double majors…) that a job
 * profile's eligibility may require (owner decision B2-13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120)->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('student_category_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_category_id', 'student_profile_id'], 'student_category_student_unique');
            $table->index('student_profile_id', 'student_category_student_student_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_category_student');
        Schema::dropIfExists('student_categories');
    }
};
