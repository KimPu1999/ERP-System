<?php

use App\Models\Company;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function goodsReceiptFixture(int $quantity = 10): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Receiving Co', 'code' => 'RECV']);
    $supplier = Supplier::create(['tenant_id' => $tenant->id, 'name' => 'Receiving Supplier', 'code' => 'RECV-SUP']);
    $unit = UnitOfMeasure::create(['tenant_id' => $tenant->id, 'name' => 'Each', 'code' => 'EA']);
    $product = Product::create(['tenant_id' => $tenant->id, 'name' => 'Received Part', 'slug' => 'received-part', 'status' => 'active']);
    $variant = ProductVariant::create(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'sku' => 'RECV-1', 'internal_barcode' => 'RECV001', 'cost' => '1', 'price' => '2', 'is_active' => true]);
    $warehouse = Warehouse::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Receiving warehouse', 'code' => 'RECV-WH']);
    $location = WarehouseLocation::create(['tenant_id' => $tenant->id, 'warehouse_id' => $warehouse->id, 'name' => 'Receiving bin', 'code' => 'RECV-BIN']);
    $order = PurchaseOrder::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'warehouse_id' => $warehouse->id, 'supplier_id' => $supplier->id, 'created_by' => $user->id, 'number' => 'PO-RECV', 'state' => 'approved']);
    $item = PurchaseOrderItem::create(['tenant_id' => $tenant->id, 'purchase_order_id' => $order->id, 'variant_id' => $variant->id, 'ordered_quantity' => (string) $quantity, 'received_quantity' => '0', 'returned_quantity' => '0', 'unit_price' => '1']);
    return [$user, $tenant, $order, $item, $warehouse, $location];
}

it('keeps drafts out of inventory and posts only accepted quantities', function (): void {
    [$user, $tenant, $order, $item, , $location] = goodsReceiptFixture();
    $service = app(GoodsReceiptService::class);
    $draft = $service->createDraft($tenant->id, $user->id, $order, ['idempotency_key' => 'phase4-draft', 'warehouse_id' => $location->warehouse_id, 'lines' => [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'accepted_quantity' => '2', 'rejected_quantity' => '1', 'damaged_quantity' => '1']]]);
    expect($draft->state)->toBe('draft');
    expect(DB::table('stock_movements')->where('tenant_id', $tenant->id)->count())->toBe(0);
    $posted = $service->postReceipt($tenant->id, $user->id, $draft);
    expect($posted->state)->toBe('posted');
    expect(DB::table('stock_movements')->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect((string) InventoryBalance::where('tenant_id', $tenant->id)->where('variant_id', $item->variant_id)->value('quantity'))->toBe('2.000000');
    expect((string) $item->fresh()->received_quantity)->toBe('2.000000');
});

it('supports partial and full receiving without over-receiving', function (): void {
    [$user, $tenant, $order, $item, $warehouse, $location] = goodsReceiptFixture();
    $service = app(GoodsReceiptService::class);
    foreach ([['key' => 'phase4-40', 'quantity' => '4'], ['key' => 'phase4-30', 'quantity' => '3'], ['key' => 'phase4-30b', 'quantity' => '3']] as $receiptData) {
        $draft = $service->createDraft($tenant->id, $user->id, $order->fresh(), ['idempotency_key' => $receiptData['key'], 'warehouse_id' => $warehouse->id, 'lines' => [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'accepted_quantity' => $receiptData['quantity']]]]);
        $service->postReceipt($tenant->id, $user->id, $draft);
    }
    expect($order->fresh()->state)->toBe('received');
    expect((string) $item->fresh()->received_quantity)->toBe('10.000000');
    expect(GoodsReceipt::where('tenant_id', $tenant->id)->where('state', 'posted')->count())->toBe(3);
    expect(fn () => $service->createDraft($tenant->id, $user->id, $order->fresh(), ['idempotency_key' => 'phase4-too-much', 'warehouse_id' => $warehouse->id, 'lines' => [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'accepted_quantity' => '1']]]))->toThrow(ValidationException::class);
    expect(DB::table('stock_movements')->where('tenant_id', $tenant->id)->count())->toBe(3);
});

it('is idempotent and does not allow editing a posted receipt', function (): void {
    [$user, $tenant, $order, $item, $warehouse, $location] = goodsReceiptFixture();
    $service = app(GoodsReceiptService::class);
    $draft = $service->createDraft($tenant->id, $user->id, $order, ['idempotency_key' => 'phase4-idempotent', 'warehouse_id' => $warehouse->id, 'lines' => [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'accepted_quantity' => '2']]]);
    $posted = $service->postReceipt($tenant->id, $user->id, $draft);
    $retry = $service->postReceipt($tenant->id, $user->id, $posted);
    expect($retry->id)->toBe($posted->id);
    expect(DB::table('stock_movements')->where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(fn () => $service->updateDraft($tenant->id, $user->id, $posted, ['warehouse_id' => $warehouse->id, 'lines' => [['purchase_order_item_id' => $item->id, 'location_id' => $location->id, 'accepted_quantity' => '1']]]))->toThrow(ValidationException::class);
});
