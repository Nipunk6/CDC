<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Template tab's controls. qtype: mcq_single | mcq_multi | text | yes_no | dropdown | date | rating |
 * static_text | rich_text | file | sequence. Answers live in survey_responses.answers keyed by question id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('qtype', 20);
            $table->text('question');
            $table->string('help_text', 1000)->nullable();
            $table->json('options')->nullable();
            $table->json('settings')->nullable(); // e.g. {"max": 5} for Rating
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['survey_id', 'sort_order'], 'survey_questions_survey_sort_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_questions');
    }
};
