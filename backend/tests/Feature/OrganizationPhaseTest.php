<?php

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\CompanyDocument;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function organizationUser(string $permission = 'organization.manage'): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permissionModel = Permission::firstOrCreate(['name' => $permission]);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Organization manager', 'slug' => 'organization-manager']);
    $role->permissions()->sync([$permissionModel->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    return [$user, $tenant];
}

it('returns an authorized company profile without leaking another tenant', function (): void {
    [$user, $tenant] = organizationUser('reports.view');
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Northwind', 'code' => 'NW', 'legal_name' => 'Northwind Legal']);
    $otherTenant = Tenant::factory()->create();
    $otherCompany = Company::create(['tenant_id' => $otherTenant->id, 'name' => 'Other', 'code' => 'OTHER']);

    $this->actingAs($user)->getJson('/api/v1/companies/'.$company->id, ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.legal_name', 'Northwind Legal');
    $this->actingAs($user)->getJson('/api/v1/companies/'.$otherCompany->id, ['X-Tenant-Id' => $tenant->id])->assertNotFound();
});

it('limits the company selector to the users authorized company scope', function (): void {
    [$user, $tenant] = organizationUser('reports.view');
    $allowed = Company::create(['tenant_id' => $tenant->id, 'name' => 'Allowed', 'code' => 'ALLOWED']);
    $hidden = Company::create(['tenant_id' => $tenant->id, 'name' => 'Hidden', 'code' => 'HIDDEN']);
    $role = Role::where('tenant_id', $tenant->id)->firstOrFail();
    RoleAssignment::where('tenant_id', $tenant->id)->where('user_id', $user->id)->update(['company_id' => $allowed->id]);

    $this->actingAs($user)->getJson('/api/v1/companies', ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $allowed->id);
    expect($role->id)->not->toBeEmpty()->and($hidden->id)->not->toBe($allowed->id);
});

it('updates a company profile only with organization permission and validates input', function (): void {
    [$user, $tenant] = organizationUser();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Original', 'code' => 'ORIG']);
    $duplicate = Company::create(['tenant_id' => $tenant->id, 'name' => 'Duplicate', 'code' => 'DUP']);

    $this->actingAs($user)->patchJson('/api/v1/companies/'.$company->id, ['legal_name' => 'Updated Legal', 'email' => 'invalid', 'website' => 'not-a-url', 'timezone' => 'UTC', 'default_locale' => 'en', 'date_format' => 'YYYY-MM-DD'], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
    $this->actingAs($user)->patchJson('/api/v1/companies/'.$company->id, ['legal_name' => 'Updated Legal', 'code' => $duplicate->code, 'timezone' => 'Asia/Yangon', 'default_locale' => 'my', 'date_format' => 'DD/MM/YYYY'], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
    $this->actingAs($user)->patchJson('/api/v1/companies/'.$company->id, ['legal_name' => 'Updated Legal', 'trading_name' => 'Updated', 'timezone' => 'Asia/Yangon', 'default_locale' => 'my', 'date_format' => 'DD/MM/YYYY'], ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.name', 'Updated Legal')->assertJsonPath('data.trading_name', 'Updated');
});

it('creates a complete active company with owner membership inside the current tenant', function (): void {
    [$user, $tenant] = organizationUser('company.manage');
    app(SubscriptionService::class)->provisionTrial($tenant);

    $response = $this->actingAs($user)->postJson('/api/v1/companies', [
        'name' => 'Northwind Legal',
        'legal_name' => 'Northwind Legal',
        'trading_name' => 'Northwind',
        'code' => 'NW',
        'business_type' => 'Retail',
        'registration_number' => 'REG-001',
        'tax_identifier' => 'TAX-001',
        'email' => 'office@northwind.test',
        'phone' => '+959123456789',
        'country_code' => 'MM',
        'region' => 'Yangon',
        'city' => 'Yangon',
        'base_currency' => 'MMK',
        'timezone' => 'Asia/Yangon',
        'default_locale' => 'my',
        'date_format' => 'DD/MM/YYYY',
    ], ['X-Tenant-Id' => $tenant->id]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Northwind Legal')
        ->assertJsonPath('data.trading_name', 'Northwind')
        ->assertJsonPath('data.is_active', true);
    $companyId = $response->json('data.id');
    expect(CompanyMembership::where('company_id', $companyId)->where('user_id', $user->id)->where('status', 'active')->exists())->toBeTrue();
});

it('rejects company profile changes without an authorized role', function (): void {
    [$owner, $tenant] = organizationUser();
    $user = User::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Protected', 'code' => 'PRO']);

    $this->actingAs($user)->patchJson('/api/v1/companies/'.$company->id, ['legal_name' => 'Nope'], ['X-Tenant-Id' => $tenant->id])->assertForbidden();
    expect($owner->id)->not->toBe($user->id);
});

it('returns accurate organization overview counts and unavailable team state', function (): void {
    [$user, $tenant] = organizationUser();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Overview Co', 'code' => 'OV']);
    $branch = Branch::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Main', 'code' => 'MAIN', 'is_active' => true]);
    Department::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'branch_id' => $branch->id, 'name' => 'Finance', 'code' => 'FIN']);

    $this->actingAs($user)->getJson('/api/v1/organization/overview?company_id='.$company->id, ['X-Tenant-Id' => $tenant->id])->assertOk()->assertJsonPath('data.kpis.active_branches.value', 1)->assertJsonPath('data.kpis.departments.value', 1)->assertJsonPath('data.kpis.active_members.value', 1)->assertJsonPath('data.kpis.teams.available', false);
});

it('validates branding and keeps company documents private', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    [$user, $tenant] = organizationUser();
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Media Co', 'code' => 'MEDIA']);

    $this->actingAs($user)->postJson('/api/v1/companies/'.$company->id.'/logo', ['file' => UploadedFile::fake()->create('logo.txt', 10, 'text/plain')], ['X-Tenant-Id' => $tenant->id])->assertUnprocessable();
    $this->actingAs($user)->post('/api/v1/companies/'.$company->id.'/logo', ['file' => UploadedFile::fake()->image('logo.png')], ['X-Tenant-Id' => $tenant->id])->assertOk();
    $this->actingAs($user)->post('/api/v1/companies/'.$company->id.'/documents', ['category' => 'tax_document', 'file' => UploadedFile::fake()->create('tax.pdf', 20, 'application/pdf')], ['X-Tenant-Id' => $tenant->id])->assertCreated();
    expect(CompanyDocument::where('company_id', $company->id)->count())->toBe(1);
});
