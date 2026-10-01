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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_cycle_id')->constrained()->cascadeOnDelete();
            $table->enum('offer_type', ['intern', 'intern_ppo', 'ppo_offered', 'fulltime', 'intern_fulltime', 'intern_performance_ppo']);
            $table->unsignedBigInteger('ctc_annual')->nullable();
            $table->unsignedInteger('stipend_monthly')->nullable();
            $table->string('currency', 8)->default('INR');
            $table->foreignId('announced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('announced_at');
            $table->timestamps();

            $table->index(['placement_cycle_id', 'offer_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
