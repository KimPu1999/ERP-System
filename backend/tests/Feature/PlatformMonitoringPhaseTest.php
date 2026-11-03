<?php

use App\Models\{MonitoringAlert, MonitoringErrorGroup, MonitoringIncident, MonitoringObservation, PlatformAuditLog, User};
use App\Services\MonitoringService;
use Illuminate\Support\Facades\Hash;

it('protects monitoring APIs to verified platform administrators', function (): void {
    $this->getJson('/api/v1/platform/monitoring/overview')->assertUnauthorized();

    $business = User::factory()->create();
    $this->actingAs($business)->getJson('/api/v1/platform/monitoring/overview')->assertForbidden();

    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $response = $this->actingAs($platform)->getJson('/api/v1/platform/monitoring/overview')->assertOk();
    expect($response->json('data.overall'))->toBe('unknown')
        ->and($response->json('data.services'))->not->toBeEmpty()
        ->and($response->json('data.limitations.api'))->toContain('unavailable');
});

it('uses stored observations and marks stale telemetry unknown without probing on dashboard reads', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    MonitoringObservation::create(['service' => 'database', 'check_key' => 'connectivity', 'status' => 'healthy', 'observed_at' => now()->subMinutes(10), 'detail' => 'old observation', 'metadata' => ['driver' => 'pgsql']]);
    MonitoringObservation::create(['service' => 'cache', 'check_key' => 'connectivity', 'status' => 'healthy', 'observed_at' => now(), 'detail' => 'fresh observation', 'metadata' => []]);

    $response = $this->actingAs($platform)->getJson('/api/v1/platform/monitoring/overview')->assertOk();
    $database = collect($response->json('data.services'))->firstWhere('service', 'database');
    $cache = collect($response->json('data.services'))->firstWhere('service', 'cache');
    expect($database['status'])->toBe('unknown')
        ->and($database['checks'][0]['stale'])->toBeTrue()
        ->and($cache['status'])->toBe('healthy');
});

it('collects real dependency observations without claiming queue or email delivery', function (): void {
    $result = app(MonitoringService::class)->collect();
    expect($result['status'])->toBeIn(['down', 'degraded', 'unknown', 'healthy'])
        ->and(MonitoringObservation::where('service', 'database')->where('status', 'healthy')->exists())->toBeTrue()
        ->and(MonitoringObservation::where('service', 'queue')->where('status', 'unknown')->exists())->toBeTrue()
        ->and(MonitoringObservation::where('service', 'email')->where('status', 'unknown')->exists())->toBeTrue();

    $queue = app(MonitoringService::class)->detail('queues');
    $email = app(MonitoringService::class)->detail('email');
    expect($queue['retry_available'])->toBeFalse()
        ->and($email['delivery_verified'])->toBeFalse()
        ->and(json_encode($queue))->not->toContain('APP_KEY');
});

it('groups sanitized exceptions and deduplicates repeated reports', function (): void {
    $service = app(MonitoringService::class);
    $exception = new RuntimeException('password=secret-value bearer abc.def.ghi token=hidden');
    $service->recordException($exception);
    $service->recordException($exception);

    $group = MonitoringErrorGroup::firstOrFail();
    expect($group->occurrence_count)->toBe(2)
        ->and($group->message)->not->toContain('secret-value')
        ->and($group->message)->not->toContain('abc.def.ghi')
        ->and($group->message)->toContain('[redacted]');
});

it('supports audited alert, incident, and settings workflows', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $alert = MonitoringAlert::create(['fingerprint' => hash('sha256', 'test'), 'service' => 'database', 'severity' => 'critical', 'title' => 'Database down', 'description' => 'test', 'status' => 'open', 'first_observed_at' => now(), 'last_observed_at' => now()]);

    $this->actingAs($platform)->postJson("/api/v1/platform/monitoring/alerts/{$alert->id}/acknowledge")->assertOk()->assertJsonPath('data.status', 'acknowledged');
    $this->actingAs($platform)->postJson("/api/v1/platform/monitoring/alerts/{$alert->id}/resolve", ['notes' => 'verified'])->assertOk()->assertJsonPath('data.status', 'resolved');

    $incident = $this->actingAs($platform)->postJson('/api/v1/platform/monitoring/incidents', ['title' => 'Database incident', 'severity' => 'critical', 'affected_services' => ['database']])->assertCreated()->json('data');
    $this->actingAs($platform)->postJson("/api/v1/platform/monitoring/incidents/{$incident['id']}/resolve", ['notes' => 'resolved'])->assertOk()->assertJsonPath('data.status', 'resolved');
    $this->actingAs($platform)->patchJson('/api/v1/platform/monitoring/settings', ['refresh_seconds' => 60, 'thresholds' => ['stale_after_seconds' => 600]])->assertOk()->assertJsonPath('data.refresh_seconds', 60);

    expect(PlatformAuditLog::where('action', 'platform.monitoring.alert_acknowledged')->exists())->toBeTrue()
        ->and(PlatformAuditLog::where('action', 'platform.monitoring.incident_created')->exists())->toBeTrue()
        ->and(PlatformAuditLog::where('action', 'platform.monitoring.settings_updated')->exists())->toBeTrue()
        ->and(MonitoringIncident::whereKey($incident['id'])->where('status', 'resolved')->exists())->toBeTrue();
});
