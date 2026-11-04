<?php

use App\Models\{Company, Permission, Role, RoleAssignment, Supplier, SupplierOnboardingApplication, Tenant, TenantMembership, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function supplierOnboardingFixture(): array
{
    $tenant = Tenant::factory()->create(['status' => 'active']);
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Onboarding Company', 'code' => 'ONB']);
    $permission = Permission::firstOrCreate(['name' => 'procurement.manage']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Procurement Manager', 'slug' => 'procurement-manager']);
    $role->permissions()->sync([$permission->id]);

    $requester = User::factory()->create();
    $approver = User::factory()->create();
    foreach ([$requester, $approver] as $user) {
        TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
        RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    }

    $supplier = Supplier::create([
        'tenant_id' => $tenant->id,
        'company_id' => $company->id,
        'name' => 'Onboarding Supplier',
        'code' => 'ONB-SUP',
        'status' => 'active',
    ]);

    return compact('tenant', 'company', 'requester', 'approver', 'supplier');
}

function tenantHeaders(string $tenantId): array
{
    return ['X-Tenant-Id' => $tenantId];
}

it('has the supplier onboarding schema, foreign keys and tenant RLS after migrations', function (): void {
    expect(Schema::hasTable('supplier_onboarding_applications'))->toBeTrue();
    expect(Schema::getColumnListing('supplier_onboarding_applications'))->toEqualCanonicalizing([
        'id', 'tenant_id', 'company_id', 'supplier_id', 'submitted_by', 'assigned_reviewer_id',
        'approval_request_id', 'number', 'application_type', 'status', 'approval_status', 'version',
        'snapshot', 'notes', 'change_request_reason', 'submitted_at', 'reviewed_at', 'created_at', 'updated_at',
    ]);

    $rls = DB::select("select c.relrowsecurity, exists(select 1 from pg_policy p where p.polrelid = c.oid and p.polname = 'supplier_onboarding_applications_tenant_isolation') as has_policy from pg_class c join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and c.relname = 'supplier_onboarding_applications'")[0];
    expect((bool) $rls->relrowsecurity)->toBeTrue()->and((bool) $rls->has_policy)->toBeTrue();
});

it('creates a tenant and company-scoped onboarding application and rejects duplicate in-progress submissions', function (): void {
    $f = supplierOnboardingFixture();
    $payload = ['company_id' => $f['company']->id, 'application_type' => 'new_supplier_onboarding'];

    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/onboarding', $payload)
        ->assertCreated();

    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/onboarding', $payload)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'An onboarding application is already in progress for this supplier and company.');

    $otherCompany = Company::create(['tenant_id' => $f['tenant']->id, 'name' => 'Other Company', 'code' => 'ONB-OTHER']);
    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/onboarding', ['company_id' => $otherCompany->id, 'application_type' => 'new_supplier_onboarding'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Supplier is not available to this company.');

    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/deactivate')
        ->assertOk()->assertJsonPath('data.status', 'inactive');
    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/activate')
        ->assertOk()->assertJsonPath('data.status', 'active');

    expect(SupplierOnboardingApplication::where('tenant_id', $f['tenant']->id)->where('supplier_id', $f['supplier']->id)->count())->toBe(1);
});

it('rolls back a failed submission without creating an approval request', function (): void {
    $f = supplierOnboardingFixture();
    $created = $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/onboarding', ['company_id' => $f['company']->id, 'application_type' => 'new_supplier_onboarding'])
        ->assertCreated()->json('data.id');

    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/purchasing/onboarding/'.$created.'/submit')
        ->assertNotFound();

    expect(SupplierOnboardingApplication::find($created)->status)->toBe('draft')
        ->and(SupplierOnboardingApplication::find($created)->approval_request_id)->toBeNull()
        ->and(App\Models\ApprovalRequest::where('document_id', $created)->count())->toBe(0);
});

it('submits and approves an application transactionally while preserving tenant isolation and audit history', function (): void {
    $f = supplierOnboardingFixture();
    $definition = App\Models\ApprovalDefinition::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'scenario' => 'supplier_onboarding', 'name' => 'Supplier onboarding approval', 'allow_self_approval' => false, 'is_active' => true]);
    App\Models\ApprovalStep::create(['tenant_id' => $f['tenant']->id, 'definition_id' => $definition->id, 'level' => 1, 'permission' => 'procurement.manage', 'minimum_amount' => '0']);

    $created = $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/onboarding', ['company_id' => $f['company']->id, 'application_type' => 'new_supplier_onboarding'])
        ->assertCreated()->json('data.id');

    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/purchasing/onboarding/'.$created.'/submit')
        ->assertOk()->assertJsonPath('data.approval_status', 'pending');

    $this->actingAs($f['approver'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/purchasing/onboarding/'.$created.'/approve', ['remarks' => 'Approved after supplier review.'])
        ->assertOk()->assertJsonPath('data.approval_status', 'approved');

    expect(SupplierOnboardingApplication::find($created)->status)->toBe('approved');
    expect(App\Models\PlatformAuditLog::where('tenant_id', $f['tenant']->id)->whereIn('action', ['supplier.onboarding.created', 'supplier.onboarding.submitted', 'supplier.onboarding.approved'])->count())->toBe(3);

    $second = $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/suppliers/'.$f['supplier']->id.'/onboarding', ['company_id' => $f['company']->id, 'application_type' => 'supplier_reverification'])
        ->assertCreated()->json('data.id');
    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/purchasing/onboarding/'.$second.'/submit')
        ->assertOk();
    $this->actingAs($f['approver'])->withHeaders(tenantHeaders($f['tenant']->id))
        ->postJson('/api/v1/purchasing/onboarding/'.$second.'/reject', ['remarks' => 'Rejected pending additional documents.'])
        ->assertOk()->assertJsonPath('data.approval_status', 'rejected');
    expect(SupplierOnboardingApplication::find($second)->status)->toBe('rejected');

    $otherTenant = Tenant::factory()->create(['status' => 'active']);
    $this->actingAs($f['requester'])->withHeaders(tenantHeaders($otherTenant->id))
        ->getJson('/api/v1/purchasing/onboarding/'.$created)
        ->assertForbidden();
});
