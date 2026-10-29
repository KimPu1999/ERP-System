<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PurchaseRequestApproved;
use App\Models\ProductVariant;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\ProcurementNumberService;
use App\Services\ProcurementStateService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PurchaseRequestController
{
    public function index(): JsonResponse { return response()->json(['data' => PurchaseRequest::where('tenant_id', app(TenantContext::class)->id())->with('items')->orderByDesc('created_at')->paginate(25)]); }
    public function store(Request $request, ProcurementNumberService $numbers, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); $data = $request->validate(['reason' => ['nullable', 'string', 'max:500'], 'items' => ['required', 'array', 'min:1'], 'items.*.variant_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]); foreach ($data['items'] as $item) abort_unless(ProductVariant::whereKey($item['variant_id'])->where('tenant_id', $tenantId)->exists(), 404); $requestModel = DB::transaction(function () use ($data, $tenantId, $request, $numbers): PurchaseRequest { $model = PurchaseRequest::create(['tenant_id' => $tenantId, 'requested_by' => $request->user()->id, 'number' => $numbers->make('PR'), 'state' => 'draft', 'reason' => $data['reason'] ?? null]); foreach ($data['items'] as $item) PurchaseRequestItem::create(['tenant_id' => $tenantId, 'purchase_request_id' => $model->id, 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity']]); return $model; }); $audit->record($request, 'purchase_request.created', $tenantId, $requestModel); return response()->json(['data' => $requestModel->load('items')], 201); }
    public function submit(Request $request, PurchaseRequest $purchaseRequest, ProcurementStateService $states, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($purchaseRequest->tenant_id === $tenantId, 404); $model = $states->transition($purchaseRequest, 'submitted', $request, $tenantId); $audit->record($request, 'purchase_request.submitted', $tenantId, $model); return response()->json(['data' => $model]); }
    public function approve(Request $request, PurchaseRequest $purchaseRequest, ProcurementStateService $states, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($purchaseRequest->tenant_id === $tenantId, 404); $model = $states->transition($purchaseRequest, 'approved', $request, $tenantId, $request->string('comment')->toString()); event(new PurchaseRequestApproved($model)); $audit->record($request, 'purchase_request.approved', $tenantId, $model); return response()->json(['data' => $model]); }
}
