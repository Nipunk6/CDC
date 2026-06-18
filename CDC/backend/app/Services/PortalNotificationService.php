<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class PortalNotificationService
{
    public function createInAppNotification(User $user, string $title, string $message, string $type = 'info'): void
    {
        PortalNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }

    public function sendLoggedEmail(User $user, Mailable $mailable, string $subject, string $template): void
    {
        try {
            Mail::to($user->email)->send($mailable);

            EmailLog::create([
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'subject' => $subject,
                'template' => $template,
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            EmailLog::create([
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'subject' => $subject,
                'template' => $template,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
