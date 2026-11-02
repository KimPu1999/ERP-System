<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\UnitOfMeasure;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\BarcodeService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductController
{
    public function index(Request $request): JsonResponse
    {
        $allowedSorts = ['name', 'status', 'created_at', 'updated_at'];
        $sort = in_array($request->string('sort')->toString(), $allowedSorts, true) ? $request->string('sort')->toString() : 'name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';
        $search = $request->string('search')->toString();
        $query = Product::query()->where('tenant_id', app(TenantContext::class)->id())->with(['variants', 'brand'])
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('name', 'ilike', "%{$search}%")->orWhereHas('variants', fn ($variant) => $variant->where('name', 'ilike', "%{$search}%")->orWhere('sku', 'ilike', "%{$search}%")->orWhere('internal_barcode', 'ilike', "%{$search}%")->orWhere('gs1_identifier', 'ilike', "%{$search}%"))))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy($sort, $direction);

        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 25), 1), 100))]);
    }

    public function store(Request $request, AuthorizationService $authorization, BarcodeService $barcodes, AuditLogger $audit): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'products.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:200'], 'slug' => ['nullable', 'alpha_dash', 'max:200'], 'description' => ['nullable', 'string'], 'category_id' => ['nullable', 'uuid'], 'brand_id' => ['nullable', 'uuid'], 'status' => ['sometimes', 'in:active,inactive'], 'variant' => ['required', 'array'], 'variant.name' => ['nullable', 'string', 'max:150'], 'variant.sku' => ['required', 'alpha_dash', 'max:80'], 'variant.gs1_identifier' => ['nullable', 'string', 'max:32'], 'variant.unit_id' => ['required', 'uuid'], 'variant.cost' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'variant.price' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]);
        if (ProductVariant::where('tenant_id', $tenantId)->where('sku', $data['variant']['sku'])->exists()) {
            throw ValidationException::withMessages(['variant.sku' => ['A product SKU with this value already exists in this workspace.']]);
        }
        if (! empty($data['variant']['gs1_identifier']) && ProductVariant::where('tenant_id', $tenantId)->where('gs1_identifier', $data['variant']['gs1_identifier'])->exists()) {
            throw ValidationException::withMessages(['variant.gs1_identifier' => ['A product barcode with this value already exists in this workspace.']]);
        }
        $this->assertReferences($tenantId, $data);
        $product = DB::transaction(function () use ($data, $tenantId, $barcodes): Product { $product = Product::create(['tenant_id' => $tenantId, 'name' => $data['name'], 'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(6)), 'description' => $data['description'] ?? null, 'category_id' => $data['category_id'] ?? null, 'brand_id' => $data['brand_id'] ?? null, 'status' => $data['status'] ?? 'active']); $variant = $data['variant']; $product->variants()->create(['tenant_id' => $tenantId, 'unit_id' => $variant['unit_id'], 'name' => $variant['name'] ?? null, 'sku' => $variant['sku'], 'internal_barcode' => $barcodes->internal($tenantId), 'gs1_identifier' => $variant['gs1_identifier'] ?? null, 'cost' => $variant['cost'], 'price' => $variant['price'], 'is_active' => true]); return $product->load('variants'); });
        $audit->record($request, 'product.created', $tenantId, $product); return response()->json(['data' => $product->load('brand')], 201);
    }

    public function update(Request $request, Product $product, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        abort_unless($product->tenant_id === $tenantId, 404);
        abort_unless($authorization->allows($request->user(), $tenantId, 'products.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:200'], 'slug' => ['nullable', 'alpha_dash', 'max:200'], 'description' => ['nullable', 'string'], 'category_id' => ['nullable', 'uuid'], 'brand_id' => ['nullable', 'uuid'], 'status' => ['sometimes', 'in:active,inactive'], 'variant' => ['required', 'array'], 'variant.name' => ['nullable', 'string', 'max:150'], 'variant.sku' => ['required', 'alpha_dash', 'max:80'], 'variant.gs1_identifier' => ['nullable', 'string', 'max:32'], 'variant.unit_id' => ['required', 'uuid'], 'variant.cost' => ['required', 'regex:/^\d+(\.\d{1,6})?$/'], 'variant.price' => ['required', 'regex:/^\d+(\.\d{1,6})?$/']]);
        $variant = $product->variants()->firstOrFail();
        if (ProductVariant::where('tenant_id', $tenantId)->where('sku', $data['variant']['sku'])->where('id', '<>', $variant->id)->exists()) {
            throw ValidationException::withMessages(['variant.sku' => ['A product SKU with this value already exists in this workspace.']]);
        }
        if (! empty($data['variant']['gs1_identifier']) && ProductVariant::where('tenant_id', $tenantId)->where('gs1_identifier', $data['variant']['gs1_identifier'])->where('id', '<>', $variant->id)->exists()) {
            throw ValidationException::withMessages(['variant.gs1_identifier' => ['A product barcode with this value already exists in this workspace.']]);
        }
        $this->assertReferences($tenantId, $data);
        $updated = DB::transaction(function () use ($data, $product, $variant): Product {
            $product->update(['name' => $data['name'], 'slug' => $data['slug'] ?? $product->slug, 'description' => $data['description'] ?? null, 'category_id' => $data['category_id'] ?? null, 'brand_id' => $data['brand_id'] ?? null, 'status' => $data['status'] ?? $product->status]);
            $variant->update(['unit_id' => $data['variant']['unit_id'], 'name' => $data['variant']['name'] ?? null, 'sku' => $data['variant']['sku'], 'gs1_identifier' => $data['variant']['gs1_identifier'] ?? null, 'cost' => $data['variant']['cost'], 'price' => $data['variant']['price'], 'is_active' => ($data['status'] ?? $product->status) === 'active']);
            return $product->fresh(['variants', 'brand']);
        });
        $audit->record($request, 'product.updated', $tenantId, $updated);
        return response()->json(['data' => $updated]);
    }

    public function export(Request $request): Response
    {
        $tenantId = app(TenantContext::class)->id(); $products = Product::where('tenant_id', $tenantId)->with('variants')->orderBy('name')->cursor(); return response()->streamDownload(function () use ($products): void { $out = fopen('php://output', 'w'); fputcsv($out, ['product_name', 'sku', 'internal_barcode', 'gs1_identifier', 'cost', 'price', 'status']); foreach ($products as $product) foreach ($product->variants as $variant) fputcsv($out, [$product->name, $variant->sku, $variant->internal_barcode, $variant->gs1_identifier, $variant->cost, $variant->price, $product->status]); fclose($out); }, 'products.csv', ['Content-Type' => 'text/csv']);
    }

    public function import(Request $request, AuthorizationService $authorization, BarcodeService $barcodes, AuditLogger $audit): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id(); abort_unless($authorization->allows($request->user(), $tenantId, 'products.manage'), 403); $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]); $handle = fopen($request->file('file')->getRealPath(), 'rb'); $headers = fgetcsv($handle); $expected = ['product_name', 'sku', 'unit_code', 'cost', 'price', 'gs1_identifier']; abort_unless($headers === $expected, 422, 'CSV headers must be: '.implode(',', $expected)); $rows = []; $errors = []; $line = 1; while (($row = fgetcsv($handle)) !== false) { $line++; if (count($row) !== count($expected)) { $errors[$line] = 'Invalid column count.'; continue; } $record = array_combine($expected, $row); if (! preg_match('/^\d+(\.\d{1,6})?$/', $record['cost']) || ! preg_match('/^\d+(\.\d{1,6})?$/', $record['price']) || $record['product_name'] === '' || $record['sku'] === '') { $errors[$line] = 'Product name, SKU, cost, and price are required and must be valid.'; continue; } $unit = UnitOfMeasure::where('tenant_id', $tenantId)->where('code', $record['unit_code'])->first(); if (! $unit) { $errors[$line] = 'Unknown tenant unit code.'; continue; } $rows[] = [$record, $unit]; } fclose($handle); if ($errors) return response()->json(['message' => 'CSV validation failed.', 'errors' => $errors], 422); DB::transaction(function () use ($rows, $tenantId, $barcodes): void { foreach ($rows as [$record, $unit]) { $product = Product::create(['tenant_id' => $tenantId, 'name' => $record['product_name'], 'slug' => Str::slug($record['product_name']).'-'.Str::lower(Str::random(6)), 'status' => 'active']); $product->variants()->create(['tenant_id' => $tenantId, 'unit_id' => $unit->id, 'sku' => $record['sku'], 'internal_barcode' => $barcodes->internal($tenantId), 'gs1_identifier' => $record['gs1_identifier'] ?: null, 'cost' => $record['cost'], 'price' => $record['price'], 'is_active' => true]); } }); $audit->record($request, 'product.bulk_imported', $tenantId, null, ['rows' => count($rows)]); return response()->json(['imported' => count($rows)]);
    }

    private function assertReferences(string $tenantId, array $data): void
    {
        $errors = [];
        if (! empty($data['category_id']) && ! ProductCategory::where('tenant_id', $tenantId)->whereKey($data['category_id'])->exists()) {
            $errors['category_id'] = ['The selected category is not available in this workspace.'];
        }
        if (! empty($data['brand_id']) && ! Brand::where('tenant_id', $tenantId)->whereKey($data['brand_id'])->exists()) {
            $errors['brand_id'] = ['The selected brand is not available in this workspace.'];
        }
        if (! UnitOfMeasure::where('tenant_id', $tenantId)->whereKey($data['variant']['unit_id'])->exists()) {
            $errors['variant.unit_id'] = ['The selected unit is not available in this workspace.'];
        }
        if ($errors) throw ValidationException::withMessages($errors);
    }
}
