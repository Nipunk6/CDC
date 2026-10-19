<?php

namespace App\Services;

use App\Mail\SpocAssignmentMail;
use App\Models\Company;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\JobPosting;
use App\Models\JobPostingSpoc;
use App\Models\ScptMember;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

/**
 * P10.2 SPOC rules in one place (owner decisions 2026-10-19, P3-D50):
 * - a SPOC is an SCPT member of the job profile's placement cycle; a job profile may have several;
 * - each SPOC gets a mail (and an in-app notice) when made SPOC, when removed, and whenever the company's HR contact
 *   changes while the job profile is open or in process;
 * - students who can see the job profile see each SPOC's name, roll number, phone and email; only the SPOC sees HR
 *   details (company recruiter contacts are otherwise never shown to students, PostingPresenter).
 */
class SpocService
{
    /** Job profile statuses whose SPOCs are told about an HR contact change. */
    public const ACTIVE_STATUSES = ['open', 'in_process'];

    public function __construct(
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
    ) {
    }

    /** @return array{name: ?string, designation: ?string, email: ?string, phone: ?string, alt_phone: ?string} */
    public static function hrDetails(?Company $company): array
    {
        return [
            'name' => $company?->hr_name,
            'designation' => $company?->hr_designation,
            'email' => $company?->hr_email,
            'phone' => $company?->hr_phone,
            'alt_phone' => $company?->hr_alt_phone,
        ];
    }

    /**
     * What a student viewing the job profile sees of its SPOCs.
     *
     * @return list<array{name: ?string, roll_no: string, phone: ?string, email: ?string}>
     */
    public static function studentFacing(JobPosting $posting): array
    {
        return $posting->spocs()->with('scptMember.studentProfile:id,roll_no,full_name,phone,institute_email')->get()
            ->map(fn (JobPostingSpoc $spoc) => $spoc->scptMember?->studentProfile)
            ->filter()
            ->map(fn (StudentProfile $s) => ['name' => $s->full_name, 'roll_no' => $s->roll_no, 'phone' => $s->phone, 'email' => $s->institute_email])
            ->values()
            ->all();
    }

    /**
     * What the company sees of its job profile's SPOCs (owner decision 2026-10-19, P3-D52): name, phone and email only —
     * never the roll number or any academic data.
     *
     * @return list<array{name: ?string, phone: ?string, email: ?string}>
     */
    public static function companyFacing(JobPosting $posting): array
    {
        return array_map(fn (array $s) => ['name' => $s['name'], 'phone' => $s['phone'], 'email' => $s['email']], self::studentFacing($posting));
    }

    public function notifyAssigned(JobPostingSpoc $spoc): void
    {
        $this->notify($spoc->scptMember, $spoc->jobPosting, 'assigned');
    }

    public function notifyRemoved(ScptMember $member, JobPosting $posting): void
    {
        $this->notify($member, $posting, 'removed');
    }

    /**
     * After a company's HR fields changed: tell every SPOC of its open / in-process job profiles. Returns how many
     * SPOCs were told.
     */
    public function notifyHrChanged(Company $company): int
    {
        $spocs = $this->activeSpocsOf($company);
        foreach ($spocs as $spoc) {
            $this->notify($spoc->scptMember, $spoc->jobPosting, 'hr_updated');
        }

        return $spocs->count();
    }

    /** The HR fields whose change triggers the `hr_updated` mail. */
    public static function hrChanged(array $before, Company $company): bool
    {
        foreach (array_keys(self::hrDetails(null)) as $key) {
            $column = $key === 'alt_phone' ? 'hr_alt_phone' : 'hr_'.$key;
            if (array_key_exists($column, $before) && (string) $before[$column] !== (string) $company->{$column}) {
                return true;
            }
        }

        return false;
    }

    /** @return Collection<int, JobPostingSpoc> */
    private function activeSpocsOf(Company $company): Collection
    {
        return JobPostingSpoc::query()
            ->whereHas('jobPosting', fn ($q) => $q->whereIn('status', self::ACTIVE_STATUSES)
                ->whereHasMorph('postable', [Jnf::class, Inf::class], fn ($f) => $f->where('company_id', $company->id)))
            ->with(['scptMember.studentProfile.user', 'jobPosting.postable.company', 'jobPosting.placementCycle'])
            ->get();
    }

    /** @param  'assigned'|'removed'|'hr_updated'  $kind */
    private function notify(?ScptMember $member, ?JobPosting $posting, string $kind): void
    {
        $student = $member?->studentProfile;
        $user = $student?->user;
        if (! $user || ! $posting) {
            return;
        }

        $company = $posting->company();
        $companyName = $company?->name ?? (string) ($posting->formData()['companyProfile']['name'] ?? 'the company');
        $role = $posting->title();
        $subject = match ($kind) {
            'assigned' => "You are SPOC for {$companyName}: {$role}",
            'hr_updated' => "HR contact updated: {$companyName} ({$role})",
            default => "You are no longer SPOC for {$companyName}: {$role}",
        };

        $this->notifications->createInAppNotification($user, $subject, match ($kind) {
            'assigned' => "The CDC selected you as SPOC for {$companyName} ({$role}). The HR details are on your SPOC page.",
            'hr_updated' => "The HR contact of {$companyName} changed. See the current details on your SPOC page.",
            default => "You are no longer SPOC for {$companyName} ({$role}).",
        }, $kind === 'removed' ? 'warning' : 'info', ['category' => 'spoc', 'job_posting_id' => $posting->id, 'link' => '/student/spoc']);

        $deadline = $posting->application_deadline?->timezone('Asia/Kolkata')->format('d M Y, h:i A');
        $this->mail->send(
            $user,
            new SpocAssignmentMail(
                $kind,
                (string) ($student->full_name ?: $user->name),
                $companyName,
                $role,
                (string) ($posting->placementCycle?->name ?? ''),
                $deadline ? "{$deadline} IST" : null,
                $kind === 'removed' ? [] : self::hrDetails($company),
                $subject
            ),
            $subject,
            'emails.spoc-assignment',
            ['job_posting_id' => $posting->id, 'kind' => 'spoc_'.$kind]
        );
    }
}
