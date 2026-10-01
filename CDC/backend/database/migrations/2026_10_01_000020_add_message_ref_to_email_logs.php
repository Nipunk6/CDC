<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a queued message to its email_logs rows so the queue worker can mark them sent or failed (QA F-015).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('message_ref', 36)->nullable()->after('template')->index();
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['message_ref']);
            $table->dropColumn('message_ref');
        });
    }
};
