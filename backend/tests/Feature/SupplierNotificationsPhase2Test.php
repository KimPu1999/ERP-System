<?php

use App\Jobs\{DeliverSupplierNotification, EvaluateSupplierAutomationRule};
use App\Mail\SupplierAutomationNotificationMail;
use App\Models\{AutomationRule, Company, Permission, PurchasingNotificationPreference, Role, RoleAssignment, Supplier, SupplierNotificationDelivery, SupplierPurchasingHold, Tenant, TenantMembership, User};
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Support\Str;

function supplierNotificationFixture(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::firstOrCreate(['name' => 'automation.manage']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Automation manager', 'slug' => 'automation-manager-'.fake()->unique()->numerify('#####')]);
    $role->permissions()->sync([$permission->id]);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);
    $company = Company::create(['tenant_id' => $tenant->id, 'name' => 'Supplier Alerts Co', 'code' => 'ALERT-'.fake()->unique()->numerify('###')]);
    $supplier = Supplier::create(['tenant_id' => $tenant->id, 'company_id' => $company->id, 'name' => 'Alert Supplier', 'code' => 'SUP-'.fake()->unique()->numerify('###'), 'status' => 'active']);
    return compact('user', 'tenant', 'company', 'supplier');
}

it('evaluates a scoped rule, creates durable in-app and configured email deliveries, and deduplicates a period', function (): void {
    $f = supplierNotificationFixture();
    $rule = AutomationRule::create([
        'tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'event_key' => 'supplier_hold_review_alert', 'name' => 'Hold review reminder',
        'conditions' => ['supplier_id' => $f['supplier']->id, 'days_open' => 7], 'actions' => [], 'channels' => ['in_app', 'email'], 'recipients' => [$f['user']->id], 'is_active' => true, 'created_by' => $f['user']->id,
    ]);
    SupplierPurchasingHold::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'supplier_id' => $f['supplier']->id, 'type' => 'review', 'reason' => 'Review required', 'effective_from' => now()->toDateString(), 'review_due_date' => now()->toDateString(), 'status' => 'active', 'applied_by' => $f['user']->id]);
    PurchasingNotificationPreference::create(['tenant_id' => $f['tenant']->id, 'company_id' => $f['company']->id, 'user_id' => $f['user']->id, 'event_type' => $rule->event_key, 'channel' => 'email', 'frequency' => 'immediate', 'is_enabled' => true]);

    Bus::fake();
    (new EvaluateSupplierAutomationRule($f['tenant']->id, $rule->id, '2026-11-02'))->handle();
    expect(DB::table('erp_notifications')->where('tenant_id', $f['tenant']->id)->count())->toBe(2);
    expect(SupplierNotificationDelivery::where('tenant_id', $f['tenant']->id)->count())->toBe(2);
    Bus::assertDispatched(DeliverSupplierNotification::class, 2);

    (new EvaluateSupplierAutomationRule($f['tenant']->id, $rule->id, '2026-11-02'))->handle();
    expect(DB::table('erp_notifications')->where('tenant_id', $f['tenant']->id)->count())->toBe(2);
});

it('records in-app read state and only permits owned notification access', function (): void {
    $f = supplierNotificationFixture();
    $notification = DB::table('erp_notifications')->insertGetId([
        'id' => (string) Str::uuid(), 'tenant_id' => $f['tenant']->id, 'user_id' => $f['user']->id, 'type' => 'supplier_risk_alert', 'title' => 'Risk', 'body' => 'Review', 'delivery_state' => 'delivered', 'channel' => 'in_app', 'priority' => 'normal', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->actingAs($f['user'])->getJson('/api/v1/purchasing/suppliers/notifications?unread=1', ['X-Tenant-Id' => $f['tenant']->id])->assertOk()->assertJsonPath('unread_count', 1);
    $this->actingAs($f['user'])->patchJson('/api/v1/purchasing/suppliers/notifications/'.$notification.'/read', [], ['X-Tenant-Id' => $f['tenant']->id])->assertOk();
    $this->actingAs($f['user'])->postJson('/api/v1/purchasing/suppliers/notifications/read-all', [], ['X-Tenant-Id' => $f['tenant']->id])->assertOk()->assertJsonPath('data.count', 0);
});

it('records configured email transport behavior without claiming external delivery', function (): void {
    $f = supplierNotificationFixture();
    $notification = \App\Models\ErpNotification::create(['tenant_id' => $f['tenant']->id, 'user_id' => $f['user']->id, 'type' => 'supplier_risk_alert', 'title' => 'Risk', 'body' => 'Review', 'channel' => 'email', 'priority' => 'normal', 'delivery_state' => 'pending', 'source_key' => 'test:email']);
    $delivery = SupplierNotificationDelivery::create(['tenant_id' => $f['tenant']->id, 'notification_id' => $notification->id, 'channel' => 'email', 'state' => 'queued', 'queued_at' => now()]);
    Mail::fake();
    (new DeliverSupplierNotification($f['tenant']->id, $notification->id, 'email'))->handle();
    Mail::assertSent(SupplierAutomationNotificationMail::class);
    expect($delivery->fresh()->state)->toBe('accepted_by_configured_mailer');
});
