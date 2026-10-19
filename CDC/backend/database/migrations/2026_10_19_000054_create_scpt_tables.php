<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P10.1 / P10.2 SCPT and SPOC (owner decisions 2026-10-19, P3-D50): the Student Coordinators for Placement Team of a
 * placement cycle, and which of them is a SPOC (single point of contact) for which job profile. A job profile may have
 * several SPOCs; membership is per cycle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scpt_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->string('designation', 100)->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['placement_cycle_id', 'student_profile_id'], 'scpt_members_cycle_student_unique');
            $table->index('student_profile_id', 'scpt_members_student_index');
        });

        Schema::create('job_posting_spocs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scpt_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_posting_id', 'scpt_member_id'], 'job_posting_spocs_unique');
            $table->index('scpt_member_id', 'job_posting_spocs_member_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_posting_spocs');
        Schema::dropIfExists('scpt_members');
    }
};
