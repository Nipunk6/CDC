<?php

namespace App\Providers;

use App\Services\MailDispatchService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
            $email = urlencode((string) $notifiable->getEmailForPasswordReset());

            return "{$frontendUrl}/auth/reset-password?token={$token}&email={$email}";
        });

        // Queued mails update their email_logs rows once the worker sends them or gives up (QA F-015).
        Event::listen(MessageSent::class, [MailDispatchService::class, 'markSent']);
        Event::listen(JobFailed::class, [MailDispatchService::class, 'markFailed']);

        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id ?? $request->ip();

            return Limit::perMinute(60)->by((string) $key);
        });

        // Per-account login budget (QA F-016): guessing one roll number / email is slowed down whatever the source
        // IP. The per-IP `api` bucket still applies on top.
        RateLimiter::for('login', function (Request $request) {
            $account = strtolower(trim((string) ($request->input('roll_no') ?: $request->input('email'))));

            return Limit::perMinute(10)->by('login:'.$account);
        });

        // Signed resume links: admin previews all arrive from the Next.js server's IP, and exported
        // spreadsheets open many links at once, so they get their own, larger bucket (D60).
        RateLimiter::for('signed-files', function (Request $request) {
            return Limit::perMinute(600)->by($request->ip());
        });
    }
}
