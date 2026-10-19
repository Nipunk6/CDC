<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request 2026-10-19: when a job profile opens for applications the admin chooses whether to post a notice and
 * whether to send an email, each with its own target audience and text. `job_postings.announcement` keeps that choice
 * (a scheduled job profile applies it when it opens); `notices.intro` is the admin's own message shown above the
 * job profile details when a student opens the notice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->json('announcement')->nullable()->after('eligibility_snapshot');
        });

        Schema::table('notices', function (Blueprint $table) {
            $table->text('intro')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('notices', function (Blueprint $table) {
            $table->dropColumn('intro');
        });

        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('announcement');
        });
    }
};
