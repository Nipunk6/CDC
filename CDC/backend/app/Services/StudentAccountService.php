<?php

namespace App\Services;

use App\Jobs\SendStudentInvitation;
use App\Mail\StudentInvitationMail;
use App\Models\StudentProfile;
use App\Models\User;
use App\Support\ProgrammeCatalogue;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
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

    /**
     * Optional S4.6 academic extras, read after IMPORT_COLUMNS so sheets made from the old template still import.
     */
    public const IMPORT_EXTRA_COLUMNS = StudentProfile::ACADEMIC_EXTRAS;

    /**
     * Human-readable template headers with format hints (S5.6), in IMPORT_COLUMNS + IMPORT_EXTRA_COLUMNS order.
     * The importer maps columns by header name, so these, the old snake_case headers and Superset's sample CSV
     * headers all import.
     */
    public const IMPORT_HEADERS = [
        'roll_no' => 'Institute Roll Number (Mandatory)',
        'full_name' => 'Full Name (Mandatory)',
        'institute_email' => 'Email Address (Mandatory)',
        'programme' => 'Programme (Mandatory)',
        'branch' => 'Branch (Mandatory)',
        'graduating_batch' => 'Passout Batch (YYYY)',
        'gender' => 'Gender (M/F/O)',
        'current_cgpa' => 'CGPA',
        'ongoing_backlogs' => 'Ongoing Backlogs',
        'total_backlogs' => 'Total Backlogs',
        'tenth_percent' => 'Xth Score (%)',
        'twelfth_percent' => 'XIIth Score (%)',
        'date_of_birth' => 'Date Of Birth (YYYY-MM-DD)',
        'personal_email' => 'Personal Email Address',
        'phone' => 'Mobile (10 Digits)',
        'category' => 'Social Category',
        'pwd' => 'PwD (Yes/No)',
        'home_state' => 'Home State',
        'current_semester' => 'Current Semester',
        'course_start_date' => 'Course Start Date (YYYY-MM-DD)',
        'course_end_date' => 'Course End Date (YYYY-MM-DD)',
        'lateral_entry' => 'Lateral Entry (Yes/No)',
        'tenth_board' => 'Xth Board',
        'tenth_passing_year' => 'Year of passing 10th',
        'twelfth_board' => 'XIIth Board',
        'twelfth_passing_year' => 'Year of passing 12th',
        'previous_degree' => 'Previous Degree',
        'previous_degree_score' => 'Previous Degree Score',
        'previous_degree_score_type' => 'Previous Degree Score Type (cgpa/percentage)',
    ];

    /**
     * Other header spellings the importer accepts (compared after normaliseHeader()), incl. Superset's sample CSV.
     * `first_name`/`middle_name`/`last_name` merge into full_name and `phone_country_code` into phone.
     */
    private const HEADER_ALIASES = [
        'roll_no' => ['rollno', 'rollnumber', 'instituterollnumber', 'identificationno'],
        'full_name' => ['name', 'studentname'],
        'first_name' => ['firstname'],
        'middle_name' => ['middlename'],
        'last_name' => ['lastname'],
        'institute_email' => ['email', 'emailaddress', 'instituteemail', 'instituteemailaddress'],
        'personal_email' => ['personalemail', 'personalemailaddress'],
        'programme' => ['program'],
        'branch' => ['department'],
        'graduating_batch' => ['batch', 'passoutbatch', 'passoutyear', 'graduatingbatch'],
        'current_cgpa' => ['cgpa'],
        'tenth_percent' => ['xthscore', 'classxpercentage', '10th'],
        'twelfth_percent' => ['xiithscore', 'classxiipercentage', '12th'],
        'date_of_birth' => ['dob'],
        'phone' => ['mobile', 'mobileno', 'mobilenumber', 'contactno', 'phonenumber'],
        'phone_country_code' => ['mobilecountrycode', 'countrycode'],
        'category' => ['socialcategory'],
        'course_start_date' => ['currentcoursestartdate'],
        'course_end_date' => ['currentcourseenddate'],
        // Superset's single course column, read as programme + branch when those are blank (L18).
        'current_course_name' => ['currentcoursename', 'coursename'],
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
                self::instituteDomainRule($ignore),
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
        ] + self::academicExtraRules();
    }

    /**
     * Domains accepted for an institute email (B2-10): config `students.institute_email_domains`, plus the demo
     * seeder's reserved .test domain in the local and testing environments only.
     *
     * @return list<string>
     */
    public static function instituteEmailDomains(): array
    {
        $domains = (array) config('students.institute_email_domains', ['iitism.ac.in']);
        if (app()->environment(['local', 'testing'])) {
            $domains = array_merge($domains, (array) config('students.demo_email_domains', []));
        }

        return array_values(array_unique(array_map('strtolower', $domains)));
    }

    public static function instituteDomainMessage(): string
    {
        $shown = array_map(fn ($d) => '@'.$d, (array) config('students.institute_email_domains', ['iitism.ac.in']));

        return sprintf("Use the student's institute email (%s).", implode(' or ', $shown));
    }

    /**
     * The institute email must be on an institute domain. An edit that keeps the stored address is not re-checked,
     * so a legacy row stays editable.
     */
    private static function instituteDomainRule(?StudentProfile $ignore): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
            if (! is_string($value) || ($ignore && strtolower($value) === strtolower((string) $ignore->institute_email))) {
                return;
            }
            $domain = strtolower((string) substr(strrchr($value, '@') ?: '', 1));
            if (! in_array($domain, self::instituteEmailDomains(), true)) {
                $fail(self::instituteDomainMessage());
            }
        };
    }

    /**
     * Where each S4.6 extra sits in a sheet. With a header row the extras are found by their column name (so an old
     * sheet with other trailing columns never feeds them by accident); without one they follow positionally from
     * `$offset`.
     *
     * @param  list<mixed>|null  $header
     * @return array<string, int>
     */
    public static function extraColumnIndexes(?array $header, int $offset): array
    {
        if ($header === null) {
            return array_combine(StudentProfile::ACADEMIC_EXTRAS, range($offset, $offset + count(StudentProfile::ACADEMIC_EXTRAS) - 1));
        }

        $names = array_map(fn ($cell) => preg_replace('/[^a-z0-9_]/', '', str_replace([' ', '-'], '_', strtolower(trim((string) $cell)))), $header);
        $indexes = [];
        foreach (StudentProfile::ACADEMIC_EXTRAS as $field) {
            $index = array_search($field, $names, true);
            if ($index !== false) {
                $indexes[$field] = (int) $index;
            }
        }

        return $indexes;
    }

    /** "Date Of Birth (YYYY-MM-DD)" → "dateofbirth": lower case, hints in brackets and punctuation dropped. */
    public static function normaliseHeader(mixed $cell): string
    {
        $text = strtolower(trim((string) $cell));
        $text = preg_replace('/\([^)]*\)/', '', $text) ?? $text;

        return preg_replace('/[^a-z0-9]/', '', $text) ?? '';
    }

    /**
     * Column index of every known field in a header row (first occurrence wins).
     *
     * @param  list<mixed>  $header
     * @return array<string, int>
     */
    public static function importColumnMap(array $header): array
    {
        static $lookup = null;
        if ($lookup === null) {
            $lookup = [];
            foreach (self::IMPORT_HEADERS as $field => $label) {
                $lookup[self::normaliseHeader($field)] = $field;
                $lookup[self::normaliseHeader($label)] = $field;
            }
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    $lookup[$alias] = $field;
                }
            }
        }

        $map = [];
        foreach ($header as $index => $cell) {
            $field = $lookup[self::normaliseHeader($cell)] ?? null;
            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = (int) $index;
            }
        }

        return $map;
    }

    /**
     * A header row names the roll number column and at least one other known column.
     *
     * @param  list<mixed>  $cells
     */
    public static function isImportHeader(array $cells): bool
    {
        $map = self::importColumnMap($cells);

        return isset($map['roll_no']) && count($map) >= 2;
    }

    /**
     * Column positions of a header-less file: IMPORT_COLUMNS, then the S4.6 extras.
     *
     * @return array<string, int>
     */
    public static function positionalColumnMap(): array
    {
        return array_flip(self::IMPORT_COLUMNS) + self::extraColumnIndexes(null, count(self::IMPORT_COLUMNS));
    }

    /**
     * One sheet row as C3 fields, merging Superset's First/Middle/Last name and Mobile Country Code + Mobile, and
     * reading Superset's "Current Course Name" as the programme and branch when the Programme cell is blank (L18).
     * A course name that matches no catalogue programme and branch leaves them blank and sets `$courseError`.
     *
     * @param  list<string>  $cells
     * @param  array<string, int>  $map
     * @param  array<string, list<string>>|null  $catalogue  ProgrammeCatalogue::all(), passed in by bulk imports
     * @return array<string, mixed>
     */
    public static function mapImportRow(array $cells, array $map, ?array $catalogue = null, ?string &$courseError = null): array
    {
        $courseError = null;
        $row = [];
        foreach (array_merge(self::IMPORT_COLUMNS, self::IMPORT_EXTRA_COLUMNS) as $field) {
            if (isset($map[$field])) {
                $row[$field] = $cells[$map[$field]] ?? null;
            }
        }
        foreach (self::IMPORT_COLUMNS as $field) {
            $row[$field] ??= null; // a missing required column is reported per row by validation
        }

        $cell = fn (string $field): string => isset($map[$field]) ? trim((string) ($cells[$map[$field]] ?? '')) : '';

        if (trim((string) $row['full_name']) === '') {
            $parts = array_filter([$cell('first_name'), $cell('middle_name'), $cell('last_name')], fn ($p) => $p !== '');
            $row['full_name'] = $parts === [] ? null : implode(' ', $parts);
        }

        $code = ltrim($cell('phone_country_code'), '+ ');
        $phone = trim((string) $row['phone']);
        if ($code !== '' && $phone !== '' && ! str_starts_with($phone, '+')) {
            $row['phone'] = '+'.$code.' '.$phone;
        }

        $course = $cell('current_course_name');
        if ($course !== '' && trim((string) $row['programme']) === '') {
            $branch = trim((string) $row['branch']);
            $match = self::resolveCourseName($course, $branch, $catalogue ?? ProgrammeCatalogue::all());
            if ($match !== null) {
                $row['programme'] = $match['programme'];
                $row['branch'] = $match['branch'];
            } else {
                $courseError = sprintf(
                    'Current Course Name "%s"%s does not match a programme and branch in the programme catalogue. Write it as "Programme - Branch", e.g. "B.Tech - Computer Science & Engineering", or fill the Programme and Branch columns.',
                    $course,
                    $branch !== '' ? sprintf(' with Branch "%s"', $branch) : ''
                );
            }
        }

        return $row;
    }

    /**
     * Superset's "Current Course Name" as a catalogue programme and branch (L18), e.g.
     * "B.Tech - Computer Science & Engineering", "M.Tech (2 Year) - GATE - Mining Engineering" or "MBA - Finance".
     *
     * Every split at " - ", "-", ",", ":" or " in " (and "Programme (Branch)") is tried: the left part has to name one
     * programme, by its catalogue name or a short form ("B.Tech", "M.Tech", "Integrated M.Tech", "MBA"…), and the right
     * part one of its branches through ProgrammeCatalogue::resolve (case, spacing and "&"/"and" do not matter). A
     * programme with a single branch may be named alone. With `$branch` (a Branch column) the course names only the
     * programme. Anything that matches nothing, or more than one programme and branch, is null.
     *
     * @param  array<string, list<string>>  $catalogue
     * @return array{programme: string, branch: string}|null
     */
    public static function resolveCourseName(string $course, string $branch, array $catalogue): ?array
    {
        $course = trim(preg_replace('/\s+/u', ' ', $course) ?? $course);
        $aliases = self::programmeAliases($catalogue);
        $programmeOf = fn (string $text): ?string => ($aliases[self::courseKey($text)] ?? null) ?: null;
        $branchOf = function (string $programme, string $text) use ($catalogue): ?array {
            $key = self::branchKey($text);
            $found = array_values(array_filter($catalogue[$programme] ?? [], fn ($b) => $key !== '' && self::branchKey($b) === $key));

            return count($found) === 1 ? ProgrammeCatalogue::resolve($programme, $found[0], $catalogue) : null;
        };

        // Candidate (programme text, branch text) pairs.
        $pairs = [];
        if (preg_match_all('/\s*[-–—:,]\s*|\s+in\s+/iu', $course, $separators, PREG_OFFSET_CAPTURE)) {
            foreach ($separators[0] as [$separator, $offset]) {
                $pairs[] = [substr($course, 0, $offset), substr($course, $offset + strlen($separator)), $course];
            }
        }
        if (preg_match('/^(.+?)\s*\(([^()]+)\)$/u', $course, $parts)) {
            $pairs[] = [$parts[1], $parts[2], $course];
        }

        $matches = [];
        if ($branch !== '') {
            foreach (array_merge([$course], array_column($pairs, 0)) as $text) {
                $programme = $programmeOf($text);
                if ($programme !== null && ($match = $branchOf($programme, $branch))) {
                    $matches[$match['programme'].'|'.$match['branch']] = $match;
                }
            }
        } else {
            foreach ($pairs as [$left, $right, $whole]) {
                $programme = $programmeOf($left);
                // "MBA - Finance" names the programme and also spells its branch "MBA - Finance" in full.
                if ($programme !== null && ($match = $branchOf($programme, $right) ?? $branchOf($programme, $whole))) {
                    $matches[$match['programme'].'|'.$match['branch']] = $match;
                }
            }
            $programme = $programmeOf($course);
            if ($matches === [] && $programme !== null && count($catalogue[$programme] ?? []) === 1) {
                $match = ProgrammeCatalogue::resolve($programme, $catalogue[$programme][0], $catalogue);
                if ($match) {
                    $matches['single'] = $match;
                }
            }
        }

        return count($matches) === 1 ? array_values($matches)[0] : null;
    }

    /**
     * Short names under which a course name may refer to each programme: the catalogue name, and without the
     * "(N Year)" and entrance-exam parts, e.g. "M.Tech (2 Year) - GATE" → "M.Tech (2 Year) - GATE", "M.Tech - GATE",
     * "M.Tech". A short name shared by two programmes maps to false (ambiguous).
     *
     * @param  array<string, list<string>>  $catalogue
     * @return array<string, string|false> courseKey => programme
     */
    private static function programmeAliases(array $catalogue): array
    {
        $aliases = [];
        foreach (array_keys($catalogue) as $programme) {
            $plain = trim(preg_replace('/\s*\(\s*\d+\s*years?\s*\)/i', '', $programme) ?? $programme);
            $names = [$programme, $plain, ProgrammeCatalogue::displayName($programme), explode(' - ', $plain)[0]];
            foreach (explode(' / ', $plain) as $part) {
                $names[] = $part;
                $names[] = explode(' - ', $part)[0];
            }
            foreach (array_unique(array_map(fn ($name) => self::courseKey((string) $name), $names)) as $key) {
                if ($key === '') {
                    continue;
                }
                $aliases[$key] = isset($aliases[$key]) && $aliases[$key] !== $programme ? false : $programme;
            }
        }

        return $aliases;
    }

    /** "M.Tech (2 Year)" → "mtech": lower case, "(N Year)" and punctuation dropped. */
    private static function courseKey(string $text): string
    {
        $text = preg_replace('/\(\s*\d+\s*years?\s*\)/i', '', strtolower($text)) ?? $text;

        return preg_replace('/[^a-z0-9]/', '', $text) ?? '';
    }

    /** "Computer Science and Engineering" and "computer science & engineering" → the same key. */
    private static function branchKey(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', str_replace('&', 'and', strtolower($text))) ?? '';
    }

    /**
     * Rules for the S4.6 academic extras (CDC-entered only). Shared by the admin form, the bulk import and the
     * academic update, so every write path accepts the same values.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function academicExtraRules(): array
    {
        return [
            'current_semester' => ['nullable', 'integer', 'min:1', 'max:12'],
            'course_start_date' => ['nullable', 'date_format:Y-m-d'],
            'course_end_date' => ['nullable', 'date_format:Y-m-d', self::notBefore('course_start_date')],
            'lateral_entry' => ['nullable', 'boolean'],
            'tenth_board' => ['nullable', 'string', 'max:120'],
            'tenth_passing_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'twelfth_board' => ['nullable', 'string', 'max:120'],
            'twelfth_passing_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'previous_degree' => ['nullable', 'string', 'max:120'],
            'previous_degree_score' => ['nullable', 'numeric', 'min:0', 'max:100', self::cgpaWithinTen()],
            'previous_degree_score_type' => ['nullable', 'in:cgpa,percentage', 'required_with:previous_degree_score'],
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
            'course_start_date.date_format' => 'Course start date must be a full date, e.g. 01-08-2023 or 2023-08-01.',
            'course_end_date.date_format' => 'Course end date must be a full date, e.g. 31-05-2027 or 2027-05-31.',
            'previous_degree_score_type.in' => 'Previous degree score type must be cgpa or percentage.',
            'previous_degree_score_type.required_with' => 'Say whether the previous degree score is a cgpa or a percentage.',
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

        foreach (['date_of_birth', 'course_start_date', 'course_end_date'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = $this->normaliseDate($data[$field]);
            }
        }

        // Unlike pwd, a blank lateral_entry cell means "not a lateral entry".
        if (array_key_exists('lateral_entry', $data) && ! is_bool($data['lateral_entry'])) {
            $flag = strtolower((string) ($data['lateral_entry'] ?? ''));
            $data['lateral_entry'] = in_array($flag, ['1', 'yes', 'y', 'true'], true);
        }

        if (isset($data['previous_degree_score_type'])) {
            $type = strtolower((string) $data['previous_degree_score_type']);
            $data['previous_degree_score_type'] = ['%' => 'percentage', 'percent' => 'percentage'][$type] ?? $type;
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
        // S5 invitation tracking. A resend un-revokes (B2-9); the job itself skips revoked or activated accounts.
        $user = $student->user;
        if ($user) {
            $now = now();
            $user->forceFill([
                'invited_at' => $user->invited_at ?? $now,
                'last_invited_at' => $now,
                'invite_count' => (int) $user->invite_count + 1,
                'invite_revoked_at' => null,
            ])->save();
        }

        if ($this->mail->mode() === 'sync') {
            SendStudentInvitation::dispatchSync($student->id);

            return;
        }

        SendStudentInvitation::dispatch($student->id);
    }

    /**
     * Revoke Invites (S5.4, B2-9): the set-password link stops working and the status becomes Revoked. Any
     * forgot-password link is cancelled too, so the account cannot be activated until the CDC resends. The caller
     * refuses activated accounts (those are suspended instead).
     */
    public function revokeInvitation(StudentProfile $student): void
    {
        $user = $student->user;
        Password::broker('invites')->deleteToken($user);
        Password::broker()->deleteToken($user);
        $user->forceFill(['invite_revoked_at' => now()])->save();
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

    /** The field's date must not be before the other date field (when both are given). */
    private static function notBefore(string $other): ValidationRule
    {
        return new class($other) implements DataAwareRule, ValidationRule
        {
            private array $data = [];

            public function __construct(private readonly string $other)
            {
            }

            public function setData(array $data): static
            {
                $this->data = $data;

                return $this;
            }

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                $start = $this->data[$this->other] ?? null;
                if (is_string($start) && is_string($value) && $start !== '' && $value < $start) {
                    $fail('Course end date cannot be before the course start date.');
                }
            }
        };
    }

    /** A previous-degree CGPA is out of 10. */
    private static function cgpaWithinTen(): ValidationRule
    {
        return new class implements DataAwareRule, ValidationRule
        {
            private array $data = [];

            public function setData(array $data): static
            {
                $this->data = $data;

                return $this;
            }

            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if (($this->data['previous_degree_score_type'] ?? null) === 'cgpa' && is_numeric($value) && (float) $value > 10) {
                    $fail('A previous degree CGPA must be between 0 and 10.');
                }
            }
        };
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
