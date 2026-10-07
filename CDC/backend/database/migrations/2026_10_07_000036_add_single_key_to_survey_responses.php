<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One response per student when "Allow multiple submission" is off, guarded by the database (L21): such a response
 * carries `single_key` = "<survey id>:<student profile id>" under a unique index, so two simultaneous first
 * submissions cannot both be stored. Responses to surveys that allow several submissions keep it NULL (NULLs never
 * collide). Existing rows: the first response of each student to a single-submission survey gets the key; any older
 * duplicates stay NULL, so the index builds cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->string('single_key', 64)->nullable()->after('student_profile_id');
        });

        DB::table('survey_responses')
            ->join('surveys', 'surveys.id', '=', 'survey_responses.survey_id')
            ->where('surveys.allow_multiple', false)
            ->groupBy('survey_responses.survey_id', 'survey_responses.student_profile_id')
            ->selectRaw('min(survey_responses.id) as id, survey_responses.survey_id, survey_responses.student_profile_id')
            ->orderBy('survey_responses.survey_id')
            ->get()
            ->each(fn ($row) => DB::table('survey_responses')->where('id', $row->id)->update(['single_key' => $row->survey_id.':'.$row->student_profile_id]));

        Schema::table('survey_responses', function (Blueprint $table) {
            $table->unique('single_key', 'survey_responses_single_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropUnique('survey_responses_single_key_unique');
        });
        Schema::table('survey_responses', function (Blueprint $table) {
            $table->dropColumn('single_key');
        });
    }
};
