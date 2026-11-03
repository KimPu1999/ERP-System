<?php

return ['stateful' => array_values(array_filter(array_map('trim', explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost:5173,127.0.0.1:5173'))))), 'guard' => ['web'], 'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 43200), 'middleware' => ['authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class, 'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class, 'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class, 'verify_session' => Illuminate\Session\Middleware\StartSession::class]];
