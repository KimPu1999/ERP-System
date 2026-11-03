<?php

use App\Models\{PlatformAuditLog, PlatformEmailTemplate, PlatformSetting, User};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('exposes central platform settings without deployment secrets', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $response = $this->actingAs($platform)->getJson('/api/v1/platform/settings')->assertOk();
    $response->assertJsonStructure(['data' => ['general', 'branding', 'email', 'localization', 'business', 'subscriptions', 'notifications', 'maintenance', 'security', 'authentication']]);
    expect($response->json('data.email.value'))->not->toHaveKey('password')->and($response->json('data'))->not->toHaveKey('app_key');
    $this->actingAs(User::factory()->create())->getJson('/api/v1/platform/settings')->assertForbidden();
});

it('persists platform settings, increments versions, and audits the safe change', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $response = $this->actingAs($platform)->patchJson('/api/v1/platform/settings/general', ['value' => ['display_name' => 'ERP Cloud Yangon', 'description' => 'Updated', 'support_email' => 'support@example.test', 'support_phone' => null, 'organization_contact' => null, 'default_locale' => 'my', 'default_timezone' => 'Asia/Yangon', 'date_format' => 'Y-m-d', 'time_format' => 'H:i', 'application_url_display' => 'http://localhost:5173'], 'expected_version' => 0])->assertOk();
    expect($response->json('data.version'))->toBe(1)->and(PlatformSetting::where('setting_key', 'general')->firstOrFail()->value['display_name'])->toBe('ERP Cloud Yangon');
    expect(PlatformAuditLog::where('action', 'platform.settings.general.changed')->exists())->toBeTrue();
    $this->actingAs($platform)->patchJson('/api/v1/platform/settings/general', ['value' => ['display_name' => 'stale', 'description' => 'Updated', 'support_email' => 'support@example.test', 'support_phone' => null, 'organization_contact' => null, 'default_locale' => 'my', 'default_timezone' => 'Asia/Yangon', 'date_format' => 'Y-m-d', 'time_format' => 'H:i', 'application_url_display' => 'http://localhost:5173'], 'expected_version' => 0])->assertStatus(409);
});

it('rejects unsafe template expressions and records a safe template version', function (): void {
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->patchJson('/api/v1/platform/settings/email-templates/welcome/en', ['subject' => 'Hello {{platform_name}}', 'body' => '@php echo $password; @endphp', 'status' => 'draft', 'expected_version' => 0])->assertUnprocessable();
    $this->actingAs($platform)->patchJson('/api/v1/platform/settings/email-templates/welcome/en', ['subject' => 'Hello {{platform_name}}', 'body' => 'Welcome {{user_name}}.', 'status' => 'published', 'expected_version' => 0])->assertOk();
    expect(PlatformEmailTemplate::where('template_key', 'welcome')->where('locale', 'en')->value('status'))->toBe('published');
});

it('reports log mail transport truthfully and never claims inbox delivery', function (): void {
    config(['mail.default' => 'log']);
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $response = $this->actingAs($platform)->postJson('/api/v1/platform/settings/email/test', ['recipient' => 'recipient@example.test'])->assertOk();
    $response->assertJsonPath('data.status', 'logged_not_delivered')->assertJsonPath('data.mailer', 'log');
});

it('rejects unsafe svg branding uploads and accepts private generated assets only', function (): void {
    Storage::fake('public');
    $platform = User::factory()->create(['is_platform_super_admin' => true]);
    $this->actingAs($platform)->withHeader('Accept', 'application/json')->post('/api/v1/platform/settings/branding/assets', ['asset_key' => 'logo', 'file' => UploadedFile::fake()->createWithContent('evil.svg', '<svg><script>alert(1)</script></svg>')])->assertUnprocessable();
});
