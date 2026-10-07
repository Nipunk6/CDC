<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Superset parity S3: admin-defined Excel Templates (ordered column layouts for student / applicant downloads).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('type', 40)->default('STUDENT_LIST');
            $table->json('columns'); // ordered [{key, label, cycle_id?}]
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('type', 'export_templates_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('export_templates');
    }
};
