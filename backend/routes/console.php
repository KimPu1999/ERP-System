<?php

use App\Jobs\RunAutomationRule;
use App\Models\AutomationRule;
use Illuminate\Support\Facades\{Artisan, Schedule};

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

Artisan::command('erp:purchasing-automation', function (): int {
    $now = now()->utc()->format('Y-m-d-H');
    AutomationRule::where('is_active', true)->orderBy('id')->chunkById(100, function ($rules) use ($now): void {
        foreach ($rules as $rule) RunAutomationRule::dispatch($rule->tenant_id, $rule->id, 'purchasing:'.$rule->id.':'.$now);
    });
    $this->info('Dispatched bounded tenant-scoped Purchasing automation batches.');
    return 0;
})->purpose('Dispatch safe non-financial Purchasing automation rules');

Schedule::command('erp:purchasing-automation')->hourly()->withoutOverlapping(30);
