<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\SetTenantContext;

trait HasTenantContext
{
    public function __construct(public readonly string $tenantId) {}
    public function middleware(): array { return [new SetTenantContext($this->tenantId)]; }
}
