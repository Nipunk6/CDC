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

    /**
     * Case/whitespace-insensitive lookup returning the canonical spelling, so an
     * Excel import with "computer science & engineering" still lands on the catalogue name.
     *
     * @return array{programme: string, branch: string}|null
     */
    public static function resolve(string $programme, string $branch, ?array $catalogue = null): ?array
    {
        $norm = static fn (string $v): string => preg_replace('/\s+/', ' ', strtolower(trim($v))) ?? '';

        foreach ($catalogue ?? self::all() as $name => $branches) {
            if ($norm($name) !== $norm($programme)) {
                continue;
            }

            foreach ($branches as $candidate) {
                if ($norm($candidate) === $norm($branch)) {
                    return ['programme' => $name, 'branch' => $candidate];
                }
            }

            return null;
        }

        return null;
    }

    /**
     * PHP port of getDisplayName() in frontend salarygrid.tsx, used to match a student's programme
     * to the form's programmeSalaries / programmeStipends rows.
     */
    public static function displayName(string $programme): string
    {
        $name = preg_replace('/\s*\(\d+\s*Year\)/i', '', $programme) ?? $programme;
        $name = preg_replace('/\s*-\s*(JEE Advanced|GATE|JAM|CAT|GATE\/NET)$/i', '', $name) ?? $name;

        if ($name === 'B.Tech / B.Tech Double Major / B.Tech-M.Tech Dual Degree') {
            return 'B.Tech / Double Major / Dual Degree';
        }

        return $name;
    }

    public static function sameProgramme(string $a, string $b): bool
    {
        return strcasecmp(self::displayName(trim($a)), self::displayName(trim($b))) === 0;
    }
}
