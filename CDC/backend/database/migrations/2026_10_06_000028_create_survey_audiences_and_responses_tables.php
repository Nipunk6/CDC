<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Target Audience for this survey" groups (any match may answer) and the responses (answers JSON keyed by question id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('audience_type', 40); // all | branches | cycle | batch | offer_holders | posting_applicants
            $table->json('audience_filter')->nullable();
            $table->timestamps();
        });

        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->foreignId('student_profile_id')->constrained('student_profiles')->cascadeOnDelete();
            $table->json('answers');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['survey_id', 'student_profile_id'], 'survey_responses_survey_student_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_audiences');
    }
};
