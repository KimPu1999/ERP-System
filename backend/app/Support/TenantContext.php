<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class TenantContext
{
    private ?string $tenantId = null;

    public function set(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantId]);
        }
    }

    public function id(): ?string { return $this->tenantId; }

    public function setPlatformAdmin(bool $enabled): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.platform_admin', ?, false)", [$enabled ? 'true' : 'false']);
        }
    }

    public function clear(): void
    {
        $this->tenantId = null;
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.tenant_id', '', false)");
            DB::statement("SELECT set_config('app.platform_admin', 'false', false)");
        }
    }
}
