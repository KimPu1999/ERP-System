<?php

namespace App\Services;

use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\Decimal;

final class StockOperationService
{
    public function __construct(private readonly StockMovementService $movements) {}

    public function adjustment(string $tenantId, string $userId, array $lines, string $reason, string $key): StockAdjustment
    {
        return DB::transaction(function () use ($tenantId, $userId, $lines, $reason, $key): StockAdjustment { $existing = StockAdjustment::where('tenant_id', $tenantId)->where('idempotency_key', $key)->first(); if ($existing) return $existing; $adjustment = StockAdjustment::create(['tenant_id' => $tenantId, 'created_by' => $userId, 'status' => 'posted', 'idempotency_key' => $key, 'reason' => $reason]); foreach ($lines as $index => $line) $this->movements->post(['tenant_id' => $tenantId, 'variant_id' => $line['variant_id'], 'location_id' => $line['location_id'], 'created_by' => $userId, 'movement_type' => 'adjustment', 'quantity' => $line['quantity'], 'unit_cost' => $line['unit_cost'] ?? '0', 'reference_type' => StockAdjustment::class, 'reference_id' => $adjustment->id, 'idempotency_key' => $key.':'.$index]); return $adjustment; });
    }

    public function transfer(string $tenantId, string $userId, string $from, string $to, array $lines, ?string $reason, string $key): StockTransfer
    {
        if ($from === $to) throw ValidationException::withMessages(['to_location_id' => ['Transfer locations must differ.']]);
        foreach ($lines as $line) if (Decimal::compare(Decimal::normalize((string) $line['quantity']), '0') <= 0) throw ValidationException::withMessages(['quantity' => ['Transfer quantities must be positive.']]);
        return DB::transaction(function () use ($tenantId, $userId, $from, $to, $lines, $reason, $key): StockTransfer { $existing = StockTransfer::where('tenant_id', $tenantId)->where('idempotency_key', $key)->first(); if ($existing) return $existing; $transfer = StockTransfer::create(['tenant_id' => $tenantId, 'from_location_id' => $from, 'to_location_id' => $to, 'created_by' => $userId, 'status' => 'posted', 'idempotency_key' => $key, 'reason' => $reason]); foreach ($lines as $index => $line) { $lineModel = StockTransferLine::create(['tenant_id' => $tenantId, 'transfer_id' => $transfer->id, 'variant_id' => $line['variant_id'], 'quantity' => $line['quantity']]); $positive = Decimal::normalize((string) $lineModel->quantity); $this->movements->post(['tenant_id' => $tenantId, 'variant_id' => $lineModel->variant_id, 'location_id' => $from, 'created_by' => $userId, 'movement_type' => 'transfer_out', 'quantity' => '-'.$positive, 'unit_cost' => '0', 'reference_type' => StockTransfer::class, 'reference_id' => $transfer->id, 'idempotency_key' => $key.':out:'.$index]); $this->movements->post(['tenant_id' => $tenantId, 'variant_id' => $lineModel->variant_id, 'location_id' => $to, 'created_by' => $userId, 'movement_type' => 'transfer_in', 'quantity' => $positive, 'unit_cost' => '0', 'reference_type' => StockTransfer::class, 'reference_id' => $transfer->id, 'idempotency_key' => $key.':in:'.$index]); } return $transfer; });
    }
}
