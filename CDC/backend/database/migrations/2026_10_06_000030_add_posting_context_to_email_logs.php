<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S6.8 (Communication Log): which job profile a mail belongs to, and what kind of mail it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->foreignId('job_posting_id')->nullable()->after('user_id')->constrained('job_postings')->nullOnDelete();
            $table->string('kind', 40)->nullable()->after('template');
            $table->index(['job_posting_id', 'created_at'], 'email_logs_posting_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex('email_logs_posting_created_index');
            $table->dropConstrainedForeignId('job_posting_id');
            $table->dropColumn('kind');
        });
    }
};
