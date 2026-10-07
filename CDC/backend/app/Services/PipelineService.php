<?php

namespace App\Services;

use App\Jobs\SendRoundResultMails;
use App\Mail\RoundResultMail;
use App\Models\Application;
use App\Models\ApplicationRoundResult;
use App\Models\JobPosting;
use App\Models\PostingRound;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Per-round selection results (spec B3 / M6): drafts, publishing, waitlists (unranked, D90).
 * Nothing written here is visible to students or companies until publish() stamps published_at.
 */
class PipelineService
{
    public function __construct(
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
    ) {
    }

    /**
     * Map roll numbers to this posting's live (applied) applications.
     *
     * @param  list<string>  $rollNumbers
     * @return array{0: array<string, Application>, 1: list<array{roll_no: string, reason: string}>}
     */
    public function resolveApplicants(JobPosting $posting, array $rollNumbers, bool $forCompany = false): array
    {
        $normalised = array_values(array_unique(array_filter(array_map(fn ($r) => strtoupper(trim((string) $r)), $rollNumbers))));

        $applications = $posting->applications()
            ->with('studentProfile:id,roll_no,full_name')
            ->whereHas('studentProfile', fn ($q) => $q->whereIn('roll_no', $normalised))
            ->get()
            ->keyBy(fn (Application $a) => $a->studentProfile->roll_no);

        $found = [];
        $unknown = [];

        foreach ($normalised as $roll) {
            $application = $applications->get($roll);
            if (! $application) {
                $unknown[] = ['roll_no' => $roll, 'reason' => 'Not an applicant of this job profile.'];
            } elseif ($application->status !== 'applied') {
                // Companies never learn who withdrew (D88).
                $unknown[] = ['roll_no' => $roll, 'reason' => $forCompany ? 'Not an applicant of this job profile.' : 'Withdrew the application.'];
            } else {
                $found[$roll] = $application;
            }
        }

        return [$found, $unknown];
    }

    /**
     * Entries may name a student by email instead of roll number (Superset parity S1.3). Emails are matched against the
     * institute and personal email of this posting's applicants and replaced by the roll number; unmatched emails are
     * reported, never dropped.
     *
     * @param  list<array{roll_no: string, result: string}>  $entries
     * @return array{0: list<array{roll_no: string, result: string, email?: string}>, 1: list<array{roll_no: string, reason: string}>}
     */
    public function resolveEmails(JobPosting $posting, array $entries): array
    {
        $emails = array_values(array_unique(array_map(
            fn ($e) => strtolower(trim($e['roll_no'])),
            array_filter($entries, fn ($e) => str_contains($e['roll_no'], '@'))
        )));
        if ($emails === []) {
            return [$entries, []];
        }

        // Both sides lower-cased, so matching does not depend on the column's collation (L10).
        $byEmail = [];
        $posting->applications()
            ->with('studentProfile:id,roll_no,institute_email,personal_email')
            ->whereHas('studentProfile', fn ($q) => $q->where(fn ($w) => $w
                ->whereIn(DB::raw('LOWER(TRIM(institute_email))'), $emails)
                ->orWhereIn(DB::raw('LOWER(TRIM(personal_email))'), $emails)))
            ->get()
            ->each(function (Application $a) use (&$byEmail): void {
                foreach (['institute_email', 'personal_email'] as $field) {
                    if ($a->studentProfile->{$field}) {
                        $byEmail[strtolower(trim($a->studentProfile->{$field}))] = $a->studentProfile->roll_no;
                    }
                }
            });

        $resolved = [];
        $errors = [];
        foreach ($entries as $entry) {
            if (! str_contains($entry['roll_no'], '@')) {
                $resolved[] = $entry;

                continue;
            }
            $roll = $byEmail[strtolower(trim($entry['roll_no']))] ?? null;
            if ($roll === null) {
                $errors[] = ['roll_no' => strtolower(trim($entry['roll_no'])), 'reason' => 'No applicant of this job profile has this email.'];

                continue;
            }
            $resolved[] = ['roll_no' => $roll, 'email' => strtolower(trim($entry['roll_no']))] + $entry;
        }

        return [$resolved, $errors];
    }

    /**
     * Applications that take part in a round: every live applicant for the first round, otherwise those
     * selected (published) in the previous round.
     *
     * @return Collection<int, Application>
     */
    public function pool(PostingRound $round): Collection
    {
        $posting = $round->jobPosting;
        $previous = $posting->rounds()->where('sort_order', '<', $round->sort_order)->get()->last();

        $query = $posting->applications()->where('status', 'applied');

        if ($previous) {
            $query->whereHas('roundResults', fn ($q) => $q
                ->where('posting_round_id', $previous->id)
                ->where('result', 'selected')
                ->whereNotNull('published_at'));
        }

        return $query->get();
    }

    /**
     * Write DRAFT rows. Refuses to overwrite a published row (that needs the addendum / re-add protocols).
     *
     * @param  list<array{application: Application, result: string, is_addendum?: bool, remark?: string|null}>  $entries
     * @return array{written: int, skipped: list<array{roll_no: string, reason: string}>}
     */
    public function writeDrafts(PostingRound $round, array $entries, ?User $admin): array
    {
        $written = 0;
        $skipped = [];

        DB::transaction(function () use ($round, $entries, $admin, &$written, &$skipped): void {
            foreach ($entries as $entry) {
                /** @var Application $application */
                $application = $entry['application'];
                $row = ApplicationRoundResult::query()
                    ->where('application_id', $application->id)
                    ->where('posting_round_id', $round->id)
                    ->lockForUpdate()
                    ->first();

                $roll = $application->studentProfile?->roll_no ?? (string) $application->id;

                if ($row && $row->isPublished()) {
                    $skipped[] = [
                        'roll_no' => $roll,
                        'reason' => $row->result === 'rejected'
                            ? 'Already rejected in this stage (published). Use "Re-add" to bring them back.'
                            : 'Result already published for this stage.',
                    ];

                    continue;
                }

                // Someone published as not selected in an EARLIER round comes back only through Re-add (D75).
                if ($entry['result'] !== 'rejected' && $this->rejectedEarlier($application, $round)) {
                    $skipped[] = ['roll_no' => $roll, 'reason' => 'Not shortlisted in an earlier stage. Use "Re-add" on that stage first.'];

                    continue;
                }

                $attributes = [
                    'result' => $entry['result'],
                    // Additions after a round was published are always addenda; a re-added draft keeps its flag.
                    'is_addendum' => (bool) ($entry['is_addendum'] ?? false) || $round->status === 'completed' || (bool) $row?->is_addendum,
                    'decided_by' => $admin?->id,
                    'remark' => $entry['remark'] ?? ($row?->is_addendum ? $row->remark : null),
                ];

                if ($row) {
                    $row->update($attributes);
                } else {
                    ApplicationRoundResult::create($attributes + [
                        'application_id' => $application->id,
                        'posting_round_id' => $round->id,
                    ]);
                }
                $written++;
            }
        });

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * Rows that publish() would make visible right now (drafts of live applications), and the pool members that
     * "reject remaining" would mark as not selected.
     *
     * @return array{drafts: int, to_reject: int}
     */
    public function publishPreview(PostingRound $round): array
    {
        $drafts = $round->results()
            ->whereNull('published_at')
            ->where('result', '!=', 'pending')
            ->whereHas('application', fn ($q) => $q->where('status', 'applied'))
            ->count();

        $decided = $round->results()->where('result', '!=', 'pending')->pluck('application_id')->all();
        $toReject = $this->pool($round)->filter(fn (Application $a) => ! in_array($a->id, $decided, true))->count();

        return ['drafts' => $drafts, 'to_reject' => $toReject];
    }

    /**
     * Publish every draft row of a round's LIVE applications; optionally mark the rest of the round's pool (including
     * attendance-only "pending" rows) as not selected first. Mails are NOT sent here — call dispatchResultMails().
     *
     * @return array{selected: int, waitlisted: int, rejected: int, published_at: string, row_ids: list<int>}
     */
    public function publish(PostingRound $round, ?User $admin, bool $rejectRemaining): array
    {
        $now = now()->startOfSecond();

        $published = DB::transaction(function () use ($round, $admin, $rejectRemaining, $now) {
            if ($rejectRemaining) {
                $rows = $round->results()->get()->keyBy('application_id');
                foreach ($this->pool($round) as $application) {
                    $row = $rows->get($application->id);
                    if (! $row) {
                        ApplicationRoundResult::create([
                            'application_id' => $application->id,
                            'posting_round_id' => $round->id,
                            'result' => 'rejected',
                            'decided_by' => $admin?->id,
                        ]);
                    } elseif ($row->result === 'pending' && ! $row->isPublished()) {
                        // Attendance-only rows: keep the attendance, decide the result.
                        $row->update(['result' => 'rejected', 'decided_by' => $admin?->id]);
                    }
                }
            }

            $drafts = $round->results()
                ->whereNull('published_at')
                ->where('result', '!=', 'pending')
                ->whereHas('application', fn ($q) => $q->where('status', 'applied'))
                ->lockForUpdate()
                ->get()
                // Someone published as not selected in an earlier round never gets a later result (CR-13).
                ->reject(fn (ApplicationRoundResult $row) => $row->result !== 'rejected' && $this->rejectedEarlier($row->application, $round))
                ->values();

            foreach ($drafts as $row) {
                $row->forceFill(['published_at' => $now])->save();
            }

            $round->update(['status' => 'completed']);

            $next = $round->jobPosting->rounds()->where('sort_order', '>', $round->sort_order)->first();
            if ($next && $next->status === 'pending') {
                $next->update(['status' => 'ongoing']);
            }

            if ($round->jobPosting->status === 'open') {
                $round->jobPosting->update(['status' => 'in_process']);
            }

            return $drafts;
        });

        return [
            'selected' => $published->where('result', 'selected')->count(),
            'waitlisted' => $published->where('result', 'waitlisted')->count(),
            'rejected' => $published->where('result', 'rejected')->count(),
            'published_at' => $now->toDateTimeString(),
            'row_ids' => $published->pluck('id')->all(),
        ];
    }

    /**
     * E4 for exactly the given (published) result rows, in BCC batches from a queued job (spec B6, D89, QA F-008).
     *
     * @param  iterable<int>  $rowIds
     */
    public function dispatchResultMails(PostingRound $round, iterable $rowIds, bool $withNextRound = true, string $kind = 'stage_result'): void
    {
        $ids = collect($rowIds)->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        if ($this->mail->mode() === 'sync') {
            SendRoundResultMails::dispatchSync($round->id, $ids, $withNextRound, $kind);

            return;
        }

        SendRoundResultMails::dispatch($round->id, $ids, $withNextRound, $kind);
    }

    /**
     * "Move to next round" for a waitlisted candidate (D90, QA F-002): the PUBLISHED waitlist row becomes a published
     * "selected" in the SAME round, so the student enters the next round's pool like everyone who cleared this round
     * — nothing is pre-decided in the next round. Returns null when the candidate is not on the published waitlist.
     */
    public function promoteFromWaitlist(PostingRound $round, Application $application, ?User $admin): ?ApplicationRoundResult
    {
        return DB::transaction(function () use ($round, $application, $admin) {
            $row = $round->results()->where('application_id', $application->id)->lockForUpdate()->first();
            if (! $row || $row->result !== 'waitlisted' || ! $row->isPublished() || $application->status !== 'applied') {
                return null;
            }

            $row->update(['result' => 'selected', 'published_at' => now(), 'decided_by' => $admin?->id, 'remark' => 'Promoted from On Hold']);

            return $row;
        });
    }

    /**
     * Take a waitlisted candidate off the waitlist: published → published as not selected, draft → deleted.
     */
    public function removeFromWaitlist(PostingRound $round, Application $application, ?User $admin): ?ApplicationRoundResult
    {
        return DB::transaction(function () use ($round, $application, $admin) {
            $row = $round->results()->where('application_id', $application->id)->lockForUpdate()->first();
            if (! $row || $row->result !== 'waitlisted') {
                return null;
            }

            if ($row->isPublished()) {
                $row->update(['result' => 'rejected', 'published_at' => now(), 'decided_by' => $admin?->id, 'remark' => 'Removed from On Hold']);
            } else {
                $row->delete();
            }

            return $row;
        });
    }

    private function rejectedEarlier(Application $application, PostingRound $round): bool
    {
        return ApplicationRoundResult::query()
            ->where('application_id', $application->id)
            ->where('result', 'rejected')
            ->whereNotNull('published_at')
            ->whereHas('postingRound', fn ($q) => $q->where('job_posting_id', $round->job_posting_id)->where('sort_order', '<', $round->sort_order))
            ->exists();
    }

    /**
     * E4: selected / waitlisted mail, regret mail for the rejected, plus in-app notifications.
     *
     * @param  Collection<int, ApplicationRoundResult>  $rows
     */
    public function notifyResults(JobPosting $posting, PostingRound $round, Collection $rows, ?PostingRound $next, string $kind = 'stage_result'): void
    {
        $company = $posting->company()?->name ?? 'the company';
        $title = $posting->title();
        $batches = []; // "outcome|addendum" => users who get the identical mail

        foreach ($rows as $row) {
            $student = $row->application?->studentProfile;
            $user = $student?->user;
            if (! $user || ($row->result === 'selected' && $row->application?->offer)) {
                continue; // offer holders get E5 instead
            }

            $outcome = $row->result;
            $message = match ($outcome) {
                'selected' => "You have been shortlisted in {$round->name} for {$title} at {$company}.",
                'waitlisted' => "You are On Hold at {$round->name} for {$title} at {$company}.",
                default => "You were not shortlisted after {$round->name} for {$title} at {$company}.",
            };

            $this->notifications->createInAppNotification(
                $user,
                "{$company}: {$round->name} result",
                $message,
                $outcome === 'selected' ? 'success' : ($outcome === 'waitlisted' ? 'info' : 'warning')
            );

            $batches[$outcome.'|'.(int) $row->is_addendum][] = $user;
        }

        foreach ($batches as $key => $users) {
            [$outcome, $addendum] = explode('|', $key);
            $mailable = new RoundResultMail(null, $company, $title, $round->name, $outcome, $next?->name, (bool) $addendum);
            $this->mail->sendBulk($users, $mailable, $mailable->envelope()->subject, 'emails.round-result', ['job_posting_id' => $posting->id, 'kind' => $kind]);
        }
    }
}
