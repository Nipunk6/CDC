<?php

namespace App\Services;

use App\Jobs\SendStudentInvitation;
use App\Mail\StudentInvitationMail;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\ProgrammeCatalogue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Creating student accounts (single form, bulk import, demo seeder) and sending the E1 invitation.
 */
class StudentAccountService
{
    /** Column order of the bulk-import sheet (spec M2.2). */
    public const IMPORT_COLUMNS = [
        'roll_no', 'full_name', 'institute_email', 'programme', 'branch', 'graduating_batch', 'gender',
        'current_cgpa', 'ongoing_backlogs', 'total_backlogs', 'tenth_percent', 'twelfth_percent',
        'date_of_birth', 'personal_email', 'phone', 'category', 'pwd', 'home_state',
    ];

    public function __construct(private readonly MailDispatchService $mail)
    {
    }

    /**
     * Validation rules for every C3 field. `$ignore` skips the unique checks for the profile being edited.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(?StudentProfile $ignore = null, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return [
            'roll_no' => [$required, 'string', 'max:30', 'regex:/^[A-Z0-9]+$/', Rule::unique('student_profiles', 'roll_no')->ignore($ignore?->id)],
            'full_name' => [$required, 'string', 'max:255', 'regex:/^[\pL\s\'.-]+$/u'],
            'institute_email' => [
                $required, 'string', 'email', 'max:255',
                Rule::unique('student_profiles', 'institute_email')->ignore($ignore?->id),
                Rule::unique('users', 'email')->ignore($ignore?->user_id),
            ],
            'programme' => [$required, 'string', 'max:255'],
            'branch' => [$required, 'string', 'max:255'],
            'graduating_batch' => [$required, 'integer', 'min:2000', 'max:2100'],
            'gender' => [$required, 'in:male,female,other'],
            'current_cgpa' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'ongoing_backlogs' => ['nullable', 'integer', 'min:0', 'max:100'],
            'total_backlogs' => ['nullable', 'integer', 'min:0', 'max:200', 'gte:ongoing_backlogs'],
            'tenth_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'twelfth_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // normalise() turns every accepted spelling into Y-m-d; anything else (e.g. a two-digit year) is refused (QA F-006).
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before:today'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'category' => ['nullable', 'string', 'max:30'],
            'pwd' => ['nullable', 'boolean'],
            'home_state' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public static function messages(): array
    {
        return [
            'roll_no.regex' => 'Roll number may contain only letters and digits.',
            'roll_no.unique' => 'A student with this roll number already exists.',
            'institute_email.unique' => 'This institute email is already registered.',
            'full_name.regex' => 'Name may contain only letters, spaces, apostrophes, dots and hyphens.',
            'total_backlogs.gte' => 'Total backlogs cannot be fewer than ongoing backlogs.',
            'gender.in' => 'Gender must be male, female or other.',
            'date_of_birth.date_format' => 'Date of birth must be a full date with a four-digit year, e.g. 06-05-2004 or 2004-05-06.',
        ];
    }

    /**
     * Bring free-typed values (form or spreadsheet) to their stored shape before validation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function normalise(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                $data[$key] = $value === '' ? null : $value;
            }
        }

        if (isset($data['roll_no'])) {
            $data['roll_no'] = strtoupper((string) $data['roll_no']);
        }

        foreach (['institute_email', 'personal_email'] as $field) {
            if (isset($data[$field])) {
                $data[$field] = strtolower((string) $data[$field]);
            }
        }

        if (isset($data['gender'])) {
            $gender = strtolower((string) $data['gender']);
            $data['gender'] = ['m' => 'male', 'f' => 'female', 'o' => 'other'][$gender] ?? $gender;
        }

        if (array_key_exists('pwd', $data) && ! is_bool($data['pwd'])) {
            $flag = strtolower((string) ($data['pwd'] ?? ''));
            $data['pwd'] = in_array($flag, ['1', 'yes', 'y', 'true'], true);
        }

        if (! empty($data['date_of_birth'])) {
            $data['date_of_birth'] = $this->normaliseDate($data['date_of_birth']);
        }

        foreach (['ongoing_backlogs', 'total_backlogs'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === null) {
                $data[$field] = 0;
            }
        }

        return $data;
    }

    /**
     * Resolve programme/branch against the catalogue; returns an error message or null.
     *
     * @param  array<string, mixed>  $data  modified in place to the canonical spelling
     */
    public function applyCatalogue(array &$data, ?array $catalogue = null): ?string
    {
        if (empty($data['programme']) || empty($data['branch'])) {
            return null;
        }

        $match = ProgrammeCatalogue::resolve((string) $data['programme'], (string) $data['branch'], $catalogue);

        if (! $match) {
            return sprintf('"%s" is not a branch of "%s" in the programme catalogue.', $data['branch'], $data['programme']);
        }

        $data['programme'] = $match['programme'];
        $data['branch'] = $match['branch'];

        return null;
    }

    /**
     * Create the user + profile atomically. Does not send the invitation.
     *
     * The account password is never known to anyone (the student sets their own via E1). Bulk imports pass one
     * pre-hashed throwaway value per chunk so each row does not pay for a bcrypt hash.
     *
     * @param  array<string, mixed>  $data  validated C3 fields
     */
    public function create(array $data, ?string $passwordHash = null): StudentProfile
    {
        return DB::transaction(function () use ($data, $passwordHash): StudentProfile {
            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['institute_email'],
                'password' => $passwordHash ?? Str::random(32),
                'role' => 'student',
                'is_active' => true,
            ]);

            return StudentProfile::create(array_merge(
                ['ongoing_backlogs' => 0, 'total_backlogs' => 0, 'pwd' => false],
                array_intersect_key($data, array_flip((new StudentProfile())->getFillable())),
                ['user_id' => $user->id]
            ));
        });
    }

    /**
     * E1 via a job: queued in `queued` mail mode, run inline in `sync` mode.
     */
    public function sendInvitation(StudentProfile $student): void
    {
        if ($this->mail->mode() === 'sync') {
            SendStudentInvitation::dispatchSync($student->id);

            return;
        }

        SendStudentInvitation::dispatch($student->id);
    }

    /**
     * Roll number + a set-password link (password-broker token; D41/D53).
     */
    public function deliverInvitation(StudentProfile $student): void
    {
        $user = $student->user;
        $token = Password::broker('invites')->createToken($user);
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $query = http_build_query(['token' => $token, 'email' => $user->email]);

        $this->mail->send(
            $user,
            new StudentInvitationMail(
                name: $student->full_name,
                rollNo: $student->roll_no,
                setPasswordUrl: "{$frontendUrl}/auth/student/set-password?{$query}",
                loginUrl: "{$frontendUrl}/auth/login/student",
            ),
            StudentInvitationMail::SUBJECT,
            'emails.student-invitation'
        );
    }

    private function normaliseDate(mixed $value): ?string
    {
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return (string) $value;
            }
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'd.m.Y'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, (string) $value);

                if ($date && $date->format($format) === (string) $value) {
                    return $date->toDateString();
                }
            } catch (Throwable) {
                // try the next format
            }
        }

        return (string) $value; // left for the `date_format` rule to reject
    }
}
