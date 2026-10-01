<?php

namespace App\Services;

use App\Mail\PortalNoticeMail;
use App\Models\JobPosting;
use App\Models\User;

/**
 * In-app + email notices to a posting's company users (E9, E10 decisions) and to all admins (E10 submissions).
 */
class StakeholderNotifier
{
    public function __construct(
        private readonly MailDispatchService $mail,
        private readonly PortalNotificationService $notifications
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
                'emails.portal-notice'
            );
        }

        return $users->count();
    }

    /**
     * @param  list<string>  $lines
     */
    public function notifyAdmins(string $subject, string $headline, array $lines = [], ?string $path = null): void
    {
        $url = $path ? rtrim((string) config('app.frontend_url'), '/').$path : null;

        foreach (User::query()->where('role', 'admin')->where('is_active', true)->get() as $admin) {
            $this->notifications->createInAppNotification($admin, $subject, $headline, 'info');
            $this->mail->send(
                $admin,
                new PortalNoticeMail($subject, "Hello {$admin->name},", $headline, $lines, $url, 'Review in Admin Portal'),
                $subject,
                'emails.portal-notice'
            );
        }
    }
}
