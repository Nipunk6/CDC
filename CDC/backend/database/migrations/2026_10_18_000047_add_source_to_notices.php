<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 2026-10-18: every job profile opened for applications is announced on the student Notices page,
 * to all students. `source` marks those automatic notices ('job_profile') apart from the ones admins write (null),
 * so the portal finds and keeps exactly one per job profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->string('source', 30)->nullable()->after('job_posting_id');
            $table->index(['source', 'job_posting_id']);
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropIndex(['source', 'job_posting_id']);
            $table->dropColumn('source');
        });
    }
};
