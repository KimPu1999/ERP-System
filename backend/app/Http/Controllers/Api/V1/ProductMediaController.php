<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ProductMediaController
{
    public function store(Request $request, Product $product, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { $tenantId = app(TenantContext::class)->id(); abort_unless($product->tenant_id === $tenantId, 404); abort_unless($authorization->allows($request->user(), $tenantId, 'products.manage'), 403); $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,webp,pdf'], 'kind' => ['sometimes', 'in:image,attachment']]); $path = $request->file('file')->store('tenant/'.$tenantId.'/products', 'public'); $media = ProductMedia::create(['tenant_id' => $tenantId, 'product_id' => $product->id, 'path' => $path, 'mime_type' => $request->file('file')->getMimeType(), 'kind' => $data['kind'] ?? 'image']); $audit->record($request, 'product.media.created', $tenantId, $media, ['product_id' => $product->id]); return response()->json(['data' => [...$media->toArray(), 'url' => Storage::disk('public')->url($path)]], 201); }
}
