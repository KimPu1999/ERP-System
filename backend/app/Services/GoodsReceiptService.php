<?php

namespace App\Services;

use App\Events\GoodsReceiptPosted;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\WarehouseLocation;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GoodsReceiptService
{
    public function __construct(private readonly StockMovementService $movements, private readonly ProcurementNumberService $numbers) {}

    public function post(string $tenantId, string $userId, PurchaseOrder $order, array $lines, string $key): GoodsReceipt
    {
        return DB::transaction(function () use ($tenantId, $userId, $order, $lines, $key): GoodsReceipt {
            $existing = GoodsReceipt::where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
            if ($existing) return $existing;
            abort_unless($order->tenant_id === $tenantId, 404);
            if (! in_array($order->state, ['approved', 'partially_received'], true)) throw ValidationException::withMessages(['purchase_order' => ['Purchase order is not receivable in its current state.']]);
            $receipt = GoodsReceipt::create(['tenant_id' => $tenantId, 'purchase_order_id' => $order->id, 'received_by' => $userId, 'number' => $this->numbers->make('GR'), 'state' => 'draft', 'idempotency_key' => $key]);
            $fullyReceived = true;
            foreach ($lines as $index => $line) {
                $item = PurchaseOrderItem::where('tenant_id', $tenantId)->where('purchase_order_id', $order->id)->whereKey($line['purchase_order_item_id'])->lockForUpdate()->firstOrFail();
                abort_unless(WarehouseLocation::whereKey($line['location_id'])->where('tenant_id', $tenantId)->exists(), 404);
                $quantity = Decimal::normalize((string) $line['quantity']);
                $remaining = Decimal::sub((string) $item->ordered_quantity, (string) $item->received_quantity);
                if (Decimal::compare($quantity, '0') <= 0 || Decimal::compare($quantity, $remaining) > 0) throw ValidationException::withMessages(['quantity' => ['Receipt quantity exceeds the remaining purchase order quantity.']]);
                GoodsReceiptItem::create(['tenant_id' => $tenantId, 'goods_receipt_id' => $receipt->id, 'purchase_order_item_id' => $item->id, 'location_id' => $line['location_id'], 'quantity' => $quantity]);
                $this->movements->post(['tenant_id' => $tenantId, 'variant_id' => $item->variant_id, 'location_id' => $line['location_id'], 'created_by' => $userId, 'movement_type' => 'purchase_receipt', 'quantity' => $quantity, 'unit_cost' => $item->unit_price, 'reference_type' => GoodsReceipt::class, 'reference_id' => $receipt->id, 'idempotency_key' => 'goods-receipt:'.$receipt->id.':'.$index]);
                $item->update(['received_quantity' => Decimal::add((string) $item->received_quantity, $quantity)]);
                if (Decimal::compare((string) $item->received_quantity, (string) $item->ordered_quantity) < 0) $fullyReceived = false;
            }
            $receipt->update(['state' => 'posted', 'posted_at' => now()]);
            $order->update(['state' => $fullyReceived ? 'received' : 'partially_received']);
            event(new GoodsReceiptPosted($receipt->fresh('items')));
            return $receipt->fresh('items');
        });
    }
}
