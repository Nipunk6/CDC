<?php

namespace App\Services;

use App\Models\StudentFieldVerification;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Profile verification. Every student-changeable field (contact fields, profile links, photo) carries a status:
 * unverified (the default, and again after every change by the student), verified or rejected (with a remark).
 * The student's value applies at once. A profile is verified when every field is verified (an empty one too: the
 * admin confirms that leaving it blank is fine) and no resume is pending or rejected.
 */
class StudentVerificationService
{
    /** Field key => label. Keys match student_profiles columns, except `photo` (= photo_path). */
    public const FIELDS = [
        'personal_email' => 'Personal email',
        'phone' => 'Contact No.',
        'home_state' => 'Home state',
        'linkedin_url' => 'LinkedIn',
        'github_url' => 'GitHub',
        'photo' => 'Photo',
    ];

    public const STATUSES = ['unverified', 'verified', 'rejected'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly PortalNotificationService $notifications
    ) {
    }

    /**
     * The student changed these fields: each one goes back to unverified. Unknown keys are ignored.
     *
     * @param  list<string>  $fields
     */
    public function markChanged(StudentProfile $student, array $fields): void
    {
        foreach (array_intersect($fields, array_keys(self::FIELDS)) as $field) {
            StudentFieldVerification::query()->updateOrCreate(
                ['student_profile_id' => $student->id, 'field' => $field],
                ['status' => 'unverified', 'remark' => null, 'changed_at' => now(), 'reviewed_by' => null, 'reviewed_at' => null]
            );
        }
    }

    /**
     * An admin entered these values themselves, so they count as verified.
     *
     * @param  list<string>  $fields
     */
    public function markVerifiedByAdmin(Request $request, StudentProfile $student, array $fields): void
    {
        foreach (array_intersect($fields, array_keys(self::FIELDS)) as $field) {
            StudentFieldVerification::query()->updateOrCreate(
                ['student_profile_id' => $student->id, 'field' => $field],
                ['status' => 'verified', 'remark' => null, 'changed_at' => now(), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]
            );
        }
    }

    /**
     * Accept or reject one field. Returns false (and changes nothing) when the student changed the field after
     * `$expectedChangedAt`, the version the admin looked at.
     */
    public function decide(Request $request, StudentProfile $student, string $field, string $status, ?string $remark, ?string $expectedChangedAt): bool
    {
        $result = DB::transaction(function () use ($request, $student, $field, $status, $remark, $expectedChangedAt) {
            $row = StudentFieldVerification::query()
                ->where('student_profile_id', $student->id)->where('field', $field)->lockForUpdate()->first();

            if (! $this->sameMoment($row?->changed_at, $expectedChangedAt)) {
                return null;
            }

            $before = $row?->only(['status', 'remark']) ?? ['status' => 'unverified', 'remark' => null];
            $row = StudentFieldVerification::query()->updateOrCreate(
                ['student_profile_id' => $student->id, 'field' => $field],
                ['status' => $status, 'remark' => $status === 'rejected' ? $remark : null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]
            );

            return [$before, $row];
        });

        if ($result === null) {
            return false;
        }

        [$before, $row] = $result;
        $this->audit->log($request, 'student.field_'.($status === 'verified' ? 'verify' : 'reject'), $student, ['field' => $field] + $before, ['field' => $field] + $row->only(['status', 'remark']));

        if ($status === 'rejected' && $student->user) {
            $this->notifications->createInAppNotification(
                $student->user,
                'Profile change needs correction',
                sprintf('The CDC could not verify your %s. Remark: %s', strtolower(self::FIELDS[$field]), $remark),
                'warning'
            );
        }

        return true;
    }

    /**
     * "Verify all": accept every listed field the admin saw. Already-verified fields are left alone; a field the
     * student changed after the admin opened the page is skipped so that it gets a fresh look.
     *
     * @param  list<array{field: string, changed_at?: ?string}>  $items
     * @return array{verified: int, skipped: int}  skipped = changed since the admin looked
     */
    public function verifyAll(Request $request, StudentProfile $student, array $items): array
    {
        $statuses = $student->fieldVerifications()->pluck('status', 'field');
        $verified = 0;
        $skipped = 0;

        foreach ($items as $item) {
            $field = $item['field'];
            if (($statuses[$field] ?? null) === 'verified') {
                continue;
            }

            $this->decide($request, $student, $field, 'verified', null, $item['changed_at'] ?? null) ? $verified++ : $skipped++;
        }

        return ['verified' => $verified, 'skipped' => $skipped];
    }

    /**
     * One entry per field for the admin student page and the student's own profile page.
     *
     * @return list<array<string, mixed>>
     */
    public function fieldsFor(StudentProfile $student): array
    {
        $rows = $student->fieldVerifications()->with('reviewer:id,name')->get()->keyBy('field');
        $values = $this->values($student);

        return collect(self::FIELDS)->map(function (string $label, string $field) use ($rows, $values) {
            $row = $rows->get($field);

            return [
                'field' => $field,
                'label' => $label,
                'value' => $field === 'photo' ? null : $values[$field],
                'has_value' => ! $this->isEmpty($values[$field]),
                'status' => $row->status ?? 'unverified',
                'remark' => $row?->remark,
                'changed_at' => $row?->changed_at?->toIso8601String(),
                'reviewed_by' => $row?->reviewer?->name,
                'reviewed_at' => $row?->reviewed_at?->toIso8601String(),
            ];
        })->values()->all();
    }

    /**
     * Whether each student's profile is fully verified, for a page of students in three queries.
     *
     * @param  Collection<int, StudentProfile>  $students
     * @return array<int, bool> student id => verified
     */
    public function verifiedMap(Collection $students): array
    {
        $ids = $students->pluck('id');
        $verifiedFields = StudentFieldVerification::query()->whereIn('student_profile_id', $ids)->where('status', 'verified')
            ->get(['student_profile_id', 'field'])->groupBy('student_profile_id')->map(fn ($rows) => $rows->pluck('field')->all());
        $openResumes = DB::table('resumes')->whereIn('student_profile_id', $ids)->whereIn('status', ['pending', 'rejected'])
            ->distinct()->pluck('student_profile_id')->flip();

        return $students->mapWithKeys(fn (StudentProfile $student) => [
            $student->id => ! $openResumes->has($student->id) && array_diff(array_keys(self::FIELDS), $verifiedFields->get($student->id, [])) === [],
        ])->all();
    }

    public function isVerified(StudentProfile $student): bool
    {
        return $this->verifiedMap(collect([$student]))[$student->id];
    }

    /** @return array<string, mixed> */
    private function values(StudentProfile $student): array
    {
        $values = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $values[$field] = $field === 'photo' ? $student->getAttributes()['photo_path'] ?? null : $student->getAttribute($field);
        }

        return $values;
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function sameMoment(?Carbon $stored, ?string $expected): bool
    {
        if ($expected === null || $expected === '') {
            return $stored === null;
        }

        return $stored !== null && $stored->equalTo(Carbon::parse($expected));
    }
}
