<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S7.3: survey forms. Draft → published → archived (published surveys are never hard-deleted, B2-7).
 * A survey may point at a job profile for context only (PPO consent, B2-5: answers never change offers or blocks).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('survey_type', 30)->default('general'); // general | ppo_consent | feedback
            $table->text('welcome_text')->nullable(); // rich text, displayed as plain text (D76)
            $table->text('concluding_text')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft | published | archived
            $table->boolean('is_public')->default(false); // any signed-in student (B2-6)
            $table->boolean('allow_multiple')->default(false);
            $table->boolean('allow_edits')->default(false);
            $table->timestamp('deadline_at')->nullable();
            $table->foreignId('job_posting_id')->nullable()->constrained('job_postings')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
