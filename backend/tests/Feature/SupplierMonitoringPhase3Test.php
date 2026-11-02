<?php

use App\Jobs\DeliverSupplierNotification;
use App\Models\{AutomationRule,AutomationRun,Company,ErpNotification,Permission,Role,RoleAssignment,Supplier,SupplierNotificationDelivery,SupplierPurchasingHold,Tenant,TenantMembership,User};
use Illuminate\Support\Facades\{Bus,DB,Schema};
use Illuminate\Support\Str;

function supplierMonitoringFixturePhase3(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'automation.manage']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Monitoring manager', 'slug' => 'monitoring-manager-'.fake()->unique()->numerify('#####')]);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Monitoring Co', 'code' => 'MON-'.fake()->unique()->numerify('###')]);
    $supplier = Supplier::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Monitoring Supplier', 'code' => 'MS-'.fake()->unique()->numerify('###'), 'status' => 'active']);
    return compact('user', 'tenant', 'company', 'supplier');
}

it('reports real execution totals, success rate, metadata and scoped pagination', function (): void {
    $f = supplierMonitoringFixturePhase3();
    $rule = AutomationRule::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'event_key' => 'supplier_risk_alert', 'name' => 'Risk monitor', 'conditions' => [], 'actions' => [], 'is_active' => true, 'created_by' => $f['user']->id]);
    SupplierPurchasingHold::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'supplier_id' => $f['supplier']->id, 'type' => 'review', 'reason' => 'Monitoring test', 'effective_from' => now()->toDateString(), 'status' => 'active', 'applied_by' => $f['user']->id]);
    AutomationRun::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'rule_id' => $rule->id, 'automation_type' => $rule->event_key, 'idempotency_key' => 'monitor:completed', 'state' => 'completed', 'attempts' => 1, 'trigger_type' => 'scheduled', 'started_at' => now()->subMinute(), 'completed_at' => now(), 'duration_ms' => 120, 'correlation_id' => (string) Str::uuid()]);
    AutomationRun::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'rule_id' => $rule->id, 'automation_type' => $rule->event_key, 'idempotency_key' => 'monitor:failed', 'state' => 'failed', 'attempts' => 2, 'trigger_type' => 'scheduled', 'started_at' => now()->subMinute(), 'completed_at' => now(), 'duration_ms' => 250, 'failure_classification' => 'execution', 'last_error' => 'sanitized failure']);

    $response = $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/monitoring?company_id='.$f['company']->id.'&per_page=10', ['X-Tenant-Id' => $f['tenant']->id])->assertOk();
    $response->assertJsonPath('data.kpis.total_runs', 2)->assertJsonPath('data.kpis.successful_runs', 1)->assertJsonPath('data.kpis.failed_runs', 1)->assertJsonPath('data.kpis.success_rate', 50)->assertJsonPath('data.kpis.open_alerts', 1)->assertJsonPath('data.runs.data.0.automation_type', 'supplier_risk_alert');
    expect(Schema::hasColumns('automation_runs', ['company_id', 'started_at', 'duration_ms', 'failure_classification', 'correlation_id']))->toBeTrue();
});

it('blocks cross-tenant execution details and exposes sanitized platform failed jobs', function (): void {
    $owner = supplierMonitoringFixturePhase3();
    $rule = AutomationRule::create(['tenant_id' => $owner['tenant']->id, 'company_id' => $owner['company']->id, 'event_key' => 'supplier_risk_alert', 'name' => 'Scoped run', 'conditions' => [], 'actions' => [], 'is_active' => true, 'created_by' => $owner['user']->id]);
    $run = AutomationRun::create(['tenant_id' => $owner['tenant']->id, 'company_id' => $owner['company']->id, 'rule_id' => $rule->id, 'automation_type' => $rule->event_key, 'idempotency_key' => 'detail:run', 'state' => 'failed', 'attempts' => 1]);
    $other = supplierMonitoringFixturePhase3();
    $this->actingAs($other['user'])->getJson('/api/v1/purchasing/suppliers/monitoring/runs/'.$run->id, ['X-Tenant-Id' => $other['tenant']->id])->assertNotFound();

    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    TenantMembership::create(['tenant_id' => $owner['tenant']->id, 'user_id' => $platform->id, 'status' => 'active']);
    DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{"secret":"do-not-expose"}', 'exception' => "RuntimeException: provider token=secret-value\n#0 /private/path", 'failed_at' => now()]);
    $this->actingAs($platform)->getJson('/api/v1/purchasing/suppliers/monitoring/failed-jobs', ['X-Tenant-Id' => $owner['tenant']->id])->assertOk()->assertDontSee('do-not-expose')->assertDontSee('secret-value');
});

it('allows only bounded, authorized retry of failed notification deliveries', function (): void {
    $f = supplierMonitoringFixturePhase3();
    $rule = AutomationRule::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'event_key' => 'supplier_risk_alert', 'name' => 'Retryable alert', 'conditions' => [], 'actions' => [], 'is_active' => true, 'created_by' => $f['user']->id]);
    $notification = ErpNotification::create(['tenant_id' => $f['tenant']->id, 'user_id' => $f['user']->id, 'type' => $rule->event_key, 'title' => 'Risk', 'body' => 'Review', 'delivery_state' => 'failed', 'rule_id' => $rule->id, 'company_id' => $f['company']->id, 'channel' => 'in_app', 'priority' => 'high', 'source_key' => 'retry:1']);
    $delivery = SupplierNotificationDelivery::create(['tenant_id' => $f['tenant']->id, 'notification_id' => $notification->id, 'channel' => 'in_app', 'state' => 'failed', 'attempts' => 1, 'last_error' => 'temporary']);
    Bus::fake();
    $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/monitoring/deliveries/'.$delivery->id.'/retry', [], ['X-Tenant-Id' => $f['tenant']->id])->assertStatus(202)->assertJsonPath('status', 'queued');
    Bus::assertDispatched(DeliverSupplierNotification::class);
    expect($delivery->fresh()->state)->toBe('queued');
    $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/monitoring/deliveries/'.$delivery->id.'/retry', [], ['X-Tenant-Id' => $f['tenant']->id])->assertUnprocessable();
});
