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
use App\Services\StudentRecordService;
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
        'phone' => 'Contact No.',
        'programme' => 'Programme',
        'branch' => 'Branch',
        'graduating_batch' => 'Passout Batch',
        'current_cgpa' => 'CGPA',
        'ongoing_backlogs' => 'Ongoing backlogs',
        'total_backlogs' => 'Total backlogs',
        'gender' => 'Gender',
        'date_of_birth' => 'Date of birth',
        'tenth_percent' => 'Class X Percentage',
        'twelfth_percent' => 'Class XII Percentage',
        'category' => 'Social Category',
        'pwd' => 'PwD',
        'home_state' => 'Home state',
        'linkedin_url' => 'LinkedIn',
        'github_url' => 'GitHub',
        // S4.6 academic extras (Superset labels)
        'current_semester' => 'Current Semester',
        'course_start_date' => 'Course Start Date',
        'course_end_date' => 'Course End Date',
        'lateral_entry' => 'Lateral Entry',
        'tenth_board' => 'Xth Board',
        'tenth_passing_year' => 'Year of passing 10th',
        'twelfth_board' => 'XIIth Board',
        'twelfth_passing_year' => 'Year of passing 12th',
        'previous_degree' => 'Previous Degree',
        'previous_degree_score' => 'Previous Degree Score',
        'previous_degree_score_type' => 'Previous Degree Score Type',
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
        $validated = $request->validate($this->filterRules() + [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::PER_PAGE],
        ]);

        $page = $this->filteredQuery($validated)
            ->with('user:id,email,is_active')
            ->orderBy('roll_no')
            ->paginate((int) ($validated['per_page'] ?? self::PER_PAGE));

        return response()->json([
            'students' => collect($page->items())->map(fn (StudentProfile $s) => $this->listPayload($s)),
            // S5.5 header: "N students registered · total N students invited" (registered = Accepted).
            'invitation_summary' => [
                'registered' => \App\Models\User::query()->where('role', 'student')->whereNotNull('activated_at')->count(),
                'invited' => \App\Models\User::query()->where('role', 'student')->whereNotNull('invited_at')->count(),
            ],
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * "Download as Excel" of the filtered student list (S4.4): the Default Template, or `?template=<id>`.
     */
    public function export(Request $request, \App\Services\TemplateExports $templates): StreamedResponse
    {
        $validated = $request->validate($this->filterRules() + ['template' => ['nullable', 'integer']]);
        $template = \App\Services\TemplateExports::find($validated['template'] ?? null);
        $query = $this->filteredQuery($validated);

        $this->audit->log($request, 'student.export', null, null, [
            'count' => (clone $query)->count(),
            'filters' => \App\Support\StudentDirectoryFilters::active($validated),
            'template_id' => $template?->id,
        ]);

        $fileName = 'students'.($template ? '-'.Str::slug($template->name) : '').'-'.now('Asia/Kolkata')->format('Ymd-Hi').'.xlsx';

        return $templates->students($query, $template, $fileName);
    }

    /**
     * Pending-requests banner (S4.7): profile update requests waiting for the CDC (branch changes + resumes).
     */
    public function pendingRequests(): JsonResponse
    {
        $branchChanges = \App\Models\BranchChangeRequest::query()->where('status', 'pending')->count();
        $resumes = \App\Models\Resume::query()->where('status', 'pending')->count();

        return response()->json([
            'branch_changes' => $branchChanges,
            'resumes' => $resumes,
            'total' => $branchChanges + $resumes,
        ]);
    }

    public function show(StudentProfile $studentProfile, StudentRecordService $record): JsonResponse
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

        // QA F-021: the full blocks history (active and lifted), in the same shape as GET /admin/blocks.
        $blocks = $studentProfile->placementBlocks()
            ->with(['placementCycle:id,name', 'offer:id,offer_type,job_posting_id', 'blockedBy:id,name', 'unblockedBy:id,name'])
            ->latest('id')
            ->get()
            ->map(fn (\App\Models\PlacementBlock $b) => $b->toArray() + ['message' => $b->message()]);

        // S4.5: summary card, Placements section (per cycle, with stage attendance) and Resumes & Documents.
        $resumes = $studentProfile->resumes()
            ->with('reviewedBy:id,name')
            ->orderBy('slot')
            ->get()
            ->map(fn (\App\Models\Resume $r) => $r->toArray() + ['preview_url' => $r->previewUrl()]);

        return response()->json([
            'student' => $this->detailPayload($studentProfile) + [
                'applications' => $applications,
                'offers' => $offers,
                'placement_blocks' => $blocks,
                'summary' => [
                    'cgpa' => $studentProfile->current_cgpa,
                    'applications_count' => $applications->where('status', 'applied')->count(),
                    'offers_count' => $offers->count(),
                ],
                'placements' => $record->placements($studentProfile),
                'resumes' => $resumes,
            ],
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
        // Same for the S4.6 cross-field checks (end date vs start date, CGPA vs score type).
        if (! empty($data['course_end_date']) && ! array_key_exists('course_start_date', $data)) {
            $data['course_start_date'] = $studentProfile->course_start_date?->format('Y-m-d');
        }
        if (isset($data['previous_degree_score']) && ! array_key_exists('previous_degree_score_type', $data)) {
            $data['previous_degree_score_type'] = $studentProfile->previous_degree_score_type;
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
        // S5.3: an activated account gets no new set-password link (Forgot password still works for them).
        if ($studentProfile->user?->activated_at !== null) {
            return response()->json(['message' => 'This student has already activated their account. They can use Forgot password if they need a new one.'], 422);
        }

        $this->accounts->sendInvitation($studentProfile->load('user'));
        $this->audit->log($request, 'student.invite_resend', $studentProfile, null, ['institute_email' => $studentProfile->institute_email]);

        return response()->json(['message' => 'Invitation sent again.']);
    }

    /**
     * Two-phase bulk import: `?dry_run=1` reports errors without writing.
     *
     * Columns are mapped by header name when the file has a header row (our template, old or new, or Superset's
     * sample CSV), else by position (S5.6). Every row is accounted for: rows past `students.import_max_rows` are
     * reported, never dropped (S5.7), and the database uniqueness checks run in chunks so a 10,000-row file stays
     * at a few dozen queries.
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'file' => array_merge(['required'], SpreadsheetImportService::UPLOAD_RULES),
            'dry_run' => ['nullable', 'boolean'],
            // "Select student batch" (S5.6): fills graduating_batch for rows that leave it blank.
            'default_batch' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $dryRun = $request->boolean('dry_run');
        $defaultBatch = $request->filled('default_batch') ? (int) $request->input('default_batch') : null;
        $maxRows = max(1, (int) config('students.import_max_rows', 10000));

        try {
            ['rows' => $sheet, 'overflow' => $overflow] = $this->spreadsheets->read($request->file('file'), $maxRows + 1);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $map = StudentAccountService::positionalColumnMap();
        $first = array_key_first($sheet);
        if ($first !== null && StudentAccountService::isImportHeader($sheet[$first])) {
            $map = StudentAccountService::importColumnMap($sheet[$first]);
            unset($sheet[$first]);
        } elseif ($first !== null && StudentAccountService::normaliseHeader($sheet[$first][0] ?? '') === 'rollno') {
            unset($sheet[$first]); // a header we cannot read by name: keep the template order
        }

        // The cap counts student rows, so a header row does not cost one.
        while (count($sheet) > $maxRows) {
            $last = array_key_last($sheet);
            $overflow = [$last => $sheet[$last]] + $overflow;
            unset($sheet[$last]);
        }

        if ($sheet === [] && $overflow === []) {
            return response()->json(['message' => 'The file has no student rows.'], 422);
        }

        $catalogue = ProgrammeCatalogue::all();
        // Uniqueness against the database is checked below in chunks, not one query per row.
        $rules = array_map(
            fn (array $fieldRules) => array_values(array_filter($fieldRules, fn ($rule) => ! $rule instanceof \Illuminate\Validation\Rules\Unique)),
            $this->accounts->rules()
        );
        $messages = StudentAccountService::messages();
        $errors = [];
        $valid = [];
        $seenRoll = [];
        $seenEmail = [];

        foreach ($sheet as $rowNumber => $cells) {
            $row = StudentAccountService::mapImportRow($cells, $map, $catalogue, $courseError);
            if ($defaultBatch !== null && trim((string) ($row['graduating_batch'] ?? '')) === '') {
                $row['graduating_batch'] = $defaultBatch;
            }
            $row = $this->accounts->normalise($row);
            $rollNo = (string) ($row['roll_no'] ?? '');

            $validator = Validator::make($row, $rules, $messages);
            if ($courseError !== null || $validator->fails()) {
                // An unreadable Current Course Name (L18) replaces the "programme/branch required" errors.
                if ($courseError !== null) {
                    $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => 'programme', 'reason' => $courseError];
                }
                foreach ($validator->errors()->toArray() as $field => $fieldMessages) {
                    if ($courseError === null || ! in_array($field, ['programme', 'branch'], true)) {
                        $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'field' => $field, 'reason' => $fieldMessages[0]];
                    }
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

        foreach (array_chunk($valid, 500, true) as $chunk) {
            $rolls = StudentProfile::query()->whereIn('roll_no', array_column($chunk, 'roll_no'))->pluck('roll_no')
                ->map(fn ($r) => strtoupper((string) $r))->flip();
            $emails = array_column($chunk, 'institute_email');
            $taken = StudentProfile::query()->whereIn('institute_email', $emails)->pluck('institute_email')
                ->merge(\App\Models\User::query()->whereIn('email', $emails)->pluck('email'))
                ->map(fn ($e) => strtolower((string) $e))->flip();

            foreach ($chunk as $rowNumber => $data) {
                $field = isset($rolls[$data['roll_no']]) ? 'roll_no' : (isset($taken[$data['institute_email']]) ? 'institute_email' : null);
                if ($field !== null) {
                    $errors[] = ['row' => $rowNumber, 'roll_no' => $data['roll_no'], 'field' => $field, 'reason' => $messages[$field.'.unique']];
                    unset($valid[$rowNumber]);
                }
            }
        }

        foreach ($overflow as $rowNumber => $cells) {
            $errors[] = [
                'row' => $rowNumber,
                'roll_no' => strtoupper(trim((string) ($cells[$map['roll_no'] ?? 0] ?? ''))),
                'field' => null,
                'reason' => sprintf('Not imported: one file can hold at most %s students. Upload this row in a second file.', number_format($maxRows)),
            ];
        }

        usort($errors, fn (array $a, array $b) => $a['row'] <=> $b['row']);

        if ($dryRun) {
            return response()->json([
                'message' => sprintf('%d row(s) ready to import, %d error(s).', count($valid), count($errors)),
                'dry_run' => true,
                'valid_rows' => count($valid),
                'created' => 0,
                'over_limit_rows' => count($overflow),
                'errors' => $errors,
            ]);
        }

        if (count($valid) > 500) {
            @set_time_limit(0); // thousands of rows: one transaction per row, invitations queued per student
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
                'default_batch' => $defaultBatch,
            ]);
        }

        return response()->json([
            'message' => sprintf('%d student(s) created. %d row(s) had errors.', count($created), count($errors)),
            'dry_run' => false,
            'valid_rows' => count($valid),
            'created' => count($created),
            'over_limit_rows' => count($overflow),
            'errors' => $errors,
        ]);
    }

    public function importTemplate(): StreamedResponse
    {
        // Human-readable headers with hints (S5.6); the importer maps by header name and still reads old templates.
        return $this->templateResponse(array_values(StudentAccountService::IMPORT_HEADERS), 'student_import_template.xlsx');
    }

    public function academicsTemplate(): StreamedResponse
    {
        return $this->templateResponse(array_merge(['roll_no'], StudentAcademicSyncService::FIELDS, StudentAcademicSyncService::EXTRA_FIELDS), 'academic_update_template.xlsx');
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
        $extraIndexes = null;
        foreach ($sheet as $rowNumber => $cells) {
            if (preg_replace('/[^a-z]/', '', strtolower($cells[0] ?? '')) === 'rollno') {
                // A header row names where the optional S4.6 extras are.
                $extraIndexes ??= StudentAccountService::extraColumnIndexes($cells, 4);

                continue;
            }

            $row = [
                'row' => $rowNumber,
                'roll_no' => $cells[0] ?? '',
                'current_cgpa' => $cells[1] ?? '',
                'ongoing_backlogs' => $cells[2] ?? '',
                'total_backlogs' => $cells[3] ?? '',
            ];
            // Optional S4.6 extras: by header name, else positionally after the four original columns.
            foreach ($extraIndexes ?? StudentAccountService::extraColumnIndexes(null, 4) as $field => $index) {
                $row[$field] = $cells[$index] ?? '';
            }
            $rows[] = $row;
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

    /**
     * The student-list filters: the original single-value ones plus Apply Filters (S4.1).
     *
     * @return array<string, array<int, mixed>>
     */
    private function filterRules(): array
    {
        return \App\Support\StudentDirectoryFilters::rules() + [
            'programme' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'graduating_batch' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,suspended'],
        ];
    }

    /**
     * @return Builder<StudentProfile>
     */
    private function filteredQuery(array $validated): Builder
    {
        $query = StudentProfile::query();

        foreach (['programme', 'branch', 'graduating_batch'] as $filter) {
            if (! empty($validated[$filter])) {
                $query->where($filter, $validated[$filter]);
            }
        }

        if (! empty($validated['status'])) {
            $query->whereHas('user', fn (Builder $u) => $u->where('is_active', $validated['status'] === 'active'));
        }

        \App\Support\StudentDirectoryFilters::apply($query, $validated);

        return $query;
    }

    private function listPayload(StudentProfile $student): array
    {
        return [
            'id' => $student->id,
            'roll_no' => $student->roll_no,
            'full_name' => $student->full_name,
            'institute_email' => $student->institute_email,
            'personal_email' => $student->personal_email,
            'phone' => $student->phone,
            'has_photo' => $student->has_photo,
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
        $user = $student->user ? \App\Models\User::query()->find($student->user_id) : null;

        return $student->toArray() + [
            'is_active' => (bool) ($student->user?->is_active ?? true),
        ] + ($user ? AdminStudentInvitationController::invitationFields($user) : []);
    }
}
