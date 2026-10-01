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
        Schema::create('application_round_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('posting_round_id')->constrained()->cascadeOnDelete();
            $table->enum('attendance', ['yes', 'no'])->nullable();
            $table->enum('result', ['pending', 'selected', 'rejected', 'waitlisted'])->default('pending');
            $table->boolean('is_addendum')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('remark')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'posting_round_id']);
            $table->index(['posting_round_id', 'result']);
            $table->index('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_round_results');
    }
};
