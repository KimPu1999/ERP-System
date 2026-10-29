<?php

use App\Models\{AccountingCurrency, Branch, ChartAccount, Company, CreditNote, Customer, FiscalPeriod, FiscalYear, JournalEntry, JournalLine, Permission, Product, ProductVariant, Role, RoleAssignment, SalesInvoice, SalesInvoiceItem, SalesOrder, SalesOrderItem, Tenant, TenantMembership, UnitOfMeasure, User};

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

it('reports active and inactive authorized companies for dashboard initialization', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    Company::create(['tenant_id' => $tenant->id, 'name' => 'Inactive Company', 'code' => 'INACTIVE', 'is_active' => false]);
    $active = Company::create(['tenant_id' => $tenant->id, 'name' => 'Active Company', 'code' => 'ACTIVE', 'is_active' => true]);

    $this->actingAs($user)->getJson('/api/v1/dashboard/companies', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $active->id)
        ->assertJsonPath('meta.active_count', 1)
        ->assertJsonPath('meta.inactive_count', 1);
});

it('requires dashboard permission and active tenant membership', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => $tenant->id])->assertForbidden();
    $this->actingAs($user)->getJson('/api/v1/dashboard/overview', ['X-Tenant-Id' => Tenant::factory()->create()->id])->assertForbidden();
});

it('protects analytics endpoints with authentication, tenant membership, and dashboard permission', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $tenantB = Tenant::factory()->create();
    $endpoints = ['/api/v1/dashboard/revenue-trend', '/api/v1/dashboard/growth', '/api/v1/dashboard/financial-performance', '/api/v1/dashboard/recent-transactions', '/api/v1/dashboard/top-products'];
    foreach ($endpoints as $endpoint) {
        $response = $this->getJson($endpoint);
        expect([401, 403])->toContain($response->status());
        $this->actingAs($user)->getJson($endpoint, ['X-Tenant-Id' => $tenantB->id])->assertForbidden();
        $this->actingAs($user)->getJson($endpoint, ['X-Tenant-Id' => $tenant->id])->assertOk();
    }
});

it('returns bounded recent transactions from authorized posted sources in descending order', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Transactions Company', 'code' => 'TX']);
    $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Transaction Customer', 'code' => 'TX-C']);
    $older = SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'INV-OLDER', 'state' => 'issued', 'total' => '25']);
    $older->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
    $newer = SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'INV-NEWER', 'state' => 'paid', 'total' => '75']);
    $newer->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();

    $this->actingAs($user)->getJson('/api/v1/dashboard/recent-transactions?period=last_30_days&company_id='.$company->id.'&limit=1', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.items.0.reference', 'INV-NEWER')->assertJsonPath('data.items.0.type', 'sales_invoice')->assertJsonPath('data.pagination.limit', 1);
});

it('ranks net product sales and volume without mixing tenants or currencies', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Products Company', 'code' => 'PR']);
    AccountingCurrency::create(['tenant_id' => $tenant->id, 'code' => 'MMK', 'name' => 'Myanmar Kyat', 'decimal_places' => 0, 'is_base' => true]);
    $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Product Customer', 'code' => 'PR-C']);
    $unit = UnitOfMeasure::create(['tenant_id' => $tenant->id, 'name' => 'Each', 'code' => 'EA']);
    $first = Product::create(['tenant_id' => $tenant->id, 'name' => 'First product', 'slug' => 'first-product', 'status' => 'active']);
    $second = Product::create(['tenant_id' => $tenant->id, 'name' => 'Second product', 'slug' => 'second-product', 'status' => 'active']);
    $firstVariant = ProductVariant::create(['tenant_id' => $tenant->id, 'product_id' => $first->id, 'unit_id' => $unit->id, 'sku' => 'PR-1', 'internal_barcode' => 'PR0001', 'cost' => '5', 'price' => '10', 'is_active' => true]);
    $secondVariant = ProductVariant::create(['tenant_id' => $tenant->id, 'product_id' => $second->id, 'unit_id' => $unit->id, 'sku' => 'PR-2', 'internal_barcode' => 'PR0002', 'cost' => '5', 'price' => '10', 'is_active' => true]);
    $invoice = SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'INV-PRODUCTS', 'state' => 'issued', 'total' => '150', 'base_total' => '150']);
    SalesInvoiceItem::create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'variant_id' => $firstVariant->id, 'quantity' => '10', 'unit_price' => '10', 'discount_amount' => '0', 'tax_amount' => '0', 'line_total' => '100']);
    SalesInvoiceItem::create(['tenant_id' => $tenant->id, 'invoice_id' => $invoice->id, 'variant_id' => $secondVariant->id, 'quantity' => '5', 'unit_price' => '10', 'discount_amount' => '0', 'tax_amount' => '0', 'line_total' => '50']);
    CreditNote::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'number' => 'CN-PRODUCTS', 'state' => 'issued', 'total' => '30']);

    $response = $this->actingAs($user)->getJson('/api/v1/dashboard/top-products?period=last_30_days&company_id='.$company->id.'&ranking=sales', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.items.0.product', 'First product')->assertJsonPath('data.items.0.revenue', '80.000000');
    expect(collect($response->json('data.items'))->pluck('sku')->all())->toBe(['PR-1', 'PR-2']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/top-products?period=last_30_days&company_id='.$company->id.'&ranking=volume', ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.items.0.product', 'First product')->assertJsonPath('data.items.0.quantity', '10.000000')->assertJsonPath('data.items.0.unit_name', 'Each');
});

it('returns revenue trend and growth from recognized revenue with credit notes', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Analytics Company', 'code' => 'AN']);
    $currency = AccountingCurrency::create(['tenant_id' => $tenant->id, 'code' => 'MMK', 'name' => 'Myanmar Kyat', 'decimal_places' => 0, 'is_base' => true]);
    $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Analytics Customer', 'code' => 'AC-1']);
    $currentInvoice = SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'AN-CURRENT', 'state' => 'issued', 'total' => '100', 'base_total' => '100']);
    $currentInvoice->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
    $creditNote = CreditNote::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'invoice_id' => $currentInvoice->id, 'number' => 'AN-CN', 'state' => 'issued', 'total' => '20']);
    $creditNote->forceFill(['created_at' => now()->subDays(1), 'updated_at' => now()->subDays(1)])->save();
    $previousInvoice = SalesInvoice::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'customer_id' => $customer->id, 'number' => 'AN-PREVIOUS', 'state' => 'issued', 'total' => '50', 'base_total' => '50']);
    $previousInvoice->forceFill(['created_at' => now()->subDays(32), 'updated_at' => now()->subDays(32)])->save();

    $this->actingAs($user)->getJson('/api/v1/dashboard/revenue-trend?period=last_30_days&company_id='.$company->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.currency.code', $currency->code)->assertJsonPath('data.totals.current', '80.000000');
    $this->actingAs($user)->getJson('/api/v1/dashboard/growth?period=last_30_days&company_id='.$company->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.growth_percent', '60.00')->assertJsonPath('data.current_period.value', '80.000000');
});

it('returns posted general ledger financial performance and rejects branch precision it cannot support', function (): void {
    [$user, $tenant] = dashboardUserWithReportAccess();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Ledger Company', 'code' => 'GL']);
    $branch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Ledger Branch', 'code' => 'GL-1']);
    $currency = AccountingCurrency::create(['tenant_id' => $tenant->id, 'code' => 'MMK', 'name' => 'Myanmar Kyat', 'decimal_places' => 0, 'is_base' => true]);
    $year = FiscalYear::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'FY 2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'state' => 'open']);
    $period = FiscalPeriod::create(['tenant_id' => $tenant->id, 'fiscal_year_id' => $year->id, 'name' => 'October 2026', 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-31', 'state' => 'open']);
    $asset = ChartAccount::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => '1100', 'name' => 'Receivable', 'type' => 'asset', 'normal_balance' => 'debit']);
    $revenue = ChartAccount::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => '4100', 'name' => 'Sales revenue', 'type' => 'revenue', 'normal_balance' => 'credit']);
    $expense = ChartAccount::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => '5100', 'name' => 'Operating expense', 'type' => 'expense', 'normal_balance' => 'debit']);
    $liability = ChartAccount::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'code' => '2100', 'name' => 'Payable', 'type' => 'liability', 'normal_balance' => 'credit']);
    $saleEntry = JournalEntry::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'fiscal_period_id' => $period->id, 'currency_id' => $currency->id, 'created_by' => $user->id, 'number' => 'JE-AN-SALE', 'entry_date' => now()->toDateString(), 'state' => 'posted', 'idempotency_key' => 'analytics-sale', 'posted_at' => now()]);
    JournalLine::create(['tenant_id' => $tenant->id, 'journal_entry_id' => $saleEntry->id, 'account_id' => $asset->id, 'company_id' => $company->id, 'debit' => '100', 'base_debit' => '100']);
    JournalLine::create(['tenant_id' => $tenant->id, 'journal_entry_id' => $saleEntry->id, 'account_id' => $revenue->id, 'company_id' => $company->id, 'credit' => '100', 'base_credit' => '100']);
    $expenseEntry = JournalEntry::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'fiscal_period_id' => $period->id, 'currency_id' => $currency->id, 'created_by' => $user->id, 'number' => 'JE-AN-EXPENSE', 'entry_date' => now()->toDateString(), 'state' => 'posted', 'idempotency_key' => 'analytics-expense', 'posted_at' => now()]);
    JournalLine::create(['tenant_id' => $tenant->id, 'journal_entry_id' => $expenseEntry->id, 'account_id' => $expense->id, 'company_id' => $company->id, 'debit' => '30', 'base_debit' => '30']);
    JournalLine::create(['tenant_id' => $tenant->id, 'journal_entry_id' => $expenseEntry->id, 'account_id' => $liability->id, 'company_id' => $company->id, 'credit' => '30', 'base_credit' => '30']);

    $this->actingAs($user)->getJson('/api/v1/dashboard/financial-performance?period=last_30_days&company_id='.$company->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.totals.current.income', '100.000000')->assertJsonPath('data.totals.current.expenses', '30.000000')->assertJsonPath('data.totals.current.profit', '70.000000');
    $this->actingAs($user)->getJson('/api/v1/dashboard/financial-performance?period=last_30_days&company_id='.$company->id.'&branch_id='.$branch->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.available', false);
});
