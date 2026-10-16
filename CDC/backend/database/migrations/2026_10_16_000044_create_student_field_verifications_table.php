<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profile verification: one row per student per student-changeable field (contact fields, profile links, photo).
 * A missing row means "unverified", so every existing student starts unverified without a backfill.
 * The student's value applies at once; the admin then accepts (verified) or rejects it with a remark.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_field_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->string('field', 40);
            $table->string('status', 20)->default('unverified');
            $table->text('remark')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_profile_id', 'field'], 'student_field_verifications_student_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_field_verifications');
    }
};
