<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 2026-10-18: a published stage decision can be changed (the row becomes a draft again). The
 * decision the student last saw is kept here, so changing it back before publishing restores it silently instead of
 * mailing the student a "new" result that is the same as before. Cleared when the row is published again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_round_results', function (Blueprint $table) {
            $table->string('published_result', 20)->nullable()->after('published_at');
            $table->timestamp('previously_published_at')->nullable()->after('published_result');
        });
    }

    public function down(): void
    {
        Schema::table('application_round_results', function (Blueprint $table) {
            $table->dropColumn(['published_result', 'previously_published_at']);
        });
    }
};
