<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S7.1: the student notice board. A notice has one or more audience groups (any match sees it)
 * and per-student read receipts for the unread dots. Companies never see notices (B3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable(); // rich text (HTML); always displayed as plain text (D76)
            $table->string('attachment_path')->nullable(); // private (local) disk
            $table->string('attachment_name')->nullable();
            $table->unsignedInteger('attachment_size')->nullable();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->nullOnDelete(); // context for stage/applicant notices
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('notice_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
            $table->string('audience_type', 40); // all | branches | cycle | posting_applicants | round_results
            $table->json('audience_filter')->nullable();
            $table->timestamps();
        });

        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained('notices')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['notice_id', 'student_profile_id'], 'notice_reads_notice_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notice_audiences');
        Schema::dropIfExists('notices');
    }
};
