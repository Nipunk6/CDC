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
        Schema::table('jnfs', function (Blueprint $table): void {
            $table->timestamp('edit_access_requested_at')->nullable()->after('admin_remarks');
            $table->text('edit_access_requested_reason')->nullable()->after('edit_access_requested_at');
        });

        Schema::table('infs', function (Blueprint $table): void {
            $table->timestamp('edit_access_requested_at')->nullable()->after('admin_remarks');
            $table->text('edit_access_requested_reason')->nullable()->after('edit_access_requested_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jnfs', function (Blueprint $table): void {
            $table->dropColumn(['edit_access_requested_at', 'edit_access_requested_reason']);
        });

        Schema::table('infs', function (Blueprint $table): void {
            $table->dropColumn(['edit_access_requested_at', 'edit_access_requested_reason']);
        });
    }
};