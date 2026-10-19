<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Company History (owner decisions 2026-10-20, P3-D55): the company's HR contact as it was when each job profile was
 * opened, and a log of every change to a company's contacts. Both start empty: earlier contacts were overwritten and
 * cannot be recovered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->json('hr_snapshot')->nullable()->after('announcement');
        });

        Schema::create('company_contact_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20); // company (profile page) | admin (Companies)
            $table->json('before');
            $table->json('after');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at'], 'company_contact_changes_company_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_contact_changes');
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('hr_snapshot');
        });
    }
};
