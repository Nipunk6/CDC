<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Student invitation tracking (Superset parity S5). On `users` because the invitation is a set-password link for
 * the account (broker `invites`, keyed by users.email) and `resetPassword` works on the user row.
 *
 * Derived status: Accepted = activated_at set; Revoked = invite_revoked_at set and not activated; otherwise Sent.
 *
 * Backfill (existing student users only):
 *  - invited_at = created_at (every student account was mailed E1 when it was created);
 *  - activated_at = now() when the student has ever signed in (a personal_access_tokens row exists) or has a
 *    non-null remember_token (resetPassword writes one whenever a password is set).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('last_invited_at')->nullable();
            $table->unsignedInteger('invite_count')->default(0);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('invite_revoked_at')->nullable();
            $table->index(['role', 'activated_at'], 'users_role_activated_idx');
        });

        DB::table('users')->where('role', 'student')->update([
            'invited_at' => DB::raw('created_at'),
            'last_invited_at' => DB::raw('created_at'),
            'invite_count' => 1,
        ]);

        DB::table('users')
            ->where('role', 'student')
            ->where(function ($q): void {
                $q->whereNotNull('remember_token')
                    ->orWhereExists(function ($tokens): void {
                        $tokens->select(DB::raw(1))
                            ->from('personal_access_tokens')
                            ->whereColumn('personal_access_tokens.tokenable_id', 'users.id')
                            ->where('personal_access_tokens.tokenable_type', 'App\\Models\\User');
                    });
            })
            ->update(['activated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_activated_idx');
            $table->dropColumn(['invited_at', 'last_invited_at', 'invite_count', 'activated_at', 'invite_revoked_at']);
        });
    }
};
