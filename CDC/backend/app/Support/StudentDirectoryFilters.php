<?php

namespace App\Support;

use App\Models\CycleEnrollment;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * "Apply Filters" for the admin student lists (Superset parity S4.1): GET /admin/students, the placement's enrolled
 * list and the "Download as Excel" of each share these rules, so a list and its download always agree.
 *
 * Ranges are inclusive (≥ / ≤), the same way eligibility cutoffs work; a student with no value on record is left
 * out once a bound is set. "Placed" follows D80: at least one offer other than `ppo_offered`.
 */
final class StudentDirectoryFilters
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'programmes' => ['nullable', 'array', 'max:50'],
            'programmes.*' => ['string', 'max:255'],
            'branches' => ['nullable', 'array', 'max:200'],
            'branches.*' => ['string', 'max:255'],
            'batches' => ['nullable', 'array', 'max:50'],
            'batches.*' => ['integer', 'min:2000', 'max:2100'],
            'genders' => ['nullable', 'array'],
            'genders.*' => ['in:male,female,other'],
            'tenth_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tenth_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'twelfth_min' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'twelfth_max' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cgpa_min' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'cgpa_max' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'ongoing_backlogs_max' => ['nullable', 'integer', 'min:0', 'max:100'],
            'total_backlogs_max' => ['nullable', 'integer', 'min:0', 'max:100'],
            'placement_status' => ['nullable', 'in:placed,not_placed'],
            'blocked_status' => ['nullable', 'in:blocked,not_blocked'],
            'cycle_id' => ['nullable', 'integer', 'exists:placement_cycles,id'],
            // Invitation Status (S5): sent / accepted (Registered) / revoked, or `invited` = not registered yet.
            'invitation_status' => ['nullable', 'in:sent,accepted,revoked,invited'],
        ];
    }

    /**
     * Rules for the placement's enrolled list and its "Download as Excel": the filters above plus `status`, the
     * enrolment status (`active` = Enrolled, `suspended`).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function enrollmentRules(): array
    {
        return self::rules() + ['status' => ['nullable', 'in:active,suspended']];
    }

    /**
     * Narrow a placement's enrolments (`$cycle->enrollments()`) the way its enrolled list does, so the list and its
     * download always hold the same students. Placement and Blocked Status look at `$cycleId` unless `cycle_id`
     * names another placement.
     *
     * @param  Builder<CycleEnrollment>|HasMany<CycleEnrollment, PlacementCycle>  $query
     */
    public static function applyToEnrollments($query, array $filters, int $cycleId): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $studentFilters = array_intersect_key($filters, self::rules());
        if (array_filter($studentFilters, fn ($v) => $v !== null && $v !== '' && $v !== []) !== []) {
            $query->whereHas('studentProfile', fn (Builder $student) => self::apply($student, $studentFilters, $cycleId));
        }
    }

    /**
     * Narrow a StudentProfile query. `$cycleId` (or the `cycle_id` filter) limits Placement and Blocked Status to
     * one placement; without it they look across all placements.
     *
     * @param  Builder<StudentProfile>  $query
     */
    public static function apply(Builder $query, array $filters, ?int $cycleId = null): void
    {
        $cycleId = isset($filters['cycle_id']) ? (int) $filters['cycle_id'] : $cycleId;

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            // `%` and `_` are searched for literally (L28).
            Like::whereContains($query, ['roll_no', 'full_name', 'institute_email', 'personal_email', 'phone'], $search);
        }

        foreach (['programmes' => 'programme', 'branches' => 'branch', 'batches' => 'graduating_batch', 'genders' => 'gender'] as $key => $column) {
            $values = array_values(array_filter((array) ($filters[$key] ?? []), fn ($v) => $v !== null && $v !== ''));
            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }

        foreach (['tenth' => 'tenth_percent', 'twelfth' => 'twelfth_percent', 'cgpa' => 'current_cgpa'] as $key => $column) {
            if (isset($filters[$key.'_min']) && $filters[$key.'_min'] !== '') {
                $query->where($column, '>=', (float) $filters[$key.'_min']);
            }
            if (isset($filters[$key.'_max']) && $filters[$key.'_max'] !== '') {
                $query->where($column, '<=', (float) $filters[$key.'_max']);
            }
        }

        foreach (['ongoing_backlogs', 'total_backlogs'] as $column) {
            if (isset($filters[$column.'_max']) && $filters[$column.'_max'] !== '') {
                $query->where($column, '<=', (int) $filters[$column.'_max']);
            }
        }

        if (! empty($filters['placement_status'])) {
            $placed = function (Builder $offers) use ($cycleId): void {
                $offers->where('offer_type', '!=', 'ppo_offered');
                if ($cycleId) {
                    $offers->where('placement_cycle_id', $cycleId);
                }
            };
            $filters['placement_status'] === 'placed' ? $query->whereHas('offers', $placed) : $query->whereDoesntHave('offers', $placed);
        }

        if (! empty($filters['blocked_status'])) {
            $blocked = function (Builder $blocks) use ($cycleId): void {
                $blocks->where('active', true);
                if ($cycleId) {
                    $blocks->where('placement_cycle_id', $cycleId);
                }
            };
            $filters['blocked_status'] === 'blocked' ? $query->whereHas('placementBlocks', $blocked) : $query->whereDoesntHave('placementBlocks', $blocked);
        }

        if (! empty($filters['invitation_status'])) {
            self::applyInvitationStatus($query, (string) $filters['invitation_status']);
        }
    }

    /**
     * Invitation Status (S5) on the student's user row; see User::invitationStatus().
     *
     * @param  Builder<StudentProfile>  $query
     */
    public static function applyInvitationStatus(Builder $query, string $status): void
    {
        $query->whereHas('user', function (Builder $user) use ($status): void {
            match ($status) {
                'accepted' => $user->whereNotNull('activated_at'),
                'revoked' => $user->whereNull('activated_at')->whereNotNull('invite_revoked_at'),
                'sent' => $user->whereNull('activated_at')->whereNull('invite_revoked_at'),
                default => $user->whereNull('activated_at'),
            };
        });
    }

    /**
     * The filters the request actually set, for audit rows.
     *
     * @return array<string, mixed>
     */
    public static function active(array $filters): array
    {
        return array_filter(
            array_intersect_key($filters, self::rules() + ['programme' => 1, 'branch' => 1, 'graduating_batch' => 1, 'status' => 1]),
            fn ($v, $k) => ! str_contains($k, '.') && $v !== null && $v !== '' && $v !== [],
            ARRAY_FILTER_USE_BOTH
        );
    }
}
