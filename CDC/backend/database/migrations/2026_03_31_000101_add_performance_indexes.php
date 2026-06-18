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
        Schema::table('users', function (Blueprint $table) {
            $table->index('role');
        });

        Schema::table('jnfs', function (Blueprint $table) {
            $table->index('status');
            $table->index(['company_id', 'status']);
            $table->index('created_at');
        });

        Schema::table('infs', function (Blueprint $table) {
            $table->index('status');
            $table->index(['company_id', 'status']);
            $table->index('created_at');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at']);
            $table->index('created_at');
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index('sent_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['sent_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('infs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('jnfs', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
        });
    }
};
