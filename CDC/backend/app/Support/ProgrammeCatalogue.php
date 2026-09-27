<?php

namespace App\Support;

use App\Models\ProgrammeBranch;

/**
 * Canonical programme → branches catalogue: built-ins from config/programmes.php
 * merged with active admin-added custom rows from programme_branches.
 */
final class ProgrammeCatalogue
{
    /**
     * @return array<string, list<string>> programme name => branch names
     */
    public static function all(): array
    {
        /** @var array<string, list<string>> $catalogue */
        $catalogue = config('programmes', []);

        $custom = ProgrammeBranch::query()
            ->where('is_custom', true)
            ->where('is_active', true)
            ->orderBy('programme_name')
            ->orderBy('branch_name')
            ->get(['programme_name', 'branch_name']);

        foreach ($custom as $row) {
            $catalogue[$row->programme_name] ??= [];

            if (! in_array($row->branch_name, $catalogue[$row->programme_name], true)) {
                $catalogue[$row->programme_name][] = $row->branch_name;
            }
        }

        return $catalogue;
    }

    /** @return list<string> */
    public static function programmes(): array
    {
        return array_keys(self::all());
    }

    /** @return list<string> */
    public static function branchesFor(string $programme): array
    {
        return self::all()[$programme] ?? [];
    }

    public static function hasProgramme(string $programme): bool
    {
        return array_key_exists($programme, self::all());
    }

    public static function has(string $programme, string $branch): bool
    {
        return in_array($branch, self::branchesFor($programme), true);
    }
}
