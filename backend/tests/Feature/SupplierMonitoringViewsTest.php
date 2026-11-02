<?php

use App\Models\{Company, Permission, PlatformAuditLog, Role, RoleAssignment, Supplier, SupplierAccessReview, SupplierPortalMembership, Tenant, TenantMembership, User};
use Illuminate\Support\Str;

function supplierMonitoringViewsFixture(bool $authorized = true): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    if ($authorized) {
        $manage = Permission::firstOrCreate(['name' => 'procurement.manage']);
        $export = Permission::firstOrCreate(['name' => 'reports.export']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Procurement monitor', 'slug' => 'procurement-monitor-'.Str::lower(Str::random(8))]);
        $role->permissions()->sync([$manage->id, $export->id]);
        RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    }
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Portal Company', 'code' => 'PORTAL-'.Str::upper(Str::random(5))]);
    $supplier = Supplier::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Portal Supplier', 'code' => 'SUP-'.Str::upper(Str::random(5)), 'status' => 'active']);
    $portalUser = User::factory()->create(['name' => 'Portal User']);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $portalUser->id, 'status' => 'active']);
    $membership = SupplierPortalMembership::create(['tenant_id' => $tenant->id, 'supplier_id' => $supplier->id, 'company_id' => $company->id, 'user_id' => $portalUser->id, 'role' => 'read_only', 'status' => 'active']);
    return compact('user', 'tenant', 'company', 'supplier', 'portalUser', 'membership');
}

it('provides scoped portal activity KPIs, filters, details, redaction, and CSV export', function (): void {
    $f = supplierMonitoringViewsFixture();
    $headers = ['X-Tenant-Id' => $f['tenant']->id];
    PlatformAuditLog::create(['tenant_id' => $f['tenant']->id, 'actor_user_id' => $f['portalUser']->id, 'action' => 'supplier_portal.po_acknowledged', 'auditable_type' => 'PurchaseOrder', 'auditable_id' => (string) Str::uuid(), 'metadata' => ['company_id' => $f['company']->id, 'supplier_id' => $f['supplier']->id, 'result' => 'success', 'password' => 'never-return'], 'ip_address' => '127.0.0.1']);
    $failed = PlatformAuditLog::create(['tenant_id' => $f['tenant']->id, 'actor_user_id' => $f['portalUser']->id, 'action' => 'supplier_portal.invitation.delivery_failed', 'metadata' => ['company_id' => $f['company']->id, 'supplier_id' => $f['supplier']->id], 'ip_address' => '127.0.0.1']);
    PlatformAuditLog::create(['tenant_id' => $f['tenant']->id, 'action' => 'supplier_portal.message.sent', 'metadata' => ['company_id' => (string) Str::uuid(), 'supplier_id' => (string) Str::uuid()]]);

    $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/portal-activity?company_id='.$f['company']->id.'&actor_id='.$f['portalUser']->id.'&per_page=10', $headers)->assertOk()->assertJsonPath('data.kpis.total_activities', 2)->assertJsonPath('data.kpis.failed_activities', 1)->assertJsonPath('data.activities.total', 2)->assertJsonPath('data.activities.data.0.supplier.name', 'Portal Supplier');
    $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/portal-activity/'.$failed->id.'?company_id='.$f['company']->id, $headers)->assertOk()->assertJsonPath('data.result', 'failed')->assertJsonMissing(['password' => 'never-return']);
    $activityExport = $this->actingAs($f['user'])->get('/api/v1/purchasing/suppliers/portal-activity/export?company_id='.$f['company']->id, $headers)->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($activityExport->streamedContent())->toContain('Activity ID');
    expect($activityExport->streamedContent())->toContain('Portal Supplier');
});

it('provides access review summaries, current access, history, and scoped details', function (): void {
    $f = supplierMonitoringViewsFixture();
    $reviewer = User::factory()->create(['name' => 'Review Owner']);
    TenantMembership::create(['tenant_id' => $f['tenant']->id, 'user_id' => $reviewer->id, 'status' => 'active']);
    $open = SupplierAccessReview::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'membership_id' => $f['membership']->id, 'supplier_id' => $f['supplier']->id, 'status' => 'open', 'due_date' => now()->subDay()->toDateString()]);
    SupplierAccessReview::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'membership_id' => $f['membership']->id, 'supplier_id' => $f['supplier']->id, 'reviewer_id' => $reviewer->id, 'status' => 'approved', 'decision_notes' => 'Verified', 'reviewed_at' => now()]);
    $headers = ['X-Tenant-Id' => $f['tenant']->id];

    $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/access-reviews?company_id='.$f['company']->id.'&per_page=10', $headers)->assertOk()->assertJsonPath('data.kpis.total_reviews', 2)->assertJsonPath('data.kpis.pending_reviews', 1)->assertJsonPath('data.kpis.completed_reviews', 1)->assertJsonPath('data.kpis.overdue_reviews', 1)->assertJsonPath('data.reviews.data.0.supplier.name', 'Portal Supplier');
    $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/access-reviews/'.$open->id.'?company_id='.$f['company']->id, $headers)->assertOk()->assertJsonPath('data.current_access.role', 'read_only')->assertJsonPath('data.history.0.status', 'approved')->assertJsonPath('data.decision_workflow.available', false);
    $reviewExport = $this->actingAs($f['user'])->get('/api/v1/purchasing/suppliers/access-reviews/export?company_id='.$f['company']->id, $headers)->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($reviewExport->streamedContent())->toContain('Review ID');
    expect($reviewExport->streamedContent())->toContain('Portal Supplier');
});

it('blocks monitoring views for users without procurement permission and across tenants', function (): void {
    $owner = supplierMonitoringViewsFixture();
    $blocked = supplierMonitoringViewsFixture(false);
    $headers = ['X-Tenant-Id' => $owner['tenant']->id];
    $this->actingAs($blocked['user'])->getJson('/api/v1/purchasing/suppliers/portal-activity', $headers)->assertForbidden();
    $this->actingAs($blocked['user'])->getJson('/api/v1/purchasing/suppliers/access-reviews?company_id='.$owner['company']->id, $headers)->assertForbidden();
});
