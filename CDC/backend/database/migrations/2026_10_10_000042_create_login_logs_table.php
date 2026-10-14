<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 3 S6 (N4): one row per login attempt, of any role, successful or not. Rows are never updated, so there
     * is only `created_at`. No password is ever stored; an identifier that matches no account is stored as a hash.
     */
    public function up(): void
    {
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Email or roll number as typed (normalised) for a known account; "sha256:<hex>" when no account matched.
            $table->string('identifier', 255);
            $table->string('role', 20)->nullable();
            $table->string('ip', 45);
            // How the address was learned: signed (forwarded by the Next.js server, signature valid), direct
            // (the connection's own address), invalid_signature (a forwarded address was sent but did not verify).
            $table->string('ip_source', 20)->default('direct');
            $table->string('user_agent', 255)->nullable();
            $table->boolean('success');
            $table->string('failure_reason', 40)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['ip', 'created_at']);
            $table->index(['role', 'created_at']);
            $table->index(['success', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_logs');
    }
};
