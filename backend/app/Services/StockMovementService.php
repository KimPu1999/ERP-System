<?php

namespace App\Services;

use App\Models\InventoryAlert;
use App\Models\InventoryBalance;
use App\Models\InventorySetting;
use App\Models\StockMovement;
use App\Models\ProductVariant;
use App\Models\WarehouseLocation;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StockMovementService
{
    public function post(array $data): StockMovement
    {
        return DB::transaction(function () use ($data): StockMovement {
            if (! ProductVariant::whereKey($data['variant_id'])->where('tenant_id', $data['tenant_id'])->exists()) abort(404, 'Variant is outside the tenant context.');
            if (! WarehouseLocation::whereKey($data['location_id'])->where('tenant_id', $data['tenant_id'])->exists()) abort(404, 'Location is outside the tenant context.');
            $existing = StockMovement::where('tenant_id', $data['tenant_id'])->where('idempotency_key', $data['idempotency_key'])->first();
            if ($existing) return $existing;
            $quantity = Decimal::normalize((string) $data['quantity']);
            DB::table('inventory_balances')->insertOrIgnore(['id' => (string) \Illuminate\Support\Str::uuid(), 'tenant_id' => $data['tenant_id'], 'variant_id' => $data['variant_id'], 'location_id' => $data['location_id'], 'quantity' => '0', 'reserved_quantity' => '0', 'created_at' => now(), 'updated_at' => now()]);
            $balance = InventoryBalance::where('tenant_id', $data['tenant_id'])->where('variant_id', $data['variant_id'])->where('location_id', $data['location_id'])->lockForUpdate()->firstOrFail();
            $next = Decimal::add((string) $balance->quantity, $quantity);
            $settings = InventorySetting::where('tenant_id', $data['tenant_id'])->first();
            if (! $settings?->allow_negative_stock && Decimal::compare($next, '0') < 0) throw ValidationException::withMessages(['quantity' => ['This movement would create negative stock.']]);
            $movement = StockMovement::create([...$data, 'quantity' => $quantity, 'posted_at' => now()]);
            $balance->update(['quantity' => $next]);
            $this->refreshAlert($data['tenant_id'], $data['variant_id'], $data['location_id'], $next);
            return $movement;
        });
    }

    private function refreshAlert(string $tenantId, string $variantId, string $locationId, string $quantity): void
    {
        $rule = \App\Models\ReorderRule::where('tenant_id', $tenantId)->where('variant_id', $variantId)->where('location_id', $locationId)->first();
        if (! $rule) return;
        $alert = InventoryAlert::where('tenant_id', $tenantId)->where('variant_id', $variantId)->where('location_id', $locationId)->where('type', 'low_stock')->whereNull('resolved_at')->first();
        if (Decimal::compare($quantity, (string) $rule->reorder_point) <= 0 && ! $alert) InventoryAlert::create(['tenant_id' => $tenantId, 'variant_id' => $variantId, 'location_id' => $locationId, 'type' => 'low_stock']);
        if (Decimal::compare($quantity, (string) $rule->reorder_point) > 0 && $alert) $alert->update(['resolved_at' => now()]);
    }
}
