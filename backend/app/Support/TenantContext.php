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

    /**
     * Restore only the tenant value after a nested tenant-aware operation.
     *
     * Queue middleware can run synchronously during an HTTP request. In that
     * case clearing the whole context would also clear the parent request's
     * tenant, platform-admin and user-email settings.
     */
    public function restore(?string $tenantId): void
    {
        $this->tenantId = $tenantId;
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$tenantId ?? '']);
        }
    }

    public function setUserEmail(string $email): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.user_email', ?, false)", [$email]);
        }
    }

    public function clearUserEmail(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT set_config('app.user_email', '', false)");
        }
    }

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
            DB::statement("SELECT set_config('app.user_email', '', false)");
        }
    }
}
