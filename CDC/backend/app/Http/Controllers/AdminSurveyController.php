<?php

namespace App\Http\Controllers;

use App\Mail\BroadcastMail;
use App\Models\JobPosting;
use App\Models\StudentProfile;
use App\Models\Survey;
use App\Models\SurveyAudience;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Services\AudienceService;
use App\Services\AuditService;
use App\Services\BroadcastService;
use App\Services\ExportService;
use App\Services\SurveyAnswerService;
use App\Support\Ist;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Survey Forms" (Superset parity S7.3): builder (Template + Audience tabs), publish, report, export, clone and
 * delete-or-archive. Answers are information only; nothing here touches offers or blocks (B2-5).
 */
class AdminSurveyController extends Controller
{
    public const PER_PAGE = 25;

    /** Report lists at most this many non-responders (the count is always exact). */
    public const NON_RESPONDER_LIMIT = 2000;

    private const SETTINGS = ['title', 'survey_type', 'welcome_text', 'concluding_text', 'is_public', 'allow_multiple', 'allow_edits', 'deadline_at', 'job_posting_id', 'status', 'published_at', 'archived_at'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly AudienceService $audiences,
        private readonly BroadcastService $broadcast,
        private readonly SurveyAnswerService $answers,
        private readonly ExportService $exports
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:all,draft,published,archived'],
            'type' => ['nullable', Rule::in(array_merge(['all'], Survey::TYPES))],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $status = $validated['status'] ?? 'all';
        $type = $validated['type'] ?? 'all';
        $page = Survey::query()
            ->withCount(['questions', 'responses'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($type !== 'all', fn ($q) => $q->where('survey_type', $type))
            ->when(filled($validated['search'] ?? null), fn ($q) => Like::whereContains($q, ['title'], $validated['search']))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        return response()->json([
            'surveys' => collect($page->items())->map(fn (Survey $s) => $this->summary($s)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'welcome_text' => ['nullable', 'string', 'max:20000'],
        ], ['title.required' => 'Required field']);

        $survey = Survey::create([
            'title' => trim($validated['title']),
            'welcome_text' => $validated['welcome_text'] ?? null,
            'status' => 'draft',
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log($request, 'survey.create', $survey, null, $this->snapshot($survey));

        return response()->json(['message' => 'Success! New survey added.', 'survey' => $this->detail($survey->fresh())], 201);
    }

    public function show(Survey $survey): JsonResponse
    {
        return response()->json(['survey' => $this->detail($survey)]);
    }

    /**
     * "Save Form" (Template tab) and the Audience tab. Every key is optional. Questions are saved by id: a question
     * sent with its id is updated in place, one without an id is created, and only the questions left out are deleted,
     * so an unchanged question keeps its id and answers keyed by it stay valid (M1). The Audience tab never sends
     * questions. Questions are frozen once the survey has a response (clone it to change the questions).
     */
    public function update(Request $request, Survey $survey): JsonResponse
    {
        abort_if($survey->status === 'archived', 422, 'This survey is archived.');

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'survey_type' => ['sometimes', Rule::in(Survey::TYPES)],
            'welcome_text' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'concluding_text' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'is_public' => ['sometimes', 'boolean'],
            'allow_multiple' => ['sometimes', 'boolean'],
            'allow_edits' => ['sometimes', 'boolean'],
            'deadline_at' => ['sometimes', 'nullable', 'date'],
            'job_posting_id' => ['sometimes', 'nullable', 'integer', 'exists:job_postings,id'],
            'questions' => ['sometimes', 'array', 'max:100'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.qtype' => ['required', Rule::in(SurveyQuestion::TYPES)],
            'questions.*.question' => ['required', 'string', 'max:5000'],
            'questions.*.help_text' => ['nullable', 'string', 'max:1000'],
            'questions.*.required' => ['nullable', 'boolean'],
            'questions.*.options' => ['nullable', 'array', 'max:50'],
            'questions.*.options.*' => ['nullable', 'string', 'max:255'],
            'questions.*.settings' => ['nullable', 'array'],
            'questions.*.settings.max' => ['nullable', 'integer', 'min:2', 'max:10'],
            'audiences' => ['sometimes', 'array'],
        ], [
            'questions.*.question.required' => 'Type the question.',
        ]);

        $questions = null;
        if (array_key_exists('questions', $validated)) {
            abort_if($survey->responses()->exists(), 422, 'This survey already has responses, so its questions cannot change. Clone it to make a new version.');
            $questions = $this->cleanQuestions($validated['questions']);
        }
        $groups = array_key_exists('audiences', $validated) ? $this->audiences->normalise($validated['audiences'], AudienceService::SURVEY_TYPES) : null;

        $fields = collect($validated)->except(['questions', 'audiences'])->all();
        if (array_key_exists('deadline_at', $fields)) {
            $fields['deadline_at'] = Ist::parseOrNull($fields['deadline_at']);
        }
        if (isset($fields['title'])) {
            $fields['title'] = trim($fields['title']);
        }

        $before = $this->snapshot($survey->load(['questions', 'audiences']));

        DB::transaction(function () use ($survey, $fields, $questions, $groups): void {
            $survey->update($fields);
            if ($questions !== null) {
                $this->syncQuestions($survey, $questions);
            }
            if ($groups !== null) {
                $survey->audiences()->delete();
                foreach ($groups as $group) {
                    $survey->audiences()->create($group);
                }
            }
            $survey->touch();
        });

        $survey = $survey->fresh();
        $this->audit->log($request, 'survey.update', $survey, $before, $this->snapshot($survey->load(['questions', 'audiences'])));

        return response()->json(['message' => 'Survey saved.', 'survey' => $this->detail($survey)]);
    }

    public function publish(Request $request, Survey $survey): JsonResponse
    {
        $validated = $request->validate(['send_email' => ['nullable', 'boolean']]);

        abort_if($survey->status !== 'draft', 422, 'Only a draft survey can be published.');
        $survey->load(['questions', 'audiences']);
        abort_if($survey->questions->filter(fn (SurveyQuestion $q) => $q->isAnswerable())->isEmpty(), 422, 'Add at least one question before publishing.');
        abort_if(! $survey->is_public && $survey->audiences->isEmpty(), 422, 'Add a target audience or make this survey public before publishing.');
        abort_if($survey->deadlinePassed(), 422, 'The survey deadline has already passed. Change it before publishing.');

        $survey->update(['status' => 'published', 'published_at' => now()]);
        $audience = $survey->audienceQuery()->count();

        $mailed = 0;
        if ($validated['send_email'] ?? false) {
            $lines = BroadcastMail::paragraphs($survey->welcome_text);
            if ($survey->deadline_at) {
                $lines[] = 'Please respond by '.$survey->deadline_at->timezone('Asia/Kolkata')->format('D, d M Y · h:i A').' IST.';
            }
            $mailable = new BroadcastMail(
                "Survey: {$survey->title}",
                $survey->title,
                $lines,
                rtrim((string) config('app.frontend_url'), '/')."/student/surveys/{$survey->id}",
                'Open Survey'
            );
            // The announcement is the only survey mail; nothing chases non-responders (B2-13).
            $mailed = $this->broadcast->toAudience($survey->audienceQuery(), $mailable, 'emails.broadcast', [
                'job_posting_id' => $survey->job_posting_id,
                'kind' => 'survey',
            ]);
            $survey->update(['emailed_at' => now()]);
        }

        $this->audit->log($request, 'survey.publish', $survey, ['status' => 'draft', 'published_at' => null], [
            'status' => 'published', 'published_at' => $survey->published_at->toIso8601String(), 'audience' => $audience, 'emailed' => $mailed,
        ]);

        return response()->json([
            'message' => "Survey published to {$audience} student(s)".($mailed ? " and announced to {$mailed} by email." : '.'),
            'survey' => $this->detail($survey->fresh()),
        ]);
    }

    /**
     * Delete: a draft is deleted; a published survey is archived (never hard-deleted, B2-7) and keeps its report.
     */
    public function destroy(Request $request, Survey $survey): JsonResponse
    {
        if ($survey->status === 'archived') {
            return response()->json(['message' => 'This survey is already archived.'], 422);
        }

        if ($survey->status === 'draft' && ! $survey->responses()->exists()) {
            $before = $this->snapshot($survey->load(['questions', 'audiences'])) + ['id' => $survey->id];
            $survey->delete();
            $this->audit->log($request, 'survey.delete', null, $before, null);

            return response()->json(['message' => 'Draft survey deleted.', 'archived' => false]);
        }

        $survey->update(['status' => 'archived', 'archived_at' => now()]);
        $this->audit->log($request, 'survey.archive', $survey, ['status' => 'published', 'archived_at' => null], ['status' => 'archived', 'archived_at' => $survey->archived_at->toIso8601String()]);

        return response()->json(['message' => 'Survey archived. Students no longer see it; its report is kept.', 'archived' => true, 'survey' => $this->summary($survey->loadCount(['questions', 'responses']))]);
    }

    /**
     * Clone: questions and settings (audience too), never responses, as a new draft.
     */
    public function clone(Request $request, Survey $survey): JsonResponse
    {
        $survey->load(['questions', 'audiences']);

        $copy = DB::transaction(function () use ($survey, $request) {
            $copy = $survey->replicate(['status', 'published_at', 'archived_at', 'emailed_at', 'created_by']);
            $copy->fill([
                'title' => Str::limit('Copy of '.$survey->title, 255, ''),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ])->save();
            foreach ($survey->questions as $question) {
                $copy->questions()->create($question->only(['qtype', 'question', 'help_text', 'options', 'settings', 'required', 'sort_order']));
            }
            foreach ($survey->audiences as $audience) {
                $copy->audiences()->create($audience->toGroup());
            }

            return $copy;
        });

        $this->audit->log($request, 'survey.clone', $copy, null, ['cloned_from' => $survey->id] + $this->snapshot($copy->load(['questions', 'audiences'])));

        return response()->json(['message' => 'Survey cloned as a new draft.', 'survey' => $this->detail($copy->fresh())], 201);
    }

    /**
     * View Report: every response, responders and non-responders (admin only; counts never reach students).
     */
    public function report(Survey $survey): JsonResponse
    {
        $survey->load(['questions', 'audiences']);
        $questions = $this->answerable($survey);
        $responses = $survey->responses()->with('studentProfile')->orderBy('submitted_at')->orderBy('id')->get();

        $responderIds = $responses->pluck('student_profile_id')->unique();
        $audienceCount = $survey->audienceQuery()->count();
        $nonResponders = $this->nonResponders($survey);
        $nonResponderCount = (clone $nonResponders)->count();

        $summary = $questions->map(function (array $q) use ($responses) {
            /** @var SurveyQuestion $question */
            $question = $q['question'];
            if (! in_array($question->qtype, ['mcq_single', 'mcq_multi', 'dropdown', 'yes_no', 'rating'], true)) {
                return null;
            }
            $choices = match ($question->qtype) {
                'yes_no' => ['yes' => 'Yes', 'no' => 'No'],
                'rating' => collect(range(1, $question->ratingMax()))->mapWithKeys(fn ($n) => [(string) $n => (string) $n])->all(),
                default => collect($question->options ?? [])->mapWithKeys(fn ($o) => [(string) $o => (string) $o])->all(),
            };
            $counts = array_fill_keys(array_keys($choices), 0);
            foreach ($responses as $response) {
                foreach ((array) ($response->answers[(string) $question->id] ?? []) as $value) {
                    if (array_key_exists((string) $value, $counts)) {
                        $counts[(string) $value]++;
                    }
                }
            }

            return ['question_id' => $question->id, 'number' => $q['number'], 'counts' => collect($choices)->map(fn ($label, $key) => ['label' => $label, 'count' => $counts[$key]])->values()];
        })->filter()->values();

        return response()->json([
            'survey' => $this->summary($survey->loadCount(['questions', 'responses'])),
            'questions' => $questions->map(fn (array $q) => ['id' => $q['question']->id, 'number' => $q['number'], 'qtype' => $q['question']->qtype, 'question' => $q['question']->question])->values(),
            'counts' => [
                'audience' => $audienceCount,
                'responses' => $responses->count(),
                'responders' => $responderIds->count(),
                'non_responders' => $nonResponderCount,
            ],
            'summary' => $summary,
            'responses' => $responses->map(fn (SurveyResponse $r) => [
                'id' => $r->id,
                'submitted_at' => $r->submitted_at,
                'student' => $this->student($r->studentProfile),
                'answers' => $questions->mapWithKeys(fn (array $q) => [(string) $q['question']->id => $this->answers->display($q['question'], $r->answers[(string) $q['question']->id] ?? null)]),
                'files' => collect($r->answers)->filter(fn ($v) => is_array($v) && isset($v['path']))->keys()->map(fn ($k) => (string) $k)->values(),
            ]),
            'non_responders' => $nonResponders->limit(self::NON_RESPONDER_LIMIT)->get()->map(fn (StudentProfile $s) => $this->student($s)),
        ]);
    }

    public function export(Request $request, Survey $survey): StreamedResponse
    {
        $survey->load(['questions', 'audiences']);
        $questions = $this->answerable($survey);
        $responses = $survey->responses()->with('studentProfile')->orderBy('submitted_at')->orderBy('id')->get();
        // Second sheet (L22): every audience member without a response, not capped like the on-screen list.
        $nonResponders = $this->nonResponders($survey)->get(['student_profiles.id', 'roll_no', 'full_name', 'branch']);

        $this->audit->log($request, 'survey.export', $survey, null, ['responses' => $responses->count(), 'non_responders' => $nonResponders->count()]);

        return $this->exports->surveyWorkbook($survey, $questions->values()->all(), $responses, fn ($question, $value) => $this->answers->display($question, $value), $nonResponders);
    }

    public function file(Survey $survey, SurveyResponse $surveyResponse, SurveyQuestion $surveyQuestion): StreamedResponse
    {
        abort_unless($surveyResponse->survey_id === $survey->id && $surveyQuestion->survey_id === $survey->id, 404, 'File not found.');

        return self::streamFile($surveyResponse, $surveyQuestion);
    }

    public static function streamFile(SurveyResponse $response, SurveyQuestion $question): StreamedResponse
    {
        $value = $response->answers[(string) $question->id] ?? null;
        abort_unless(is_array($value) && isset($value['path']) && Storage::disk('local')->exists($value['path']), 404, 'File not found.');

        return Storage::disk('local')->download($value['path'], $value['name'] ?? basename($value['path']), [
            'Content-Type' => $value['mime'] ?? 'application/octet-stream',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, array{question: SurveyQuestion, number: int}> */
    private function answerable(Survey $survey): \Illuminate\Support\Collection
    {
        $n = 0;

        return $survey->questions->filter(fn (SurveyQuestion $q) => $q->isAnswerable())
            ->values()
            ->map(function (SurveyQuestion $q) use (&$n) {
                return ['question' => $q, 'number' => ++$n];
            });
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return list<array<string, mixed>>
     */
    private function cleanQuestions(array $questions): array
    {
        // validated() builds the array rule by rule, so items with an `id` can come first: restore the request order.
        ksort($questions, SORT_NUMERIC);
        $out = [];
        foreach (array_values($questions) as $index => $q) {
            $type = $q['qtype'];
            $options = null;
            if (in_array($type, SurveyQuestion::WITH_OPTIONS, true)) {
                $options = array_values(array_unique(array_filter(array_map(fn ($o) => trim((string) $o), $q['options'] ?? []), fn ($o) => $o !== '')));
                abort_if(count($options) < 2, 422, sprintf('Question %d needs at least two different options.', $index + 1));
            }
            $out[] = [
                'id' => isset($q['id']) ? (int) $q['id'] : null,
                'qtype' => $type,
                'question' => trim($q['question']),
                'help_text' => filled($q['help_text'] ?? null) ? trim($q['help_text']) : null,
                'options' => $options,
                'settings' => $type === 'rating' ? ['max' => (int) ($q['settings']['max'] ?? 5)] : null,
                'required' => in_array($type, SurveyQuestion::DISPLAY_ONLY, true) ? false : (bool) ($q['required'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * Save the builder's questions by id (M1): update the rows whose id is sent, create the ones without a (known) id,
     * delete only the rows left out. An id of another survey's question is treated as a new question.
     *
     * @param  list<array<string, mixed>>  $questions  from cleanQuestions(), in display order
     */
    private function syncQuestions(Survey $survey, array $questions): void
    {
        $rows = SurveyQuestion::query()->where('survey_id', $survey->id)->get()->keyBy('id');
        $keep = [];
        foreach ($questions as $index => $question) {
            $id = $question['id'];
            $attributes = collect($question)->except('id')->all() + ['sort_order' => $index + 1];
            $row = $id !== null && ! isset($keep[$id]) ? $rows->get($id) : null;
            if ($row) {
                $row->update($attributes);
            } else {
                $row = $survey->questions()->create($attributes);
            }
            $keep[$row->id] = true;
        }
        SurveyQuestion::query()->where('survey_id', $survey->id)->whereNotIn('id', array_keys($keep))->delete();
    }

    /**
     * Audience members who have not responded (report and the Excel "Non-responders" sheet), by roll number.
     */
    private function nonResponders(Survey $survey): \Illuminate\Database\Eloquent\Builder
    {
        return $survey->audienceQuery()
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('survey_responses')
                ->where('survey_responses.survey_id', $survey->id)
                ->whereColumn('survey_responses.student_profile_id', 'student_profiles.id'))
            ->orderBy('roll_no');
    }

    /** @return array<string, mixed> */
    private function student(?StudentProfile $s): ?array
    {
        return $s ? $s->only(['id', 'roll_no', 'full_name', 'programme', 'branch', 'graduating_batch', 'institute_email']) : null;
    }

    /** @return array<string, mixed> */
    private function snapshot(Survey $survey): array
    {
        $data = $survey->only(self::SETTINGS);
        if ($survey->relationLoaded('questions')) {
            $data['questions'] = $survey->questions->map(fn (SurveyQuestion $q) => $q->only(['id', 'qtype', 'question', 'help_text', 'options', 'settings', 'required']))->all();
        }
        if ($survey->relationLoaded('audiences')) {
            $data['audiences'] = $survey->audiences->map(fn (SurveyAudience $a) => $a->toGroup())->all();
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function summary(Survey $survey): array
    {
        $posting = $survey->job_posting_id ? JobPosting::query()->find($survey->job_posting_id) : null;

        return [
            'id' => $survey->id,
            'title' => $survey->title,
            'survey_type' => $survey->survey_type,
            'status' => $survey->status,
            'is_public' => $survey->is_public,
            'deadline_at' => $survey->deadline_at,
            'published_at' => $survey->published_at,
            'archived_at' => $survey->archived_at,
            'updated_at' => $survey->updated_at,
            'question_count' => (int) ($survey->questions_count ?? $survey->questions()->count()),
            'response_count' => (int) ($survey->responses_count ?? $survey->responses()->count()),
            'job_posting' => $posting ? ['id' => $posting->id, 'label' => trim(($posting->company()?->name ?? '').' — '.$posting->title(), ' —')] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function detail(Survey $survey): array
    {
        $survey->load(['questions', 'audiences']);

        return $this->summary($survey) + [
            'welcome_text' => $survey->welcome_text,
            'concluding_text' => $survey->concluding_text,
            'allow_multiple' => $survey->allow_multiple,
            'allow_edits' => $survey->allow_edits,
            'job_posting_id' => $survey->job_posting_id,
            'questions' => $survey->questions->map(fn (SurveyQuestion $q) => $q->only(['id', 'qtype', 'question', 'help_text', 'options', 'settings', 'required', 'sort_order']))->values(),
            'audiences' => $survey->audiences->map(fn (SurveyAudience $a) => $a->toGroup() + ['label' => $this->audiences->describe($a->toGroup())])->values(),
            'audience_count' => $survey->audienceQuery()->count(),
            'has_responses' => $survey->responses()->exists(),
        ];
    }
}
