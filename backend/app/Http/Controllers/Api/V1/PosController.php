<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{Branch, Company, Customer, InventoryBalance, PosReceipt, PosReceiptItem, PosReceiptPayment, PosRegister, PosShift, ProductVariant, WarehouseLocation};
use App\Services\{AuthorizationService, OptionalModuleService, StockMovementService};
use App\Support\{Decimal, TenantContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

final class PosController
{
    public function registers(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('pos');
        [$tenant, $companies] = $this->scopeAny($request, $authorization, ['pos.manage', 'pos.sell']);
        $query = PosRegister::where('tenant_id', $tenant)
            ->when(is_array($companies), fn ($q) => $q->whereIn('company_id', $companies))
            ->when($request->filled('company_id'), fn ($q) => $q->where('company_id', $request->string('company_id')))
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', $request->string('branch_id')))
            ->orderBy('name');
        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function products(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('pos');
        [$tenant, $companies] = $this->scope($request, $authorization, 'pos.sell');
        $data = $request->validate([
            'company_id' => ['nullable', 'uuid'],
            'branch_id' => ['nullable', 'uuid'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $this->assertCompanyAndBranch($request, $authorization, $data, $tenant, 'pos.sell');

        $term = isset($data['search']) ? '%' . addcslashes($data['search'], '%_') . '%' : null;
        $query = ProductVariant::query()->where('product_variants.tenant_id', $tenant)
            ->where('product_variants.is_active', true)
            ->whereHas('product', fn ($product) => $product->where('status', 'active'))
            ->with('product:id,name,status')
            ->when($term, fn ($q) => $q->where(function ($nested) use ($term): void {
                $nested->where('product_variants.sku', 'ilike', $term)
                    ->orWhere('product_variants.internal_barcode', 'ilike', $term)
                    ->orWhere('product_variants.gs1_identifier', 'ilike', $term)
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'ilike', $term));
            }))
            ->orderBy('product_variants.sku');

        $paginator = $query->paginate($data['per_page'] ?? 25);
        $variantIds = $paginator->getCollection()->pluck('id');
        $balanceQuery = InventoryBalance::query()->where('inventory_balances.tenant_id', $tenant)->whereIn('variant_id', $variantIds);
        $balanceQuery->whereHas('location.warehouse', function ($warehouse) use ($companies, $data): void {
            $warehouse->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies))
                ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
                ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
        });
        $available = $balanceQuery->get()->groupBy('variant_id')->map(fn ($rows) => (string) $rows->sum(fn ($row) => max((float) $row->quantity - (float) $row->reserved_quantity, 0)));
        $paginator->getCollection()->transform(fn (ProductVariant $variant) => [
            'id' => $variant->id,
            'product_id' => $variant->product_id,
            'product_name' => $variant->product?->name,
            'name' => $variant->name,
            'sku' => $variant->sku,
            'internal_barcode' => $variant->internal_barcode,
            'gs1_identifier' => $variant->gs1_identifier,
            'price' => (string) ($variant->price ?? '0'),
            'available_quantity' => $available->get($variant->id, '0'),
        ]);
        return response()->json(['data' => $paginator]);
    }

    public function shifts(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('pos');
        [$tenant, $companies] = $this->scopeAny($request, $authorization, ['pos.manage', 'pos.sell']);
        $data = $request->validate(['company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'state' => ['nullable', Rule::in(['open', 'closed'])], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = PosShift::query()->where('pos_shifts.tenant_id', $tenant)->with('register:id,company_id,branch_id,name,code,status')
            ->whereHas('register', function ($register) use ($companies, $data): void {
                $register->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies))
                    ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
                    ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
            })
            ->when($data['state'] ?? null, fn ($q, $state) => $q->where('state', $state))
            ->orderByDesc('opened_at');
        return response()->json(['data' => $query->paginate($data['per_page'] ?? 25)]);
    }

    public function receipts(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('pos');
        [$tenant, $companies] = $this->scope($request, $authorization, 'pos.sell');
        $data = $request->validate(['company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid'], 'search' => ['nullable', 'string', 'max:100'], 'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = PosReceipt::query()->where('pos_receipts.tenant_id', $tenant)->where('state', 'posted')->with(['items', 'payments', 'register:id,name,code,branch_id'])
            ->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies))
            ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->whereHas('register', fn ($register) => $register->where('branch_id', $id)))
            ->when($data['search'] ?? null, fn ($q, $search) => $q->where('number', 'ilike', '%' . addcslashes($search, '%_') . '%'))
            ->when($data['date_from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($data['date_to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at');
        return response()->json(['data' => $query->paginate($data['per_page'] ?? 25)]);
    }

    public function overview(Request $request, OptionalModuleService $modules, AuthorizationService $authorization)
    {
        $modules->require('pos');
        [$tenant, $companies] = $this->scope($request, $authorization, 'pos.sell');
        $data = $request->validate(['company_id' => ['nullable', 'uuid'], 'branch_id' => ['nullable', 'uuid']]);
        $query = PosReceipt::query()->where('tenant_id', $tenant)->where('state', 'posted')
            ->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies))
            ->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($data['branch_id'] ?? null, fn ($q, $id) => $q->whereHas('register', fn ($register) => $register->where('branch_id', $id)));
        $today = now()->startOfDay();
        $todayQuery = (clone $query)->where('created_at', '>=', $today);
        $total = (string) $todayQuery->sum('total');
        $sales = $todayQuery->count();
        $openShifts = PosShift::query()->where('tenant_id', $tenant)->where('state', 'open')->whereHas('register', function ($register) use ($companies, $data): void {
            $register->when($companies !== null, fn ($q) => $q->whereIn('company_id', $companies))->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id));
        })->count();
        return response()->json(['data' => ['today' => ['sales' => $sales, 'total' => $total], 'open_shifts' => $openShifts, 'currency_code' => null, 'currency_available' => false]]);
    }

    public function storeRegister(Request $request, AuthorizationService $auth, OptionalModuleService $modules)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id();
        $data = $request->validate(['company_id' => 'required|uuid', 'branch_id' => 'nullable|uuid', 'location_id' => 'nullable|uuid', 'name' => 'required|string|max:100', 'code' => 'required|alpha_dash|max:30', 'payment_methods' => 'nullable|array']);
        abort_unless($auth->allows($request->user(), $tenant, 'pos.manage', $data['company_id']), 403); abort_unless(Company::where('tenant_id', $tenant)->whereKey($data['company_id'])->exists(), 404);
        if (! empty($data['branch_id'])) abort_unless(Branch::where('tenant_id', $tenant)->where('company_id', $data['company_id'])->whereKey($data['branch_id'])->exists(), 404);
        if (! empty($data['location_id'])) abort_unless(WarehouseLocation::where('tenant_id', $tenant)->whereKey($data['location_id'])->whereHas('warehouse', function ($warehouse) use ($data): void { $warehouse->where('company_id', $data['company_id'])->when($data['branch_id'] ?? null, fn ($q, $id) => $q->where('branch_id', $id)); })->exists(), 404);
        return response()->json(['data' => PosRegister::create([...$data, 'tenant_id' => $tenant])], 201);
    }

    public function openShift(Request $request, PosRegister $register, OptionalModuleService $modules, AuthorizationService $auth)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id(); abort_unless($register->tenant_id === $tenant, 404);
        abort_unless($auth->allows($request->user(), $tenant, 'pos.manage', $register->company_id, $register->branch_id), 403); abort_unless($register->status === 'active', 422, 'The register is not active.');
        $data = $request->validate(['opening_cash' => 'required|numeric|min:0']);
        $shift = DB::transaction(function () use ($register, $tenant, $request, $data): PosShift {
            $lockedRegister = PosRegister::whereKey($register->id)->lockForUpdate()->firstOrFail();
            abort_unless(! PosShift::where('tenant_id', $tenant)->where('register_id', $lockedRegister->id)->where('state', 'open')->lockForUpdate()->exists(), 422, 'The register already has an open shift.');
            return PosShift::create(['tenant_id' => $tenant, 'register_id' => $lockedRegister->id, 'cashier_id' => $request->user()->id, 'opening_cash' => $data['opening_cash'], 'state' => 'open', 'opened_at' => now()]);
        });
        return response()->json(['data' => $shift], 201);
    }

    public function sell(Request $request, PosRegister $register, StockMovementService $movements, OptionalModuleService $modules, AuthorizationService $auth)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id(); abort_unless($register->tenant_id === $tenant, 404); abort_unless($auth->allows($request->user(), $tenant, 'pos.sell', $register->company_id, $register->branch_id), 403); abort_unless($register->status === 'active', 422, 'The register is not active.');
        $data = $request->validate(['shift_id' => 'required|uuid', 'customer_id' => 'nullable|uuid', 'idempotency_key' => 'required|string|max:100', 'items' => 'required|array|min:1', 'items.*.variant_id' => 'required|uuid', 'items.*.quantity' => 'required|numeric|gt:0', 'items.*.unit_price' => 'nullable|numeric|min:0', 'items.*.tax_rate' => 'nullable|numeric|min:0', 'payments' => 'required|array|min:1', 'payments.*.method' => ['required', Rule::in(['cash'])], 'payments.*.amount' => 'required|numeric|gt:0', 'payments.*.reference' => 'nullable|string|max:120']);
        $shift = PosShift::where('tenant_id', $tenant)->where('register_id', $register->id)->whereKey($data['shift_id'])->where('state', 'open')->firstOrFail(); if ($existing = PosReceipt::where('tenant_id', $tenant)->where('idempotency_key', $data['idempotency_key'])->first()) return response()->json(['data' => $existing]);
        if (! empty($data['customer_id'])) abort_unless(Customer::where('tenant_id', $tenant)->whereKey($data['customer_id'])->where(function ($q) use ($register): void { $q->whereNull('company_id')->orWhere('company_id', $register->company_id); })->exists(), 404);
        abort_unless($register->location_id && WarehouseLocation::where('tenant_id', $tenant)->whereKey($register->location_id)->whereHas('warehouse', function ($warehouse) use ($register): void { $warehouse->where('company_id', $register->company_id)->when($register->branch_id, fn ($q, $id) => $q->where('branch_id', $id)); })->exists(), 422, 'A stock location belonging to the register company is required.');
        $alreadyPosted = false;
        $receipt = DB::transaction(function () use ($data, $register, $shift, $request, $movements, $tenant, &$alreadyPosted) {
            PosRegister::whereKey($register->id)->lockForUpdate()->firstOrFail();
            if ($existing = PosReceipt::where('tenant_id', $tenant)->where('idempotency_key', $data['idempotency_key'])->first()) { $alreadyPosted = true; return $existing->load(['items', 'payments']); }
            $subtotal = '0'; $tax = '0'; $lines = [];
            foreach ($data['items'] as $line) { $variant = ProductVariant::where('tenant_id', $tenant)->whereKey($line['variant_id'])->where('is_active', true)->whereHas('product', fn ($product) => $product->where('status', 'active'))->firstOrFail(); $quantity = Decimal::normalize((string) $line['quantity']); $price = Decimal::normalize((string) ($variant->price ?? '0')); $rate = '0'; $lineSubtotal = Decimal::mul($quantity, $price); $taxAmount = '0'; $subtotal = Decimal::add($subtotal, $lineSubtotal); $tax = Decimal::add($tax, $taxAmount); $lines[] = ['variant' => $variant, 'quantity' => $quantity, 'unit_price' => $price, 'tax_rate' => $rate, 'tax_amount' => $taxAmount, 'line_total' => Decimal::add($lineSubtotal, $taxAmount)]; }
            $total = Decimal::add($subtotal, $tax); $paid = '0'; foreach ($data['payments'] as $payment) $paid = Decimal::add($paid, Decimal::normalize((string) $payment['amount'])); if (Decimal::compare($paid, $total) !== 0) throw ValidationException::withMessages(['payments' => ['POS payments must equal the receipt total.']]);
            $receipt = PosReceipt::create(['tenant_id' => $tenant, 'company_id' => $register->company_id, 'register_id' => $register->id, 'shift_id' => $shift->id, 'customer_id' => $data['customer_id'] ?? null, 'cashier_id' => $request->user()->id, 'number' => 'POS-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)), 'subtotal' => $subtotal, 'tax_total' => $tax, 'total' => $total, 'idempotency_key' => $data['idempotency_key']]);
            foreach ($lines as $index => $line) { PosReceiptItem::create(['tenant_id' => $tenant, 'receipt_id' => $receipt->id, 'variant_id' => $line['variant']->id, 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'tax_rate' => $line['tax_rate'], 'tax_amount' => $line['tax_amount'], 'line_total' => $line['line_total']]); $movements->post(['tenant_id' => $tenant, 'variant_id' => $line['variant']->id, 'location_id' => $register->location_id, 'created_by' => $request->user()->id, 'movement_type' => 'pos_sale', 'quantity' => Decimal::sub('0', $line['quantity']), 'unit_cost' => $line['variant']->cost ?? '0', 'reference_type' => PosReceipt::class, 'reference_id' => $receipt->id, 'idempotency_key' => 'pos:'.$receipt->id.':'.$index]); }
            foreach ($data['payments'] as $payment) PosReceiptPayment::create(['tenant_id' => $tenant, 'receipt_id' => $receipt->id, 'method' => $payment['method'], 'amount' => $payment['amount'], 'reference' => $payment['reference'] ?? null, 'state' => 'verified']); return $receipt->load(['items', 'payments']);
        });
        if (! $alreadyPosted) event(new \App\Events\PosReceiptPosted($tenant, $receipt->id, $receipt->company_id, (string) $receipt->total)); return response()->json(['data' => $receipt], $alreadyPosted ? 200 : 201);
    }

    public function closeShift(Request $request, PosShift $shift, OptionalModuleService $modules, AuthorizationService $auth)
    {
        $modules->require('pos'); $tenant = app(TenantContext::class)->id(); abort_unless($shift->tenant_id === $tenant, 404); $register = $shift->register()->firstOrFail(); abort_unless($auth->allows($request->user(), $tenant, 'pos.manage', $register->company_id, $register->branch_id), 403); abort_unless($shift->state === 'open', 422, 'Shift is already closed.'); $data = $request->validate(['closing_cash' => 'required|numeric|min:0', 'closing_notes' => 'nullable|string']); $shift->update([...$data, 'state' => 'closed', 'closed_at' => now()]); return response()->json(['data' => $shift->fresh()]);
    }

    private function scope(Request $request, AuthorizationService $authorization, string $permission): array
    {
        $tenant = (string) app(TenantContext::class)->id(); $company = $request->string('company_id')->toString() ?: null; $branch = $request->string('branch_id')->toString() ?: null; if ($company) { abort_unless($authorization->allows($request->user(), $tenant, $permission, $company, $branch), 403); return [$tenant, [$company]]; } $companies = $authorization->accessibleCompanyIds($request->user(), $tenant, $permission); abort_unless($companies === null || $companies !== [], 403); if ($branch) { $branchCompany = Branch::where('tenant_id', $tenant)->whereKey($branch)->value('company_id'); abort_unless($branchCompany && ($companies === null || in_array($branchCompany, $companies, true)) && $authorization->allows($request->user(), $tenant, $permission, $branchCompany, $branch), 403); } return [$tenant, $companies];
    }

    private function scopeAny(Request $request, AuthorizationService $authorization, array $permissions): array
    {
        foreach ($permissions as $permission) {
            try {
                return $this->scope($request, $authorization, $permission);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                if ($exception->getStatusCode() !== 403) throw $exception;
            }
        }
        abort(403);
    }

    private function assertCompanyAndBranch(Request $request, AuthorizationService $authorization, array $data, string $tenant, string $permission): void
    {
        if (! empty($data['company_id'])) abort_unless($authorization->allows($request->user(), $tenant, $permission, $data['company_id'], $data['branch_id'] ?? null), 403);
        if (! empty($data['branch_id'])) abort_unless(Branch::where('tenant_id', $tenant)->whereKey($data['branch_id'])->when($data['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))->exists(), 404);
    }
}
