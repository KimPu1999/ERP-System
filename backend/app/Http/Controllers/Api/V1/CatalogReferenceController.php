<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Brand;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CatalogReferenceController
{
    public function index(): JsonResponse { $tenantId = app(TenantContext::class)->id(); return response()->json(['categories' => ProductCategory::where('tenant_id', $tenantId)->orderBy('name')->get(), 'brands' => Brand::where('tenant_id', $tenantId)->orderBy('name')->get(), 'units' => UnitOfMeasure::where('tenant_id', $tenantId)->orderBy('name')->get()]); }
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'products.manage'), 403); $data = $request->validate(['type' => ['required', 'in:category,brand,unit'], 'name' => ['required', 'string', 'max:150'], 'code' => ['nullable', 'alpha_dash', 'max:40'], 'parent_id' => ['nullable', 'uuid'], 'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6']]); $model = match ($data['type']) { 'category' => ProductCategory::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'slug' => Str::slug($data['name']), 'parent_id' => $data['parent_id'] ?? null]), 'brand' => Brand::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'slug' => Str::slug($data['name'])]), default => UnitOfMeasure::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'code' => $data['code'], 'decimal_places' => $data['decimal_places'] ?? 3]), }; $audit->record($request, 'catalog.reference.created', $tenantId, $model, ['type' => $data['type']]); return response()->json(['data' => $model], 201); }
}
