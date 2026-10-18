<?php

namespace App\Services;

use App\Mail\PortalNoticeMail;
use App\Models\JobPosting;
use App\Models\User;
use App\Support\StaffPermissions;

/**
 * In-app + email notices to a posting's company users (E9, E10 decisions) and to the admins who can act on a company
 * submission (E10): super admins and staff holding the given permission, never every admin.
 */
class StakeholderNotifier
{
    public function __construct(
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications,
        private readonly StaffAccessService $access
    ) {
    }

    /**
     * @param  list<string>  $lines
     */
    public function notifyCompany(JobPosting $posting, string $subject, string $headline, array $lines = [], string $type = 'info'): int
    {
        $company = $posting->company();
        if (! $company) {
            return 0;
        }

        $users = User::query()->where('role', 'company')->where('company_id', $company->id)->where('is_active', true)->get();
        $url = rtrim((string) config('app.frontend_url'), '/')."/company/postings/{$posting->id}";

        foreach ($users as $user) {
            $this->notifications->createInAppNotification($user, $subject, $headline, $type);
            $this->mail->send(
                $user,
                new PortalNoticeMail($subject, "Hello {$user->name},", $headline, $lines, $url, 'Open in Company Portal'),
                $subject,
                'emails.portal-notice',
                ['job_posting_id' => $posting->id, 'kind' => 'company_notice']
            );
        }

        return $users->count();
    }

    /**
     * @param  list<string>  $lines
     * @param  array{job_posting_id?: int|null, kind?: string|null}  $context  email_logs context (Communication Log, fix L19)
     * @param  string|list<string>  $permission  who is told: super admins plus staff with this permission at `$level`
     */
    public function notifyAdmins(string $subject, string $headline, array $lines = [], ?string $path = null, array $context = [], string|array $permission = StaffPermissions::ANY, string $level = 'read'): void
    {
        $url = $path ? rtrim((string) config('app.frontend_url'), '/').$path : null;

        foreach ($this->access->recipients($permission, $level) as $admin) {
            $this->notifications->createInAppNotification($admin, $subject, $headline, 'info');
            $this->mail->send(
                $admin,
                new PortalNoticeMail($subject, "Hello {$admin->name},", $headline, $lines, $url, 'Review in Admin Portal'),
                $subject,
                'emails.portal-notice',
                $context
            );
        }
    }
}
