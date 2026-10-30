<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\ResolveSupplierPortalTenant;
use App\Http\Middleware\RequirePlatformSuperAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateProductionConfiguration;
use App\Support\TenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->append(SetLocale::class);
        $middleware->append(ValidateProductionConfiguration::class);
        $middleware->statefulApi();
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : route('login'));
        $middleware->alias(['tenant' => ResolveTenant::class, 'supplier.portal' => ResolveSupplierPortalTenant::class, 'platform.admin' => RequirePlatformSuperAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Keep Laravel's default exception handler binding and rendering pipeline.
    })
    ->withSingletons([TenantContext::class => fn (): TenantContext => new TenantContext()])
    ->create();
