<?php

use App\Models\{Company, Permission, Product, ProductVariant, PurchaseOrder, PurchaseOrderItem, Role, RoleAssignment, Supplier, Tenant, TenantMembership, UnitOfMeasure, User, Warehouse};

function purchaseOrderWorkspaceFixture(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'procurement.manage']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Procurement manager', 'slug' => 'procurement-manager-'.fake()->unique()->numerify('####')]);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Procurement Workspace', 'code' => 'PO-CO', 'base_currency' => 'MMK']);
    $supplier = Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Authorized Supplier', 'code' => 'SUP-1', 'status' => 'active']);
    $unit = UnitOfMeasure::create(['tenant_id' => $tenant->id, 'name' => 'Each', 'code' => 'EA']);
    $product = Product::create(['tenant_id' => $tenant->id, 'name' => 'Procurement Item', 'slug' => 'procurement-item', 'status' => 'active']);
    $variant = ProductVariant::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'sku' => 'PO-ITEM-1', 'internal_barcode' => 'POITEM001', 'cost' => '10', 'price' => '12', 'is_active' => true]);
    return [$user, $tenant, $company, $supplier, $variant];
}

it('returns scoped purchase orders with currency-safe summary data', function (): void {
    [$user, $tenant, $company, $supplier, $variant] = purchaseOrderWorkspaceFixture();
    $order = PurchaseOrder::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'PO-100', 'state' => 'approved', 'base_total' => '25.500000']);
    PurchaseOrderItem::create(['tenant_id' => $tenant->id, 'purchase_order_id' => $order->id, 'variant_id' => $variant->id, 'ordered_quantity' => '2', 'received_quantity' => '0', 'returned_quantity' => '0', 'unit_price' => '12.75']);

    $this->actingAs($user)->getJson('/api/v1/purchase-orders?company_id='.$company->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.data.0.number', 'PO-100')->assertJsonPath('summary.approved', 1)->assertJsonPath('summary.outstanding_value.value', '25.500000');
    $this->actingAs($user)->getJson('/api/v1/purchase-orders/'.$order->id, ['X-Tenant-Id' => $tenant->id])
        ->assertOk()->assertJsonPath('data.items.0.variant.product.name', 'Procurement Item');
});

it('creates a draft without posting stock and rejects unauthorized company access', function (): void {
    [$user, $tenant, $company, $supplier, $variant] = purchaseOrderWorkspaceFixture();
    $response = $this->actingAs($user)->postJson('/api/v1/purchase-orders', ['company_id' => $company->id, 'supplier_id' => $supplier->id, 'items' => [['variant_id' => $variant->id, 'ordered_quantity' => '3', 'unit_price' => '10.25']]], ['X-Tenant-Id' => $tenant->id])->assertCreated()->assertJsonPath('data.state', 'draft');
    expect(PurchaseOrder::find($response->json('data.id'))->base_total)->toBe('30.750000');

    $unauthorized = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $unauthorized->id, 'status' => 'active']);
    $this->actingAs($unauthorized)->getJson('/api/v1/purchase-orders?company_id='.$company->id, ['X-Tenant-Id' => $tenant->id])->assertForbidden();
});

it('persists decimal-safe phase two totals and validates warehouse scope', function (): void {
    [$user, $tenant, $company, $supplier, $variant] = purchaseOrderWorkspaceFixture();
    $warehouse = Warehouse::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Main warehouse', 'code' => 'MAIN', 'is_active' => true]);

    $response = $this->actingAs($user)->postJson('/api/v1/purchase-orders', [
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'order_date' => '2026-10-29',
        'shipping_total' => '2',
        'items' => [[
            'variant_id' => $variant->id,
            'description' => 'Phase two item',
            'ordered_quantity' => '3',
            'unit_price' => '10',
            'discount_type' => 'percent',
            'discount_amount' => '10',
            'tax_rate' => '5',
        ]],
    ], ['X-Tenant-Id' => $tenant->id])->assertCreated();

    expect($response->json('data.subtotal'))->toBe('30.000000')
        ->and($response->json('data.discount_total'))->toBe('3.000000')
        ->and($response->json('data.tax_total'))->toBe('1.350000')
        ->and($response->json('data.grand_total'))->toBe('30.350000')
        ->and($response->json('data.items.0.line_total'))->toBe('28.350000')
        ->and(PurchaseOrderItem::where('purchase_order_id', $response->json('data.id'))->value('line_total'))->toBe('28.350000');
});

it('does not allow duplicate variants or unauthorized print access', function (): void {
    [$user, $tenant, $company, $supplier, $variant] = purchaseOrderWorkspaceFixture();
    $payload = ['company_id' => $company->id, 'supplier_id' => $supplier->id, 'items' => [
        ['variant_id' => $variant->id, 'ordered_quantity' => '1', 'unit_price' => '10'],
        ['variant_id' => $variant->id, 'ordered_quantity' => '2', 'unit_price' => '10'],
    ]];
    $this->actingAs($user)->postJson('/api/v1/purchase-orders', $payload, ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();

    $order = PurchaseOrder::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'PO-PRINT-1', 'state' => 'draft']);
    $unauthorized = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $unauthorized->id, 'status' => 'active']);
    $this->actingAs($unauthorized)->get('/api/v1/purchase-orders/'.$order->id.'/print', ['X-Tenant-Id' => $tenant->id])->assertForbidden();
});
