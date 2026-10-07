<?php

namespace App\Http\Controllers;

use App\Models\CycleEnrollment;
use App\Models\PlacementCycle;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\BlockingPolicy;
use App\Services\SpreadsheetImportService;
use App\Support\ProgrammeCatalogue;
use App\Support\StudentDirectoryFilters;
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
        private readonly SpreadsheetImportService $spreadsheets,
        private readonly BlockingPolicy $blocking
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
        // "Save as draft" (S8.3): a draft placement is hidden from students until it is published.
        $validated = $request->validate($this->rules() + ['is_draft' => ['sometimes', 'boolean']]);

        $cycle = PlacementCycle::create($validated + [
            'status' => 'open',
            'is_draft' => false,
            'created_by' => $request->user()?->id,
        ]);

        $this->audit->log($request, 'cycle.create', $cycle, null, $cycle->only([
            'name', 'type', 'starts_on', 'ends_on', 'status', 'is_draft', 'allowed_programmes', 'description',
        ]));

        return response()->json([
            'message' => 'Placement created successfully.',
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
            'message' => 'Placement updated successfully.',
            'placement_cycle' => $this->cyclePayload($placementCycle->fresh()->loadCount('enrollments')),
        ]);
    }

    /** "Publish placement" (S8.3): a draft becomes visible to its enrolled students and can take job profiles. */
    public function publish(Request $request, PlacementCycle $placementCycle): JsonResponse
    {
        if (! $placementCycle->is_draft) {
            return response()->json(['message' => 'This placement is already published.'], 422);
        }

        $placementCycle->update(['is_draft' => false]);

        $this->audit->log($request, 'cycle.publish', $placementCycle, ['is_draft' => true], ['is_draft' => false]);

        return response()->json([
            'message' => 'Placement published. Its enrolled students can now see it.',
            'placement_cycle' => $this->cyclePayload($placementCycle->fresh()->loadCount('enrollments')),
        ]);
    }

    public function close(Request $request, PlacementCycle $placementCycle): JsonResponse
    {
        if (! $placementCycle->isOpen()) {
            return response()->json([
                'message' => 'This placement is already closed.',
            ], 422);
        }

        $placementCycle->update(['status' => 'closed']);

        $this->audit->log($request, 'cycle.close', $placementCycle, ['status' => 'open'], ['status' => 'closed']);

        return response()->json([
            'message' => 'Placement closed.',
            'placement_cycle' => $this->cyclePayload($placementCycle->fresh()->loadCount('enrollments')),
        ]);
    }

    /**
     * "Download as Excel" of the enrolled list (S4.4, M5): the same filters as enrollments(), with the default columns
     * or `?template=<id>`. Without filters every enrolment is exported, exactly as before.
     */
    public function exportStudents(Request $request, PlacementCycle $placementCycle, \App\Services\ExportService $exports, \App\Services\TemplateExports $templates): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validated = $request->validate(StudentDirectoryFilters::enrollmentRules() + ['template' => ['nullable', 'integer']]);
        $template = \App\Services\TemplateExports::find($validated['template'] ?? null);
        $filters = StudentDirectoryFilters::active($validated);
        $narrow = $filters === [] ? null : fn ($enrollments) => StudentDirectoryFilters::applyToEnrollments($enrollments, $filters, $placementCycle->id);

        $this->audit->log($request, 'cycle.export', $placementCycle, null, [
            'enrolled' => $placementCycle->enrollments()->count(),
            'count' => $placementCycle->enrollments()->when($narrow !== null, fn ($query) => $narrow($query))->count(),
            'filters' => $filters,
            'template_id' => $template?->id,
        ]);

        return $template ? $templates->cycleStudents($placementCycle, $template, $narrow) : $exports->studentsWorkbook($placementCycle, $narrow);
    }

    /**
     * Paginated list of the students enrolled in this cycle, with the student list's Apply Filters (S4.1).
     * `status` here is the enrolment status; Placement and Blocked Status look at this placement unless
     * `cycle_id` names another.
     */
    public function enrollments(Request $request, PlacementCycle $placementCycle): JsonResponse
    {
        $validated = $request->validate(StudentDirectoryFilters::enrollmentRules() + [
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = $placementCycle->enrollments()
            ->with('studentProfile')
            ->orderBy('id');

        // Shared with exportStudents(), so "Download as Excel" holds exactly the students listed here.
        StudentDirectoryFilters::applyToEnrollments($query, $validated, $placementCycle->id);

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
     * Suspend or reactivate one enrolment (S4.8, D88(c)). A suspended enrolment makes the student ineligible for
     * every job profile of this placement (EligibilityService rule 1); their history here is kept.
     */
    public function updateEnrollment(Request $request, PlacementCycle $placementCycle, CycleEnrollment $enrollment): JsonResponse
    {
        abort_if((int) $enrollment->placement_cycle_id !== (int) $placementCycle->id, 404);

        $validated = $request->validate(['status' => ['required', 'in:active,suspended']]);

        $before = $enrollment->only(['student_profile_id', 'status']);
        if ($before['status'] === $validated['status']) {
            return response()->json([
                'message' => $validated['status'] === 'active' ? 'This student is already enrolled.' : 'This enrolment is already suspended.',
                'enrollment' => $enrollment->load('studentProfile'),
            ]);
        }

        $enrollment->update(['status' => $validated['status']]);

        $this->audit->log($request, 'cycle.enrollment_status', $enrollment, $before, $enrollment->only(['student_profile_id', 'status']));

        return response()->json([
            'message' => $validated['status'] === 'active' ? 'Enrolment reactivated.' : 'Enrolment suspended. The student cannot apply to this placement\'s job profiles.',
            'enrollment' => $enrollment->load('studentProfile'),
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
        $newStudentIds = [];
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
            $newStudentIds[] = $student->id;
        }

        // Owner decision (QA F-004): an offer block follows the student into a cycle they join later.
        $carried = $this->blocking->carryForward($placementCycle, $newStudentIds, $request->user()?->id);

        if ($enrolled !== []) {
            $this->audit->log($request, 'cycle.enroll', $placementCycle, null, [
                'enrolled_count' => count($enrolled),
                'already_enrolled_count' => $alreadyEnrolled,
                'error_count' => count($errors),
                'roll_nos' => array_slice($enrolled, 0, self::AUDIT_SAMPLE),
                'roll_nos_truncated' => count($enrolled) > self::AUDIT_SAMPLE,
            ]);
        }
        foreach ($carried as $block) {
            $this->audit->log($request, 'block.create', $block, null, $block->only(['student_profile_id', 'placement_cycle_id', 'scope', 'reason', 'offer_id']) + ['via' => 'enrolment']);
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
            'blocks_carried' => $carried->count(),
            'errors' => $errors,
        ]);
    }

    public function unenroll(Request $request, PlacementCycle $placementCycle, StudentProfile $studentProfile): JsonResponse
    {
        $enrollment = $placementCycle->enrollments()
            ->where('student_profile_id', $studentProfile->id)
            ->first();

        if (! $enrollment) {
            return response()->json(['message' => 'This student is not enrolled in this placement.'], 404);
        }

        // Keep placement history consistent: someone with an offer or applications here stays enrolled (suspend instead).
        $hasActivity = \App\Models\Offer::query()->where('placement_cycle_id', $placementCycle->id)->where('student_profile_id', $studentProfile->id)->exists()
            || \App\Models\Application::query()->where('student_profile_id', $studentProfile->id)
                ->whereHas('jobPosting', fn ($q) => $q->where('placement_cycle_id', $placementCycle->id))->exists();
        if ($hasActivity) {
            return response()->json(['message' => 'This student has applications or offers in this placement and cannot be removed.'], 422);
        }

        $before = $enrollment->only(['placement_cycle_id', 'student_profile_id', 'status']);
        $enrollment->delete();

        $this->audit->log($request, 'cycle.unenroll', $placementCycle, $before, null);

        return response()->json(['message' => 'Student removed from this placement.']);
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
            'is_draft' => (bool) $cycle->is_draft,
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
        if ($rollNumbers === []) {
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
}
