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
        Schema::create('alumni_outreach_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->index();
            $table->string('country_code', 10)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->string('programme', 120)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('current_organization')->nullable();
            $table->string('current_designation')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('country', 120)->nullable();
            $table->string('linkedin_url')->nullable();
            $table->boolean('willing_to_mentor')->default(false);
            $table->boolean('willing_to_refer')->default(false);
            $table->text('message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alumni_outreach_submissions');
    }
};
