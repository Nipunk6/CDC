<?php

namespace App\Http\Controllers;

use App\Models\JobPosting;
use App\Models\StudentCategory;
use App\Models\StudentProfile;
use App\Services\AuditService;
use App\Services\EligibilityService;
use App\Services\SpreadsheetImportService;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Student Categories for Placement (Superset parity S8.4, owner decision B2-13): CDC-defined categories assigned to
 * students, which a job profile's eligibility may require. Every write is audited.
 */
class AdminStudentCategoryController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly SpreadsheetImportService $spreadsheets
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'categories' => StudentCategory::query()->withCount('students')->orderBy('title')->get(['id', 'title', 'description', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120', 'unique:student_categories,title'],
            'description' => ['nullable', 'string', 'max:1000'],
        ], ['title.unique' => 'A category with this title already exists.']);

        $category = StudentCategory::create($validated + ['created_by' => $request->user()->id]);
        $this->audit->log($request, 'student_category.create', $category, null, $category->only(['title', 'description']));

        return response()->json(['message' => 'Category created.', 'category' => $category], 201);
    }

    public function update(Request $request, StudentCategory $studentCategory): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('student_categories', 'title')->ignore($studentCategory->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], ['title.unique' => 'A category with this title already exists.']);

        $before = $studentCategory->only(array_keys($validated));
        $studentCategory->update($validated);
        $this->audit->log($request, 'student_category.update', $studentCategory, $before, $studentCategory->only(array_keys($validated)));

        return response()->json(['message' => 'Category updated.', 'category' => $studentCategory]);
    }

    /**
     * Deleting is refused while an open or in-process job profile requires the category, because removing it would
     * change who may apply without anyone editing that job profile's eligibility.
     */
    public function destroy(Request $request, StudentCategory $studentCategory): JsonResponse
    {
        $inUse = JobPosting::query()->whereIn('status', ['open', 'in_process'])->get(['id', 'eligibility_snapshot', 'postable_type', 'postable_id'])
            ->filter(fn (JobPosting $p) => in_array($studentCategory->id, EligibilityService::categoryIds($p->eligibility_snapshot ?? []), true));
        if ($inUse->isNotEmpty()) {
            return response()->json([
                'message' => sprintf('%d open job profile(s) require this category. Remove it from their eligibility first.', $inUse->count()),
            ], 422);
        }

        $before = $studentCategory->only(['id', 'title', 'description']) + ['students' => $studentCategory->students()->count()];
        $studentCategory->delete();
        $this->audit->log($request, 'student_category.delete', null, $before, null);

        return response()->json(['message' => 'Category deleted.']);
    }

    public function members(Request $request, StudentCategory $studentCategory): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));
        $page = $studentCategory->students()
            ->when($search !== '', fn ($q) => Like::whereContains($q, ['roll_no', 'full_name'], $search)) // `%` and `_` literal (L28)
            ->orderBy('roll_no')
            ->paginate(50, ['student_profiles.id', 'roll_no', 'full_name', 'programme', 'branch', 'graduating_batch']);

        return response()->json([
            'students' => collect($page->items())->map(fn ($s) => $s->only(['id', 'roll_no', 'full_name', 'programme', 'branch', 'graduating_batch'])),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    /**
     * Assign students by roll number: a pasted list or an uploaded sheet (first column). Unknown roll numbers are
     * reported, never dropped; one audit row per batch (D25).
     */
    public function assign(Request $request, StudentCategory $studentCategory): JsonResponse
    {
        $request->validate([
            'roll_nos' => ['required_without:file', 'array'],
            'roll_nos.*' => ['nullable', 'string', 'max:30'],
            'file' => array_merge(['required_without:roll_nos'], SpreadsheetImportService::UPLOAD_RULES),
        ]);

        try {
            $rolls = $request->hasFile('file')
                ? $this->spreadsheets->firstColumn($request->file('file'), 'rollno', 'rollnumber', 'institutorollnumber')
                : $request->input('roll_nos', []);
        } catch (Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $rolls = array_values(array_unique(array_filter(array_map(fn ($r) => strtoupper(trim((string) $r)), $rolls))));
        if ($rolls === []) {
            return response()->json(['message' => 'No roll numbers were found in the input.'], 422);
        }

        $students = StudentProfile::query()->whereIn('roll_no', $rolls)->pluck('id', 'roll_no');
        $unknown = array_values(array_diff($rolls, $students->keys()->all()));
        $already = $studentCategory->students()->whereIn('student_profiles.id', $students->values())->pluck('student_profiles.id')->all();
        $new = array_values(array_diff($students->values()->all(), $already));

        if ($new !== []) {
            $studentCategory->students()->attach(array_fill_keys($new, ['assigned_by' => $request->user()->id]));
            $this->audit->log($request, 'student_category.assign', $studentCategory, null, [
                'added' => count($new),
                'roll_nos' => array_slice($students->flip()->only($new)->values()->all(), 0, 100),
            ]);
        }

        return response()->json([
            'message' => sprintf('%d student(s) added. %d already in the category. %d not found.', count($new), count($already), count($unknown)),
            'added' => count($new),
            'errors' => array_map(fn ($r) => ['roll_no' => $r, 'reason' => 'No student with this roll number.'], $unknown),
        ]);
    }

    public function unassign(Request $request, StudentCategory $studentCategory, StudentProfile $studentProfile): JsonResponse
    {
        if (! $studentCategory->students()->whereKey($studentProfile->id)->exists()) {
            return response()->json(['message' => 'This student is not in the category.'], 422);
        }

        $studentCategory->students()->detach($studentProfile->id);
        $this->audit->log($request, 'student_category.unassign', $studentCategory, ['roll_no' => $studentProfile->roll_no], null);

        return response()->json(['message' => 'Student removed from the category.']);
    }

    /** The categories of one student (student page). */
    public function forStudent(StudentProfile $studentProfile): JsonResponse
    {
        return response()->json(['categories' => $studentProfile->studentCategories()->orderBy('title')->get(['student_categories.id', 'title'])]);
    }
}
