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
        Schema::table('alumni_outreach_submissions', function (Blueprint $table): void {
            $table->text('general_comments')->nullable()->after('message');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alumni_outreach_submissions', function (Blueprint $table): void {
            $table->dropColumn('general_comments');
        });
    }
};
