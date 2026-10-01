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
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('roll_no', 30)->unique();
            $table->string('full_name');
            $table->string('institute_email')->unique();
            $table->string('personal_email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('programme');
            $table->string('branch');
            $table->unsignedSmallInteger('graduating_batch');
            $table->decimal('current_cgpa', 4, 2)->nullable();
            $table->unsignedSmallInteger('ongoing_backlogs')->default(0);
            $table->unsignedSmallInteger('total_backlogs')->default(0);
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth')->nullable();
            $table->decimal('tenth_percent', 5, 2)->nullable();
            $table->decimal('twelfth_percent', 5, 2)->nullable();
            $table->string('category', 30)->nullable();
            $table->boolean('pwd')->default(false);
            $table->string('home_state')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->timestamps();

            $table->index(['programme', 'branch', 'graduating_batch']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_profiles');
    }
};
