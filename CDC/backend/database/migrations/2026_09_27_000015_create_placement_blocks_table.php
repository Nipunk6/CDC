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
        Schema::create('placement_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_cycle_id')->constrained()->cascadeOnDelete();
            $table->enum('scope', ['all', 'internships_only']);
            $table->enum('reason', ['offer', 'debarred', 'manual']);
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete();
            $table->string('remark')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unblocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('unblocked_at')->nullable();
            $table->timestamps();

            $table->index(['student_profile_id', 'placement_cycle_id', 'active'], 'placement_blocks_student_cycle_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placement_blocks');
    }
};
