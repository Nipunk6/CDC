<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Owner request 2026-10-19: the calendar is fully customisable. A user can rename, move, annotate or hide an automatic
 * item (event, deadline, selection stage, date of visit) in their own calendar only; the placement data is untouched.
 * `item_key` names the item ("round:12"); title/starts_at/link are a snapshot so a moved item shows in its new month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('item_key', 40);
            $table->string('type', 20);
            $table->boolean('hidden')->default(false);
            $table->string('title', 255);
            $table->timestamp('starts_at');
            $table->text('note')->nullable();
            $table->string('link', 255)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_overrides');
    }
};
