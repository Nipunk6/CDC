<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request 2026-10-19: Google Calendar style features. Items can be marked done, own entries get a colour, and
 * both own entries and automatic items can carry a reminder (an in-app notification `remind_minutes` before).
 * An override's title/starts_at become optional: null means "as the placement data says", so marking an item done or
 * setting a reminder no longer freezes its title and time; `label` keeps the item's title for reminder text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->string('color', 20)->nullable()->after('note');
            $table->timestamp('done_at')->nullable()->after('color');
            $table->unsignedInteger('remind_minutes')->nullable()->after('done_at');
            $table->timestamp('reminded_at')->nullable()->after('remind_minutes');
        });

        Schema::table('calendar_overrides', function (Blueprint $table) {
            $table->string('title', 255)->nullable()->change();
            $table->timestamp('starts_at')->nullable()->change();
            $table->string('label', 255)->nullable()->after('starts_at');
            $table->timestamp('done_at')->nullable()->after('hidden');
            $table->unsignedInteger('remind_minutes')->nullable()->after('link');
            $table->timestamp('reminded_at')->nullable()->after('remind_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('calendar_overrides', function (Blueprint $table) {
            $table->dropColumn(['label', 'done_at', 'remind_minutes', 'reminded_at']);
        });

        Schema::table('calendar_entries', function (Blueprint $table) {
            $table->dropColumn(['color', 'done_at', 'remind_minutes', 'reminded_at']);
        });
    }
};
