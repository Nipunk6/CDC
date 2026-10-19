<?php

namespace App\Services;

use App\Models\EmailLog;
use App\Models\PortalNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

class PortalNotificationService
{
    /**
     * @param  array{category?: string|null, job_posting_id?: int|null, link?: string|null, details?: array<string, mixed>|null}  $context
     *                                                                                                                                  what the notification is about, for the student notification center
     */
    public function createInAppNotification(User $user, string $title, string $message, string $type = 'info', array $context = []): void
    {
        PortalNotification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ] + array_intersect_key($context, array_flip(['category', 'job_posting_id', 'link', 'details'])));
    }

    /**
     * The same notification for every active student in the query, inserted in chunks. With $once, a student who already
     * has a notification of that category for the job profile in $context is skipped, so it is never repeated.
     *
     * @param  array{category?: string|null, job_posting_id?: int|null, link?: string|null, details?: array<string, mixed>|null}  $context
     * @return int students notified
     */
    public function notifyStudents(Builder $students, string $title, string $message, string $type = 'info', array $context = [], bool $once = false): int
    {
        $query = (clone $students)->whereHas('user', fn (Builder $u) => $u->where('is_active', true));
        if ($once && isset($context['category'], $context['job_posting_id'])) {
            $query->whereNotIn('student_profiles.user_id', PortalNotification::query()
                ->where('job_posting_id', $context['job_posting_id'])
                ->where('category', $context['category'])
                ->select('user_id'));
        }

        $row = [
            'title' => mb_substr($title, 0, 255),
            'message' => $message,
            'type' => $type,
            'category' => $context['category'] ?? null,
            'job_posting_id' => $context['job_posting_id'] ?? null,
            'link' => $context['link'] ?? null,
            'details' => isset($context['details']) ? json_encode($context['details']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $count = 0;
        $query->select('student_profiles.id', 'student_profiles.user_id')->chunkById(500, function ($chunk) use ($row, &$count): void {
            $userIds = $chunk->pluck('user_id')->filter()->unique()->values();
            PortalNotification::query()->insert($userIds->map(fn ($id) => $row + ['user_id' => $id])->all());
            $count += $userIds->count();
        }, 'student_profiles.id', 'id');

        return $count;
    }

    /**
     * @param  array{job_posting_id?: int|null, kind?: string|null}  $context  extra email_logs columns (Communication Log)
     */
    public function sendLoggedEmail(User $user, Mailable $mailable, string $subject, string $template, array $context = []): void
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
            ] + $context);
        } catch (\Throwable $exception) {
            EmailLog::create([
                'user_id' => $user->id,
                'recipient_email' => $user->email,
                'subject' => $subject,
                'template' => $template,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ] + $context);
        }
    }
}
