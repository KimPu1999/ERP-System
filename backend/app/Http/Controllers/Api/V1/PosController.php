<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Branch, Company, PosReceipt, PosReceiptItem, PosReceiptPayment, PosRegister, PosShift, ProductVariant, WarehouseLocation};
use App\Services\{AuthorizationService, OptionalModuleService, StockMovementService};
use App\Support\{Decimal, TenantContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PosController
{
    public function registers(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('pos');
        [$tenant, $companies] = $this->scope($request, $authorization, 'pos.manage');
        $query = PosRegister::where('tenant_id', $tenant)
            ->when(is_array($companies), fn ($q) => $q->whereIn('company_id', $companies))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->string('branch_id')))
            ->orderBy('name');
        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function storeRegister(Request $request, AuthorizationService $auth, OptionalModuleService $modules)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id();
        $data = $request->validate(['company_id' => 'required|uuid', 'branch_id' => 'nullable|uuid', 'location_id' => 'nullable|uuid', 'name' => 'required|string|max:100', 'code' => 'required|alpha_dash|max:30', 'payment_methods' => 'nullable|array']);
        abort_unless($auth->allows($request->user(), $tenant, 'pos.manage', $data['company_id']), 403); abort_unless(Company::where('tenant_id', $tenant)->whereKey($data['company_id'])->exists(), 404);
        if (! empty($data['branch_id'])) abort_unless(Branch::where('tenant_id', $tenant)->where('company_id', $data['company_id'])->whereKey($data['branch_id'])->exists(), 404);
        if (! empty($data['location_id'])) abort_unless(WarehouseLocation::where('tenant_id', $tenant)->whereKey($data['location_id'])->exists(), 404);
        return response()->json(['data' => PosRegister::create([...$data, 'tenant_id' => $tenant])], 201);
    }

    public function openShift(Request $request, PosRegister $register, OptionalModuleService $modules, AuthorizationService $auth)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id(); abort_unless($register->tenant_id === $tenant, 404);
        abort_unless($auth->allows($request->user(), $tenant, 'pos.manage', $register->company_id, $register->branch_id), 403); abort_unless(! PosShift::where('register_id', $register->id)->where('state', 'open')->exists(), 422, 'The register already has an open shift.');
        $data = $request->validate(['opening_cash' => 'required|numeric|min:0']);
        return response()->json(['data' => PosShift::create(['tenant_id' => $tenant, 'register_id' => $register->id, 'cashier_id' => $request->user()->id, 'opening_cash' => $data['opening_cash'], 'state' => 'open', 'opened_at' => now()])], 201);
    }

    public function sell(Request $request, PosRegister $register, StockMovementService $movements, OptionalModuleService $modules, AuthorizationService $auth)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id(); abort_unless($register->tenant_id === $tenant, 404); abort_unless($auth->allows($request->user(), $tenant, 'pos.sell', $register->company_id, $register->branch_id), 403);
        $data = $request->validate(['shift_id' => 'required|uuid', 'customer_id' => 'nullable|uuid', 'idempotency_key' => 'required|string|max:100', 'items' => 'required|array|min:1', 'items.*.variant_id' => 'required|uuid', 'items.*.quantity' => 'required|numeric|gt:0', 'items.*.unit_price' => 'required|numeric|min:0', 'items.*.tax_rate' => 'nullable|numeric|min:0', 'payments' => 'required|array|min:1', 'payments.*.method' => 'required|string|max:40', 'payments.*.amount' => 'required|numeric|gt:0']);
        $shift = PosShift::where('tenant_id', $tenant)->where('register_id', $register->id)->whereKey($data['shift_id'])->where('state', 'open')->firstOrFail(); if ($existing = PosReceipt::where('tenant_id', $tenant)->where('idempotency_key', $data['idempotency_key'])->first()) return response()->json(['data' => $existing]);
        $receipt = DB::transaction(function () use ($data, $register, $shift, $request, $movements, $tenant) {
            $subtotal = '0'; $tax = '0'; $lines = [];
            foreach ($data['items'] as $line) { $variant = ProductVariant::where('tenant_id', $tenant)->whereKey($line['variant_id'])->firstOrFail(); $quantity = Decimal::normalize((string) $line['quantity']); $price = Decimal::normalize((string) $line['unit_price']); $rate = Decimal::normalize((string) ($line['tax_rate'] ?? 0)); $lineSubtotal = Decimal::mul($quantity, $price); $taxAmount = Decimal::div(Decimal::mul($lineSubtotal, $rate), '100'); $subtotal = Decimal::add($subtotal, $lineSubtotal); $tax = Decimal::add($tax, $taxAmount); $lines[] = ['variant' => $variant, 'quantity' => $quantity, 'unit_price' => $price, 'tax_rate' => $rate, 'tax_amount' => $taxAmount, 'line_total' => Decimal::add($lineSubtotal, $taxAmount)]; }
            $total = Decimal::add($subtotal, $tax); $paid = '0'; foreach ($data['payments'] as $payment) $paid = Decimal::add($paid, Decimal::normalize((string) $payment['amount'])); if (Decimal::compare($paid, $total) !== 0) throw ValidationException::withMessages(['payments' => ['POS payments must equal the receipt total.']]);
            $receipt = PosReceipt::create(['tenant_id' => $tenant, 'company_id' => $register->company_id, 'register_id' => $register->id, 'shift_id' => $shift->id, 'customer_id' => $data['customer_id'] ?? null, 'cashier_id' => $request->user()->id, 'number' => 'POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)), 'subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $total, 'idempotency_key' => $data['idempotency_key']]);
            foreach ($lines as $index => $line) { if (! $register->location_id) throw ValidationException::withMessages(['register' => ['A stock location is required for stock-tracked POS sales.']]); PosReceiptItem::create(['tenant_id' => $tenant, 'receipt_id' => $receipt->id, 'variant_id' => $line['variant']->id, 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'tax_rate' => $line['tax_rate'], 'tax_amount' => $line['tax_amount'], 'line_total' => $line['line_total']]); $movements->post(['tenant_id' => $tenant, 'variant_id' => $line['variant']->id, 'location_id' => $register->location_id, 'created_by' => $request->user()->id, 'movement_type' => 'pos_sale', 'quantity' => Decimal::sub('0', $line['quantity']), 'unit_cost' => $line['variant']->cost ?? '0', 'reference_type' => PosReceipt::class, 'reference_id' => $receipt->id, 'idempotency_key' => 'pos:'.$receipt->id.':'.$index]); }
            foreach ($data['payments'] as $payment) PosReceiptPayment::create(['tenant_id' => $tenant, 'receipt_id' => $receipt->id, 'method' => $payment['method'], 'amount' => $payment['amount'], 'reference' => $payment['reference'] ?? null, 'state' => 'verified']); return $receipt->load(['items', 'payments']);
        });
        event(new \App\Events\PosReceiptPosted($tenant, $receipt->id, $receipt->company_id, (string) $receipt->total)); return response()->json(['data' => $receipt], 201);
    }

    public function closeShift(Request $request, PosShift $shift, OptionalModuleService $modules, AuthorizationService $auth)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id(); abort_unless($shift->tenant_id === $tenant, 404); $register = $shift->register()->firstOrFail(); abort_unless($auth->allows($request->user(), $tenant, 'pos.manage', $register->company_id, $register->branch_id), 403); abort_unless($shift->state === 'open', 422, 'Shift is already closed.'); $data = $request->validate(['closing_cash' => 'required|numeric|min:0', 'closing_notes' => 'nullable|string']); $shift->update([...$data, 'state' => 'closed', 'closed_at' => now()]); return response()->json(['data' => $shift->fresh()]);
    }

    private function scope(Request $request, AuthorizationService $authorization, string $permission): array
    {
        $tenant = (string) app(TenantContext::class)->id(); $company = $request->string('company_id')->toString() ?: null; if ($company) { abort_unless($authorization->allows($request->user(), $tenant, $permission, $company), 403); return [$tenant, [$company]]; } $companies = $authorization->accessibleCompanyIds($request->user(), $tenant, $permission); abort_unless($companies === null || $companies !== [], 403); return [$tenant, $companies];
    }
}
