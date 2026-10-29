<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('erp:status', function (): void {
    $this->info('Enterprise ERP Cloud API foundation is configured.');
});

Artisan::command('erp:health', function (): void {
    $this->call('about');
    $this->info('Use GET /api/v1/health for dependency checks.');
});

Artisan::command('erp:release-check', function (): int {
    $checks = [
        'production environment' => app()->environment('production'),
        'debug disabled' => ! (bool) config('app.debug'),
        'application key configured' => filled(config('app.key')),
        'encrypted sessions' => (bool) config('session.encrypt'),
        'secure session cookies' => (bool) config('session.secure'),
        'database TLS configured' => in_array(config('database.connections.pgsql.sslmode'), ['require', 'verify-ca', 'verify-full'], true),
    ];
    foreach ($checks as $name => $passed) {
        $passed ? $this->info("PASS: {$name}") : $this->error("FAIL: {$name}");
    }
    return in_array(false, $checks, true) ? 1 : 0;
});
