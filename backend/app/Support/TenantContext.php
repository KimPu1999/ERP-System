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
     * Capture the full connection-local security context so an entry point
     * that temporarily changes it can restore exactly what existed before.
     *
     * Restoring the previous values (instead of unconditionally clearing) is
     * what keeps pooled/reused PostgreSQL connections free of tenant-context
     * leakage between operations.
     *
     * @return array{tenant: ?string, platform_admin: string, user_email: string}
     */
    public function snapshot(): array
    {
        if (DB::getDriverName() !== 'pgsql') {
            return ['tenant' => $this->tenantId, 'platform_admin' => 'false', 'user_email' => ''];
        }

        return [
            'tenant' => $this->tenantId,
            'platform_admin' => DB::scalar("SELECT current_setting('app.platform_admin', true)") ?? 'false',
            'user_email' => (string) (DB::scalar("SELECT current_setting('app.user_email', true)") ?? ''),
        ];
    }

    /**
     * Restore a previously captured context snapshot.
     *
     * @param array{tenant: ?string, platform_admin: string|bool, user_email: string|null} $snapshot
     */
    public function restoreSnapshot(array $snapshot): void
    {
        $this->tenantId = $snapshot['tenant'] ?? null;
        if (DB::getDriverName() !== 'pgsql') return;

        DB::statement("SELECT set_config('app.tenant_id', ?, false)", [$this->tenantId ?? '']);
        DB::statement("SELECT set_config('app.platform_admin', ?, false)", [$this->boolSetting($snapshot['platform_admin'] ?? false)]);
        $email = is_string($snapshot['user_email'] ?? null) ? $snapshot['user_email'] : '';
        DB::statement("SELECT set_config('app.user_email', ?, false)", [$email]);
    }

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

    /** Normalize any prior representation of a boolean connection setting to 'true'/'false'. */
    private function boolSetting(string|bool $value): string
    {
        if (is_bool($value)) return $value ? 'true' : 'false';

        return in_array(strtolower(trim($value)), ['true', 't', '1', 'on'], true) ? 'true' : 'false';
    }
}
