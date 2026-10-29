<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\StockMovementService;
use Illuminate\Validation\ValidationException;

function inventoryFixture(): array
{
    $user = User::factory()->create(); $tenantA = Tenant::factory()->create(); $tenantB = Tenant::factory()->create(); TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'status' => 'active']);
    $company = Company::create(['tenant_id' => $tenantA->id, 'name' => 'Inventory Company', 'code' => 'INV']); $unit = UnitOfMeasure::create(['tenant_id' => $tenantA->id, 'name' => 'Each', 'code' => 'EA']); $product = Product::create(['tenant_id' => $tenantA->id, 'name' => 'Widget', 'slug' => 'widget', 'status' => 'active']); $variant = ProductVariant::create(['tenant_id' => $tenantA->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'sku' => 'W-1', 'internal_barcode' => 'ERPTEST001', 'cost' => '1.000000', 'price' => '2.000000', 'is_active' => true]); $warehouse = Warehouse::create(['tenant_id' => $tenantA->id, 'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN']); $location = WarehouseLocation::create(['tenant_id' => $tenantA->id, 'warehouse_id' => $warehouse->id, 'name' => 'A-01', 'code' => 'A-01']); $productB = Product::create(['tenant_id' => $tenantB->id, 'name' => 'Tenant B Item', 'slug' => 'tenant-b-item', 'status' => 'active']); return [$user, $tenantA, $tenantB, $variant, $location, $productB];
}

it('posts immutable stock movements and never allows negative stock by default', function (): void {
    [$user, $tenantA, , $variant, $location] = inventoryFixture(); $service = app(StockMovementService::class); $first = $service->post(['tenant_id' => $tenantA->id, 'variant_id' => $variant->id, 'location_id' => $location->id, 'created_by' => $user->id, 'movement_type' => 'receipt', 'quantity' => '10', 'unit_cost' => '1.000000', 'idempotency_key' => 'receipt-1']); $duplicate = $service->post(['tenant_id' => $tenantA->id, 'variant_id' => $variant->id, 'location_id' => $location->id, 'created_by' => $user->id, 'movement_type' => 'receipt', 'quantity' => '10', 'unit_cost' => '1.000000', 'idempotency_key' => 'receipt-1']); expect($duplicate->id)->toBe($first->id); expect(InventoryBalance::where('variant_id', $variant->id)->value('quantity'))->toBe('10.000000'); expect(fn () => $service->post(['tenant_id' => $tenantA->id, 'variant_id' => $variant->id, 'location_id' => $location->id, 'created_by' => $user->id, 'movement_type' => 'issue', 'quantity' => '-11', 'unit_cost' => '1.000000', 'idempotency_key' => 'issue-1']))->toThrow(ValidationException::class);
});

it('does not return another tenant products', function (): void {
    [$user, $tenantA, , , , $productB] = inventoryFixture(); $response = $this->actingAs($user)->getJson('/api/v1/products', ['X-Tenant-Id' => $tenantA->id]); $response->assertOk()->assertJsonMissing(['id' => $productB->id]);
});
