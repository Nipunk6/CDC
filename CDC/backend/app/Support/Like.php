<?php

namespace App\Support;

/**
 * Literal "contains" search for LIKE: a `%` or `_` the admin types matches that character, not any text.
 *
 * The pattern escapes `\`, `%` and `_` with a backslash, and the escape character is bound as a parameter
 * (`LIKE ? ESCAPE ?`). Written into the SQL a backslash would need `'\\'` on MySQL but `'\'` on SQLite; bound,
 * the same statement runs on both (MySQL in dev and production, SQLite in the tests).
 */
final class Like
{
    public const ESCAPE = '\\';

    /** `%term%` with the LIKE wildcards in `$term` escaped. */
    public static function contains(string $term): string
    {
        return '%'.strtr($term, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
    }

    /**
     * Add `(col1 LIKE ? OR col2 LIKE ? …)` matching `$term` literally anywhere in any of the columns.
     *
     * @template TQuery of \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     *
     * @param  TQuery  $query
     * @param  list<string>  $columns
     * @return TQuery
     */
    public static function whereContains($query, array $columns, string $term)
    {
        $pattern = self::contains($term);

        return $query->where(function ($inner) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $inner->orWhereRaw($inner->getGrammar()->wrap($column).' like ? escape ?', [$pattern, self::ESCAPE]);
            }
        });
    }
}
