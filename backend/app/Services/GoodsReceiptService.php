<?php

namespace App\Services;

use App\Events\GoodsReceiptPosted;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\ProcurementDocumentHistory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\PurchasingSetting;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class GoodsReceiptService
{
    public function __construct(private readonly StockMovementService $movements, private readonly ProcurementNumberService $numbers) {}

    /** Create a draft only. This method never touches inventory balances or stock movements. */
    public function createDraft(string $tenantId, string $userId, PurchaseOrder $order, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($tenantId, $userId, $order, $data): GoodsReceipt {
            $key = (string) ($data['idempotency_key'] ?? Str::uuid());
            $existing = GoodsReceipt::where('tenant_id', $tenantId)->where('idempotency_key', $key)->first();
            if ($existing) return $existing->load('items');
            $lockedOrder = PurchaseOrder::whereKey($order->id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            $this->assertReceivable($lockedOrder);
            $warehouseId = $data['warehouse_id'] ?? $lockedOrder->warehouse_id;
            $this->validateWarehouseAndLines($tenantId, $lockedOrder, $warehouseId, $data['lines']);
            $receipt = GoodsReceipt::create(['tenant_id' => $tenantId, 'company_id' => $lockedOrder->company_id, 'branch_id' => $lockedOrder->branch_id, 'warehouse_id' => $warehouseId, 'purchase_order_id' => $lockedOrder->id, 'received_by' => $userId, 'number' => $this->numbers->makeFor('GR', $tenantId, $lockedOrder->company_id, $lockedOrder->branch_id), 'receipt_date' => $data['receipt_date'] ?? now()->toDateString(), 'supplier_delivery_number' => $data['supplier_delivery_number'] ?? null, 'reference_number' => $data['reference_number'] ?? null, 'receiving_notes' => $data['receiving_notes'] ?? null, 'state' => 'draft', 'idempotency_key' => $key]);
            foreach ($data['lines'] as $line) {
                $quantities = $this->quantities($line);
                $receipt->items()->create(['tenant_id' => $tenantId, 'purchase_order_item_id' => $line['purchase_order_item_id'], 'location_id' => $line['location_id'], 'quantity' => $quantities['total'], 'accepted_quantity' => $quantities['accepted'], 'rejected_quantity' => $quantities['rejected'], 'damaged_quantity' => $quantities['damaged'], 'inspection_status' => $line['inspection_status'] ?? ($quantities['rejected'] !== '0.000000' || $quantities['damaged'] !== '0.000000' ? 'inspected' : 'accepted'), 'inspection_notes' => $line['inspection_notes'] ?? null, 'rejection_reason' => $line['rejection_reason'] ?? null, 'damage_reason' => $line['damage_reason'] ?? null, 'batch_number' => $line['batch_number'] ?? null, 'serial_number' => $line['serial_number'] ?? null, 'manufacturing_date' => $line['manufacturing_date'] ?? null, 'expiry_date' => $line['expiry_date'] ?? null]);
            }
            return $receipt->load('items');
        });
    }

    public function updateDraft(string $tenantId, string $userId, GoodsReceipt $receipt, array $data): GoodsReceipt
    {
        return DB::transaction(function () use ($tenantId, $userId, $receipt, $data): GoodsReceipt {
            $locked = GoodsReceipt::whereKey($receipt->id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            if ($locked->state !== 'draft') throw ValidationException::withMessages(['state' => ['Only draft receipts can be edited.']]);
            $order = PurchaseOrder::whereKey($locked->purchase_order_id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            $this->assertReceivable($order);
            $warehouseId = $data['warehouse_id'] ?? $locked->warehouse_id;
            $this->validateWarehouseAndLines($tenantId, $order, $warehouseId, $data['lines']);
            $locked->update(['company_id' => $order->company_id, 'branch_id' => $order->branch_id, 'warehouse_id' => $warehouseId, 'receipt_date' => $data['receipt_date'] ?? $locked->receipt_date, 'supplier_delivery_number' => $data['supplier_delivery_number'] ?? null, 'reference_number' => $data['reference_number'] ?? null, 'receiving_notes' => $data['receiving_notes'] ?? null]);
            $locked->items()->delete();
            foreach ($data['lines'] as $line) {
                $quantities = $this->quantities($line);
                $locked->items()->create(['tenant_id' => $tenantId, 'purchase_order_item_id' => $line['purchase_order_item_id'], 'location_id' => $line['location_id'], 'quantity' => $quantities['total'], 'accepted_quantity' => $quantities['accepted'], 'rejected_quantity' => $quantities['rejected'], 'damaged_quantity' => $quantities['damaged'], 'inspection_status' => $line['inspection_status'] ?? 'accepted', 'inspection_notes' => $line['inspection_notes'] ?? null, 'rejection_reason' => $line['rejection_reason'] ?? null, 'damage_reason' => $line['damage_reason'] ?? null, 'batch_number' => $line['batch_number'] ?? null, 'serial_number' => $line['serial_number'] ?? null, 'manufacturing_date' => $line['manufacturing_date'] ?? null, 'expiry_date' => $line['expiry_date'] ?? null]);
            }
            return $locked->load('items');
        });
    }

    /** Post a draft atomically. Accepted quantities are the only quantities sent to the ledger. */
    public function postReceipt(string $tenantId, string $userId, GoodsReceipt $receipt): GoodsReceipt
    {
        $alreadyPosted = false;
        $posted = DB::transaction(function () use ($tenantId, $userId, $receipt, &$alreadyPosted): GoodsReceipt {
            $locked = GoodsReceipt::whereKey($receipt->id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            if ($locked->state === 'posted') { $alreadyPosted = true; return $locked->load('items'); }
            if ($locked->state !== 'draft') throw ValidationException::withMessages(['state' => ['Only draft receipts can be posted.']]);
            $order = PurchaseOrder::whereKey($locked->purchase_order_id)->where('tenant_id', $tenantId)->lockForUpdate()->firstOrFail();
            $this->assertReceivable($order);
            $items = $locked->items()->lockForUpdate()->get();
            if ($items->isEmpty()) throw ValidationException::withMessages(['lines' => ['A receipt must contain at least one line.']]);
            $aggregates = [];
            foreach ($items as $line) $aggregates[$line->purchase_order_item_id] = Decimal::add($aggregates[$line->purchase_order_item_id] ?? '0', (string) $line->quantity);
            foreach ($aggregates as $itemId => $physicalQuantity) {
                $orderItem = PurchaseOrderItem::whereKey($itemId)->where('tenant_id', $tenantId)->where('purchase_order_id', $order->id)->lockForUpdate()->firstOrFail();
                $remaining = Decimal::sub((string) $orderItem->ordered_quantity, (string) $orderItem->received_quantity);
                $settings = $this->settingsFor($tenantId, $order->company_id, $order->branch_id);
                $allowed = $this->receivableQuantity($remaining, $settings);
                if ($settings && ! $settings->partial_receiving_allowed && Decimal::compare($physicalQuantity, $remaining) < 0) {
                    throw ValidationException::withMessages(['lines' => ['Partial receiving is disabled by the active purchasing policy.']]);
                }
                if (Decimal::compare($physicalQuantity, $allowed) > 0) throw ValidationException::withMessages(['lines' => ['Receipt quantity exceeds the current remaining purchase order quantity and configured tolerance. Refresh before posting.']]);
            }
            foreach ($items as $line) {
                $accepted = Decimal::normalize((string) $line->accepted_quantity);
                if (Decimal::compare($accepted, '0') > 0) {
                    $orderItem = PurchaseOrderItem::whereKey($line->purchase_order_item_id)->where('tenant_id', $tenantId)->firstOrFail();
                    $this->movements->post(['tenant_id' => $tenantId, 'variant_id' => $orderItem->variant_id, 'location_id' => $line->location_id, 'created_by' => $userId, 'movement_type' => 'purchase_receipt', 'quantity' => $accepted, 'unit_cost' => $orderItem->unit_price, 'reference_type' => GoodsReceipt::class, 'reference_id' => $locked->id, 'idempotency_key' => 'goods-receipt:'.$locked->id.':'.$line->id]);
                    $orderItem->update(['received_quantity' => Decimal::add((string) $orderItem->received_quantity, $accepted)]);
                }
            }
            $locked->update(['state' => 'posted', 'posted_by' => $userId, 'posted_at' => now()]);
            $remainingItems = PurchaseOrderItem::where('tenant_id', $tenantId)->where('purchase_order_id', $order->id)->get();
            $hasReceived = $remainingItems->contains(fn ($item) => Decimal::compare((string) $item->received_quantity, '0') > 0);
            $fullyReceived = $remainingItems->every(fn ($item) => Decimal::compare((string) $item->received_quantity, (string) $item->ordered_quantity) >= 0);
            $newOrderState = $fullyReceived ? 'received' : ($hasReceived ? 'partially_received' : $order->state);
            if ($newOrderState !== $order->state) {
                ProcurementDocumentHistory::create(['tenant_id' => $tenantId, 'document_type' => 'PurchaseOrder', 'document_id' => $order->id, 'actor_id' => $userId, 'from_state' => $order->state, 'to_state' => $newOrderState, 'comment' => 'Updated by goods receipt '.$locked->number]);
                $order->update(['state' => $newOrderState]);
            }
            ProcurementDocumentHistory::create(['tenant_id' => $tenantId, 'document_type' => 'GoodsReceipt', 'document_id' => $locked->id, 'actor_id' => $userId, 'from_state' => 'draft', 'to_state' => 'posted']);
            return $locked->load('items');
        });
        if (! $alreadyPosted) event(new GoodsReceiptPosted($posted));
        return $posted;
    }

    /** Backwards-compatible API behavior for existing clients. */
    public function post(string $tenantId, string $userId, PurchaseOrder $order, array $lines, string $key): GoodsReceipt
    {
        $warehouseId = $order->warehouse_id ?: WarehouseLocation::where('tenant_id', $tenantId)->whereKey($lines[0]['location_id'])->value('warehouse_id');
        $receipt = $this->createDraft($tenantId, $userId, $order, ['idempotency_key' => $key, 'warehouse_id' => $warehouseId, 'lines' => array_map(fn (array $line): array => [...$line, 'accepted_quantity' => $line['quantity']], $lines)]);
        return $this->postReceipt($tenantId, $userId, $receipt);
    }

    private function assertReceivable(PurchaseOrder $order): void
    {
        if (! in_array($order->state, ['approved', 'partially_received'], true)) throw ValidationException::withMessages(['purchase_order' => ['Purchase order is not receivable in its current state.']]);
    }

    private function validateWarehouseAndLines(string $tenantId, PurchaseOrder $order, ?string $warehouseId, array $lines): void
    {
        if (! $warehouseId) throw ValidationException::withMessages(['warehouse_id' => ['A destination warehouse is required.']]);
        $warehouse = Warehouse::whereKey($warehouseId)->where('tenant_id', $tenantId)->where('is_active', true)->first();
        if (! $warehouse) abort(404, 'Warehouse is outside the authorized tenant or company.');
        if ($order->company_id && (string) $warehouse->company_id !== (string) $order->company_id) abort(404, 'Warehouse is outside the purchase order company.');
        if ($order->branch_id && (string) $warehouse->branch_id !== (string) $order->branch_id) abort(404, 'Warehouse is outside the purchase order branch.');
        if (! $lines) throw ValidationException::withMessages(['lines' => ['At least one receipt line is required.']]);
        $serials = [];
        foreach ($lines as $line) {
            $location = WarehouseLocation::whereKey($line['location_id'] ?? null)->where('tenant_id', $tenantId)->where('warehouse_id', $warehouse->id)->where('is_active', true)->first();
            if (! $location) abort(404, 'Storage location is outside the selected warehouse.');
            $this->quantities($line);
            if (! empty($line['serial_number'])) {
                if (in_array($line['serial_number'], $serials, true) || GoodsReceiptItem::where('tenant_id', $tenantId)->where('serial_number', $line['serial_number'])->whereHas('receipt', fn ($query) => $query->where('state', 'posted'))->exists()) throw ValidationException::withMessages(['lines' => ['Serial numbers must be unique within posted inventory receipts.']]);
                $serials[] = $line['serial_number'];
            }
        }
    }

    private function quantities(array $line): array
    {
        $accepted = Decimal::normalize((string) ($line['accepted_quantity'] ?? $line['quantity'] ?? '0'));
        $rejected = Decimal::normalize((string) ($line['rejected_quantity'] ?? '0'));
        $damaged = Decimal::normalize((string) ($line['damaged_quantity'] ?? '0'));
        $total = Decimal::add(Decimal::add($accepted, $rejected), $damaged);
        if (Decimal::compare($total, '0') <= 0 || Decimal::compare($accepted, '0') < 0 || Decimal::compare($rejected, '0') < 0 || Decimal::compare($damaged, '0') < 0) throw ValidationException::withMessages(['lines' => ['Receipt quantities must be positive and non-negative by classification.']]);
        if (! empty($line['serial_number']) && Decimal::compare($total, '1') !== 0) throw ValidationException::withMessages(['lines' => ['A serial-tracked line must represent exactly one unit.']]);
        return ['accepted' => $accepted, 'rejected' => $rejected, 'damaged' => $damaged, 'total' => $total];
    }

    private function settingsFor(string $tenantId, ?string $companyId, ?string $branchId): ?PurchasingSetting
    {
        if (! $companyId) return null;
        return PurchasingSetting::where('tenant_id', $tenantId)->where('company_id', $companyId)
            ->where(function ($query) use ($branchId): void { $query->where('branch_id', $branchId)->orWhereNull('branch_id'); })
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 1 ELSE 0 END')->first();
    }

    private function receivableQuantity(string $remaining, ?PurchasingSetting $settings): string
    {
        if (! $settings || Decimal::compare((string) $settings->over_receiving_tolerance, '0') <= 0) return $remaining;
        $extra = $settings->over_receiving_tolerance_unit === 'percent'
            ? Decimal::div(Decimal::mul($remaining, (string) $settings->over_receiving_tolerance), '100')
            : (string) $settings->over_receiving_tolerance;
        return Decimal::add($remaining, $extra);
    }
}
