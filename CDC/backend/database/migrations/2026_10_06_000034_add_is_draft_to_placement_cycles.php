<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S8.3: a Draft placement is hidden from students and takes no job profiles until it is published.
 * A separate boolean, not a new `status` enum value, so open/closed keep their meaning everywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placement_cycles', function (Blueprint $table) {
            $table->boolean('is_draft')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('placement_cycles', fn (Blueprint $table) => $table->dropColumn('is_draft'));
    }
};
