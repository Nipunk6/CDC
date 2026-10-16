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
        // Staff access answers are cached for one request only, so role changes apply on the next request.
        $this->app->scoped(\App\Services\StaffAccessService::class);
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

        // A signed-in user gets 300 requests a minute of their own (an admin page makes several calls at once and
        // 60 a minute stopped normal work); a guest stays at 60 a minute per address (owner decision 2026-10-12).
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user();

            return $user
                ? Limit::perMinute(300)->by('user:'.$user->id)
                : Limit::perMinute(60)->by('ip:'.$request->ip());
        });

        // Login's own per-IP budget (SEC-008 / QA N-1): a campus shares a few NAT addresses, so login is not in the
        // 60/min `api` bucket. The IP is the signed client IP forwarded by the Next.js server (TrustSignedClientIp).
        // Guessing at one account is slowed by LoginThrottleService's short per-account backoff (no hard lockout).
        RateLimiter::for('login-ip', function (Request $request) {
            return Limit::perMinute(600)->by('login-ip:'.$request->ip());
        });

        // Signed resume links: admin previews all arrive from the Next.js server's IP, and exported
        // spreadsheets open many links at once, so they get their own, larger bucket (D60).
        RateLimiter::for('signed-files', function (Request $request) {
            return Limit::perMinute(600)->by($request->ip());
        });
    }
}
