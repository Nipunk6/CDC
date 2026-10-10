<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-1.2: when the daily recipient cap is used up, a mail is deferred to a later IST day instead of being dropped.
 * `scheduled_for` (UTC) records when its queued send becomes due; NULL = sent or queued straight away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->timestamp('scheduled_for')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn('scheduled_for');
        });
    }
};
