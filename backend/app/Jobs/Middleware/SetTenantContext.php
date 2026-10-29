<?php

namespace App\Jobs\Middleware;

use App\Support\TenantContext;

final class SetTenantContext
{
    public function __construct(private readonly string $tenantId) {}

    public function handle(object $job, object $next): void
    {
        $context = app(TenantContext::class);
        try { $context->set($this->tenantId); $next($job); } finally { $context->clear(); }
    }
}
