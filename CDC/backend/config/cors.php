<?php

$configuredOrigins = array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URLS', ''))
));

$frontendOrigins = array_values(array_unique(array_filter([
    (string) env('FRONTEND_URL', 'http://127.0.0.1:3000'),
    'http://127.0.0.1:3000',
    'http://localhost:3000',
    ...$configuredOrigins,
], static fn ($origin): bool => $origin !== '')));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $frontendOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // Lets the browser read the export file names (D88).
    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    'supports_credentials' => true,

];
