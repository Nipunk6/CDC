<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * The one write path for academic standing (CGPA + backlogs).
 *
 * Today it is fed by the admin's Excel re-upload; the future institute-DB sync
 * must call apply() too, so eligibility always sees data from one place.
 */
class StudentAcademicSyncService
{
    public const FIELDS = ['current_cgpa', 'ongoing_backlogs', 'total_backlogs'];

    /** Optional S4.6 academic extras, after FIELDS in the sheet. Blank cells keep the current value, like FIELDS. */
    public const EXTRA_FIELDS = StudentProfile::ACADEMIC_EXTRAS;

    public function __construct(
        private readonly AuditService $audit,
        private readonly PortalNotificationService $notifications,
        private readonly StudentAccountService $accounts
    ) {
    }

    /**
     * @param  list<array{row?: int, roll_no: string, current_cgpa?: mixed, ongoing_backlogs?: mixed, total_backlogs?: mixed}>  $rows
     * @return array{updated: int, unchanged: int, errors: list<array{row: int|null, roll_no: string, field: string|null, reason: string}>}
     */
    public function apply(array $rows, ?User $admin, ?string $ip = null): array
    {
        $report = ['updated' => 0, 'unchanged' => 0, 'errors' => []];
        $rollNumbers = array_values(array_unique(array_map(
            static fn (array $row): string => strtoupper(trim((string) ($row['roll_no'] ?? ''))),
            $rows
        )));

        $students = [];
        foreach (array_chunk($rollNumbers, 500) as $chunk) {
            foreach (StudentProfile::query()->with('user')->whereIn('roll_no', $chunk)->get() as $student) {
                $students[$student->roll_no] = $student;
            }
        }

        $seen = [];

        foreach ($rows as $row) {
            $rowNumber = $row['row'] ?? null;
            $rollNo = strtoupper(trim((string) ($row['roll_no'] ?? '')));

            if ($rollNo === '') {
                $report['errors'][] = ['row' => $rowNumber, 'roll_no' => '', 'field' => 'roll_no', 'reason' => 'Roll number is missing.'];

                continue;
            }

            if (isset($seen[$rollNo])) {
                $report['errors'][] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => 'roll_no', 'reason' => 'Duplicate roll number in this upload.'];

                continue;
            }
            $seen[$rollNo] = true;

            $student = $students[$rollNo] ?? null;

            if (! $student) {
                $report['errors'][] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => 'roll_no', 'reason' => 'No student found with this roll number.'];

                continue;
            }

            $values = [];
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $row) && trim((string) $row[$field]) !== '') {
                    $values[$field] = trim((string) $row[$field]);
                }
            }

            $extras = [];
            foreach (self::EXTRA_FIELDS as $field) {
                if (array_key_exists($field, $row) && trim((string) $row[$field]) !== '') {
                    $extras[$field] = trim((string) $row[$field]);
                }
            }
            $extras = $this->accounts->normalise($extras);

            // Cross-field checks need the stored partner value when the sheet leaves it blank.
            $context = [];
            if (isset($extras['course_end_date']) && ! isset($extras['course_start_date'])) {
                $context['course_start_date'] = $student->course_start_date?->format('Y-m-d');
            }
            if (isset($extras['previous_degree_score']) && ! isset($extras['previous_degree_score_type'])) {
                $context['previous_degree_score_type'] = $student->previous_degree_score_type;
            }

            $validator = Validator::make(array_merge($student->only(self::FIELDS), $values, $context, $extras), [
                'current_cgpa' => ['nullable', 'numeric', 'min:0', 'max:10'],
                'ongoing_backlogs' => ['required', 'integer', 'min:0', 'max:100'],
                'total_backlogs' => ['required', 'integer', 'min:0', 'max:200', 'gte:ongoing_backlogs'],
            ] + StudentAccountService::academicExtraRules(), [
                'total_backlogs.gte' => 'Total backlogs cannot be fewer than ongoing backlogs.',
            ] + StudentAccountService::messages());

            if ($validator->fails()) {
                $field = array_key_first($validator->errors()->toArray());
                $report['errors'][] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => $field, 'reason' => $validator->errors()->first($field)];

                continue;
            }

            $tracked = array_merge(self::FIELDS, array_keys($extras));
            $before = $student->only($tracked);
            $student->fill($values + $extras);

            if (! $student->isDirty()) {
                $report['unchanged']++;

                continue;
            }

            $student->save();
            $report['updated']++;

            $this->audit->logAs($admin, $ip, 'student.academics_sync', $student, $before, $student->only($tracked));
            $this->notifications->createInAppNotification(
                $student->user,
                'Academic record updated',
                sprintf(
                    'Your academic record was updated by the CDC: CGPA %s, ongoing backlogs %d, total backlogs %d.',
                    $student->current_cgpa ?? '—',
                    $student->ongoing_backlogs,
                    $student->total_backlogs
                ),
                'info'
            );
        }

        return $report;
    }
}
