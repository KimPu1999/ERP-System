<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Company;
use App\Models\CompanyDocument;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class CompanyController
{
    public function index(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $companyIds = [];
        $allCompanies = false;
        foreach (['organization.manage', 'company.manage', 'reports.view'] as $permission) {
            $ids = $authorization->accessibleCompanyIds($request->user(), (string) $tenantId, $permission);
            if ($ids === null) {
                $allCompanies = true;
                break;
            }
            $companyIds = array_merge($companyIds, $ids);
        }
        $query = Company::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name');
        if (!$allCompanies) $query->whereIn('id', array_values(array_unique($companyIds)));
        return response()->json(['data' => $query->paginate(25)]);
    }

    public function dashboardIndex(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $tenantId = (string) $request->header('X-Tenant-Id');
        $ids = $authorization->accessibleCompanyIds($request->user(), $tenantId, 'reports.view');
        $query = Company::query()->where('tenant_id', $tenantId)->where('is_active', true)->orderBy('name');
        if (is_array($ids)) $query->whereIn('id', $ids);
        return response()->json(['data' => $query->paginate(100)]);
    }

    public function show(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['organization.manage', 'company.manage', 'reports.view']), 403);
        return response()->json(['data' => $this->payload($company)]);
    }

    public function store(Request $request, AuthorizationService $authorization, AuditLogger $audit, SubscriptionService $subscriptions): JsonResponse
    {
        $tenantId = app(TenantContext::class)->id();
        abort_unless($tenantId && $authorization->allows($request->user(), $tenantId, 'company.manage'), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'code' => ['required', 'alpha_dash', 'max:40'], 'tenant_id' => ['prohibited']]);
        $subscriptions->limit($tenantId, 'companies', Company::where('tenant_id', $tenantId)->count());
        $company = Company::create([...$data, 'tenant_id' => $tenantId, 'legal_name' => $data['name']]);
        $audit->record($request, 'company.created', $company->tenant_id, $company);
        return response()->json(['data' => $this->payload($company)], 201);
    }

    public function update(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['company.manage', 'organization.manage']), 403);
        $data = $request->validate($this->profileRules($company));
        $payload = collect($data)->except(['tenant_id', 'id'])->all();
        if (array_key_exists('legal_name', $payload) && filled($payload['legal_name'])) $payload['name'] = $payload['legal_name'];
        $company->update($payload);
        $audit->record($request, 'company.updated', $company->tenant_id, $company);
        return response()->json(['data' => $this->payload($company->fresh())]);
    }

    public function uploadLogo(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { return $this->uploadBrandAsset($request, $company, $authorization, $audit, 'logo'); }
    public function uploadCover(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { return $this->uploadBrandAsset($request, $company, $authorization, $audit, 'cover'); }
    public function deleteLogo(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { return $this->deleteBrandAsset($request, $company, $authorization, $audit, 'logo'); }
    public function deleteCover(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse { return $this->deleteBrandAsset($request, $company, $authorization, $audit, 'cover'); }

    public function documents(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['organization.manage', 'company.manage', 'reports.view']), 403);
        return response()->json(['data' => $company->documents()->latest()->get(['id', 'name', 'category', 'mime_type', 'size', 'created_at'])->map(fn (CompanyDocument $document) => [...$document->toArray(), 'download_path' => "/companies/{$company->id}/documents/{$document->id}"]) ]);
    }

    public function uploadDocument(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['company.manage', 'organization.manage']), 403);
        $data = $request->validate(['file' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'], 'category' => ['required', Rule::in(['registration_certificate', 'tax_document', 'other'])]]);
        $file = $data['file'];
        $path = $file->store("private/tenant/{$company->tenant_id}/companies/{$company->id}", 'local');
        $document = CompanyDocument::create(['tenant_id' => $company->tenant_id, 'company_id' => $company->id, 'uploaded_by' => $request->user()->id, 'name' => $file->getClientOriginalName(), 'category' => $data['category'], 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);
        $audit->record($request, 'company.document.created', $company->tenant_id, $document, ['company_id' => $company->id]);
        return response()->json(['data' => $document], 201);
    }

    public function downloadDocument(Request $request, Company $company, CompanyDocument $document, AuthorizationService $authorization)
    {
        $this->assertCompany($company);
        abort_unless($document->tenant_id === $company->tenant_id && $document->company_id === $company->id, 404);
        abort_unless($this->allowed($authorization, $request, $company, ['organization.manage', 'company.manage', 'reports.view']), 403);
        abort_unless(Storage::disk('local')->exists($document->path), 404);
        return Storage::disk('local')->download($document->path, $document->name, ['Content-Type' => $document->mime_type]);
    }

    public function deleteDocument(Request $request, Company $company, CompanyDocument $document, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($document->tenant_id === $company->tenant_id && $document->company_id === $company->id, 404);
        abort_unless($this->allowed($authorization, $request, $company, ['company.manage', 'organization.manage']), 403);
        Storage::disk('local')->delete($document->path);
        $document->delete();
        $audit->record($request, 'company.document.deleted', $company->tenant_id, $document, ['company_id' => $company->id]);
        return response()->json(['status' => 'deleted']);
    }

    private function profileRules(Company $company): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:200'], 'code' => ['sometimes', 'alpha_dash', 'max:40', Rule::unique('companies', 'code')->where(fn ($query) => $query->where('tenant_id', $company->tenant_id))->ignore($company->id)],
            'legal_name' => ['nullable', 'string', 'max:200'], 'trading_name' => ['nullable', 'string', 'max:200'], 'business_type' => ['nullable', 'string', 'max:120'], 'registration_number' => ['nullable', 'string', 'max:120'], 'tax_identifier' => ['nullable', 'string', 'max:120'], 'tax_registration_details' => ['nullable', 'string', 'max:5000'], 'registration_date' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:5000'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:60'], 'website' => ['nullable', 'url', 'max:255'], 'country_code' => ['nullable', 'alpha', 'size:2'], 'region' => ['nullable', 'string', 'max:120'], 'city' => ['nullable', 'string', 'max:120'], 'postal_code' => ['nullable', 'string', 'max:30'], 'street_address' => ['nullable', 'string', 'max:255'], 'base_currency' => ['nullable', 'alpha', 'size:3'], 'timezone' => ['required', 'timezone'], 'default_locale' => ['required', 'string', 'max:10'], 'date_format' => ['required', 'string', 'max:30'], 'tenant_id' => ['prohibited'], 'is_active' => ['prohibited'],
        ];
    }

    private function uploadBrandAsset(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit, string $kind): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['company.manage', 'organization.manage']), 403);
        $data = $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp']]);
        $file = $data['file'];
        $path = $file->store("tenant/{$company->tenant_id}/companies/{$company->id}", 'public');
        $column = $kind === 'logo' ? 'logo_path' : 'cover_image_path';
        if ($company->{$column}) Storage::disk('public')->delete($company->{$column});
        $company->update([$column => $path]);
        $audit->record($request, "company.{$kind}.updated", $company->tenant_id, $company);
        return response()->json(['data' => $this->payload($company->fresh())]);
    }

    private function deleteBrandAsset(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit, string $kind): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['company.manage', 'organization.manage']), 403);
        $column = $kind === 'logo' ? 'logo_path' : 'cover_image_path';
        if ($company->{$column}) Storage::disk('public')->delete($company->{$column});
        $company->update([$column => null]);
        $audit->record($request, "company.{$kind}.deleted", $company->tenant_id, $company);
        return response()->json(['data' => $this->payload($company->fresh())]);
    }

    private function assertCompany(Company $company): void
    {
        abort_unless($company->tenant_id === app(TenantContext::class)->id(), 404);
        abort_unless($company->is_active, 403, 'Company is inactive.');
    }

    private function allowed(AuthorizationService $authorization, Request $request, Company $company, array $permissions): bool
    {
        foreach ($permissions as $permission) if ($authorization->allows($request->user(), $company->tenant_id, $permission, $company->id)) return true;
        return false;
    }

    private function payload(Company $company): array
    {
        return [...$company->toArray(), 'logo_url' => $company->logo_path ? Storage::disk('public')->url($company->logo_path) : null, 'cover_image_url' => $company->cover_image_path ? Storage::disk('public')->url($company->cover_image_path) : null];
    }
}
