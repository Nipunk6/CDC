<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SEC-003: the "is this address verified yet?" check is answered only for the browser that asked for the link.
     * That browser gets a status token; its hash is kept here.
     */
    public function up(): void
    {
        Schema::table('recruiter_email_verifications', function (Blueprint $table) {
            $table->string('status_token_hash', 64)->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('recruiter_email_verifications', function (Blueprint $table) {
            $table->dropColumn('status_token_hash');
        });
    }
};
