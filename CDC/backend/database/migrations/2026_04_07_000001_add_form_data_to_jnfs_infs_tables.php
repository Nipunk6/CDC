<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds form_data JSON column to store comprehensive form data
     * from the professional JNF/INF forms (eligibility matrix, salary grid,
     * selection process, declarations, etc.)
     */
    public function up(): void
    {
        Schema::table('jnfs', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('admin_remarks');
        });

        Schema::table('infs', function (Blueprint $table) {
            $table->json('form_data')->nullable()->after('admin_remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jnfs', function (Blueprint $table) {
            $table->dropColumn('form_data');
        });

        Schema::table('infs', function (Blueprint $table) {
            $table->dropColumn('form_data');
        });
    }
};
