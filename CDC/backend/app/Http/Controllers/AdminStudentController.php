<?php

namespace App\Http\Controllers;

use App\Mail\StudentProfileUpdatedMail;
use App\Models\AuditLog;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\MailDispatchService;
use App\Services\PortalNotificationService;
use App\Services\SpreadsheetImportService;
use App\Services\StudentAcademicSyncService;
use App\Services\StudentAccountService;
use App\Support\ProgrammeCatalogue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AdminStudentController extends Controller
{
    public const PER_PAGE = 50;

    /** Labels used in the E8 change summary and nowhere else. */
    private const FIELD_LABELS = [
        'roll_no' => 'Roll number',
        'full_name' => 'Name',
        'institute_email' => 'Institute email',
        'personal_email' => 'Personal email',
        'phone' => 'Phone',
        'programme' => 'Programme',
        'branch' => 'Branch',
        'graduating_batch' => 'Graduating batch',
        'current_cgpa' => 'CGPA',
        'ongoing_backlogs' => 'Ongoing backlogs',
        'total_backlogs' => 'Total backlogs',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of birth',
        'tenth_percent' => '10th %',
        'twelfth_percent' => '12th %',
        'category' => 'Category',
        'pwd' => 'PwD',
        'home_state' => 'Home state',
        'linkedin_url' => 'LinkedIn',
        'github_url' => 'GitHub',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly StudentAccountService $accounts,
        private readonly StudentAcademicSyncService $academics,
        private readonly SpreadsheetImportService $spreadsheets,
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'programme' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'graduating_batch' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,suspended'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = StudentProfile::query()
            ->with('user:id,email,is_active')
            ->orderBy('roll_no');

        $search = trim((string) ($validated['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('roll_no', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('institute_email', 'like', "%{$search}%");
            });
        }

        foreach (['programme', 'branch', 'graduating_batch'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (! empty($validated['status'])) {
            $query->whereHas('user', fn (Builder $u) => $u->where('is_active', $validated['status'] === 'active'));
        }

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'students' => collect($page->items())->map(fn (StudentProfile $s) => $this->listPayload($s)),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(StudentProfile $studentProfile): JsonResponse
    {
        $studentProfile->load([
            'user:id,email,is_active,created_at',
            'cycleEnrollments.placementCycle:id,name,type,status,starts_on,ends_on',
            'branchChangeRequests' => fn ($q) => $q->latest(),
        ]);

        $branchChangeIds = $studentProfile->branchChangeRequests->pluck('id');

        $auditTrail = AuditLog::query()
            ->with('user:id,name,email')
            ->where(function (Builder $q) use ($studentProfile, $branchChangeIds): void {
                $q->where(fn (Builder $s) => $s->where('subject_type', StudentProfile::class)->where('subject_id', $studentProfile->id))
                    ->orWhere(fn (Builder $s) => $s->where('subject_type', \App\Models\BranchChangeRequest::class)->whereIn('subject_id', $branchChangeIds))
                    ->orWhere(fn (Builder $s) => $s->where('subject_type', \App\Models\Offer::class)->whereIn('subject_id', $studentProfile->offers()->select('id')))
                    ->orWhere(fn (Builder $s) => $s->where('subject_type', \App\Models\PlacementBlock::class)->whereIn('subject_id', $studentProfile->placementBlocks()->select('id')))
                    ->orWhere(fn (Builder $s) => $s->where('subject_type', \App\Models\Application::class)->whereIn('subject_id', $studentProfile->applications()->select('id')));
            })
            ->latest('id')
            ->limit(100)
            ->get();

        $applications = $studentProfile->applications()
            ->with('jobPosting.postable.company:id,name')
            ->latest('applied_at')
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'job_posting_id' => $a->job_posting_id,
                'title' => $a->jobPosting?->title(),
                'company_name' => $a->jobPosting?->company()?->name,
                'status' => $a->status,
                'used_unverified_resume' => $a->used_unverified_resume,
                'placed_elsewhere_flag' => $a->placed_elsewhere_flag,
                'applied_at' => $a->applied_at,
            ]);

        $offers = $studentProfile->offers()
            ->with(['company:id,name', 'placementCycle:id,name'])
            ->latest('announced_at')
            ->get()
            ->map(fn ($o) => $o->only(['id', 'offer_type', 'ctc_annual', 'stipend_monthly', 'currency', 'announced_at', 'job_posting_id']) + [
                'label' => \App\Models\Offer::LABELS[$o->offer_type] ?? $o->offer_type,
                'company_name' => $o->company?->name,
                'cycle_name' => $o->placementCycle?->name,
            ]);

        return response()->json([
            'student' => $this->detailPayload($studentProfile) + ['applications' => $applications, 'offers' => $offers],
            'audit_logs' => $auditTrail,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->accounts->normalise($request->only(array_keys($this->accounts->rules())));
        $data['ongoing_backlogs'] ??= 0;
        $data['total_backlogs'] ??= 0;
        $validated = Validator::make($data, $this->accounts->rules(), StudentAccountService::messages())->validate();

        if ($error = $this->accounts->applyCatalogue($validated)) {
            return response()->json(['message' => $error, 'errors' => ['branch' => [$error]]], 422);
        }

        $student = $this->accounts->create($validated);
        $this->accounts->sendInvitation($student);

        $this->audit->log($request, 'student.create', $student, null, $student->only(array_keys(self::FIELD_LABELS)));

        return response()->json([
            'message' => 'Student created. An invitation has been sent to their institute email.',
            'student' => $this->listPayload($student->load('user:id,email,is_active')),
        ], 201);
    }

    public function update(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        $rules = $this->accounts->rules($studentProfile, partial: true);
        $data = $this->accounts->normalise($request->only(array_keys($rules)));

        // gte:ongoing_backlogs needs both values even when only one is sent.
        $data += ['ongoing_backlogs' => $studentProfile->ongoing_backlogs];
        if (! array_key_exists('total_backlogs', $data)) {
            $data['total_backlogs'] = $studentProfile->total_backlogs;
        }

        $validated = Validator::make($data, $rules, StudentAccountService::messages())->validate();

        $validated += ['programme' => $studentProfile->programme, 'branch' => $studentProfile->branch];
        // Only re-check the catalogue when the placement changes, so students of a since-retired branch stay editable.
        $placementChanged = $validated['programme'] !== $studentProfile->programme || $validated['branch'] !== $studentProfile->branch;
        if ($placementChanged && ($error = $this->accounts->applyCatalogue($validated))) {
            return response()->json(['message' => $error, 'errors' => ['branch' => [$error]]], 422);
        }

        $tracked = array_keys(self::FIELD_LABELS);
        $before = $studentProfile->only($tracked);

        DB::transaction(function () use ($studentProfile, $validated): void {
            $studentProfile->fill($validated);
            $studentProfile->save();

            $user = $studentProfile->user;
            $user->fill(['name' => $studentProfile->full_name, 'email' => $studentProfile->institute_email]);
            if ($user->isDirty()) {
                $user->save();
            }
        });

        $after = $studentProfile->only($tracked);
        $changed = array_values(array_filter($tracked, fn (string $f) => ($before[$f] ?? null) != ($after[$f] ?? null)));

        if ($changed !== []) {
            $this->audit->log(
                $request,
                'student.update',
                $studentProfile,
                array_intersect_key($before, array_flip($changed)),
                array_intersect_key($after, array_flip($changed))
            );
            $this->notifyProfileChange($studentProfile, $changed, $after);
        }

        return response()->json([
            'message' => $changed === [] ? 'No changes to save.' : 'Student profile updated.',
            'student' => $this->detailPayload($studentProfile->fresh()->load([
                'user:id,email,is_active,created_at',
                'cycleEnrollments.placementCycle:id,name,type,status,starts_on,ends_on',
                'branchChangeRequests' => fn ($q) => $q->latest(),
            ])),
        ]);
    }

    public function suspend(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        return $this->setActive($request, $studentProfile, false);
    }

    public function reactivate(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        return $this->setActive($request, $studentProfile, true);
    }

    public function resendInvitation(Request $request, StudentProfile $studentProfile): JsonResponse
    {
        $this->accounts->sendInvitation($studentProfile->load('user'));
        $this->audit->log($request, 'student.invite_resend', $studentProfile, null, ['institute_email' => $studentProfile->institute_email]);

        return response()->json(['message' => 'Invitation sent again.']);
    }

    /**
     * Two-phase bulk import: `?dry_run=1` reports errors without writing.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => array_merge(['required'], SpreadsheetImportService::UPLOAD_RULES),
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $dryRun = $request->boolean('dry_run');

        try {
            $sheet = $this->spreadsheets->rows($request->file('file'));
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        // Tolerate the template's header row.
        $first = array_key_first($sheet);
        if ($first !== null && preg_replace('/[^a-z]/', '', strtolower($sheet[$first][0] ?? '')) === 'rollno') {
            unset($sheet[$first]);
        }

        if ($sheet === []) {
            return response()->json(['message' => 'The file has no student rows.'], 422);
        }

        $catalogue = ProgrammeCatalogue::all();
        $rules = $this->accounts->rules();
        $errors = [];
        $valid = [];
        $seenRoll = [];
        $seenEmail = [];

        foreach ($sheet as $rowNumber => $cells) {
            $row = [];
            foreach (StudentAccountService::IMPORT_COLUMNS as $index => $column) {
                $row[$column] = $cells[$index] ?? null;
            }
            $row = $this->accounts->normalise($row);
            $rollNo = (string) ($row['roll_no'] ?? '');

            $validator = Validator::make($row, $rules, StudentAccountService::messages());
            if ($validator->fails()) {
                foreach ($validator->errors()->toArray() as $field => $messages) {
                    $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => $field, 'reason' => $messages[0]];
                }

                continue;
            }

            $data = $validator->validated();

            if ($error = $this->accounts->applyCatalogue($data, $catalogue)) {
                $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => 'branch', 'reason' => $error];

                continue;
            }

            if (isset($seenRoll[$data['roll_no']]) || isset($seenEmail[$data['institute_email']])) {
                $field = isset($seenRoll[$data['roll_no']]) ? 'roll_no' : 'institute_email';
                $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => $field, 'reason' => 'Duplicate of an earlier row in this file.'];

                continue;
            }

            $seenRoll[$data['roll_no']] = true;
            $seenEmail[$data['institute_email']] = true;
            $valid[$rowNumber] = $data;
        }

        if ($dryRun) {
            return response()->json([
                'message' => sprintf('%d row(s) ready to import, %d error(s).', count($valid), count($errors)),
                'dry_run' => true,
                'valid_rows' => count($valid),
                'created' => 0,
                'errors' => $errors,
            ]);
        }

        $created = [];
        $passwordHash = null;
        foreach ($valid as $rowNumber => $data) {
            if (count($created) % 100 === 0) {
                $passwordHash = Hash::make(Str::random(40));
            }

            try {
                // One transaction per row (inside create()) so a single failure never sinks the batch.
                $student = $this->accounts->create($data, $passwordHash);
            } catch (Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'roll_no' => $data['roll_no'], 'field' => null, 'reason' => 'Could not be created (it may have been added by someone else just now).'];

                continue;
            }

            $this->audit->log($request, 'student.create', $student, null, $student->only(array_keys(self::FIELD_LABELS)) + ['source' => 'import']);
            $this->accounts->sendInvitation($student);
            $created[] = $student->roll_no;
        }

        if ($created !== []) {
            $this->audit->log($request, 'student.import', null, null, [
                'created_count' => count($created),
                'error_count' => count($errors),
                'roll_nos' => array_slice($created, 0, 100),
                'roll_nos_truncated' => count($created) > 100,
            ]);
        }

        return response()->json([
            'message' => sprintf('%d student(s) created. %d row(s) had errors.', count($created), count($errors)),
            'dry_run' => false,
            'valid_rows' => count($valid),
            'created' => count($created),
            'errors' => $errors,
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        return $this->templateResponse(StudentAccountService::IMPORT_COLUMNS, 'student_import_template.xlsx');
    }

    public function academicsTemplate(): StreamedResponse
    {
        return $this->templateResponse(array_merge(['roll_no'], StudentAcademicSyncService::FIELDS), 'academic_update_template.xlsx');
    }

    /**
     * Excel of `roll_no, current_cgpa, ongoing_backlogs, total_backlogs` — also the future institute-DB sync entry point.
     */
    public function academicBulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'file' => array_merge(['required'], SpreadsheetImportService::UPLOAD_RULES),
        ]);

        try {
            $sheet = $this->spreadsheets->rows($request->file('file'));
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $rows = [];
        foreach ($sheet as $rowNumber => $cells) {
            if (preg_replace('/[^a-z]/', '', strtolower($cells[0] ?? '')) === 'rollno') {
                continue;
            }

            $rows[] = [
                'row' => $rowNumber,
                'roll_no' => $cells[0] ?? '',
                'current_cgpa' => $cells[1] ?? '',
                'ongoing_backlogs' => $cells[2] ?? '',
                'total_backlogs' => $cells[3] ?? '',
            ];
        }

        if ($rows === []) {
            return response()->json(['message' => 'The file has no rows.'], 422);
        }

        $report = $this->academics->apply($rows, $request->user(), $request->ip());

        return response()->json([
            'message' => sprintf('%d student(s) updated, %d unchanged, %d error(s).', $report['updated'], $report['unchanged'], count($report['errors'])),
        ] + $report);
    }

    public function photo(StudentProfile $studentProfile): StreamedResponse|JsonResponse
    {
        if (! $studentProfile->photo_path || ! Storage::disk('local')->exists($studentProfile->photo_path)) {
            return response()->json(['message' => 'No photo uploaded.'], 404);
        }

        return Storage::disk('local')->response($studentProfile->photo_path);
    }

    private function setActive(Request $request, StudentProfile $studentProfile, bool $active): JsonResponse
    {
        $user = $studentProfile->user;

        if ($user->is_active === $active) {
            return response()->json([
                'message' => $active ? 'This student is already active.' : 'This student is already suspended.',
            ], 422);
        }

        // Tokens stay: the `active` middleware answers every request of a suspended user with "Account suspended",
        // so an open session shows that message instead of a bare "Unauthenticated" (owner decision, QA F-020).
        $user->update(['is_active' => $active]);

        $this->audit->log(
            $request,
            $active ? 'student.reactivate' : 'student.suspend',
            $studentProfile,
            ['is_active' => ! $active],
            ['is_active' => $active]
        );

        return response()->json([
            'message' => $active ? 'Student account reactivated.' : 'Student account suspended.',
            'student' => $this->listPayload($studentProfile->load('user:id,email,is_active')),
        ]);
    }

    /**
     * @param  list<string>  $changed
     * @param  array<string, mixed>  $after
     */
    private function notifyProfileChange(StudentProfile $student, array $changed, array $after): void
    {
        $lines = array_map(function (string $field) use ($after): string {
            $value = $after[$field] ?? null;
            $value = is_bool($value) ? ($value ? 'Yes' : 'No') : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ?? '—'));

            return sprintf('%s: %s', self::FIELD_LABELS[$field], $value);
        }, $changed);

        $this->notifications->createInAppNotification(
            $student->user,
            'Profile updated by CDC',
            'The CDC updated your profile: '.implode(', ', array_map(fn ($f) => self::FIELD_LABELS[$f], $changed)).'.',
            'info'
        );

        $this->mail->send(
            $student->user,
            new StudentProfileUpdatedMail(
                name: $student->full_name,
                headline: 'The CDC has updated your placement portal profile. The new values are:',
                lines: $lines,
                subjectLine: 'Your CDC profile was updated',
            ),
            'Your CDC profile was updated',
            'emails.student-profile-updated'
        );
    }

    private function templateResponse(array $headers, string $fileName): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$sheet->getHighestColumn().'1')->getFont()->setBold(true);
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function listPayload(StudentProfile $student): array
    {
        return [
            'id' => $student->id,
            'roll_no' => $student->roll_no,
            'full_name' => $student->full_name,
            'institute_email' => $student->institute_email,
            'programme' => $student->programme,
            'branch' => $student->branch,
            'graduating_batch' => $student->graduating_batch,
            'current_cgpa' => $student->current_cgpa,
            'ongoing_backlogs' => $student->ongoing_backlogs,
            'total_backlogs' => $student->total_backlogs,
            'gender' => $student->gender,
            'is_active' => (bool) ($student->user?->is_active ?? true),
        ];
    }

    private function detailPayload(StudentProfile $student): array
    {
        return $student->toArray() + [
            'is_active' => (bool) ($student->user?->is_active ?? true),
        ];
    }
}
