<?php

use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\GoodsReceiptService;
use Illuminate\Validation\ValidationException;

function procurementFixture(): array
{
    $user = User::factory()->create(); $tenantA = Tenant::factory()->create(); $tenantB = Tenant::factory()->create(); TenantMembership::create(['tenant_id' => $tenantA->id, 'user_id' => $user->id, 'status' => 'active']); $company = Company::create(['tenant_id' => $tenantA->id, 'name' => 'Procurement Co', 'code' => 'PROC']); $supplier = Supplier::create(['tenant_id' => $tenantA->id, 'name' => 'Supplier A', 'code' => 'SUP-A']); $unit = UnitOfMeasure::create(['tenant_id' => $tenantA->id, 'name' => 'Each', 'code' => 'EA']); $product = Product::create(['tenant_id' => $tenantA->id, 'name' => 'Part', 'slug' => 'part', 'status' => 'active']); $variant = ProductVariant::create(['tenant_id' => $tenantA->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'sku' => 'PART-1', 'internal_barcode' => 'ERPPROC001', 'cost' => '1', 'price' => '2', 'is_active' => true]); $warehouse = Warehouse::create(['tenant_id' => $tenantA->id, 'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN']); $location = WarehouseLocation::create(['tenant_id' => $tenantA->id, 'warehouse_id' => $warehouse->id, 'name' => 'Receiving', 'code' => 'REC']); $order = PurchaseOrder::create(['tenant_id' => $tenantA->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'PO-1', 'state' => 'approved']); $item = PurchaseOrderItem::create(['tenant_id' => $tenantA->id, 'purchase_order_id' => $order->id, 'variant_id' => $variant->id, 'ordered_quantity' => '10', 'received_quantity' => '0', 'returned_quantity' => '0', 'unit_price' => '1']); Supplier::create(['tenant_id' => $tenantB->id, 'name' => 'Supplier B', 'code' => 'SUP-B']); return [$user, $tenantA, $tenantB, $order, $item, $location];
}

it('prevents duplicate and over-receiving', function (): void {
    [$user, $tenant, , $order, $item, $location] = procurementFixture(); $service = app(GoodsReceiptService::class); $first = $service->post($tenant->id, $user->id, $order, [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '4']], 'receipt-1'); $duplicate = $service->post($tenant->id, $user->id, $order->fresh(), [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '4']], 'receipt-1'); expect($duplicate->id)->toBe($first->id); expect(GoodsReceipt::where('tenant_id', $tenant->id)->count())->toBe(1); expect(fn () => $service->post($tenant->id, $user->id, $order->fresh(), [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'quantity' => '7']], 'receipt-2'))->toThrow(ValidationException::class);
});

it('does not expose another tenant suppliers', function (): void {
    [$user, $tenantA, $tenantB] = procurementFixture(); $response = $this->actingAs($user)->getJson('/api/v1/suppliers', ['X-Tenant-Id' => $tenantA->id]); $response->assertOk()->assertJsonMissing(['tenant_id' => $tenantB->id]);
});
