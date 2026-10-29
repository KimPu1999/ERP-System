<?php

use App\Models\{Company, Permission, Product, ProductVariant, PurchaseOrder, PurchaseOrderItem, Role, RoleAssignment, Supplier, Tenant, TenantMembership, UnitOfMeasure, User};

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
