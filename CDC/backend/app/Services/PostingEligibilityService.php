<?php

namespace App\Services;

use App\Jobs\SendPostingFloatedMails;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\AuditLog;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\ProgrammeCatalogue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Changing a floated drive's eligibility (D103). The one write path for eligibility_snapshot after float: the drive's
 * "Edit eligibility" dialog and the admin form editor both come through here, so the snapshot and the form's
 * form_data always hold the same criteria. Who is eligible is always decided by EligibilityService.
 */
final class PostingEligibilityService
{
    /** Drive statuses whose eligibility may still change. */
    public const EDITABLE_STATUSES = ['open', 'in_process'];

    /** Values a criteria key takes when the snapshot never stored it (older forms). Same meaning as "absent". */
    private const DEFAULTS = [
        'eligibility' => [],
        'globalCgpa' => '',
        'globalBacklogs' => false,
        'genderFilter' => 'all',
        'graduatingBatch' => '',
        'minTenthPercent' => '',
        'minTwelfthPercent' => '',
        'allowedStudentCategories' => [],
    ];

    private const PROGRAMME_KEYS = ['programme', 'expanded', 'courseDurationYears', 'graduatingBatch', 'graduatingBatches', 'branches'];

    public function __construct(
        private readonly EligibilityService $eligibility,
        private readonly AuditService $audit,
        private readonly MailDispatchService $mail
    ) {
    }

    /** Null when the drive's eligibility may change, otherwise the reason it may not. */
    public function refusal(JobPosting $posting): ?string
    {
        return in_array($posting->status, self::EDITABLE_STATUSES, true)
            ? null
            : "This job profile is {$posting->status}, so its eligibility can no longer be changed.";
    }

    /**
     * Null when applications may be reopened (or the deadline moved) together with an eligibility change, otherwise
     * why not. Same rules as "Reopen applications" (D75(h), D67): no results entered yet and the cycle still open.
     */
    public function reopenRefusal(JobPosting $posting): ?string
    {
        if ($refusal = $this->refusal($posting)) {
            return $refusal;
        }
        if (ApplicationRoundResult::query()->whereIn('posting_round_id', $posting->rounds()->pluck('id'))->exists()) {
            return 'Results have already been entered for this job profile, so applications cannot be reopened.';
        }
        if ($posting->placementCycle && ! $posting->placementCycle->isOpen()) {
            return 'The placement is closed, so applications cannot be reopened.';
        }

        return null;
    }

    /** The drive's current criteria, every key present. */
    public function current(JobPosting $posting): array
    {
        return array_replace(self::DEFAULTS, array_intersect_key($posting->eligibilityRules(), self::DEFAULTS));
    }

    /**
     * Validate the proposed keys (only those sent) and merge them over the current criteria.
     *
     * @throws ValidationException
     */
    public function proposed(JobPosting $posting, array $input): array
    {
        $input = array_intersect_key($input, self::DEFAULTS);
        $current = $this->current($posting);

        $validator = Validator::make($input, $this->rules(), $this->messages());
        $validator->after(function ($validator) use ($input, $current): void {
            if ($validator->errors()->isNotEmpty() || ! array_key_exists('eligibility', $input)) {
                return;
            }
            foreach ($this->catalogueErrors($input['eligibility'], $current['eligibility']) as $key => $message) {
                $validator->errors()->add($key, $message);
            }
        });
        $validator->validate();

        $normalised = [];
        foreach ($input as $key => $value) {
            $normalised[$key] = match ($key) {
                'eligibility' => $this->normaliseMatrix($value),
                'globalBacklogs' => $this->truthy($value),
                'genderFilter' => strtolower((string) $value),
                'allowedStudentCategories' => EligibilityService::categoryIds(['allowedStudentCategories' => $value ?? []]),
                default => trim((string) ($value ?? '')),
            };
        }

        return array_replace($current, $normalised);
    }

    /** True when the proposed criteria would decide anything differently (UI-only `expanded` flags are ignored). */
    public function differs(array $before, array $after): bool
    {
        return $this->fingerprint($before) !== $this->fingerprint($after);
    }

    /**
     * What a change would do, without writing anything.
     *
     * @return array<string, mixed>
     */
    public function preview(JobPosting $posting, array $criteria): array
    {
        $draft = $this->withCriteria($posting, $criteria);
        $before = $this->eligibleIds($posting);
        $after = $this->eligibleIds($draft);
        $newly = array_values(array_diff($after, $before));
        $lost = array_values(array_diff($before, $after));
        $canNotify = $posting->acceptsApplications();
        $notified = $this->notifiedIds($posting);
        $applicants = $this->applicantIds($posting);
        $unnotified = count(array_diff($newly, $notified, $applicants));
        $reopenRefusal = $this->reopenRefusal($posting);

        $affected = Application::query()
            ->where('job_posting_id', $posting->id)
            ->where('status', 'applied')
            ->whereIn('student_profile_id', $lost ?: [0])
            ->with(['studentProfile.user', 'studentProfile.cycleEnrollments'])
            ->get()
            ->sortBy(fn (Application $a) => $a->studentProfile->roll_no)
            ->map(fn (Application $a) => [
                'application_id' => $a->id,
                'roll_no' => $a->studentProfile->roll_no,
                'full_name' => $a->studentProfile->full_name,
                'programme' => $a->studentProfile->programme,
                'branch' => $a->studentProfile->branch,
                'current_cgpa' => $a->studentProfile->current_cgpa,
                'reasons' => $this->eligibility->check($a->studentProfile, $draft)['reasons'],
            ])
            ->values()
            ->all();

        return [
            'changed' => $this->differs($this->current($posting), $criteria),
            'currently_eligible' => count($before),
            'eligible_after' => count($after),
            'newly_eligible' => count($newly),
            'no_longer_eligible' => count($lost),
            'can_notify' => $canNotify,
            'will_be_notified' => $canNotify ? $unnotified : 0,
            // Newly eligible students never told about the drive: who would be mailed if applications are (re)opened.
            'newly_eligible_unnotified' => $unnotified,
            // Who would be mailed if applications are reopened with this change: everyone eligible after it, never told.
            'notified_on_reopen' => count(array_diff($after, $notified, $applicants)),
            'applications_open' => $canNotify,
            'can_reopen' => $reopenRefusal === null,
            'reopen_refusal' => $reopenRefusal,
            'affected_applicants' => $affected,
        ];
    }

    /**
     * Write the new criteria to the drive's snapshot and the form's form_data in one transaction, audit it, and
     * (optionally) send E2 to students who became eligible and were never told about this drive.
     *
     * @param  string  $source  "posting" (Edit eligibility dialog) or "form_editor" (admin JNF/INF editor)
     * @param  Carbon|null  $openUntil  also (re)open applications until this instant, so everyone eligible under the
     *                                  new criteria can apply (owner request 2026-10-06); the caller checks reopenRefusal()
     * @return array{changed: bool, reopened: bool, newly_eligible: int, no_longer_eligible: int, notified: int}
     */
    public function apply(JobPosting $posting, array $criteria, ?User $actor, ?string $ip, bool $notify, string $source, ?Carbon $openUntil = null): array
    {
        $result = DB::transaction(function () use ($posting, $criteria, $actor, $ip, $notify, $source, $openUntil): array {
            $posting = JobPosting::query()->with('placementCycle')->lockForUpdate()->findOrFail($posting->id);
            $before = $this->current($posting);
            $changed = $this->differs($before, $criteria);

            if (! $changed && $openUntil === null) {
                return ['changed' => false, 'reopened' => false, 'newly_eligible' => 0, 'no_longer_eligible' => 0, 'notified' => 0, 'ids' => []];
            }

            $beforeIds = $this->eligibleIds($posting);
            $window = fn () => ['status' => $posting->status, 'application_deadline' => $posting->application_deadline?->toIso8601String()];
            $windowBefore = $window();

            if ($changed) {
                $form = $posting->postable_type::query()->lockForUpdate()->findOrFail($posting->postable_id);
                $form->form_data = array_replace(is_array($form->form_data) ? $form->form_data : [], JobPosting::withoutAdminOnlyKeys($criteria)); // categories live only in the snapshot (fix M2)
                $form->save();

                $posting->eligibility_snapshot = $criteria;
                $posting->setRelation('postable', $form);
            }
            if ($openUntil !== null) {
                $posting->application_deadline = $openUntil;
                $posting->status = 'open';
            }
            $posting->save();

            $afterIds = $this->eligibleIds($posting);
            $newly = array_values(array_diff($afterIds, $beforeIds));
            $lost = array_values(array_diff($beforeIds, $afterIds));
            // Mail newly eligible students; when applications are (re)opened, every eligible student never told about
            // the drive (someone made eligible while it was closed hears about it now). Applicants are never mailed.
            $toNotify = $notify && $posting->acceptsApplications()
                ? array_values(array_diff($openUntil !== null ? $afterIds : $newly, $this->notifiedIds($posting), $this->applicantIds($posting)))
                : [];

            $reopened = $openUntil !== null;
            $beforeState = $changed ? ['criteria' => $before] : [];
            $afterState = $changed ? ['criteria' => $criteria] : [];
            if ($reopened) {
                $beforeState['applications'] = $windowBefore;
                $afterState['applications'] = $window();
            }

            // posting.eligibility_update when the criteria changed (with the reopening, if any); a bare reopening from
            // the same dialog is a posting.reopen like the Overview tab's button.
            $this->audit->logAs($actor, $ip, $changed ? 'posting.eligibility_update' : 'posting.reopen', $posting, $beforeState, $afterState + [
                'source' => $source,
                'newly_eligible' => count($newly),
                'no_longer_eligible' => count($lost),
                'notify_newly_eligible' => $notify,
                'notified' => count($toNotify),
            ]);

            return ['changed' => $changed, 'reopened' => $reopened, 'newly_eligible' => count($newly), 'no_longer_eligible' => count($lost), 'notified' => count($toNotify), 'ids' => $toNotify];
        });

        if ($result['ids'] !== []) {
            $ids = $result['ids'];
            $id = $posting->id;
            // Mail only once the change is committed (the form editor wraps this call in its own transaction).
            DB::afterCommit(fn () => $this->mail->mode() === 'sync'
                ? SendPostingFloatedMails::dispatchSync($id, $ids)
                : SendPostingFloatedMails::dispatch($id, $ids));
        }

        unset($result['ids']);

        return $result;
    }

    /**
     * Students already sent E2 for this drive (the ledger SendPostingFloatedMails writes as `posting.notify`).
     *
     * @return list<int>
     */
    public function notifiedIds(JobPosting $posting): array
    {
        return AuditLog::query()
            ->where('action', 'posting.notify')
            ->where('subject_type', JobPosting::class)
            ->where('subject_id', $posting->id)
            ->pluck('after')
            ->flatMap(fn ($after) => is_array($after) ? ($after['student_profile_ids'] ?? []) : [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------------------------------------

    /** An unsaved copy of the drive carrying the proposed criteria (the preview's "after"). */
    private function withCriteria(JobPosting $posting, array $criteria): JobPosting
    {
        $draft = $posting->replicate();
        $draft->eligibility_snapshot = $criteria;
        $draft->setRelation('postable', $posting->postable);

        return $draft;
    }

    /** @return list<int> students with a live application to the drive */
    private function applicantIds(JobPosting $posting): array
    {
        return Application::query()->where('job_posting_id', $posting->id)->where('status', 'applied')
            ->pluck('student_profile_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> */
    private function eligibleIds(JobPosting $posting): array
    {
        return $this->eligibility->eligibleStudentsQuery($posting)->pluck('student_profiles.id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        $cgpa = $this->decimal(10, 2, 'must be blank or a CGPA between 0 and 10 with at most 2 decimals');
        $percent = $this->decimal(100, 3, 'must be blank or between 0 and 100 with at most 2 decimals');
        $cap = function (string $attribute, mixed $value, \Closure $fail): void {
            if ($value !== null && trim((string) $value) !== '' && ! preg_match('/^\d{1,3}$/', trim((string) $value))) {
                $fail('Backlog limits must be blank or a whole number from 0 to 999.');
            }
        };
        $year = 'regex:/^\d{4}$/';

        return [
            'eligibility' => ['sometimes', 'array', 'min:1'],
            'eligibility.*' => ['array'],
            'eligibility.*.programme' => ['required', 'string', 'max:255'],
            'eligibility.*.branches' => ['required', 'array'],
            'eligibility.*.branches.*' => ['array'],
            'eligibility.*.branches.*.branch' => ['required', 'string', 'max:255'],
            'eligibility.*.branches.*.selected' => ['sometimes', 'nullable', 'boolean'],
            'eligibility.*.branches.*.backlogsAllowed' => ['sometimes', 'nullable', 'boolean'],
            'eligibility.*.branches.*.cgpa' => ['sometimes', 'nullable', $cgpa],
            'eligibility.*.branches.*.maxOngoingBacklogs' => ['sometimes', 'nullable', $cap],
            'eligibility.*.branches.*.maxTotalBacklogs' => ['sometimes', 'nullable', $cap],
            'eligibility.*.graduatingBatches' => ['sometimes', 'nullable', 'array'],
            'eligibility.*.graduatingBatches.*' => ['nullable', $year],
            'eligibility.*.graduatingBatch' => ['sometimes', 'nullable', $year],
            'globalCgpa' => ['sometimes', 'nullable', $cgpa],
            'globalBacklogs' => ['sometimes', 'nullable', 'boolean'],
            'genderFilter' => ['sometimes', 'nullable', 'in:all,male,female'],
            'graduatingBatch' => ['sometimes', 'nullable', $year],
            'minTenthPercent' => ['sometimes', 'nullable', $percent],
            'minTwelfthPercent' => ['sometimes', 'nullable', $percent],
            'allowedStudentCategories' => ['sometimes', 'nullable', 'array', 'max:50'],
            'allowedStudentCategories.*' => ['integer', 'exists:student_categories,id'],
        ];
    }

    /** @return array<string, string> */
    private function messages(): array
    {
        return [
            'eligibility.min' => 'Select at least one programme and branch.',
            'genderFilter.in' => 'Gender must be all, male or female.',
            'graduatingBatch.regex' => 'The passout batch must be a four-digit year.',
            'eligibility.*.graduatingBatches.*.regex' => 'Passout batches must be four-digit years.',
            'eligibility.*.graduatingBatch.regex' => 'Passout batches must be four-digit years.',
            'allowedStudentCategories.*.exists' => 'One of the chosen student categories no longer exists.',
        ];
    }

    /** Blank, or a non-negative number ≤ $max with at most 2 decimals (the wizards' rule, D61). */
    private function decimal(int $max, int $digits, string $message): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($max, $digits, $message): void {
            if ($value === null || is_bool($value) || trim((string) $value) === '') {
                return;
            }
            $text = trim((string) $value);
            if (! preg_match('/^\d{1,'.$digits.'}(\.\d{1,2})?$/', $text) || (float) $text > $max) {
                $label = match (true) {
                    str_ends_with($attribute, 'minTenthPercent') => 'Min Class X Percentage',
                    str_ends_with($attribute, 'minTwelfthPercent') => 'Min Class XII Percentage',
                    str_ends_with($attribute, 'globalCgpa') => 'The global CGPA',
                    default => 'A branch CGPA cut-off',
                };
                $fail("{$label} {$message}.");
            }
        };
    }

    /**
     * Programmes and selected branches must be in ProgrammeCatalogue. A branch already selected on the drive stays
     * allowed even if it has since been retired, so the CDC can still edit the other criteria.
     *
     * @return array<string, string>
     */
    private function catalogueErrors(array $matrix, array $current): array
    {
        $errors = [];
        $kept = [];
        foreach ($current as $entry) {
            foreach (is_array($entry['branches'] ?? null) ? $entry['branches'] : [] as $row) {
                if (is_array($row) && $this->truthy($row['selected'] ?? false)) {
                    $kept[strtolower(($entry['programme'] ?? '').'::'.($row['branch'] ?? ''))] = true;
                }
            }
        }

        $selected = 0;
        foreach ($matrix as $i => $entry) {
            $programme = (string) ($entry['programme'] ?? '');
            $knownProgramme = ProgrammeCatalogue::hasProgramme($programme);

            foreach ($entry['branches'] ?? [] as $j => $row) {
                if (! $this->truthy($row['selected'] ?? false)) {
                    continue;
                }
                $selected++;
                $branch = (string) ($row['branch'] ?? '');
                if (isset($kept[strtolower("{$programme}::{$branch}")])) {
                    continue;
                }
                if (! $knownProgramme) {
                    $errors["eligibility.{$i}.programme"] = "\"{$programme}\" is not a programme in the catalogue.";
                } elseif (! ProgrammeCatalogue::has($programme, $branch)) {
                    $errors["eligibility.{$i}.branches.{$j}.branch"] = "\"{$branch}\" is not a branch of {$programme}.";
                }
            }
        }

        if ($selected === 0) {
            $errors['eligibility'] = 'Select at least one programme and branch.';
        }

        return $errors;
    }

    /** Known keys only; booleans as booleans, numbers as strings (D57); caps cleared while backlogs are off. */
    private function normaliseMatrix(array $matrix): array
    {
        $string = fn ($value) => $value === null || is_bool($value) ? '' : trim((string) $value);

        return array_values(array_map(function ($entry) use ($string) {
            $entry = array_intersect_key($entry, array_flip(self::PROGRAMME_KEYS));
            $entry['programme'] = trim((string) $entry['programme']);
            if (array_key_exists('expanded', $entry)) {
                $entry['expanded'] = $this->truthy($entry['expanded']);
            }
            if (array_key_exists('graduatingBatches', $entry)) {
                $entry['graduatingBatches'] = array_values(array_filter(array_map($string, (array) $entry['graduatingBatches']), fn ($b) => $b !== ''));
            }
            if (array_key_exists('graduatingBatch', $entry)) {
                $entry['graduatingBatch'] = $string($entry['graduatingBatch']);
            }
            $entry['branches'] = array_values(array_map(function ($row) use ($string) {
                $allowed = $this->truthy($row['backlogsAllowed'] ?? false);

                return [
                    'branch' => trim((string) $row['branch']),
                    'selected' => $this->truthy($row['selected'] ?? false),
                    'cgpa' => $string($row['cgpa'] ?? ''),
                    'backlogsAllowed' => $allowed,
                    'maxOngoingBacklogs' => $allowed ? $string($row['maxOngoingBacklogs'] ?? '') : '',
                    'maxTotalBacklogs' => $allowed ? $string($row['maxTotalBacklogs'] ?? '') : '',
                ];
            }, $entry['branches']));

            return $entry;
        }, $matrix));
    }

    /** What the eligibility rules read, in a comparable form. */
    private function fingerprint(array $criteria): string
    {
        $criteria = array_replace(self::DEFAULTS, array_intersect_key($criteria, self::DEFAULTS));
        $string = fn ($value) => $value === null || is_bool($value) ? '' : trim((string) $value);
        $matrix = [];

        foreach (is_array($criteria['eligibility']) ? $criteria['eligibility'] : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $rows = [];
            foreach (is_array($entry['branches'] ?? null) ? $entry['branches'] : [] as $row) {
                if (! is_array($row) || ! $this->truthy($row['selected'] ?? false)) {
                    continue;
                }
                $allowed = $this->truthy($row['backlogsAllowed'] ?? false);
                $rows[] = [$string($row['branch'] ?? ''), $string($row['cgpa'] ?? ''), $allowed,
                    $allowed ? $string($row['maxOngoingBacklogs'] ?? '') : '', $allowed ? $string($row['maxTotalBacklogs'] ?? '') : ''];
            }
            if ($rows === []) {
                continue; // a programme with nothing selected admits nobody
            }
            // The batches EligibilityService would read for this programme (D68): the list, else the single value.
            $batches = array_values(array_filter(array_map($string, (array) ($entry['graduatingBatches'] ?? [])), fn ($b) => $b !== ''));
            if ($batches === [] && $string($entry['graduatingBatch'] ?? '') !== '') {
                $batches = [$string($entry['graduatingBatch'])];
            }
            $matrix[] = [$string($entry['programme'] ?? ''), $rows, $batches];
        }

        return json_encode([
            $matrix,
            $string($criteria['globalCgpa']),
            $this->truthy($criteria['globalBacklogs']),
            strtolower($string($criteria['genderFilter'])) ?: 'all',
            $string($criteria['graduatingBatch']),
            $string($criteria['minTenthPercent']),
            $string($criteria['minTwelfthPercent']),
            (function (array $ids) { sort($ids); return $ids; })(EligibilityService::categoryIds($criteria)),
        ]);
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }
}
