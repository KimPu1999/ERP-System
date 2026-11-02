<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\PurchaseRequestApproved;
use App\Models\{ProductVariant, PurchaseRequest, PurchaseRequestItem};
use App\Services\{AuditLogger, AuthorizationService, ProcurementNumberService, ProcurementStateService};
use App\Support\TenantContext;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

final class PurchaseRequestController
{
    private function tenant(): string { return (string) app(TenantContext::class)->id(); }
    private function authorize(Request $request, AuthorizationService $authorization, string $permission = 'procurement.manage'): void { abort_unless($authorization->allows($request->user(), $this->tenant(), $permission), 403); }

    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $this->authorize($request, $authorization);
        $allowedSorts = ['created_at', 'number', 'state'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true) ? $request->string('sort')->toString() : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $query = PurchaseRequest::where('tenant_id', $this->tenant())->with(['requester', 'items.variant.product'])
            ->when($request->filled('state'), fn ($q) => $q->where('state', $request->string('state')))
            ->when($request->filled('search'), fn ($q) => $q->where('number', 'ilike', '%'.$request->string('search').'%'))
            ->orderBy($sort, $direction);
        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 10), 100))]);
    }

    public function store(Request $request, ProcurementNumberService $numbers, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->authorize($request, $authorization);
        $tenantId = $this->tenant();
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500'], 'items' => ['required', 'array', 'min:1'], 'items.*.variant_id' => ['required', 'uuid'], 'items.*.quantity' => ['required', 'regex:/^(?:[1-9]\d*)(?:\.\d{1,6})?$/']]);
        foreach ($data['items'] as $item) abort_unless(ProductVariant::where('tenant_id', $tenantId)->whereKey($item['variant_id'])->where('is_active', true)->exists(), 422, 'One or more selected products are not available in this tenant.');
        $requestModel = DB::transaction(function () use ($data, $tenantId, $request, $numbers): PurchaseRequest {
            $model = PurchaseRequest::create(['tenant_id' => $tenantId, 'requested_by' => $request->user()->id, 'number' => $numbers->make('PR'), 'state' => 'draft', 'reason' => $data['reason'] ?? null]);
            foreach ($data['items'] as $item) PurchaseRequestItem::create(['tenant_id' => $tenantId, 'purchase_request_id' => $model->id, 'variant_id' => $item['variant_id'], 'quantity' => $item['quantity']]);
            return $model;
        });
        $audit->record($request, 'purchase_request.created', $tenantId, $requestModel);
        return response()->json(['data' => $requestModel->load(['requester', 'items.variant.product'])], 201);
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest, ProcurementStateService $states, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->authorize($request, $authorization); abort_unless($purchaseRequest->tenant_id === $this->tenant(), 404);
        $model = $states->transition($purchaseRequest, 'submitted', $request, $this->tenant()); $audit->record($request, 'purchase_request.submitted', $this->tenant(), $model);
        return response()->json(['data' => $model->load(['requester', 'items.variant.product'])]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest, ProcurementStateService $states, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->authorize($request, $authorization, 'procurement.approve'); abort_unless($purchaseRequest->tenant_id === $this->tenant(), 404); abort_unless($purchaseRequest->requested_by !== $request->user()->id, 403, 'The requester cannot approve the same purchase request.');
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:1000']]); $model = $states->transition($purchaseRequest, 'approved', $request, $this->tenant(), $data['comment'] ?? null); event(new PurchaseRequestApproved($model)); $audit->record($request, 'purchase_request.approved', $this->tenant(), $model);
        return response()->json(['data' => $model->load(['requester', 'items.variant.product'])]);
    }
}
