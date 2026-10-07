<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Services\SurveyAnswerService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Student "Surveys" (Superset parity S7.3): published surveys in the student's audience (or public: any signed-in
 * student, B2-6). A survey outside the student's audience, a draft or an archived survey is a 404. Mandatory questions
 * and every answer are checked on the server (SurveyAnswerService). Students never see response counts.
 */
class StudentSurveyController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private readonly SurveyAnswerService $answers)
    {
    }

    /**
     * Newest first, 20 per page; the audience filter runs in SQL so older surveys never drop off the list (M3).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $student = $this->student($request);

        $page = Survey::query()->visibleTo($student)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
        $mine = SurveyResponse::query()->where('student_profile_id', $student->id)
            ->whereIn('survey_id', collect($page->items())->pluck('id'))
            ->get()
            ->groupBy('survey_id');

        return response()->json([
            'surveys' => collect($page->items())->map(fn (Survey $s) => $this->summary($s, $mine->get($s->id, collect())))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(Request $request, Survey $survey): JsonResponse
    {
        $student = $this->student($request);
        $this->ensureVisible($survey, $student);

        $mine = $survey->responses()->where('student_profile_id', $student->id)->orderBy('submitted_at')->get();

        return response()->json([
            'survey' => $this->summary($survey, $mine) + [
                'welcome_text' => $survey->welcome_text,
                'concluding_text' => $survey->concluding_text,
                'questions' => $survey->questions->map(fn (SurveyQuestion $q) => $q->only(['id', 'qtype', 'question', 'help_text', 'options', 'settings', 'required']))->values(),
            ],
            'responses' => $mine->map(fn (SurveyResponse $r) => [
                'id' => $r->id,
                'submitted_at' => $r->submitted_at,
                'answers' => $this->answers->publicAnswers($r->answers),
            ]),
        ]);
    }

    public function submit(Request $request, Survey $survey): JsonResponse
    {
        $student = $this->student($request);
        $this->ensureVisible($survey, $student);
        abort_if($survey->deadlinePassed(), 422, 'This survey is closed.');

        $existing = $survey->responses()->where('student_profile_id', $student->id)->exists();
        abort_if($existing && ! $survey->allow_multiple, 422, $survey->allow_edits ? 'You have already responded. Edit your response instead.' : 'You have already responded to this survey.');

        $clean = $this->answers->validate($survey, $student, $this->rawAnswers($request), $this->files($request));

        // With "Allow multiple submission" off, a second simultaneous first submission must not be stored too (L21):
        // the student's row is locked while the check and insert run, and `single_key` is unique in the database.
        try {
            $response = DB::transaction(function () use ($survey, $student, $clean): ?SurveyResponse {
                StudentProfile::query()->whereKey($student->id)->lockForUpdate()->first();
                $single = ! Survey::query()->whereKey($survey->id)->value('allow_multiple');
                if ($single && $survey->responses()->where('student_profile_id', $student->id)->exists()) {
                    return null;
                }

                return $survey->responses()->create([
                    'student_profile_id' => $student->id,
                    'single_key' => $single ? SurveyResponse::singleKey($survey->id, $student->id) : null,
                    'answers' => $clean,
                    'submitted_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            $response = null;
        }
        if (! $response) {
            $this->answers->pruneFiles($clean, []); // files stored for the refused duplicate

            return response()->json(['message' => 'You have already responded to this survey.'], 409);
        }

        return response()->json([
            'message' => 'Your response has been submitted.',
            'response' => ['id' => $response->id, 'submitted_at' => $response->submitted_at, 'answers' => $this->answers->publicAnswers($response->answers)],
        ], 201);
    }

    /**
     * "Allow edits before deadline": re-open and update one's own response until the deadline.
     */
    public function update(Request $request, Survey $survey, SurveyResponse $surveyResponse): JsonResponse
    {
        $student = $this->student($request);
        $this->ensureVisible($survey, $student);
        abort_unless($surveyResponse->survey_id === $survey->id && $surveyResponse->student_profile_id === $student->id, 404, 'Response not found.');
        abort_unless($survey->allow_edits, 422, 'Responses to this survey cannot be edited.');
        abort_if($survey->deadlinePassed(), 422, 'This survey is closed.');

        $old = $surveyResponse->answers ?? [];
        $clean = $this->answers->validate($survey, $student, $this->rawAnswers($request), $this->files($request), $surveyResponse);
        $surveyResponse->update(['answers' => $clean, 'submitted_at' => now()]);
        $this->answers->pruneFiles($old, $clean);

        return response()->json([
            'message' => 'Your response has been updated.',
            'response' => ['id' => $surveyResponse->id, 'submitted_at' => $surveyResponse->submitted_at, 'answers' => $this->answers->publicAnswers($surveyResponse->answers)],
        ]);
    }

    public function file(Request $request, Survey $survey, SurveyResponse $surveyResponse, SurveyQuestion $surveyQuestion): StreamedResponse
    {
        $student = $this->student($request);
        $this->ensureVisible($survey, $student);
        abort_unless($surveyResponse->survey_id === $survey->id && $surveyResponse->student_profile_id === $student->id && $surveyQuestion->survey_id === $survey->id, 404, 'File not found.');

        return AdminSurveyController::streamFile($surveyResponse, $surveyQuestion);
    }

    private function ensureVisible(Survey $survey, StudentProfile $student): void
    {
        $survey->loadMissing(['audiences', 'questions']);
        abort_unless($survey->isVisibleTo($student), 404, 'Survey not found.');
    }

    /**
     * `answers` arrives as an object (JSON body) or a JSON string (multipart, when files are attached).
     *
     * @return array<string|int, mixed>
     */
    private function rawAnswers(Request $request): array
    {
        $raw = $request->input('answers', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        abort_unless(is_array($raw), 422, 'Answers are missing.');

        return $raw;
    }

    /** @return array<string|int, \Illuminate\Http\UploadedFile> */
    private function files(Request $request): array
    {
        $files = $request->file('files', []);

        return is_array($files) ? $files : [];
    }

    private function student(Request $request): StudentProfile
    {
        $student = $request->user()->studentProfile;
        abort_if(! $student, 404, 'Student profile not found.');

        return $student;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SurveyResponse>  $mine
     * @return array<string, mixed>
     */
    private function summary(Survey $survey, $mine): array
    {
        $open = $survey->isOpen();

        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'deadline_at' => $survey->deadline_at,
            'published_at' => $survey->published_at,
            'is_open' => $open,
            'allow_multiple' => $survey->allow_multiple,
            'allow_edits' => $survey->allow_edits,
            'responded' => $mine->isNotEmpty(),
            'my_response_count' => $mine->count(),
            'can_submit' => $open && ($mine->isEmpty() || $survey->allow_multiple),
            'can_edit' => $open && $survey->allow_edits && $mine->isNotEmpty(),
        ];
    }
}
