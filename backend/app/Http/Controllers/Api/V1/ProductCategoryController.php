<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ProductCategory;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

final class ProductCategoryController
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) app(TenantContext::class)->id();
        abort_unless($authorization->allows($request->user(), $tenantId, 'products.manage'), 403);

        $allowedSorts = ['name', 'slug', 'created_at', 'updated_at'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true) ? $request->string('sort')->toString() : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $query = ProductCategory::query()->where('tenant_id', $tenantId)
            ->with('parent:id,name,slug')
            ->withCount(['products as direct_product_count', 'children as subcategory_count'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn ($nested) => $nested->where('name', 'ilike', "%{$search}%")->orWhere('slug', 'ilike', "%{$search}%"));
            })
            ->when($request->filled('parent_id'), fn ($query) => $query->where('parent_id', $request->string('parent_id')->toString()))
            ->when($request->filled('status') && Schema::hasColumn('product_categories', 'status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->orderBy($sort, $direction);

        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }
}
