<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner decision 2026-10-18: a student may replace a resume once no application using it is still in progress. The
 * version earlier applications were made with must not change for the CDC or the company, so a replaced resume that
 * applications point to is archived (kept with its file, taken out of its slot: `slot` NULL, `archived_at` set) and
 * the new file gets a new row in the slot. MySQL's unique (student_profile_id, slot) allows any number of NULL slots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->unsignedTinyInteger('slot')->nullable()->change();
            $table->timestamp('archived_at')->nullable()->after('reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('resumes', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
