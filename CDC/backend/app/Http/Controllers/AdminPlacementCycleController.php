<?php

namespace App\Http\Controllers;

use App\Models\CycleEnrollment;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\SpreadsheetImportService;
use App\Support\ProgrammeCatalogue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AdminPlacementCycleController extends Controller
{
    /** Enrolled-student pages are sized for a 3000-student cycle. */
    public const PER_PAGE = 50;

    /** An import can name thousands of roll numbers; only a sample goes into the audit row. */
    private const AUDIT_SAMPLE = 100;

    /**
     * Per-request memo for tables later milestones add, so index() does not probe
     * information_schema once per cycle.
     *
     * @var array<string, bool>
     */
    private array $tableExists = [];

    public function __construct(
        private readonly AuditService $audit,
        private readonly SpreadsheetImportService $spreadsheets
    ) {
    }

    public function index(): JsonResponse
    {
        $cycles = PlacementCycle::query()
            ->withCount('enrollments')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'placement_cycles' => $cycles->map(fn (PlacementCycle $cycle) => $this->cyclePayload($cycle)),
        ]);
    }

    public function show(PlacementCycle $placementCycle): JsonResponse
    {
        $placementCycle->loadCount('enrollments');
        $placementCycle->load('createdBy:id,name,email');

        return response()->json([
            'placement_cycle' => $this->cyclePayload($placementCycle) + [
                'description' => $placementCycle->description,
                'created_by' => $placementCycle->createdBy,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $cycle = PlacementCycle::create($validated + [
            'status' => 'open',
            'created_by' => $request->user()?->id,
        ]);

        $this->audit->log($request, 'cycle.create', $cycle, null, $cycle->only([
            'name', 'type', 'starts_on', 'ends_on', 'status', 'allowed_programmes', 'description',
        ]));

        return response()->json([
            'message' => 'Placement cycle created successfully.',
            'placement_cycle' => $this->cyclePayload($cycle->loadCount('enrollments')),
        ], 201);
    }

    public function update(Request $request, PlacementCycle $placementCycle): JsonResponse
    {
        // `status` is accepted here so an admin can reopen a cycle closed by mistake.
        $validated = $request->validate($this->rules() + [
            'status' => ['sometimes', 'in:open,closed'],
        ]);

        $tracked = ['name', 'type', 'starts_on', 'ends_on', 'status', 'allowed_programmes', 'description'];
        $before = $placementCycle->only($tracked);

        $placementCycle->update($validated);

        $this->audit->log($request, 'cycle.update', $placementCycle, $before, $placementCycle->only($tracked));

        return response()->json([
            'message' => 'Placement cycle updated successfully.',
            'placement_cycle' => $this->cyclePayload($placementCycle->fresh()->loadCount('enrollments')),
        ]);
    }

    public function close(Request $request, PlacementCycle $placementCycle): JsonResponse
    {
        if (! $placementCycle->isOpen()) {
            return response()->json([
                'message' => 'This placement cycle is already closed.',
            ], 422);
        }

        $placementCycle->update(['status' => 'closed']);

        $this->audit->log($request, 'cycle.close', $placementCycle, ['status' => 'open'], ['status' => 'closed']);

        return response()->json([
            'message' => 'Placement cycle closed.',
            'placement_cycle' => $this->cyclePayload($placementCycle->fresh()->loadCount('enrollments')),
        ]);
    }

    /**
     * Paginated list of the students enrolled in this cycle.
     */
    public function enrollments(Request $request, PlacementCycle $placementCycle): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $placementCycle->enrollments()
            ->with('studentProfile')
            ->orderBy('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $search = trim((string) ($validated['search'] ?? ''));

        if ($search !== '' && $this->studentDirectoryReady()) {
            $query->whereHas('studentProfile', function (Builder $student) use ($search): void {
                $student->where('roll_no', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('branch', 'like', "%{$search}%")
                    ->orWhere('programme', 'like', "%{$search}%");
            });
        }

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'enrollments' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * Bulk-enrol students by pasted roll numbers or an uploaded Excel/CSV column.
     */
    public function enroll(Request $request, PlacementCycle $placementCycle): JsonResponse
    {
        $request->validate([
            'roll_nos' => ['required_without:file', 'array', 'min:1'],
            'roll_nos.*' => ['nullable', 'string', 'max:30'],
            'file' => array_merge(['required_without:roll_nos'], SpreadsheetImportService::UPLOAD_RULES),
        ]);

        try {
            $candidates = $request->hasFile('file')
                ? $this->spreadsheets->firstColumn($request->file('file'), 'roll_no', 'roll no', 'roll number')
                : $this->pastedRollNumbers($request->input('roll_nos', []));
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        if ($candidates === []) {
            return response()->json(['message' => 'No roll numbers were found in the input.'], 422);
        }

        // Normalise and de-duplicate first, so the lookups below can be batched.
        $rollNumbers = [];
        $errors = [];
        $seen = [];

        foreach ($candidates as $rowNumber => $rollNo) {
            $rollNo = strtoupper(trim($rollNo));

            if (isset($seen[$rollNo])) {
                $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'reason' => 'Duplicate roll number in this upload.'];

                continue;
            }

            $seen[$rollNo] = true;
            $rollNumbers[$rowNumber] = $rollNo;
        }

        // Two queries for the whole batch rather than two per row (a cycle holds ~3000 students).
        $students = $this->findStudentsByRollNumber(array_values($rollNumbers));
        $enrolledIds = array_fill_keys($placementCycle->enrollments()->pluck('student_profile_id')->all(), true);

        $enrolled = [];
        $alreadyEnrolled = 0;

        foreach ($rollNumbers as $rowNumber => $rollNo) {
            $student = $students[$rollNo] ?? null;

            if (! $student) {
                $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'reason' => 'No student found with this roll number.'];

                continue;
            }

            if (isset($enrolledIds[$student->id])) {
                $alreadyEnrolled++;

                continue;
            }

            try {
                CycleEnrollment::create([
                    'placement_cycle_id' => $placementCycle->id,
                    'student_profile_id' => $student->id,
                    'status' => 'active',
                    'enrolled_by' => $request->user()?->id,
                ]);
            } catch (QueryException $exception) {
                // The unique index is the source of truth if two admins import at once.
                $alreadyEnrolled++;

                continue;
            } catch (Throwable $exception) {
                $errors[] = ['row' => $rowNumber, 'roll_no' => $rollNo, 'reason' => 'Could not be enrolled. Please try again.'];

                continue;
            }

            $enrolledIds[$student->id] = true;
            $enrolled[] = $rollNo;
        }

        if ($enrolled !== []) {
            $this->audit->log($request, 'cycle.enroll', $placementCycle, null, [
                'enrolled_count' => count($enrolled),
                'already_enrolled_count' => $alreadyEnrolled,
                'error_count' => count($errors),
                'roll_nos' => array_slice($enrolled, 0, self::AUDIT_SAMPLE),
                'roll_nos_truncated' => count($enrolled) > self::AUDIT_SAMPLE,
            ]);
        }

        return response()->json([
            'message' => sprintf(
                '%d student(s) enrolled. %d already enrolled. %d row(s) could not be processed.',
                count($enrolled),
                $alreadyEnrolled,
                count($errors)
            ),
            'enrolled' => count($enrolled),
            'already_enrolled' => $alreadyEnrolled,
            'errors' => $errors,
        ]);
    }

    public function unenroll(Request $request, PlacementCycle $placementCycle, StudentProfile $studentProfile): JsonResponse
    {
        $enrollment = $placementCycle->enrollments()
            ->where('student_profile_id', $studentProfile->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'This student is not enrolled in this cycle.'], 404);
        }

        $before = $enrollment->only(['placement_cycle_id', 'student_profile_id', 'status']);
        $enrollment->delete();

        $this->audit->log($request, 'cycle.unenroll', $placementCycle, $before, null);

        return response()->json(['message' => 'Student removed from this placement cycle.']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:fulltime,internship'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'description' => ['nullable', 'string', 'max:5000'],
            'allowed_programmes' => ['required', 'array', 'min:1'],
            'allowed_programmes.*.programme' => [
                'required',
                'string',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (! ProgrammeCatalogue::hasProgramme((string) $value)) {
                        $fail(sprintf('"%s" is not a programme in the catalogue.', $value));
                    }
                },
            ],
            'allowed_programmes.*.batches' => ['required', 'array', 'min:1'],
            'allowed_programmes.*.batches.*' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    /**
     * @return array<int, string> position in the pasted list (1-based) => roll number
     */
    private function pastedRollNumbers(array $input): array
    {
        $values = [];

        foreach (array_values($input) as $index => $rollNo) {
            $rollNo = trim((string) $rollNo);

            if ($rollNo !== '') {
                $values[$index + 1] = $rollNo;
            }
        }

        return $values;
    }

    /**
     * Counts for tables that later milestones create (job_postings in M5, offers in M7).
     */
    private function cyclePayload(PlacementCycle $cycle): array
    {
        return [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'type' => $cycle->type,
            'starts_on' => $cycle->starts_on?->toDateString(),
            'ends_on' => $cycle->ends_on?->toDateString(),
            'status' => $cycle->status,
            'allowed_programmes' => $cycle->allowed_programmes ?? [],
            'enrolled_students_count' => (int) ($cycle->enrollments_count ?? 0),
            'postings_count' => $this->relatedCount('job_postings', $cycle->id),
            'offers_count' => $this->relatedCount('offers', $cycle->id),
            'created_at' => $cycle->created_at,
            'updated_at' => $cycle->updated_at,
        ];
    }

    private function relatedCount(string $table, int $cycleId): int
    {
        if (! $this->tableExists($table)) {
            return 0;
        }

        return (int) DB::table($table)->where('placement_cycle_id', $cycleId)->count();
    }

    private function tableExists(string $table): bool
    {
        return $this->tableExists[$table] ??= Schema::hasTable($table);
    }

    /**
     * Resolve roll numbers to student profiles in chunks.
     *
     * @param  list<string>  $rollNumbers
     * @return array<string, StudentProfile> roll number => profile
     */
    private function findStudentsByRollNumber(array $rollNumbers): array
    {
        if ($rollNumbers === [] || ! $this->studentDirectoryReady()) {
            return [];
        }

        $found = [];

        foreach (array_chunk($rollNumbers, 500) as $chunk) {
            foreach (StudentProfile::query()->whereIn('roll_no', $chunk)->get(['id', 'roll_no']) as $student) {
                $found[(string) $student->roll_no] = $student;
            }
        }

        return $found;
    }

    /**
     * True once M2 has replaced the student_profiles stub with the real columns.
     */
    private function studentDirectoryReady(): bool
    {
        return Schema::hasColumn('student_profiles', 'roll_no');
    }
}
