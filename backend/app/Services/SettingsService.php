<?php

namespace App\Services;

use App\Models\{PlatformBrandingAsset, PlatformEmailTemplate, PlatformSetting};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Central platform settings boundary.
 *
 * Precedence is deliberately explicit: platform defaults -> persisted platform
 * override -> tenant/user preferences in their existing domain services. This
 * service never reads or writes infrastructure secrets.
 */
final class SettingsService
{
    private const CACHE_PREFIX = 'platform-setting:';

    /** @return array<string, array<string, mixed>> */
    public function defaults(): array
    {
        return [
            'general' => [
                'display_name' => config('app.name', 'Enterprise ERP Cloud'),
                'description' => 'A secure multi-tenant ERP platform.',
                'support_email' => null,
                'support_phone' => null,
                'organization_contact' => null,
                'default_locale' => 'en',
                'default_timezone' => 'Asia/Yangon',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'application_url_display' => config('app.url'),
            ],
            'branding' => [
                'primary_color' => '#2563eb',
                'secondary_color' => '#0f172a',
                'accent_color' => '#14b8a6',
                'default_theme' => 'system',
                'sidebar_branding' => true,
                'login_branding' => true,
                'browser_title' => config('app.name', 'Enterprise ERP Cloud'),
                'assets' => [],
            ],
            'email' => [
                'reply_to' => null,
                'mailer' => config('mail.default'),
                'sender_name' => config('mail.from.name'),
                'sender_email' => config('mail.from.address'),
                'configuration_ready' => $this->mailConfigurationReady(),
                'external_delivery' => config('mail.default') !== 'log',
                'transport_note' => config('mail.default') === 'log'
                    ? 'The log transport records messages locally; it does not deliver email.'
                    : 'Transport acceptance is not proof of inbox delivery.',
            ],
            'localization' => [
                'default_locale' => 'en',
                'default_timezone' => 'Asia/Yangon',
                'date_format' => 'Y-m-d',
                'time_format' => 'H:i',
                'supported_locales' => ['en', 'my'],
            ],
            'business' => [
                'public_registration_enabled' => true,
                'require_email_verification' => true,
                'require_platform_approval' => true,
                'default_initial_status' => 'pending',
                'default_owner_role' => 'owner',
                'invitation_expiration_days' => 7,
                'business_logo_required' => false,
            ],
            'subscriptions' => [
                'default_plan_id' => null,
                'trial_eligible' => true,
                'trial_duration_days' => 14,
                'renewal_reminder_days' => 7,
                'grace_period_days' => 3,
                'default_billing_currency' => 'MMK',
                'invoice_due_days' => 30,
                'expiration_behavior' => 'restrict_new_operations',
            ],
            'notifications' => [
                'email_enabled' => true,
                'in_app_enabled' => true,
                'business_approval' => true,
                'subscription' => true,
                'payment' => true,
                'security_alerts' => true,
                'system_health' => true,
                'scheduled_reports' => true,
            ],
            'maintenance' => [
                'notice_enabled' => false,
                'notice' => null,
                'starts_at' => null,
                'ends_at' => null,
                'infrastructure_mode' => app()->isDownForMaintenance(),
                'infrastructure_mode_controlled_externally' => true,
            ],
        ];
    }

    /** @return array{value: array<string, mixed>, version: int} */
    public function get(string $key): array
    {
        $defaults = $this->defaults();
        abort_unless(array_key_exists($key, $defaults), 404, 'Unknown platform settings category.');
        $stored = Cache::remember(self::CACHE_PREFIX.$key, now()->addMinutes(10), fn (): ?PlatformSetting => PlatformSetting::query()->where('scope', 'platform')->where('setting_key', $key)->first());
        $value = array_replace($defaults[$key], $stored?->value ?? []);
        if ($key === 'branding') {
            $value['assets'] = $this->assetUrls();
        }
        if ($key === 'email') {
            $value['mailer'] = config('mail.default');
            $value['configuration_ready'] = $this->mailConfigurationReady();
            $value['external_delivery'] = config('mail.default') !== 'log';
        }
        if ($key === 'maintenance') {
            $value['infrastructure_mode'] = app()->isDownForMaintenance();
        }
        return ['value' => $value, 'version' => $stored?->version ?? 0];
    }

    /**
     * Read only the two non-secret policy categories needed before platform
     * authentication context exists. PostgreSQL RLS permits this path only
     * through the server-controlled connection-local flag below, which is
     * always reset before the connection is returned to the pool.
     *
     * @return array<string, mixed>
     */
    public function getForPolicy(string $key): array
    {
        abort_unless(in_array($key, ['business', 'subscriptions'], true), 403, 'This settings category is not available to policy evaluation.');
        if (DB::getDriverName() !== 'pgsql') return $this->get($key)['value'];
        DB::statement("SELECT set_config('app.platform_settings_policy_read', 'true', false)");
        try {
            $stored = PlatformSetting::query()->where('scope', 'platform')->where('setting_key', $key)->first();
        } finally {
            DB::statement("SELECT set_config('app.platform_settings_policy_read', 'false', false)");
        }
        return array_replace($this->defaults()[$key], $stored?->value ?? []);
    }

    /** @param array<string, mixed> $value */
    public function put(string $key, array $value, ?int $expectedVersion, ?string $updatedBy): array
    {
        $current = PlatformSetting::query()->where('scope', 'platform')->where('setting_key', $key)->lockForUpdate()->first();
        $currentVersion = $current?->version ?? 0;
        if ($expectedVersion !== null && $expectedVersion !== $currentVersion) {
            throw new RuntimeException('This settings section changed in another session. Refresh and try again.');
        }
        $setting = PlatformSetting::updateOrCreate(
            ['scope' => 'platform', 'setting_key' => $key],
            ['value' => $value, 'version' => $currentVersion + 1, 'updated_by' => $updatedBy],
        );
        Cache::put(self::CACHE_PREFIX.$key, $setting, now()->addMinutes(10));
        return ['value' => array_replace($this->defaults()[$key], $value), 'version' => $setting->version];
    }

    /** @return array<string, string> */
    public function assetUrls(): array
    {
        return PlatformBrandingAsset::query()->get()->mapWithKeys(fn (PlatformBrandingAsset $asset): array => [$asset->asset_key => Storage::disk('public')->url($asset->path)])->all();
    }

    public function mailConfigurationReady(): bool
    {
        $mailer = (string) config('mail.default');
        if ($mailer === 'log' || $mailer === 'array') return true;
        if ($mailer === 'smtp') return filled(config('mail.mailers.smtp.host')) && filled(config('mail.from.address'));
        return filled(config("mail.mailers.{$mailer}")) && filled(config('mail.from.address'));
    }

    /** @return array<string, array<string, mixed>> */
    public function templateDefaults(): array
    {
        return [
            'email_verification' => ['subject' => 'Verify your email address', 'body' => 'Please verify your email address to continue.'],
            'welcome' => ['subject' => 'Welcome to {{platform_name}}', 'body' => 'Welcome to {{platform_name}}.'],
            'business_invitation' => ['subject' => 'You are invited to {{business_name}}', 'body' => 'Use the secure invitation link to join {{business_name}}.'],
            'business_approval' => ['subject' => 'Business approved', 'body' => 'Your business registration has been approved.'],
            'business_rejection' => ['subject' => 'Business registration update', 'body' => 'Your business registration requires review.'],
            'password_reset' => ['subject' => 'Reset your password', 'body' => 'Use the secure password reset link to choose a new password.'],
            'subscription_activated' => ['subject' => 'Subscription activated', 'body' => 'Your subscription is now active.'],
            'payment_confirmation' => ['subject' => 'Payment confirmation', 'body' => 'Your payment has been recorded.'],
            'invoice_notification' => ['subject' => 'Invoice notification', 'body' => 'A new invoice is available.'],
            'trial_expiration' => ['subject' => 'Trial expiration reminder', 'body' => 'Your trial period is ending soon.'],
            'security_alert' => ['subject' => 'Security alert', 'body' => 'A security event requires your attention.'],
            'maintenance_notification' => ['subject' => 'Maintenance notification', 'body' => 'Planned maintenance is scheduled.'],
        ];
    }

    /** @return list<string> */
    public function allowedTemplateVariables(): array
    {
        return ['platform_name', 'business_name', 'user_name', 'support_email', 'verification_url', 'reset_url', 'invitation_url'];
    }
}
