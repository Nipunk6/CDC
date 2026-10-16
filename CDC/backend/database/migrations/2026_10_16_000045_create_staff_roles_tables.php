<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff roles (owner request 16-10-2026, supersedes the S9 deferral and "every admin is god"):
 * super admins build roles from the fixed permission catalogue (App\Support\StaffPermissions), assign one role
 * per admin, and may tick or untick single permissions for one person (user_permission_overrides).
 * Super admins keep `is_super_admin` and skip every check. The built-in "Full Access" role (slug full_access)
 * always grants everything, also permissions added later; every existing non-super admin gets it here, so nobody
 * loses access when this ships.
 *
 * Invited staff (owner, same day): the super admin enters only name, email and role; the person sets a password
 * from the invitation mail and then fills in their own details. `profile_completed_at` is null until they do;
 * every existing admin counts as complete.
 *
 * Department limit (owner, same day, for the "Dept FIC" role): `staff_departments` lists branch names; when set, the
 * person sees only students of those branches (any programme). Null = every department.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->nullable()->unique();
            $table->string('name', 80)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 20)->default('primary');
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('staff_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_role_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 60);
            $table->string('level', 10);
            $table->timestamps();

            $table->unique(['staff_role_id', 'permission'], 'staff_role_permissions_role_permission_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('staff_role_id')->nullable()->after('is_super_admin')->constrained('staff_roles')->nullOnDelete();
            $table->boolean('staff_role_enabled')->default(true)->after('staff_role_id');
            $table->timestamp('profile_completed_at')->nullable()->after('staff_role_enabled');
            $table->json('staff_departments')->nullable()->after('profile_completed_at');
        });

        Schema::create('user_permission_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 60);
            $table->string('level', 10);
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'permission'], 'user_permission_overrides_user_permission_unique');
        });

        $now = now();
        $roleId = DB::table('staff_roles')->insertGetId([
            'slug' => 'full_access',
            'name' => 'Full Access',
            'description' => 'Everything except managing users and roles. Always includes new permissions.',
            'color' => 'primary',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('users')->where('role', 'admin')->where('is_super_admin', false)->update(['staff_role_id' => $roleId]);
        DB::table('users')->where('role', 'admin')->update(['profile_completed_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_permission_overrides');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_role_id');
            $table->dropColumn(['staff_role_enabled', 'profile_completed_at', 'staff_departments']);
        });
        Schema::dropIfExists('staff_role_permissions');
        Schema::dropIfExists('staff_roles');
    }
};
