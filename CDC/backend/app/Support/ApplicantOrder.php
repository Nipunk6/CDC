<?php

namespace App\Support;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Company applicant lists sorted by CGPA (owner request 2026-10-19, P3-D54). Without a sort the list keeps the order
 * of applying. Students with no CGPA on record always come last; equal CGPAs are ordered by roll number. The company
 * page sorts the same way (frontend `sortByCgpa`), so its downloads match what is on screen.
 */
final class ApplicantOrder
{
    /** @return 'asc'|'desc'|null the CGPA direction asked for in `?sort=cgpa&dir=…`, or null for the default order */
    public static function fromRequest(Request $request): ?string
    {
        $validated = $request->validate([
            'sort' => ['nullable', 'in:cgpa'],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        return ($validated['sort'] ?? null) === 'cgpa' ? ($validated['dir'] ?? 'desc') : null;
    }

    /**
     * @param  Collection<int, Application>  $applications  with studentProfile loaded
     * @return Collection<int, Application>
     */
    public static function byCgpa(Collection $applications, ?string $direction): Collection
    {
        if ($direction === null) {
            return $applications->values();
        }

        return $applications->sort(function (Application $a, Application $b) use ($direction) {
            $x = $a->studentProfile?->current_cgpa;
            $y = $b->studentProfile?->current_cgpa;
            if ($x === null || $y === null) {
                if ($x !== $y) {
                    return $x === null ? 1 : -1; // no CGPA on record: last, whichever direction
                }
            } elseif ((float) $x !== (float) $y) {
                return $direction === 'desc' ? ((float) $y <=> (float) $x) : ((float) $x <=> (float) $y);
            }

            return strcmp((string) $a->studentProfile?->roll_no, (string) $b->studentProfile?->roll_no);
        })->values();
    }
}
