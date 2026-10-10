<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // SEC-008: the Next.js server signs the real client IP with this shared secret (same value as the frontend's
    // INTERNAL_PROXY_SECRET). Empty = forwarded IPs are never trusted.
    'internal_proxy' => [
        'secret' => env('INTERNAL_PROXY_SECRET', ''),
        'max_skew_seconds' => 120,
    ],

    // Recruiter email addresses are checked for DNS records (`email:rfc,dns`). Tests switch this off so they never
    // depend on live DNS (SEC-007 / SEC-010 test seam).
    'recruiter_email' => [
        'dns_check' => (bool) env('RECRUITER_EMAIL_DNS_CHECK', true),
    ],

];
