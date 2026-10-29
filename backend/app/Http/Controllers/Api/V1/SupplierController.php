<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupplierController
{
    public function index(Request $request): JsonResponse { $query = Supplier::where('tenant_id', app(TenantContext::class)->id())->when($request->filled('search'), fn ($query) => $query->where('name', 'ilike', '%'.$request->string('search').'%'))->orderBy('name'); return response()->json(['data' => $query->paginate(min($request->integer('per_page', 25), 100))]); }
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403); $data = $request->validate(['name' => ['required', 'string', 'max:200'], 'code' => ['required', 'alpha_dash', 'max:50'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string'], 'tax_identifier' => ['nullable', 'string', 'max:100']]); $supplier = Supplier::create([...$data, 'tenant_id' => $tenantId]); $audit->record($request, 'supplier.created', $tenantId, $supplier); return response()->json(['data' => $supplier], 201); }
    public function update(Request $request, Supplier $supplier, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($supplier->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'procurement.manage'), 403); $data = $request->validate(['name' => ['sometimes', 'string', 'max:200'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string'], 'tax_identifier' => ['nullable', 'string', 'max:100'], 'status' => ['sometimes', 'in:active,inactive']]); $supplier->update($data); $audit->record($request, 'supplier.updated', $tenantId, $supplier); return response()->json(['data' => $supplier->fresh()]); }
}
