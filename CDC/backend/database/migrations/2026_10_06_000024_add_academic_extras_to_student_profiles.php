<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S4.6: extra academic fields, entered by the CDC only (bulk import, academic update, admin form).
 * Students see them read-only. Semester-wise CGPA waits for the institute sync.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_semester')->nullable();
            $table->date('course_start_date')->nullable();
            $table->date('course_end_date')->nullable();
            $table->boolean('lateral_entry')->default(false);
            $table->string('tenth_board', 120)->nullable();
            $table->unsignedSmallInteger('tenth_passing_year')->nullable();
            $table->string('twelfth_board', 120)->nullable();
            $table->unsignedSmallInteger('twelfth_passing_year')->nullable();
            $table->string('previous_degree', 120)->nullable();
            $table->decimal('previous_degree_score', 5, 2)->nullable();
            $table->string('previous_degree_score_type', 20)->nullable(); // cgpa | percentage
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'current_semester', 'course_start_date', 'course_end_date', 'lateral_entry', 'tenth_board', 'tenth_passing_year',
                'twelfth_board', 'twelfth_passing_year', 'previous_degree', 'previous_degree_score', 'previous_degree_score_type',
            ]);
        });
    }
};
