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
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->morphs('postable');
            $table->foreignId('placement_cycle_id')->constrained()->cascadeOnDelete();
            $table->dateTime('application_deadline');
            $table->enum('status', ['open', 'in_process', 'completed', 'cancelled'])->default('open')->index();
            $table->boolean('share_contact_details')->default(false);
            $table->foreignId('floated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('floated_at')->nullable();
            $table->json('eligibility_snapshot')->nullable();
            $table->timestamps();

            $table->unique(['postable_type', 'postable_id']);
            $table->index('application_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
