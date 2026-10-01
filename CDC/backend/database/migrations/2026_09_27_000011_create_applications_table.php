<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resume_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['applied', 'withdrawn'])->default('applied')->index();
            $table->boolean('used_unverified_resume')->default(false);
            $table->boolean('placed_elsewhere_flag')->default(false);
            $table->json('answers')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['job_posting_id', 'student_profile_id']);
            $table->index(['student_profile_id', 'status']);
            $table->index('applied_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
