<?php

use App\Jobs\{EvaluateSupplierAutomationRule, RunAutomationRule};
use App\Models\{AutomationRule, Company, Permission, PlatformAuditLog, Role, RoleAssignment, Supplier, Tenant, TenantMembership, User};
use Illuminate\Support\Facades\Bus;

function supplierAutomationFixture(bool $withPermission = true): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    if ($withPermission) {
        $permission = Permission::firstOrCreate(['name' => 'automation.manage']);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Automation manager', 'slug' => 'automation-manager-'.fake()->unique()->numerify('####')]);
        $role->permissions()->sync([$permission->id]);
        RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    }
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Automation Co', 'code' => 'AUTO-'.fake()->unique()->numerify('###')]);
    $supplier = Supplier::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Scoped Supplier', 'code' => 'SUP-'.fake()->unique()->numerify('###'), 'status' => 'active']);
    return compact('user', 'tenant', 'company', 'supplier');
}

it('exposes only server-controlled supplier automation types', function (): void {
    $f = supplierAutomationFixture();
    $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/automations/types', ['X-Tenant-Id' => $f['tenant']->id])
        ->assertOk()->assertJsonPath('data.0.key', 'supplier_onboarding_reminder')
        ->assertJsonMissing(['key' => 'arbitrary_php']);
});

it('creates scoped allowlisted rules, rejects unknown configuration, and audits changes', function (): void {
    $f = supplierAutomationFixture();
    $headers = ['X-Tenant-Id' => $f['tenant']->id];
    $payload = ['company_id' => $f['company']->id, 'automation_type' => 'supplier_missing_document_reminder', 'name' => 'Missing documents', 'configuration' => ['supplier_id' => $f['supplier']->id, 'days_before' => 30]];
    $created = $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/automations', $payload, $headers)
        ->assertCreated()->assertJsonPath('data.automation_type', 'supplier_missing_document_reminder');
    $ruleId = $created->json('data.id');

    $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/automations', [...$payload, 'name' => 'Unknown', 'automation_type' => 'arbitrary_php'], $headers)->assertUnprocessable();
    $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/automations', [...$payload, 'name' => 'Unknown config', 'configuration' => ['raw_sql' => 'select 1']], $headers)->assertUnprocessable();
    $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/automations', [...$payload, 'name' => 'Missing documents'], $headers)->assertUnprocessable();

    $this->actingAs($f['user'])->patchJson('/api/v1/purchasing/suppliers/automations/'.$ruleId, [...$payload, 'name' => 'Updated documents'], $headers)->assertOk();
    $this->actingAs($f['user'])->patchJson('/api/v1/purchasing/suppliers/automations/'.$ruleId.'/status', ['enabled' => true], $headers)->assertOk()->assertJsonPath('data.is_active', true);
    expect(PlatformAuditLog::where('tenant_id', $f['tenant']->id)->whereIn('action', ['supplier_automation_rule.created', 'supplier_automation_rule.changed', 'supplier_automation_rule.enabled'])->count())->toBe(3);
});

it('blocks unauthorized and cross-tenant automation access', function (): void {
    $f = supplierAutomationFixture(false);
    $headers = ['X-Tenant-Id' => $f['tenant']->id];
    $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/automations', ['company_id' => $f['company']->id, 'automation_type' => 'supplier_risk_alert', 'name' => 'Risk alert', 'configuration' => []], $headers)->assertForbidden();

    $owner = supplierAutomationFixture();
    $rule = AutomationRule::create(['tenant_id' => $owner['tenant']->id, 'company_id' => $owner['company']->id, 'event_key' => 'supplier_risk_alert', 'name' => 'Tenant rule', 'conditions' => [], 'actions' => [], 'is_active' => false, 'created_by' => $owner['user']->id]);
    $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/automations', ['X-Tenant-Id' => $owner['tenant']->id])->assertForbidden();
    expect($rule->tenant_id)->not->toBe($f['tenant']->id);
});

it('dispatches enabled supplier allowlisted rules to the phase two evaluator', function (): void {
    $f = supplierAutomationFixture();
    AutomationRule::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'event_key' => 'supplier_hold_review_alert', 'name' => 'Hold review', 'conditions' => [], 'actions' => [], 'is_active' => true, 'created_by' => $f['user']->id]);
    Bus::fake();
    $this->artisan('erp:purchasing-automation')->assertExitCode(0);
    Bus::assertDispatched(EvaluateSupplierAutomationRule::class);
    Bus::assertNotDispatched(RunAutomationRule::class);
});
