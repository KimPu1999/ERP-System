<?php

it('fails closed when unsafe production configuration is detected', function (): void {
    $original = [
        'app.env' => config('app.env'),
        'app.debug' => config('app.debug'),
        'mail.default' => config('mail.default'),
        'session.secure' => config('session.secure'),
        'cors.allowed_origins.0' => config('cors.allowed_origins.0'),
    ];

    config([
        'app.env' => 'production',
        'app.debug' => true,
        'mail.default' => 'log',
        'session.secure' => false,
        'cors.allowed_origins.0' => 'http://localhost:5173',
    ]);

    try {
        $this->get('/api/v1/health')->assertServiceUnavailable();
    } finally {
        config($original);
    }
});
