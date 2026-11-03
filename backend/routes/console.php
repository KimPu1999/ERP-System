<?php

use App\Jobs\{EvaluateSupplierAutomationRule, RunAutomationRule};
use App\Models\{AutomationRule, PlatformAuditLog, User};
use App\Services\MonitoringService;
use Illuminate\Support\Facades\{Artisan, Cache, Schedule};

Artisan::command('erp:status', function (): void {
    $this->info('Enterprise ERP Cloud API foundation is configured.');
});

Artisan::command('platform:provision-admin {email} {--yes : Confirm the platform-admin promotion without an interactive prompt}', function (string $email): int {
    $email = strtolower(trim($email));
    $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

    if (! $user) {
        $this->error('No existing user was found for that email address. Register the user first.');
        return 1;
    }

    if ($user->status !== 'active' || $user->suspended_at) {
        $this->error('The user must be active and not suspended before platform access can be granted.');
        return 1;
    }

    if (! $user->hasVerifiedEmail()) {
        $this->error('The user email must be verified before platform access can be granted.');
        return 1;
    }

    if ($user->is_platform_super_admin === true) {
        $this->info('This user is already a Platform Super Admin.');
        return 0;
    }

    if (! $this->option('yes') && ! $this->confirm("Grant Platform Control Center access to {$user->email}?")) {
        $this->warn('No changes were made.');
        return 1;
    }

    $user->forceFill(['is_platform_super_admin' => true])->save();

    PlatformAuditLog::create([
        'actor_user_id' => null,
        'tenant_id' => null,
        'action' => 'platform.admin.provisioned',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'metadata' => ['source' => 'artisan', 'target_email' => $user->email],
        'ip_address' => null,
    ]);

    $this->info("Platform Control Center access granted to {$user->email}.");
    $this->line('The user must sign in at /platform/login.');
    return 0;
})->purpose('Provision an existing verified user as a Platform Super Admin');

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
    Cache::put('erp:purchasing-automation:last_run', now()->toIso8601String(), now()->addHours(2));
    $now = now()->utc()->format('Y-m-d-H');
    AutomationRule::where('is_active', true)->orderBy('id')->chunkById(100, function ($rules) use ($now): void {
        foreach ($rules as $rule) {
            if (in_array($rule->event_key, AutomationRule::SUPPLIER_ALLOWLIST, true)) {
                $period = match ($rule->frequency) {
                    'once' => 'once',
                    'daily' => now()->utc()->format('Y-m-d'),
                    'weekly' => now()->utc()->format('o-W'),
                    'monthly' => now()->utc()->format('Y-m'),
                    default => $now,
                };
                EvaluateSupplierAutomationRule::dispatch($rule->tenant_id, $rule->id, $period);
            } else {
                RunAutomationRule::dispatch($rule->tenant_id, $rule->id, 'purchasing:'.$rule->id.':'.$now);
            }
        }
    });
    $this->info('Dispatched bounded tenant-scoped Purchasing automation batches. Supplier notifications remain non-financial.');
    return 0;
})->purpose('Dispatch safe non-financial Purchasing automation rules');

Schedule::command('erp:purchasing-automation')->hourly()->withoutOverlapping(30);

Artisan::command('platform:monitor', function (MonitoringService $monitoring): int {
    $result = $monitoring->collect();
    $this->info('Platform monitoring collector completed: '.($result['status'] ?? 'unknown'));
    return 0;
})->purpose('Collect bounded platform operational health observations');

Schedule::command('platform:monitor')->everyMinute()->withoutOverlapping(1);
