<?php

namespace App\Services;

use App\Models\{Branch, Invitation, OnboardingProgress, Product, ProductCategory, Tenant, UnitOfMeasure, Warehouse};
use Illuminate\Support\Facades\DB;

final class OnboardingProgressService
{
    private const VERSION = 1;

    private const STEPS = [
        ['key' => 'email_verification', 'label' => 'Email verification', 'required' => true, 'action_path' => '/verify-email'],
        ['key' => 'business_approval', 'label' => 'Business approval', 'required' => true, 'action_path' => '/workspace-access'],
        ['key' => 'company_profile', 'label' => 'Company profile', 'required' => true, 'action_path' => '/organization/profile'],
        ['key' => 'accounting_defaults', 'label' => 'Accounting defaults', 'required' => true, 'action_path' => '/accounting/integration'],
        ['key' => 'first_branch', 'label' => 'First branch', 'required' => true, 'action_path' => '/organization/branches'],
        ['key' => 'catalog_references', 'label' => 'Catalog references', 'required' => true, 'action_path' => '/catalog/references'],
        ['key' => 'product_categories', 'label' => 'Product categories', 'required' => true, 'action_path' => '/catalog/categories'],
        ['key' => 'first_product', 'label' => 'First product', 'required' => true, 'action_path' => '/catalog'],
        ['key' => 'staff_invitations', 'label' => 'Staff invitations', 'required' => false, 'action_path' => '/organization/members'],
        ['key' => 'inventory_setup', 'label' => 'Inventory setup', 'required' => false, 'action_path' => '/inventory/warehouses'],
        ['key' => 'sales_setup', 'label' => 'Sales setup', 'required' => false, 'action_path' => '/crm-sales/customers'],
        ['key' => 'workspace_readiness', 'label' => 'Workspace readiness', 'required' => true, 'action_path' => '/business/account'],
    ];

    public function overview(Tenant $tenant, ?string $userId = null): array
    {
        $userId ??= request()->user()?->id;
        $tenant->loadMissing(['companies']);
        $company = $tenant->companies->firstWhere('is_active', true) ?? $tenant->companies->first();
        $user = $userId ? $tenant->users()->whereKey($userId)->first() : null;
        $companyId = $company?->id;
        $checks = [
            'email_verification' => (bool) $user?->hasVerifiedEmail(),
            'business_approval' => $tenant->status === 'active',
            'company_profile' => (bool) ($company && $company->is_active && filled($company->name) && filled($company->code)),
            'accounting_defaults' => (bool) ($company && filled($company->base_currency) && filled($company->fiscal_year_start)),
            'first_branch' => $companyId ? Branch::where('tenant_id', $tenant->id)->where('company_id', $companyId)->where('is_active', true)->exists() : false,
            'catalog_references' => UnitOfMeasure::where('tenant_id', $tenant->id)->exists(),
            'product_categories' => ProductCategory::where('tenant_id', $tenant->id)->exists(),
            'first_product' => Product::where('tenant_id', $tenant->id)->where('status', 'active')->exists(),
            'staff_invitations' => Invitation::where('tenant_id', $tenant->id)->exists(),
            'inventory_setup' => Warehouse::where('tenant_id', $tenant->id)->where('is_active', true)->exists(),
            'sales_setup' => DB::table('customers')->where('tenant_id', $tenant->id)->where('status', 'active')->exists(),
        ];
        $requiredKeys = collect(self::STEPS)->where('required', true)->pluck('key');
        $checks['workspace_readiness'] = $requiredKeys->every(fn (string $key) => $key === 'workspace_readiness' || ($checks[$key] ?? false));
        $stored = OnboardingProgress::where('tenant_id', $tenant->id)->get()->keyBy('step_key');
        $steps = collect(self::STEPS)->map(function (array $definition) use ($checks, $stored): array {
            $key = $definition['key'];
            $isComplete = (bool) ($checks[$key] ?? false);
            $record = $stored->get($key);
            return [...$definition, 'status' => $isComplete ? 'completed' : ($definition['required'] ? 'blocked' : 'not_started'), 'complete' => $isComplete, 'completed_at' => $record?->completed_at?->toISOString(), 'source_type' => $record?->source_type, 'source_id' => $record?->source_id];
        })->values();
        $required = $steps->where('required', true);
        $completedRequired = $required->where('complete', true)->count();
        $next = $steps->first(fn (array $step) => ! $step['complete']);
        return ['tenant_id' => $tenant->id, 'schema_version' => self::VERSION, 'status' => $completedRequired === $required->count() ? 'completed' : ($completedRequired ? 'in_progress' : 'not_started'), 'progress_percent' => $required->count() ? (int) round(($completedRequired / $required->count()) * 100) : 0, 'next_step' => $next, 'steps' => $steps->all()];
    }

    public function complete(Tenant $tenant, string $stepKey, string $userId): array
    {
        $definition = collect(self::STEPS)->firstWhere('key', $stepKey);
        abort_unless($definition, 404, 'Onboarding step not found.');
        $overview = $this->overview($tenant, $userId);
        $step = collect($overview['steps'])->firstWhere('key', $stepKey);
        abort_unless($step['complete'], 422, 'This onboarding step is not complete according to current workspace data.');
        [$sourceType, $sourceId] = $this->completionSource($tenant, $stepKey, $userId);
        DB::transaction(function () use ($tenant, $stepKey, $userId, $sourceType, $sourceId): void {
            OnboardingProgress::updateOrCreate(['tenant_id' => $tenant->id, 'step_key' => $stepKey], ['status' => 'completed', 'completed_at' => now(), 'completed_by' => $userId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'schema_version' => self::VERSION]);
        });
        return $this->overview($tenant, $userId);
    }

    private function completionSource(Tenant $tenant, string $stepKey, string $userId): array
    {
        $company = $tenant->companies()->where('is_active', true)->first() ?? $tenant->companies()->first();
        $sources = [
            'email_verification' => ['users', $userId],
            'business_approval' => ['tenants', $tenant->id],
            'company_profile' => ['companies', $company?->id],
            'accounting_defaults' => ['companies', $company?->id],
            'first_branch' => ['branches', Branch::where('tenant_id', $tenant->id)->where('company_id', $company?->id)->where('is_active', true)->value('id')],
            'catalog_references' => ['units_of_measure', UnitOfMeasure::where('tenant_id', $tenant->id)->value('id')],
            'product_categories' => ['product_categories', ProductCategory::where('tenant_id', $tenant->id)->value('id')],
            'first_product' => ['products', Product::where('tenant_id', $tenant->id)->where('status', 'active')->value('id')],
            'staff_invitations' => ['invitations', Invitation::where('tenant_id', $tenant->id)->value('id')],
            'inventory_setup' => ['warehouses', Warehouse::where('tenant_id', $tenant->id)->where('is_active', true)->value('id')],
            'sales_setup' => ['customers', DB::table('customers')->where('tenant_id', $tenant->id)->where('status', 'active')->value('id')],
            'workspace_readiness' => ['tenants', $tenant->id],
        ];
        $source = $sources[$stepKey] ?? [null, null];
        abort_unless($source[1], 422, 'The onboarding source record is no longer available.');
        return $source;
    }
}
