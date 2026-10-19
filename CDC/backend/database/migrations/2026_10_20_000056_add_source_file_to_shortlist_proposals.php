<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company bulk upload of stage decisions (owner decisions 2026-10-20, P3-D56): a proposal made from an uploaded sheet
 * keeps the file's name and a copy of it, so the CDC sees where it came from and can download the original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shortlist_proposals', function (Blueprint $table) {
            $table->string('source_file_name')->nullable()->after('payload');
            $table->string('source_file_path')->nullable()->after('source_file_name');
        });
    }

    public function down(): void
    {
        Schema::table('shortlist_proposals', function (Blueprint $table) {
            $table->dropColumn(['source_file_name', 'source_file_path']);
        });
    }
};
