<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('category_org_type')->nullable()->comment('Category or Organization Type');
            $table->date('date_of_establishment')->nullable()->comment('Date of Establishment');
            $table->string('annual_turnover')->nullable()->comment('Annual Turnover (NIRF)');
            $table->text('linkedin_url')->nullable()->comment('Social Media / LinkedIn URL');
            $table->json('industry_sector_tags')->nullable()->comment('Industry Sector Tags (multi-select)');
            $table->string('mnc_hq_country_city')->nullable()->comment('If MNC — HQ Country/City');
            $table->text('nature_of_business')->nullable()->comment('Nature of Business');
            $table->longText('company_description')->nullable()->comment('Company Description (rich text)');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'category_org_type',
                'date_of_establishment',
                'annual_turnover',
                'linkedin_url',
                'industry_sector_tags',
                'mnc_hq_country_city',
                'nature_of_business',
                'company_description',
            ]);
        });
    }
};
