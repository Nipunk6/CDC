<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S8.2: the Users directory's Edit User fields. All nullable; `name` stays the display name and is
 * rebuilt from first/middle/last whenever an admin edits them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable()->after('name');
            $table->string('middle_name', 100)->nullable()->after('first_name');
            $table->string('last_name', 100)->nullable()->after('middle_name');
            $table->string('designation', 150)->nullable()->after('last_name');
            $table->string('mobile', 25)->nullable()->after('designation');
            $table->string('alias', 100)->nullable()->after('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['first_name', 'middle_name', 'last_name', 'designation', 'mobile', 'alias']));
    }
};
