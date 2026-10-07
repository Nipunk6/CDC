<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S6: date of visit and scheduled opening on job profiles, venue on stages, help text on questions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->date('visit_date')->nullable()->after('application_deadline');
            // While set (and in the future) the job profile is hidden from students; the scheduler opens it (S6.2).
            $table->timestamp('scheduled_open_at')->nullable()->after('visit_date');
            $table->index('scheduled_open_at', 'job_postings_scheduled_open_index');
        });

        Schema::table('posting_rounds', function (Blueprint $table) {
            $table->string('venue', 255)->nullable()->after('scheduled_at');
        });

        Schema::table('posting_questions', function (Blueprint $table) {
            $table->string('help_text', 500)->nullable()->after('question');
        });
    }

    public function down(): void
    {
        Schema::table('posting_questions', fn (Blueprint $table) => $table->dropColumn('help_text'));
        Schema::table('posting_rounds', fn (Blueprint $table) => $table->dropColumn('venue'));
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropIndex('job_postings_scheduled_open_index');
            $table->dropColumn(['visit_date', 'scheduled_open_at']);
        });
    }
};
