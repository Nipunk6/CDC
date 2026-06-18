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
        Schema::table('companies', function (Blueprint $table) {
            $table->string('hr_designation')->nullable()->after('hr_name');
            $table->string('hr_alt_phone')->nullable()->after('hr_phone');

            $table->text('postal_address')->nullable()->after('website');
            $table->unsignedInteger('employee_count')->nullable()->after('postal_address');
            $table->string('sector')->nullable()->after('industry');
            $table->string('logo_path')->nullable()->after('sector');

            $table->json('head_talent_contact')->nullable()->after('logo_path');
            $table->json('primary_contact')->nullable()->after('head_talent_contact');
            $table->json('secondary_contact')->nullable()->after('primary_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'hr_designation',
                'hr_alt_phone',
                'postal_address',
                'employee_count',
                'sector',
                'logo_path',
                'head_talent_contact',
                'primary_contact',
                'secondary_contact',
            ]);
        });
    }
};
