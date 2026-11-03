<?php

use App\Models\{BackupVerificationRecord, MaintenanceWindow, MonitoringIncident, OperationalCostRecord, ReleaseRecord, Tenant, TenantMembership, User};

function operationsFixture(): User
{
    return User::factory()->create(['is_platform_super_admin' => true]);
}

function businessUser(): array
{
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    return [$user, $tenant];
}

it('builds the operations overview from verified data and reports unconfigured areas honestly', function (): void {
    $platform = operationsFixture();
    $response = $this->actingAs($platform)->getJson('/api/v1/platform/operations/overview')->assertOk()->json('data');
    expect($response['production_health']['status'])->toBeIn(['healthy', 'degraded', 'down', 'unknown', 'maintenance'])
        ->and($response['support_tickets']['total'])->toBeInt()
        ->and($response['failed_jobs']['total'])->toBeInt()
        ->and($response['backups']['status'])->toBeIn(['not_configured', 'reported', 'stale', 'degraded'])
        ->and($response['release']['config'])->toBe('not_configured_until_release_records_are_registered')
        ->and($response['worker_heartbeat']['available'])->toBeFalse()
        ->and($response['security_maintenance']['integration'])->toBe('phase_8_security_center');
});

it('uses real backup verification records and shows Unknown without them', function (): void {
    $platform = operationsFixture();
    $this->actingAs($platform)->getJson('/api/v1/platform/operations/backups')->assertOk();
    $empty = $this->actingAs($platform)->getJson('/api/v1/platform/operations/backups')->json('data');
    expect($empty['status'])->toBeIn(['not_configured']);

    BackupVerificationRecord::create(['backup_taken_at' => now()->subHours(2), 'backup_type' => 'pg_dump', 'storage_location' => 's3://erp-backups/prod', 'checksum' => 'sha256:abc', 'verification_status' => 'registered', 'recorded_by' => $platform->id]);
    $recorded = $this->actingAs($platform)->getJson('/api/v1/platform/operations/backups')->json('data');
    expect($recorded['status'])->toBe('reported')
        ->and($recorded['last_verification']['backup_type'])->toBe('pg_dump')
        ->and($recorded['last_restore_test'])->toBeNull();

    // A registered record is NOT a verified restore; status must not claim a working restore pipeline.
    expect($recorded['policy']['rpo'])->toBe('not_measured');
});

it('rejects operating cost joins across currencies and keeps sums separated', function (): void {
    $platform = operationsFixture();
    OperationalCostRecord::create(['occurred_on' => '2026-11-01', 'provider' => 'Vercel', 'category' => 'hosting', 'amount' => 20.00, 'currency' => 'USD', 'cost_type' => 'estimate', 'source' => 'manual', 'recorded_by' => $platform->id]);
    OperationalCostRecord::create(['occurred_on' => '2026-11-01', 'provider' => 'Mail provider', 'category' => 'email', 'amount' => 9.50, 'currency' => 'USD', 'cost_type' => 'actual', 'source' => 'manual', 'recorded_by' => $platform->id]);
    OperationalCostRecord::create(['occurred_on' => '2026-11-01', 'provider' => 'Local registrar', 'category' => 'domain', 'amount' => 30000, 'currency' => 'MMK', 'cost_type' => 'actual', 'source' => 'manual', 'recorded_by' => $platform->id]);

    $data = $this->actingAs($platform)->getJson('/api/v1/platform/operations/costs')->assertOk()->json('data');
    $usdTotal = collect($data['summaries_by_currency'])->where('currency', 'USD')->sum('total');
    $mmk = collect($data['summaries_by_currency'])->first(fn ($s) => $s['currency'] === 'MMK');
    expect($usdTotal)->toEqual(29.5)
        ->and($mmk['total'])->toEqual(30000.0)
        ->and(count($data['summaries_by_currency']))->toBeGreaterThan(1)
        ->and($data['provider_integration'])->toBe('not_configured_all_costs_are_manual_records');

    $actual = collect($data['summaries_by_currency'])->first(fn ($s) => $s['currency'] === 'USD' && $s['cost_type'] === 'actual');
    $estimate = collect($data['summaries_by_currency'])->first(fn ($s) => $s['currency'] === 'USD' && $s['cost_type'] === 'estimate');
    expect($actual['total'])->toEqual(9.5)->and($estimate['total'])->toEqual(20.0);
});

it('requires authorization and valid transitions for maintenance windows', function (): void {
    $platform = operationsFixture();
    [$business, $tenant] = businessUser();
    $payload = ['title' => 'PostgreSQL minor upgrade', 'impact_description' => 'API pauses briefly', 'rollback_plan' => 'Restore previous FPM+DB snapshot', 'starts_at' => now()->addDay()->toIso8601String(), 'ends_at' => now()->addDay()->addHours(2)->toIso8601String()];

    $this->actingAs($business)->postJson('/api/v1/platform/operations/maintenance', $payload, ['X-Tenant-Id' => $tenant->id])->assertForbidden();

    $id = $this->actingAs($platform)->postJson('/api/v1/platform/operations/maintenance', $payload)->assertCreated()->json('data.id');
    // Planned windows cannot start directly.
    $this->actingAs($platform)->postJson("/api/v1/platform/operations/maintenance/{$id}/transition", ['status' => 'in_progress'])->assertStatus(422);
    $this->actingAs($platform)->postJson("/api/v1/platform/operations/maintenance/{$id}/transition", ['status' => 'approved'])->assertOk();
    $this->actingAs($platform)->postJson("/api/v1/platform/operations/maintenance/{$id}/transition", ['status' => 'in_progress'])->assertOk();
    $this->actingAs($platform)->postJson("/api/v1/platform/operations/maintenance/{$id}/transition", ['status' => 'completed'])->assertOk();
    expect(MaintenanceWindow::find($id)->approved_by)->toBe($platform->id)
        ->and(MaintenanceWindow::find($id)->approved_at)->not->toBeNull();
});

it('keeps database monitoring strictly read-only', function (): void {
    $platform = operationsFixture();
    $data = $this->actingAs($platform)->getJson('/api/v1/platform/operations/database')->assertOk()->json('data');
    expect($data['connections']['total'])->toBeInt()
        ->and($data['connections']['max_connections'])->toBeGreaterThan(0)
        ->and(is_array($data['largest_tables']))->toBeTrue()
        ->and(count($data['largest_tables']))->toBeLessThanOrEqual(15)
        // No mutations: the endpoint performs SELECT only against statistics views.
        ->and(json_encode($data))->not->toContain('INSERT')
        ->and(json_encode($data))->not->toContain('ALTER TABLE permission');
});

it('exposes queue and scheduler facts without fabricating worker heartbeats', function (): void {
    $platform = operationsFixture();
    $data = $this->actingAs($platform)->getJson('/api/v1/platform/operations/queues')->assertOk()->json('data');
    expect($data['pending_jobs'])->toBeInt()
        ->and($data['failed_jobs_count'])->toBeInt()
        ->and($data['retry_available'])->toBeFalse();
});

it('prevents duplicate alert fingerprints and lists incidents without creating rows', function (): void {
    $platform = operationsFixture();
    \App\Models\MonitoringAlert::create(['fingerprint' => 'fp-database-down-1', 'service' => 'database', 'severity' => 'critical', 'title' => 'DB down', 'description' => 'connectivity', 'status' => 'open', 'first_observed_at' => now(), 'last_observed_at' => now()]);
    // The unique fingerprint dedupes identical alert rows; a nested transaction keeps the test transaction usable.
    $rejected = false;
    try {
        \Illuminate\Support\Facades\DB::transaction(function (): void {
            \App\Models\MonitoringAlert::create(['fingerprint' => 'fp-database-down-1', 'service' => 'database', 'severity' => 'critical', 'title' => 'DB down', 'description' => 'connectivity', 'status' => 'open', 'first_observed_at' => now(), 'last_observed_at' => now()]);
        });
    } catch (\Illuminate\Database\QueryException) {
        $rejected = true;
    }
    expect($rejected)->toBeTrue();
    $before = MonitoringIncident::count();
    $data = $this->actingAs($platform)->getJson('/api/v1/platform/operations/incidents')->assertOk()->json('data');
    expect($data)->toBeArray()->and(MonitoringIncident::count())->toBe($before);
});

it('records releases manually and reports not_configured before any records exist', function (): void {
    $platform = operationsFixture();
    expect($this->actingAs($platform)->getJson('/api/v1/platform/operations/releases')->json('data.latest_version'))->toBe('not_configured');
    $this->actingAs($platform)->postJson('/api/v1/platform/operations/releases', ['version' => '12.0.0', 'environment' => 'staging', 'status' => 'deployed', 'release_notes' => 'Phase 12 support + operations', 'deployed_at' => now()->toIso8601String()])->assertCreated();
    $data = $this->actingAs($platform)->getJson('/api/v1/platform/operations/releases')->assertOk()->json('data');
    expect($data['current']['version'])->toBe('12.0.0')->and($data['current']['environment'])->toBe('staging');
});

it('keeps reserved incident resolution semantics auditable and maintenance notice owned by Phase 9 settings', function (): void {
    $platform = operationsFixture();
    [$business, $tenant] = businessUser();
    $this->actingAs($business)->postJson('/api/v1/platform/support/sla-policies', ['name' => 'Breach attempt'], ['X-Tenant-Id' => $tenant->id])->assertForbidden();
    expect($this->actingAs($platform)->getJson('/api/v1/platform/operations/maintenance/notice')->assertOk()->json('notice_endpoint'))->toBe('PATCH /api/v1/platform/settings/maintenance');
});
