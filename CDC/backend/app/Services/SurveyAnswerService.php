<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use App\Models\SurveyResponse;
use App\Support\UploadType;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Server-side checking of survey answers (Superset parity S7.3): mandatory questions, allowed options, rating range,
 * sequences that are a full ordering, dates, and File Upload (PDF/image, max 5 MB, private disk, B3).
 * Answers are stored as JSON keyed by question id. Answers are information only (B2-5).
 */
class SurveyAnswerService
{
    public const FILE_MAX_KB = 5120;

    public const FILE_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    /** Answers keyed by a question this survey no longer has: the student's page is older than the survey (M1). */
    public const SURVEY_CHANGED = 'This survey was updated — please reload it';

    /**
     * @param  array<string|int, mixed>  $answers  raw answers keyed by question id
     * @param  array<string|int, UploadedFile>  $files  File Upload answers keyed by question id
     * @return array<string, mixed> clean answers keyed by question id (string)
     *
     * @throws ValidationException
     */
    public function validate(Survey $survey, StudentProfile $student, array $answers, array $files, ?SurveyResponse $existing = null): array
    {
        $errors = [];
        $clean = [];
        $newFiles = [];

        // Every key must be a question of this survey. A form opened before the CDC changed the questions would
        // otherwise lose those answers silently (M1); the student reloads instead.
        $known = $survey->questions->map(fn (SurveyQuestion $q) => (string) $q->id)->all();
        $sent = array_map('strval', array_merge(array_keys($answers), array_keys($files)));
        if (array_diff($sent, $known) !== []) {
            throw ValidationException::withMessages(['survey' => self::SURVEY_CHANGED]);
        }

        foreach ($survey->questions as $question) {
            if (! $question->isAnswerable()) {
                continue;
            }
            $key = (string) $question->id;
            $raw = $answers[$key] ?? $answers[$question->id] ?? null;

            try {
                if ($question->qtype === 'file') {
                    $file = $files[$key] ?? $files[$question->id] ?? null;
                    $value = $file instanceof UploadedFile ? $this->checkFile($file) : ($existing?->answers[$key] ?? null);
                    if ($file instanceof UploadedFile) {
                        $newFiles[$key] = $file;
                    }
                } else {
                    $value = $this->clean($question, $raw);
                }
            } catch (\InvalidArgumentException $e) {
                $errors["answers.{$key}"] = $e->getMessage();

                continue;
            }

            if ($value === null) {
                if ($question->required) {
                    $errors["answers.{$key}"] = 'This question is mandatory.';
                }

                continue;
            }
            $clean[$key] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        // Files are stored only once every answer passed.
        foreach ($newFiles as $key => $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'pdf');
            $path = $file->storeAs("surveys/{$survey->id}/{$student->id}", Str::uuid().'.'.$ext, 'local');
            $clean[$key] = [
                'path' => $path,
                'name' => Str::limit($file->getClientOriginalName(), 200, ''),
                'size' => $file->getSize() ?: 0,
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
            ];
        }

        return $clean;
    }

    /**
     * Delete files of an old answer set that the new set no longer references.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function pruneFiles(array $old, array $new): void
    {
        $keep = collect($new)->filter(fn ($v) => is_array($v) && isset($v['path']))->pluck('path')->all();
        foreach ($old as $value) {
            if (is_array($value) && isset($value['path']) && ! in_array($value['path'], $keep, true)) {
                Storage::disk('local')->delete($value['path']);
            }
        }
    }

    /**
     * Answers safe to send to a browser: File Upload answers lose their storage path.
     *
     * @param  array<string, mixed>|null  $answers
     * @return array<string, mixed>
     */
    public function publicAnswers(?array $answers): array
    {
        return collect($answers ?? [])->map(fn ($v) => is_array($v) && isset($v['path']) ? ['name' => $v['name'] ?? 'file', 'size' => $v['size'] ?? null] : $v)->all();
    }

    /**
     * One answer as plain text (report table and Excel export).
     */
    public function display(SurveyQuestion $question, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($question->qtype) {
            'mcq_multi' => implode('; ', (array) $value),
            'sequence' => implode(' > ', (array) $value),
            'yes_no' => $value === 'yes' ? 'Yes' : 'No',
            'rating' => $value.' / '.$question->ratingMax(),
            'date' => Carbon::parse((string) $value)->format('d M Y'),
            'rich_text' => trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", (string) $value)), ENT_QUOTES | ENT_HTML5)),
            'file' => is_array($value) ? (string) ($value['name'] ?? 'file') : '',
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }

    /**
     * @throws \InvalidArgumentException on an invalid value; returns null when unanswered
     */
    private function clean(SurveyQuestion $question, mixed $raw): mixed
    {
        $options = array_values(array_map('strval', $question->options ?? []));

        switch ($question->qtype) {
            case 'mcq_single':
            case 'dropdown':
                if ($raw === null || $raw === '') {
                    return null;
                }
                if (! is_scalar($raw) || ! in_array((string) $raw, $options, true)) {
                    throw new \InvalidArgumentException('Pick one of the listed options.');
                }

                return (string) $raw;

            case 'mcq_multi':
                if ($raw === null || $raw === '' || $raw === []) {
                    return null;
                }
                $picked = array_values(array_unique(array_map('strval', array_filter((array) $raw, 'is_scalar'))));
                if (array_diff($picked, $options) !== [] || count($picked) !== count((array) $raw)) {
                    throw new \InvalidArgumentException('Pick from the listed options.');
                }

                return array_values(array_filter($options, fn ($o) => in_array($o, $picked, true))); // option order

            case 'text':
                $text = is_scalar($raw) ? trim((string) $raw) : '';
                if (mb_strlen($text) > 5000) {
                    throw new \InvalidArgumentException('The answer may be at most 5000 characters.');
                }

                return $text === '' ? null : $text;

            case 'rich_text':
                $html = is_scalar($raw) ? (string) $raw : '';
                if (mb_strlen($html) > 20000) {
                    throw new \InvalidArgumentException('The answer is too long.');
                }

                return trim(html_entity_decode(strip_tags($html))) === '' ? null : $html;

            case 'yes_no':
                if ($raw === null || $raw === '') {
                    return null;
                }
                if (! in_array($raw, ['yes', 'no'], true)) {
                    throw new \InvalidArgumentException('Answer Yes or No.');
                }

                return $raw;

            case 'date':
                if ($raw === null || $raw === '') {
                    return null;
                }
                try {
                    $date = is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) ? Carbon::createFromFormat('!Y-m-d', $raw) : null;
                } catch (Throwable) {
                    $date = null;
                }
                if (! $date || $date->format('Y-m-d') !== $raw) {
                    throw new \InvalidArgumentException('Enter a valid date.');
                }

                return $raw;

            case 'rating':
                if ($raw === null || $raw === '') {
                    return null;
                }
                if (! is_numeric($raw) || (int) $raw != $raw || (int) $raw < 1 || (int) $raw > $question->ratingMax()) {
                    throw new \InvalidArgumentException('Pick a rating from 1 to '.$question->ratingMax().'.');
                }

                return (int) $raw;

            case 'sequence':
                if ($raw === null || $raw === '' || $raw === []) {
                    return null;
                }
                $order = array_values(array_map('strval', array_filter((array) $raw, 'is_scalar')));
                $sortedA = $order;
                $sortedB = $options;
                sort($sortedA);
                sort($sortedB);
                if ($sortedA !== $sortedB) {
                    throw new \InvalidArgumentException('Put every option in order.');
                }

                return $order;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function checkFile(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('The file could not be uploaded.');
        }
        if (($file->getSize() ?: 0) > self::FILE_MAX_KB * 1024) {
            throw new \InvalidArgumentException('The file must be at most 5 MB.');
        }
        // The content (finfo) must match the extension: a PDF named x.png is refused (L24).
        if (! UploadType::matches($file, self::FILE_MIMES)) {
            throw new \InvalidArgumentException('Upload a PDF or an image (JPG/PNG).');
        }

        return ['pending' => true];
    }
}
