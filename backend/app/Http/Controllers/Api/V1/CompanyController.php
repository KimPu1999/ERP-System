<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\AccountingCurrency;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyDocument;
use App\Models\JournalEntry;
use App\Models\PlatformAuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthorizationService;
use App\Services\SubscriptionService;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $ids = [];
        $allCompanies = false;
        foreach (['reports.view', 'organization.manage', 'company.manage'] as $permission) {
            $accessible = $authorization->accessibleCompanyIds($request->user(), $tenantId, $permission);
            if ($accessible === null) {
                $allCompanies = true;
                break;
            }
            $ids = array_merge($ids, $accessible);
        }
        $scope = Company::query()->where('tenant_id', $tenantId);
        if (! $allCompanies) $scope->whereIn('id', array_values(array_unique($ids)));
        $activeCount = (clone $scope)->where('is_active', true)->count();
        $inactiveCount = (clone $scope)->where('is_active', false)->count();
        $query = (clone $scope)->where('is_active', true)->orderBy('name');
        return response()->json(['data' => $query->paginate(100), 'meta' => [
            'active_count' => $activeCount,
            'inactive_count' => $inactiveCount,
        ]]);
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
        $this->ensureCurrencyCatalog((string) $tenantId);
        $data = $request->validate($this->createRules($tenantId));
        $subscriptions->limit($tenantId, 'companies', Company::where('tenant_id', $tenantId)->count());
        $company = DB::transaction(function () use ($data, $tenantId, $request): Company {
            $company = Company::create([
                ...collect($data)->except(['tenant_id', 'is_active'])->all(),
                'tenant_id' => $tenantId,
                'legal_name' => $data['legal_name'] ?? $data['name'],
                'timezone' => $data['timezone'] ?? config('app.timezone', 'UTC'),
                'default_locale' => $data['default_locale'] ?? 'en',
                'date_format' => $data['date_format'] ?? 'YYYY-MM-DD',
                'is_active' => true,
            ]);
            CompanyMembership::firstOrCreate(
                ['company_id' => $company->id, 'user_id' => $request->user()->id],
                ['tenant_id' => $tenantId, 'status' => 'active', 'joined_at' => now()],
            );
            return $company;
        });
        $audit->record($request, 'company.created', $company->tenant_id, $company);
        return response()->json(['data' => $this->payload($company)], 201);
    }

    public function update(Request $request, Company $company, AuthorizationService $authorization, AuditLogger $audit): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['company.manage', 'organization.manage']), 403);
        $data = $request->validate($this->profileRules($company));
        if (! filled($data['base_currency'] ?? $company->base_currency)) {
            throw ValidationException::withMessages(['base_currency' => ['Base accounting currency is required before company financial totals can be used.']]);
        }
        $payload = collect($data)->except(['tenant_id', 'id'])->all();
        if (array_key_exists('legal_name', $payload) && filled($payload['legal_name'])) $payload['name'] = $payload['legal_name'];
        if (array_key_exists('base_currency', $payload) && filled($payload['base_currency'])) {
            $payload['base_currency'] = strtoupper($payload['base_currency']);
            abort_unless(AccountingCurrency::where('tenant_id', $company->tenant_id)->where('code', $payload['base_currency'])->exists(), 422, 'The selected base currency is not available for this tenant.');
            if ($payload['base_currency'] !== strtoupper((string) $company->base_currency) && JournalEntry::where('tenant_id', $company->tenant_id)->where('company_id', $company->id)->where('state', 'posted')->exists()) {
                throw ValidationException::withMessages(['base_currency' => ['The base currency cannot be changed after posted financial transactions exist. Use an approved financial conversion procedure.']]);
            }
        }
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
        $documents = $company->documents()->with('uploader:id,name')->latest()->get(['id', 'name', 'category', 'mime_type', 'size', 'uploaded_by', 'created_at']);
        return response()->json(['data' => $documents->map(fn (CompanyDocument $document) => [...$document->toArray(), 'uploaded_by_name' => $document->uploader?->name, 'download_path' => "/companies/{$company->id}/documents/{$document->id}"]) ]);
    }

    public function activity(Request $request, Company $company, AuthorizationService $authorization): JsonResponse
    {
        $this->assertCompany($company);
        abort_unless($this->allowed($authorization, $request, $company, ['organization.manage', 'company.manage', 'reports.view']), 403);
        $activity = PlatformAuditLog::query()->where('tenant_id', $company->tenant_id)->where(function ($query) use ($company): void {
            $query->where('auditable_id', $company->id)->orWhereJsonContains('metadata->company_id', $company->id);
        })->latest()->limit(50)->get(['id', 'action', 'actor_user_id', 'auditable_type', 'auditable_id', 'metadata', 'created_at']);
        $actorNames = User::whereIn('id', $activity->pluck('actor_user_id')->filter()->unique())->pluck('name', 'id');
        return response()->json(['data' => $activity->map(fn (PlatformAuditLog $item) => [...$item->toArray(), 'actor_name' => $item->actor_user_id ? ($actorNames[$item->actor_user_id] ?? 'Authorized user') : 'System'])]);
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
            'legal_name' => ['nullable', 'string', 'max:200'], 'trading_name' => ['nullable', 'string', 'max:200'], 'business_type' => ['nullable', 'string', 'max:120'], 'industry' => ['nullable', 'string', 'max:120'], 'registration_number' => ['nullable', 'string', 'max:120'], 'tax_identifier' => ['nullable', 'string', 'max:120'], 'tax_registration_details' => ['nullable', 'string', 'max:5000'], 'registration_date' => ['nullable', 'date'], 'description' => ['nullable', 'string', 'max:5000'], 'email' => ['nullable', 'email', 'max:255'], 'phone' => ['nullable', 'string', 'max:60'], 'alternative_phone' => ['nullable', 'string', 'max:60'], 'website' => ['nullable', 'url', 'max:255'], 'country_code' => ['nullable', 'alpha', 'size:2'], 'region' => ['nullable', 'string', 'max:120'], 'city' => ['nullable', 'string', 'max:120'], 'township' => ['nullable', 'string', 'max:120'], 'postal_code' => ['nullable', 'string', 'max:30'], 'street_address' => ['nullable', 'string', 'max:255'], 'base_currency' => ['nullable', 'alpha', 'size:3', Rule::exists('accounting_currencies', 'code')->where(fn ($query) => $query->where('tenant_id', $company->tenant_id))], 'timezone' => ['required', 'timezone'], 'default_locale' => ['required', 'string', 'max:10'], 'date_format' => ['required', 'string', 'max:30'], 'fiscal_year_start' => ['nullable', 'date'], 'document_number_prefix' => ['nullable', 'alpha_dash', 'max:40'], 'number_format' => ['nullable', Rule::in(['1,234.56', '1.234,56', '1 234,56'])], 'invoice_prefix' => ['nullable', 'alpha_dash', 'max:40'], 'purchase_order_prefix' => ['nullable', 'alpha_dash', 'max:40'], 'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'tenant_id' => ['prohibited'], 'is_active' => ['prohibited'],
        ];
    }

    private function createRules(string $tenantId): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:200'],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('companies', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'business_type' => ['required', 'string', 'max:120'],
            'industry' => ['nullable', 'string', 'max:120'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'tax_identifier' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:60'],
            'alternative_phone' => ['nullable', 'string', 'max:60'],
            'country_code' => ['required', 'alpha', 'size:2'],
            'region' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'township' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'street_address' => ['required', 'string', 'max:255'],
            'base_currency' => ['required', 'alpha', 'size:3', Rule::exists('accounting_currencies', 'code')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'timezone' => ['required', 'timezone'],
            'default_locale' => ['required', 'string', 'max:10'],
            'date_format' => ['required', 'string', 'max:30'],
            'registration_date' => ['nullable', 'date'],
            'fiscal_year_start' => ['nullable', 'date'],
            'document_number_prefix' => ['nullable', 'alpha_dash', 'max:40'],
            'number_format' => ['nullable', Rule::in(['1,234.56', '1.234,56', '1 234,56'])],
            'invoice_prefix' => ['nullable', 'alpha_dash', 'max:40'],
            'purchase_order_prefix' => ['nullable', 'alpha_dash', 'max:40'],
            'brand_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tenant_id' => ['prohibited'],
            'is_active' => ['prohibited'],
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

    private function ensureCurrencyCatalog(string $tenantId): void
    {
        foreach ([['MMK', 'Myanmar Kyat'], ['USD', 'United States Dollar'], ['SGD', 'Singapore Dollar'], ['THB', 'Thai Baht'], ['EUR', 'Euro']] as [$code, $name]) {
            DB::table('accounting_currencies')->insertOrIgnore(['id' => (string) Str::uuid(), 'tenant_id' => $tenantId, 'code' => $code, 'name' => $name, 'decimal_places' => 2, 'is_base' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
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
