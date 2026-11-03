<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveSupplierPortalTenant;
use App\Http\Middleware\RequirePlatformSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureRecentStepUp;
use App\Http\Middleware\ValidateProductionConfiguration;
use App\Support\TenantContext;
use App\Services\MonitoringService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map(trim(...), explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies);
        }
        $middleware->append(SecurityHeaders::class);
        $middleware->append(SetLocale::class);
        $middleware->append(ValidateProductionConfiguration::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : route('login'));
        $middleware->alias(['active.user' => EnsureActiveUser::class, 'recent.stepup' => EnsureRecentStepUp::class, 'tenant' => ResolveTenant::class, 'supplier.portal' => ResolveSupplierPortalTenant::class, 'platform.admin' => RequirePlatformSuperAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\Throwable $exception): void {
            if (app()->bound(MonitoringService::class)) {
                app(MonitoringService::class)->recordException($exception, app()->runningInConsole() ? null : request());
            }
        });
    })
    ->withSingletons([
        TenantContext::class => fn (): TenantContext => new TenantContext(),
        MonitoringService::class => fn (\Illuminate\Container\Container $container): MonitoringService => new MonitoringService($container->make(\App\Services\AuditLogger::class)),
    ])
    ->create();
