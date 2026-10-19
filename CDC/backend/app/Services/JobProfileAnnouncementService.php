<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Support\ProgrammeCatalogue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Owner request 2026-10-19: how a job profile is announced when it opens for applications. Two independent channels,
 * each with its own target audience and text:
 *  - notice: the "<company>'s <role> is open for applications" card on the student Notices page (JobProfileNoticeService);
 *  - email:  the opening mail (SendPostingFloatedMails), still sent to a student at most once per job profile.
 *
 * Audience types: eligible (students eligible for this job profile), all (every active student), branches (picked
 * programme + branch pairs) and programmes (whole courses). Stored on job_postings.announcement; a job profile without
 * one keeps the earlier rule (notice to every student, email to eligible students).
 */
class JobProfileAnnouncementService
{
    public const AUDIENCES = ['eligible', 'all', 'branches', 'programmes'];

    public function __construct(private readonly AudienceService $audiences, private readonly EligibilityService $eligibility)
    {
    }

    /**
     * The settings in force for this job profile (stored, or the earlier defaults).
     *
     * @return array{notice: array{enabled: bool, audience: array<string, mixed>, title: ?string, message: ?string}, email: array{enabled: bool, audience: array<string, mixed>, subject: ?string, message: ?string}}
     */
    public function settings(JobPosting $posting): array
    {
        $stored = is_array($posting->announcement) ? $posting->announcement : [];

        return [
            'notice' => ($stored['notice'] ?? null) ?: ['enabled' => true, 'audience' => ['type' => 'all'], 'title' => null, 'message' => null],
            'email' => ($stored['email'] ?? null) ?: ['enabled' => true, 'audience' => ['type' => 'eligible'], 'subject' => null, 'message' => null],
        ];
    }

    /**
     * Validate an announcement from the admin screens. Throws a 422 ValidationException on bad input.
     *
     * @return array{notice: array<string, mixed>, email: array<string, mixed>}
     */
    public function normalise(mixed $input): array
    {
        Validator::make(['announcement' => $input], [
            'announcement' => ['required', 'array'],
            'announcement.notice' => ['required', 'array'],
            'announcement.notice.enabled' => ['required', 'boolean'],
            'announcement.notice.title' => ['nullable', 'string', 'max:255'],
            'announcement.notice.message' => ['nullable', 'string', 'max:5000'],
            'announcement.email' => ['required', 'array'],
            'announcement.email.enabled' => ['required', 'boolean'],
            'announcement.email.subject' => ['nullable', 'string', 'max:255'],
            'announcement.email.message' => ['nullable', 'string', 'max:5000'],
            'announcement.*.audience' => ['required', 'array'],
            'announcement.*.audience.type' => ['required', 'in:'.implode(',', self::AUDIENCES)],
        ], [], [
            'announcement.notice.title' => 'notice headline',
            'announcement.email.subject' => 'email subject',
        ])->validate();

        $text = fn ($value) => is_string($value) && trim($value) !== '' ? trim($value) : null;

        return [
            'notice' => [
                'enabled' => (bool) $input['notice']['enabled'],
                'audience' => $this->audience($input['notice']['audience'], 'notice'),
                'title' => $text($input['notice']['title'] ?? null),
                'message' => $text($input['notice']['message'] ?? null),
            ],
            'email' => [
                'enabled' => (bool) $input['email']['enabled'],
                'audience' => $this->audience($input['email']['audience'], 'email'),
                'subject' => $text($input['email']['subject'] ?? null),
                'message' => $text($input['email']['message'] ?? null),
            ],
        ];
    }

    /**
     * Audience groups (AudienceService format) for a stored audience.
     *
     * @param  array<string, mixed>  $audience
     * @return list<array{audience_type: string, audience_filter: array<string, mixed>|null}>
     */
    public function groups(JobPosting $posting, array $audience): array
    {
        return match ($audience['type'] ?? 'eligible') {
            'all' => [['audience_type' => 'all', 'audience_filter' => null]],
            'branches' => [['audience_type' => 'branches', 'audience_filter' => ['branches' => array_values($audience['branches'] ?? [])]]],
            'programmes' => [['audience_type' => 'branches', 'audience_filter' => ['branches' => array_map(fn ($p) => ['programme' => $p, 'branch' => null], array_values($audience['programmes'] ?? []))]]],
            default => [['audience_type' => 'posting_eligible', 'audience_filter' => ['job_posting_id' => $posting->id]]],
        };
    }

    /**
     * Active students in the audience. "Eligible" works for an unsaved job profile too (the float dialog's preview).
     *
     * @param  array<string, mixed>  $audience
     */
    public function query(JobPosting $posting, array $audience): Builder
    {
        return ($audience['type'] ?? 'eligible') === 'eligible'
            ? $this->eligibility->eligibleStudentsQuery($posting)
            : $this->audiences->query($this->groups($posting, $audience));
    }

    /** @param  array<string, mixed>  $audience */
    public function describe(array $audience): string
    {
        return match ($audience['type'] ?? 'eligible') {
            'all' => 'all students',
            'branches' => 'students of '.collect($audience['branches'] ?? [])->map(fn ($b) => $b['branch'])->implode(', '),
            'programmes' => 'students of '.collect($audience['programmes'] ?? [])->map(fn ($p) => ProgrammeCatalogue::displayName($p))->implode(', '),
            default => 'eligible students',
        };
    }

    /**
     * One sentence for the admin's success message, e.g. "The notice is posted for all students and eligible students
     * are being emailed."
     */
    public function summary(JobPosting $posting, bool $scheduled = false): string
    {
        $s = $this->settings($posting);
        $parts = [];
        if ($s['notice']['enabled']) {
            $parts[] = ($scheduled ? 'the notice will be posted for ' : 'the notice is posted for ').$this->describe($s['notice']['audience']);
        }
        if ($s['email']['enabled']) {
            $parts[] = ($scheduled ? $this->describe($s['email']['audience']).' will be emailed' : $this->describe($s['email']['audience']).' are being emailed');
        }
        if ($parts === []) {
            return 'No notice or email was sent.';
        }

        return ucfirst(implode(' and ', $parts)).'.';
    }

    /**
     * @param  array<string, mixed>  $audience
     * @return array<string, mixed>
     */
    private function audience(array $audience, string $channel): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(["announcement.{$channel}.audience" => $message]);

        return match ($audience['type']) {
            'branches' => (function () use ($audience, $fail) {
                $pairs = [];
                foreach ((array) ($audience['branches'] ?? []) as $pair) {
                    $programme = (string) ($pair['programme'] ?? '');
                    $branch = (string) ($pair['branch'] ?? '');
                    if (! ProgrammeCatalogue::has($programme, $branch)) {
                        $fail(sprintf('"%s" is not in the programme catalogue.', $branch ?: $programme));
                    }
                    $pairs[$programme."\0".$branch] = ['programme' => $programme, 'branch' => $branch];
                }

                return $pairs === [] ? $fail('Pick at least one branch.') : ['type' => 'branches', 'branches' => array_values($pairs)];
            })(),
            'programmes' => (function () use ($audience, $fail) {
                $programmes = array_values(array_unique(array_map('strval', (array) ($audience['programmes'] ?? []))));
                foreach ($programmes as $programme) {
                    if (! ProgrammeCatalogue::hasProgramme($programme)) {
                        $fail(sprintf('"%s" is not in the programme catalogue.', $programme));
                    }
                }

                return $programmes === [] ? $fail('Pick at least one course.') : ['type' => 'programmes', 'programmes' => $programmes];
            })(),
            default => ['type' => $audience['type']],
        };
    }
}
