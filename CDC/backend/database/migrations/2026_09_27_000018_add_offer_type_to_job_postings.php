<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offer category chosen by the admin at float time (owner decision, QA F-004): JNF → fulltime | intern_fulltime,
     * INF → intern | intern_performance_ppo. Pre-selects the offer type at announcement and drives the default block.
     */
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->string('offer_type', 32)->nullable()->after('share_contact_details');
        });
    }

    public function down(): void
    {
        Schema::table('job_postings', function (Blueprint $table) {
            $table->dropColumn('offer_type');
        });
    }
};
