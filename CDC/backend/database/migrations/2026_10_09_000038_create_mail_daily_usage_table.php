<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P-1.2 daily mail-recipient cap: recipients reserved per IST day by MailDispatchService (every To and BCC address
 * counts). Days after today hold mail that was deferred because an earlier day was full.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_daily_usage', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date')->unique();
            $table->unsignedInteger('recipients')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_daily_usage');
    }
};
