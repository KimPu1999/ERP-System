<?php

return [
    'name' => env('APP_NAME', 'Enterprise ERP Cloud'),
    'env' => env('APP_ENV', 'production'),
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    'timezone' => 'UTC',
    'locale' => env('APP_LOCALE', 'en'),
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    // Laravel's framework providers are required for cache, filesystem,
    // database, sessions, queues, validation, and the HTTP kernel.
    'providers' => (new Illuminate\Support\DefaultProviders)->toArray(),
];
