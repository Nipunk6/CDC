<?php

namespace App\Services;

use App\Models\JobPosting;
use App\Models\Notice;
use Illuminate\Support\Facades\DB;

/**
 * Owner decision 2026-10-18: every job profile opened for applications appears on the student Notices page for ALL
 * students, whatever their programme, branch or batch (eligibility still decides who can apply). One automatic notice
 * per job profile, marked `source = job_profile`; it is never emailed (eligible students already get the E2 mail).
 *
 * The notice reads like Superset's "Open for applications" card: applicable courses, eligibility, hiring stages and
 * the deadline. The body is plain text (notices never render HTML, D76); details() gives the same content as data so
 * the student page can lay it out with headings and numbered lists.
 */
class JobProfileNoticeService
{
    public const SOURCE = 'job_profile';

    public const DEFAULT_INSTITUTE = 'Indian Institute of Technology (Indian School of Mines) Dhanbad';

    public function __construct(private readonly SettingsService $settings)
    {
    }

    /** The job profile has just opened for applications: publish (or re-publish) its notice. */
    public function publish(JobPosting $posting): Notice
    {
        return DB::transaction(function () use ($posting) {
            $notice = $this->find($posting) ?? new Notice(['source' => self::SOURCE, 'job_posting_id' => $posting->id]);
            $notice->fill($this->content($posting) + ['created_by' => $posting->floated_by]);
            $notice->published_at ??= now();
            $notice->save();

            if (! $notice->audiences()->where('audience_type', 'all')->exists()) {
                $notice->audiences()->create(['audience_type' => 'all', 'audience_filter' => null]);
            }

            return $notice;
        });
    }

    /**
     * Keep an existing notice in step with its job profile: details refreshed, hidden once cancelled. A job profile that
     * never opened has no notice and gets none here.
     */
    public function sync(JobPosting $posting): void
    {
        $notice = $this->find($posting);
        if (! $notice) {
            return;
        }

        if ($posting->status === 'cancelled') {
            $notice->update(['published_at' => null]);

            return;
        }

        $this->publish($posting);
    }

    public function find(JobPosting $posting): ?Notice
    {
        return Notice::query()->where('source', self::SOURCE)->where('job_posting_id', $posting->id)->first();
    }

    /**
     * The card's content as data (for the student Notices page).
     *
     * @return array{company: string, logo_url: ?string, title: string, institute: string, courses: list<string>, eligibility: list<array{label: string, points: list<string>}>, stages: list<string>, deadline: ?string}
     */
    public function details(JobPosting $posting): array
    {
        $posting->loadMissing(['postable.company', 'rounds']);
        $rules = $posting->eligibilityRules();

        $courses = [];
        $eligibility = [];
        foreach (is_array($rules['eligibility'] ?? null) ? $rules['eligibility'] : [] as $programme) {
            if (! is_array($programme)) {
                continue;
            }
            $selected = array_values(array_filter((array) ($programme['branches'] ?? []), fn ($b) => is_array($b) && ! empty($b['selected'])));
            if ($selected === []) {
                continue;
            }
            $short = self::shortProgramme((string) ($programme['programme'] ?? ''));
            foreach ($selected as $branch) {
                $courses[] = $short.' - '.$branch['branch'];
            }

            $cutoffs = collect($selected)->map(fn ($b) => is_numeric($b['cgpa'] ?? null) ? (float) $b['cgpa'] : 0.0)->unique()->values();
            $points = match (true) {
                $cutoffs->max() <= 0 => ['All students are eligible'],
                $cutoffs->count() === 1 => [sprintf('CGPA %s and above', self::number($cutoffs->first()))],
                default => collect($selected)->map(fn ($b) => sprintf('%s: CGPA %s and above', $b['branch'], self::number((float) ($b['cgpa'] ?? 0))))->all(),
            };
            $points[] = self::backlogRule($selected);
            $eligibility[] = ['label' => $short, 'points' => array_values(array_filter($points))];
        }

        $general = array_values(array_filter([
            ($gender = (string) ($rules['genderFilter'] ?? 'all')) !== 'all' && $gender !== '' ? 'Open to '.$gender.' students only' : null,
            is_numeric($rules['minTenthPercent'] ?? null) ? 'Class X: '.self::number((float) $rules['minTenthPercent']).'% and above' : null,
            is_numeric($rules['minTwelfthPercent'] ?? null) ? 'Class XII: '.self::number((float) $rules['minTwelfthPercent']).'% and above' : null,
        ]));
        if ($general !== []) {
            $eligibility[] = ['label' => 'Also required', 'points' => $general];
        }

        return [
            'company' => $posting->company()?->name ?? 'A company',
            'logo_url' => $posting->company()?->logo_url,
            'title' => $posting->title(),
            'institute' => $this->settings->get('institute_name') ?: self::DEFAULT_INSTITUTE,
            'courses' => $courses,
            'eligibility' => $eligibility,
            'stages' => $posting->rounds->sortBy('sort_order')->pluck('name')->values()->all(),
            'deadline' => $posting->application_deadline?->timezone('Asia/Kolkata')->format('F j, Y, h:i A').' IST',
        ];
    }

    /** @return array{title: string, body: string} */
    private function content(JobPosting $posting): array
    {
        $d = $this->details($posting);
        $numbered = fn (array $items) => array_map(fn ($item, $i) => ($i + 1).'. '.$item, $items, array_keys($items));

        $lines = [
            sprintf("Applications are now being accepted for %s's Job Profile: %s.", $d['company'], $d['title']),
            '',
            'Eligible students will be able to find this job profile under JOB PROFILES options, and apply.',
            '',
            'Applicable Courses',
            $d['institute'],
            ...$numbered($d['courses']),
            '',
            'Eligibility',
        ];
        foreach ($d['eligibility'] as $i => $group) {
            $lines[] = ($i + 1).'. '.$group['label'];
            foreach ($group['points'] as $point) {
                $lines[] = '   • '.$point;
            }
        }
        if ($d['stages'] !== []) {
            array_push($lines, '', 'Hiring Process - Stages', ...$numbered($d['stages']));
        }
        if ($d['deadline']) {
            array_push($lines, '', 'Deadline for applications is '.$d['deadline'].'.');
        }

        return [
            'title' => mb_substr("Open for applications - {$d['company']}'s Job Profile - {$d['title']}", 0, 255),
            'body' => implode("\n", $lines),
        ];
    }

    /** "B.Tech (4 Year) / B.Tech Double Major (5 Year) / …" → "B.Tech"; "M.Tech (2 Year) - GATE" → "M.Tech". */
    private static function shortProgramme(string $programme): string
    {
        $short = trim((string) preg_split('/\s*[(\/]/', $programme)[0]);

        return $short !== '' ? $short : $programme;
    }

    /** @param  list<array<string, mixed>>  $branches */
    private static function backlogRule(array $branches): string
    {
        $first = $branches[0];
        if (empty($first['backlogsAllowed'])) {
            return 'No backlogs';
        }
        $ongoing = $first['maxOngoingBacklogs'] ?? '';
        $total = $first['maxTotalBacklogs'] ?? '';
        if ((string) $ongoing === '0' && (string) $total === '') {
            return 'No active backlogs';
        }

        return trim(implode(', ', array_filter([
            $ongoing !== '' ? 'Up to '.$ongoing.' active backlog(s)' : null,
            $total !== '' ? 'up to '.$total.' total backlog(s)' : null,
        ]))) ?: 'Backlogs allowed';
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
