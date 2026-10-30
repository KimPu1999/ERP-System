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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CatalogReferenceController
{
    public function index(): JsonResponse { $tenantId = app(TenantContext::class)->id(); return response()->json(['categories' => ProductCategory::where('tenant_id', $tenantId)->orderBy('name')->get(), 'brands' => Brand::where('tenant_id', $tenantId)->orderBy('name')->get(), 'units' => UnitOfMeasure::where('tenant_id', $tenantId)->orderBy('name')->get()]); }
    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'products.manage'), 403); $data = $request->validate(['type' => ['required', 'in:category,brand,unit'], 'name' => ['required', 'string', 'max:150'], 'code' => ['nullable', 'alpha_dash', 'max:40'], 'parent_id' => ['nullable', 'uuid'], 'decimal_places' => ['nullable', 'integer', 'min:0', 'max:6']]); $slug = Str::slug($data['name']); abort_if($slug === '' && in_array($data['type'], ['category', 'brand'], true), 422, 'A valid name is required.'); if (in_array($data['type'], ['category', 'brand'], true)) { $exists = DB::table($data['type'] === 'brand' ? 'brands' : 'product_categories')->where('tenant_id', $tenantId)->where('slug', $slug)->exists(); abort_if($exists, 422, 'A reference with this name already exists in this workspace.'); } if ($data['type'] === 'category' && ! empty($data['parent_id'])) abort_unless(ProductCategory::whereKey($data['parent_id'])->where('tenant_id', $tenantId)->exists(), 422, 'The parent category is not available in this workspace.'); if ($data['type'] === 'unit') { abort_if(empty($data['code']), 422, 'A unit code is required.'); abort_if(UnitOfMeasure::where('tenant_id', $tenantId)->where('code', $data['code'])->exists(), 422, 'A unit with this code already exists.'); } $model = match ($data['type']) { 'category' => ProductCategory::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'slug' => $slug, 'parent_id' => $data['parent_id'] ?? null]), 'brand' => Brand::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'slug' => $slug]), default => UnitOfMeasure::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'code' => $data['code'], 'decimal_places' => $data['decimal_places'] ?? 3]), }; $audit->record($request, 'catalog.reference.created', $tenantId, $model, ['type' => $data['type']]); return response()->json(['data' => $model], 201); }
}
