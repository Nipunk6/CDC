<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('programme_branches', function (Blueprint $table): void {
            if (! Schema::hasColumn('programme_branches', 'is_custom')) {
                $table->boolean('is_custom')->default(true)->after('branch_name');
            }
        });

        DB::table('programme_branches')->whereNull('is_custom')->update([
            'is_custom' => true,
        ]);

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        if ($this->hasIndex('programme_branches', 'programme_branches_programme_name_branch_name_is_custom_unique')) {
            return;
        }

        Schema::table('programme_branches', function (Blueprint $table): void {
            if ($this->hasIndex('programme_branches', 'programme_branches_programme_name_branch_name_unique')) {
                $table->dropUnique('programme_branches_programme_name_branch_name_unique');
            }

            $table->unique(['programme_name', 'branch_name', 'is_custom']);
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('programme_branches', function (Blueprint $table): void {
            if ($this->hasIndex('programme_branches', 'programme_branches_programme_name_branch_name_is_custom_unique')) {
                $table->dropUnique('programme_branches_programme_name_branch_name_is_custom_unique');
            }

            if (! $this->hasIndex('programme_branches', 'programme_branches_programme_name_branch_name_unique')) {
                $table->unique(['programme_name', 'branch_name']);
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', $connection->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }
};
