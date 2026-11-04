<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 14 audit fix (P0, verified): FORCE ROW LEVEL SECURITY on every
 * tenant-scoped table.
 *
 * Evidence: the application connects as the table-owning role (`erp`).
 * With `relrowsecurity = true` but `relforcerowsecurity = false`, PostgreSQL
 * silently bypasses every tenant isolation policy for the table owner,
 * so all RLS policies shipped in Phase 1-13 were a no-op for the app while
 * tenant separation relied solely on application-level WHERE clauses.
 *
 * Verified experiment (isolated scratch database): as table owner with
 * tenant A rows present and `app.tenant_id` set to tenant B, the row was
 * still visible before FORCE; with FORCE applied it is filtered, tenant A
 * reads its own rows, and `app.platform_admin = true` restores platform
 * visibility. No BYPASSRLS or policy weakening is involved.
 *
 * Background paths audited before applying:
 * - Every queued job sets/restores tenant context via HasTenantContext middleware.
 * - Platform flows run under platform.admin middleware, which sets
 *   app.platform_admin = 'true' (respected by all policies).
 * - erp:purchasing-automation dispatch sweep and platform:security-prune now
 *   set that explicit platform context in routes/console.php so data sweeping
 *   commands cannot silently no-op against RLS-governed tables.
 *
 * Migration-time note: data-modifying migrations that run after this point
 * operate without a tenant context, so they must set
 * `SET app.platform_admin = 'true'` before touching RLS-governed rows.
 */
return new class extends Migration {    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->whereIn('c.relkind', ['r', 'p'])
            ->where('n.nspname', config('database.connections.pgsql.schema', 'public'))
            ->where('c.relrowsecurity', true)
            ->where('c.relforcerowsecurity', false)
            ->pluck('relname');

        foreach ($tables as $table) {
            DB::statement(sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $this->quoteIdentifier((string) $table)));
        }

        Log::notice('Row level security enforcement enabled on tenant-scoped tables.', ['tables' => $tables->count()]);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') return;

        $tables = DB::table('pg_class as c')
            ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
            ->whereIn('c.relkind', ['r', 'p'])
            ->where('n.nspname', config('database.connections.pgsql.schema', 'public'))
            ->where('c.relrowsecurity', true)
            ->where('c.relforcerowsecurity', true)
            ->pluck('relname');

        foreach ($tables as $table) {
            DB::statement(sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $this->quoteIdentifier((string) $table)));
        }
    }

    /** Safely quote a PostgreSQL identifier taken from the catalog, not user input. */
    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
