<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 3 S1: `mentor` joins the user roles (N12). Mentor accounts and pages come with P8.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','company','student','mentor') NOT NULL DEFAULT 'company'");
        } else {
            // SQLite (tests) has no real enums; the column already accepts any string.
        }
    }

    public function down(): void
    {
        // Never deletes accounts: the enum is narrowed again only when no mentor exists.
        if (DB::getDriverName() === 'mysql' && ! DB::table('users')->where('role', 'mentor')->exists()) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','company','student') NOT NULL DEFAULT 'company'");
        }
    }
};
