<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request 2026-10-19: the student notification center is personal (the Notices page is general). A notification
 * now says what it is about (`category`: new_job, announcement, deadline, status, schedule, result, offer, message, …),
 * which job profile it concerns, where it leads (`link`, a portal path) and, for a job profile, the details shown
 * when the student expands it (courses, eligibility, stages, deadline).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('category', 30)->nullable()->after('type');
            $table->foreignId('job_posting_id')->nullable()->after('category')->constrained('job_postings')->nullOnDelete();
            $table->string('link')->nullable()->after('job_posting_id');
            $table->json('details')->nullable()->after('link');
            $table->index(['user_id', 'job_posting_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'job_posting_id', 'category']);
            $table->dropConstrainedForeignId('job_posting_id');
            $table->dropColumn(['category', 'link', 'details']);
        });
    }
};
