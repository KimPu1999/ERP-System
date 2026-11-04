<?php

use App\Models\{Company, Tenant};
use Illuminate\Support\Facades\DB;

it('forces row level security on every RLS-governed table', function (): void {
    $unforced = DB::table('pg_class as c')
        ->join('pg_namespace as n', 'n.oid', '=', 'c.relnamespace')
        ->whereIn('c.relkind', ['r', 'p'])
        ->where('n.nspname', 'public')
        ->where('c.relrowsecurity', true)
        ->where('c.relforcerowsecurity', false)
        ->pluck('relname');

    expect($unforced->all())->toBe([]);
});

it('enforces database-level tenant isolation even for the table-owning role', function (): void {
    $tenantA = Tenant::create(['name' => 'RLS Tenant A', 'slug' => 'rls-tenant-a']);
    $tenantB = Tenant::create(['name' => 'RLS Tenant B', 'slug' => 'rls-tenant-b']);
    Company::create(['tenant_id' => $tenantA->id, 'name' => 'A Company', 'code' => 'A1']);

    $companyAQuery = fn (): iterable => Company::where('tenant_id', $tenantA->id)->get();

    // Simulate the table-owning connection of tenant B with the platform
    // escape switch explicitly OFF: tenant A rows must be invisible.
    DB::statement("SELECT set_config('app.platform_admin', 'false', false)");
    DB::statement(sprintf("SELECT set_config('app.tenant_id', '%s', false)", $tenantB->id));
    expect($companyAQuery()->count())->toBe(0);

    // Tenant B must be blocked from writing into tenant A's scope; the
    // violating insert runs inside a savepoint so the surrounding test
    // transaction stays valid, and a reader-side UPDATE silently matches
    // nothing.
    expect(fn (): Company => DB::transaction(fn (): Company => Company::create(['tenant_id' => $tenantA->id, 'name' => 'Injected by B', 'code' => 'B-x'])))->toThrow(RuntimeException::class, 'violates row-level security policy');
    expect(Company::where('tenant_id', $tenantA->id)->update(['name' => 'Tampered']))->toBe(0);
    expect(Company::where('tenant_id', $tenantA->id)->count())->toBe(0);

    // Restoring tenant A's own context keeps full legitimate access.
    DB::statement(sprintf("SELECT set_config('app.tenant_id', '%s', false)", $tenantA->id));
    expect($companyAQuery()->count())->toBe(1);

    // Platform context restores visibility for platform sweeps.
    DB::statement("SELECT set_config('app.platform_admin', 'true', false)");
    expect($companyAQuery()->count())->toBe(1);
});
