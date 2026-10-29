<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Rfq;
use App\Models\Supplier;
use App\Models\SupplierQuotation;
use App\Models\SupplierQuotationItem;
use App\Models\ProductVariant;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\ProcurementNumberService;
use App\Services\ProcurementStateService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RfqController
{
    public function index(): JsonResponse { return response()->json(['data' => Rfq::where('tenant_id', app(TenantContext::class)->id())->orderByDesc('created_at')->paginate(25)]); }
    public function store(Request $request, ProcurementNumberService $numbers, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403); $data = $request->validate(['purchase_request_id' => ['nullable', 'uuid'], 'quote_due_at' => ['nullable', 'date'], 'supplier_ids' => ['required', 'array', 'min:1'], 'supplier_ids.*' => ['uuid']]); foreach ($data['supplier_ids'] as $supplierId) abort_unless(Supplier::whereKey($supplierId)->where('tenant_id', $tenantId)->exists(), 404); $rfq = DB::transaction(function () use ($data, $tenantId, $numbers): Rfq { $rfq = Rfq::create(['tenant_id' => $tenantId, 'purchase_request_id' => $data['purchase_request_id'] ?? null, 'number' => $numbers->make('RFQ'), 'state' => 'draft', 'quote_due_at' => $data['quote_due_at'] ?? null]); $rfq->suppliers()->sync($data['supplier_ids']); return $rfq; }); $audit->record($request, 'rfq.created', $tenantId, $rfq); return response()->json(['data' => $rfq], 201); }
    public function send(Request $request, Rfq $rfq, ProcurementStateService $states): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($rfq->tenant_id === $tenantId, 404); return response()->json(['data' => $states->transition($rfq, 'sent', $request, $tenantId)]); }
    public function quote(Request $request, Rfq $rfq, ProcurementNumberService $numbers, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($rfq->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403); $data = $request->validate(['supplier_id' => ['required', 'uuid'], 'valid_until' => ['nullable', 'date'], 'items' => ['required', 'array', 'min:1'], 'items.*.variant_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'items.*.unit_price' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); abort_unless(Supplier::whereKey($data['supplier_id'])->where('tenant_id', $tenantId)->exists(), 404); foreach ($data['items'] as $item) abort_unless(ProductVariant::whereKey($item['variant_id'])->where('tenant_id', $tenantId)->exists(), 404); $quote = DB::transaction(function () use ($data, $rfq, $tenantId, $numbers): SupplierQuotation { $quote = SupplierQuotation::create(['tenant_id' => $tenantId, 'rfq_id' => $rfq->id, 'supplier_id' => $data['supplier_id'], 'number' => $numbers->make('QUOTE'), 'state' => 'submitted', 'valid_until' => $data['valid_until'] ?? null]); foreach ($data['items'] as $item) SupplierQuotationItem::create(['tenant_id' => $tenantId, 'quotation_id' => $quote->id, ...$item]); return $quote; }); $audit->record($request, 'supplier_quotation.submitted', $tenantId, $quote); return response()->json(['data' => $quote], 201); }
}
