<?php

use App\Models\{AuditLogArchive, MonitoringAlert, Permission, PlatformAuditLog, ReportExport, Role, RoleAssignment, SecurityEvent, Tenant, TenantMembership, User};
use App\Models\SecuritySetting;
use App\Services\{AuditLogger, MfaService, PlatformAnalyticsService, SecurityEventLogger, SecurityPolicy};
use App\Support\TenantContext;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use Illuminate\Http\Request;
use App\Jobs\GeneratePlatformReportExport;
use Carbon\Carbon;

it('exposes real security overview only to platform administrators', function (): void {
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->getJson('/api/v1/platform/security/overview')->assertOk()->assertJsonStructure(['data' => ['metrics', 'posture', 'posture_basis', 'recent_events']]);
    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/security/overview')->assertForbidden();
});

it('records successful login and new-device activity without storing a device fingerprint', function (): void {
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true, 'email_verified_at' => now(), 'password' => 'password']);
    $this->postJson('/api/v1/auth/platform/login', ['email' => $platform->email, 'password' => 'password'])->assertOk();
    expect(SecurityEvent::where('user_id', $platform->id)->where('event', 'auth.platform_login_succeeded')->exists())->toBeTrue();
    $event = SecurityEvent::where('user_id', $platform->id)->where('event', 'auth.new_device')->latest()->first();
    expect($event)->not->toBeNull()->and($event?->metadata)->toHaveKey('device_hash')->and($event?->metadata)->not->toHaveKey('device_fingerprint');
});

it('keeps audit events immutable and redacts sensitive logger metadata', function (): void {
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $log = PlatformAuditLog::create(['actor_user_id' => $platform->id, 'action' => 'security.test', 'metadata' => ['safe' => true]]);
    expect(fn () => $log->update(['action' => 'changed']))->toThrow(\LogicException::class);
    $this->actingAs($platform)->getJson('/api/v1/platform/security/audit-logs')->assertOk()->assertJsonMissing(['password' => 'secret']);
});

it('redacts nested sensitive metadata and rejects direct database audit mutation', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $request = Request::create('/security-test', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
    $request->setUserResolver(fn () => $platform);
    app(AuditLogger::class)->record($request, 'security.nested_test', null, null, ['safe' => ['value' => 'ok'], 'credentials' => ['password' => 'secret', 'nested_token' => 'raw']]);
    $log = PlatformAuditLog::latest()->firstOrFail();
    expect($log->metadata)->toMatchArray(['safe' => ['value' => 'ok'], 'credentials' => '[REDACTED]']);
    expect(fn () => DB::table('platform_audit_logs')->where('id', $log->id)->update(['action' => 'tampered']))->toThrow(\Illuminate\Database\QueryException::class);
    expect(fn () => DB::table('platform_audit_logs')->where('id', $log->id)->delete())->toThrow(\Illuminate\Database\QueryException::class);
});

it('does not expose raw database session identifiers', function (): void {
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->withSession(['_token' => 'test'])->getJson('/api/v1/platform/security/sessions')->assertOk()->assertJsonStructure(['data']);
});

it('requires a valid TOTP confirmation before enabling MFA', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $response = $this->actingAs($platform)->postJson('/api/v1/auth/mfa/enroll');
    $response->assertOk();
    $secret = $response->json('secret');
    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => '000000'])->assertStatus(422);
    $code = app(MfaService::class)->totp($secret, (int) floor(time() / 30));
    $this->postJson('/api/v1/auth/mfa/confirm', ['code' => $code])->assertOk()->assertJsonStructure(['recovery_codes']);
    expect($platform->fresh()->mfa_enabled_at)->not->toBeNull();
});

it('accepts leading-zero TOTP codes and rejects expired windows and another secret', function (): void {
    $mfa = app(MfaService::class);
    $secret = 'JBSWY3DPEHPK3PXP';
    Carbon::setTestNow(Carbon::createFromTimestamp(1700000000));
    $counter = intdiv(1700000000, 30);
    $code = $mfa->totp($secret, $counter);
    expect($mfa->verify($secret, $code))->toBeTrue()
        ->and($mfa->verify('KRUGS4ZANFZSAYJA', $code))->toBeFalse();
    Carbon::setTestNow(Carbon::createFromTimestamp(1700000121));
    expect($mfa->verify($secret, $code))->toBeFalse();
    Carbon::setTestNow();
});

it('does not decrypt malformed MFA storage into a usable secret', function (): void {
    $user = User::factory()->make(['mfa_secret_encrypted' => 'malformed-ciphertext']);
    expect(app(MfaService::class)->decrypt($user))->toBeNull();
});

it('returns security settings with secure defaults and validates updates', function (): void {
    config(['security.platform_mfa_required' => true]);
    expect(app(SecurityPolicy::class)->platformMfaRequired())->toBeTrue();
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->getJson('/api/v1/platform/security/settings')->assertOk()->assertJsonPath('data.platform_mfa_required', false);
    $this->actingAs($platform)->patchJson('/api/v1/platform/security/settings', ['step_up_validity_minutes' => 0])->assertUnprocessable();
    // The environment-controlled policy key is not persisted and cannot weaken enforcement.
    $this->postJson('/api/v1/auth/step-up', ['password' => 'password', 'code' => null])->assertOk();
    $this->patchJson('/api/v1/platform/security/settings', ['platform_mfa_required' => false])->assertOk()->assertJsonPath('data.platform_mfa_required', false);
    expect(SecuritySetting::whereKey('platform_mfa_required')->exists())->toBeFalse();
});

it('requires recent authentication for valid security setting changes', function (): void {
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->patchJson('/api/v1/platform/security/settings', ['step_up_validity_minutes' => 15])->assertStatus(428);
    $this->actingAs($platform)->postJson('/api/v1/auth/step-up', ['password' => 'password'])->assertOk();
    $this->patchJson('/api/v1/platform/security/settings', ['step_up_validity_minutes' => 15])->assertOk()->assertJsonPath('data.step_up_validity_minutes', 15);
});

it('lists and revokes personal access tokens without exposing token material', function (): void {
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $token = $platform->createToken('integration', ['read'])->accessToken;
    $this->actingAs($platform)->getJson('/api/v1/auth/tokens')->assertOk()->assertJsonPath('data.0.name', 'integration')->assertJsonMissing(['token' => $token]);
    $this->postJson('/api/v1/auth/step-up', ['password' => 'password'])->assertOk();
    $this->deleteJson('/api/v1/auth/tokens/'.$platform->tokens()->first()->id)->assertOk();
    expect($platform->tokens()->count())->toBe(0);
});

it('deduplicates repeated authentication failure alerts', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $request = Request::create('/api/v1/auth/platform/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.20.30.40', 'HTTP_USER_AGENT' => 'security-test']);
    $logger = app(SecurityEventLogger::class);
    for ($i = 0; $i < 5; $i++) $logger->record($request, 'auth.login_failed', $platform->id, null, ['password' => 'redacted']);
    expect(MonitoringAlert::where('service', 'authentication')->where('title', 'Repeated authentication failures')->count())->toBe(1);
});

it('archives expired audit records before pruning them', function (): void {
    $id = (string) \Illuminate\Support\Str::uuid();
    DB::table('platform_audit_logs')->insert(['id' => $id, 'action' => 'security.expired', 'category' => 'security', 'result' => 'success', 'actor_type' => 'system', 'created_at' => now()->subDays(400), 'updated_at' => now()->subDays(400)]);
    $this->artisan('platform:security-prune')->assertSuccessful();
    expect(AuditLogArchive::whereKey($id)->exists())->toBeTrue()->and(PlatformAuditLog::whereKey($id)->exists())->toBeFalse();
});

it('requires step-up before queueing a security export', function (): void {
    Queue::fake();
    config(['security.platform_mfa_required' => false]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->postJson('/api/v1/platform/security/reports', ['report_key' => 'failed_logins', 'format' => 'csv'])->assertStatus(428);
    $this->postJson('/api/v1/auth/step-up', ['password' => 'password'])->assertOk();
    $response = $this->postJson('/api/v1/platform/security/reports', ['report_key' => 'failed_logins', 'format' => 'csv'])->assertStatus(202);
    expect($response->json('data.expires_at'))->not->toBeNull();
    Queue::assertPushed(GeneratePlatformReportExport::class);
});

it('generates private csv xlsx and pdf security exports without exposing secrets', function (): void {
    Storage::fake('local');
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    foreach (['csv', 'xlsx', 'pdf'] as $format) {
        $export = ReportExport::create(['requested_by' => $platform->id, 'report_key' => 'mfa_enrollment', 'format' => $format, 'filters' => [], 'state' => 'queued', 'platform_scope' => true, 'expires_at' => now()->addDay()]);
        (new GeneratePlatformReportExport($export->id))->handle(app(PlatformAnalyticsService::class), app(TenantContext::class));
        $path = 'private/platform-reports/'.$export->id.'.'.$format;
        Storage::disk('local')->assertExists($path);
        $content = Storage::disk('local')->get($path);
        expect($export->fresh()->state)->toBe('completed');
        expect($content)->not->toContain('password');
        if ($format === 'xlsx') expect($content)->toStartWith('PK');
        if ($format === 'pdf') expect($content)->toStartWith('%PDF-1.4');
    }
});

it('enforces platform MFA when the production policy is enabled', function (): void {
    config(['security.platform_mfa_required' => true]);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->getJson('/api/v1/platform/security/overview')->assertForbidden()->assertJsonPath('message', 'Platform MFA enrollment is required before platform access can be granted.');
    $response = $this->postJson('/api/v1/auth/platform/login', ['email' => $platform->email, 'password' => 'password'])->assertStatus(202);
    expect($response->json('mfa_enrollment_required'))->toBeTrue();
});

it('enforces the configurable MFA policy for business administrators', function (): void {
    SecuritySetting::create(['key' => 'business_admin_mfa_required', 'value' => ['value' => true]]);
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['password' => 'password']);
    TenantMembership::create(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'status' => 'active']);
    $permission = Permission::create(['name' => 'organization.manage', 'description' => 'Manage organization']);
    $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Business Admin', 'slug' => 'business-admin']);
    $role->permissions()->attach($permission);
    RoleAssignment::create(['tenant_id' => $tenant->id, 'role_id' => $role->id, 'user_id' => $user->id]);

    $response = $this->postJson('/api/v1/auth/business/login', ['email' => $user->email, 'password' => 'password']);
    $response->assertStatus(202)->assertJsonPath('mfa_enrollment_required', true)->assertJsonPath('mfa_mode', 'business');
});

it('regenerates MFA recovery codes only after step-up and replaces the prior hashes', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true, 'mfa_enabled_at' => now(), 'mfa_secret_encrypted' => app(MfaService::class)->encrypt('JBSWY3DPEHPK3PXP')]);
    $this->actingAs($platform)->postJson('/api/v1/auth/mfa/recovery-codes')->assertStatus(428);
    $this->postJson('/api/v1/auth/step-up', ['password' => 'password', 'code' => app(MfaService::class)->totp('JBSWY3DPEHPK3PXP', (int) floor(time() / 30))])->assertOk();
    $response = $this->postJson('/api/v1/auth/mfa/recovery-codes')->assertOk()->assertJsonStructure(['recovery_codes']);
    expect($response->json('recovery_codes'))->toHaveCount(8)->and($platform->fresh()->mfa_recovery_codes)->toHaveCount(8);
});
