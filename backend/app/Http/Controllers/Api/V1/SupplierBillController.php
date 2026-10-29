<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\SupplierBillRecorded;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\ProcurementNumberService;
use App\Services\ProcurementStateService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupplierBillController
{
    public function index(): JsonResponse { return response()->json(['data' => SupplierBill::where('tenant_id', app(TenantContext::class)->id())->orderByDesc('created_at')->paginate(25)]); }
    public function store(Request $request, ProcurementNumberService $numbers, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403); $data = $request->validate(['supplier_id' => ['required', 'uuid'], 'purchase_order_id' => ['nullable', 'uuid'], 'supplier_reference' => ['nullable', 'string', 'max:100'], 'subtotal' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'tax_total' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'total' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); abort_unless(Supplier::whereKey($data['supplier_id'])->where('tenant_id', $tenantId)->exists(), 404); if (! empty($data['purchase_order_id'])) abort_unless(PurchaseOrder::whereKey($data['purchase_order_id'])->where('tenant_id', $tenantId)->exists(), 404); $bill = SupplierBill::create([...$data, 'tenant_id' => $tenantId, 'number' => $numbers->make('BILL'), 'state' => 'draft']); $audit->record($request, 'supplier_bill.created', $tenantId, $bill); return response()->json(['data' => $bill], 201); }
    public function submit(Request $request, SupplierBill $supplierBill, ProcurementStateService $states): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($supplierBill->tenant_id === $tenantId, 404); return response()->json(['data' => $states->transition($supplierBill, 'submitted', $request, $tenantId)]); }
    public function approve(Request $request, SupplierBill $supplierBill, ProcurementStateService $states, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($supplierBill->tenant_id === $tenantId, 404); $bill = $states->transition($supplierBill, 'approved', $request, $tenantId); event(new SupplierBillRecorded($bill)); $audit->record($request, 'supplier_bill.approved', $tenantId, $bill); return response()->json(['data' => $bill]); }
}
