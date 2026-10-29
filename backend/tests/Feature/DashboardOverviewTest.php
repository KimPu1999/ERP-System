<?php

use App\Models\{AccountingCurrency, Branch, Company, CreditNote, Customer, Permission, Product, ProductVariant, Role, RoleAssignment, SalesInvoice, SalesOrder, SalesOrderItem, Tenant, TenantMembership, UnitOfMeasure, User};

function dashboardUserWithReportAccess(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'reports.view']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Dashboard viewer', 'slug' => 'dashboard-viewer']);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    return [$user, $tenant];
}

it('returns a tenant-scoped dashboard overview with empty states', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview?period=last_30_days', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonPath('data.currency.code', null)
        ->assertJsonPath('data.currency.configured', false)
        ->assertJsonStructure(['data' => ['filters', 'currency', 'kpis', 'charts', 'recent_sales', 'top_products', 'top_products_by_volume', 'inventory_alerts', 'pending_approvals']])
        ->assertJsonPath('data.kpis.pending_invoices.value', '0')
        ->assertJsonPath('data.kpis.pending_deliveries.value', '0');
});

it('reconciles revenue to the selected company and branch', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $companyA = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company A', 'code' => 'A']);
    $companyB = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company B', 'code' => 'B']);
    $branchA = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'name' => 'Branch A', 'code' => 'A-1']);
    AccountingCurrency::create(['tenant_id' => $tenant->id, 'code' => 'MMK', 'name' => 'Myanmar Kyat', 'decimal_places' => 0, 'is_base' => true]);
    $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Customer', 'code' => 'C-1']);
    $invoiceA = SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'branch_id' => $branchA->id, 'customer_id' => $customer->id, 'number' => 'INV-A', 'state' => 'issued', 'total' => '100', 'base_total' => '100']);
    CreditNote::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $invoiceA->id, 'number' => 'CN-A', 'state' => 'issued', 'total' => '20']);
    SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'customer_id' => $customer->id, 'number' => 'INV-B', 'state' => 'issued', 'total' => '900', 'base_total' => '900']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview?period=last_30_days&company_id='.$companyA->id.'&branch_id='.$branchA->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonPath('data.currency.code', 'MMK')
        ->assertJsonPath('data.kpis.revenue.value', '80.000000');
});

it('returns scoped pending invoice and delivery counts', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $companyA = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company A', 'code' => 'A']);
    $companyB = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company B', 'code' => 'B']);
    $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Customer', 'code' => 'C-1']);
    $unit = UnitOfMeasure::create(['tenant_id' => $tenant->id, 'name' => 'Each', 'code' => 'EA']);
    $product = Product::create(['tenant_id' => $tenant->id, 'name' => 'Widget', 'slug' => 'widget', 'status' => 'active']);
    $variant = ProductVariant::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'sku' => 'W-1', 'internal_barcode' => 'DASHBOARD001', 'cost' => '1', 'price' => '2', 'is_active' => true]);
    SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'customer_id' => $customer->id, 'number' => 'INV-A', 'state' => 'issued', 'total' => '100']);
    SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'customer_id' => $customer->id, 'number' => 'INV-B', 'state' => 'issued', 'total' => '900']);
    SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'customer_id' => $customer->id, 'number' => 'INV-PAID', 'state' => 'partially_paid', 'total' => '50', 'paid_total' => '50']);
    $order = SalesOrder::create(['tenant_id' => $tenant->id, 'company_id' => $companyA->id, 'customer_id' => $customer->id, 'created_by' => $user->id, 'number' => 'SO-A', 'state' => 'approved', 'total' => '100']);
    SalesOrderItem::create(['tenant_id' => $tenant->id, 'sales_order_id' => $order->id, 'variant_id' => $variant->id, 'ordered_quantity' => '2', 'reserved_quantity' => '2', 'delivered_quantity' => '0', 'returned_quantity' => '0', 'invoiced_quantity' => '0', 'unit_price' => '2', 'discount_rate' => '0', 'tax_rate' => '0', 'line_subtotal' => '4', 'discount_amount' => '0', 'tax_amount' => '0', 'line_total' => '4']);
    SalesOrder::create(['tenant_id' => $tenant->id, 'company_id' => $companyB->id, 'customer_id' => $customer->id, 'created_by' => $user->id, 'number' => 'SO-B', 'state' => 'approved', 'total' => '900']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview?company_id='.$companyA->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonPath('data.kpis.pending_invoices.value', '1')
        ->assertJsonPath('data.kpis.pending_deliveries.value', '1');
});

it('accepts date_from and date_to aliases for custom periods', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview?period=custom&date_from=2026-10-01&date_to=2026-10-29', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonPath('data.filters.from', '2026-10-01')
        ->assertJsonPath('data.filters.to', '2026-10-29');
});

it('only lists dashboard companies covered by the users report scope', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $companyA = Company::create(['tenant_id' => $tenant->id, 'name' => 'Company A', 'code' => 'A']);
    Company::create(['tenant_id' => $tenant->id, 'name' => 'Company B', 'code' => 'B']);
    RoleAssignment::where('tenant_id', $tenant->id)->where('user_id', $user->id)->update(['company_id' => $companyA->id]);

    $this->actingAs($user)->getJson('/api/v1/dashboard/companies', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $companyA->id);
});

it('requires dashboard permission and active tenant membership', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => $tenant->id])->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => Tenant::factory()->create()->id])->assertForbidden();
});
