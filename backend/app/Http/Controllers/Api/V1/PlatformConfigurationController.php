<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{PlatformBrandingAsset, PlatformEmailTemplate, PlatformSetting};
use App\Services\{AuditLogger, SettingsService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{DB, Mail, Storage, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PlatformConfigurationController
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): JsonResponse
    {
        $data = [];
        foreach (array_keys($this->settings->defaults()) as $category) {
            $data[$category] = $this->settings->get($category);
        }
        $data['authentication'] = [
            'email_verification_required' => true,
            'mfa_capable' => class_exists(\App\Services\MfaService::class),
            'session_encrypted' => (bool) config('session.encrypt'),
            'session_secure_cookie' => (bool) config('session.secure'),
        ];
        $data['security'] = [
            'debug_mode' => (bool) config('app.debug'),
            'platform_authorization' => 'server_enforced',
            'tenant_isolation' => 'database_context_and_server_authorization',
        ];
        $data['security_policy'] = app(PlatformAuditController::class)->settings(request())->getData(true)['data'];
        return response()->json(['data' => $data]);
    }

    public function show(string $category): JsonResponse
    {
        return response()->json(['data' => $this->settings->get($category)]);
    }

    public function update(Request $request, string $category, AuditLogger $audit): JsonResponse
    {
        $current = $this->settings->get($category);
        $before = $this->safeDiff($category, $current['value']);
        $value = $this->validateCategory($request, $category, (array) $request->input('value', $request->except(['expected_version'])));
        $expected = $request->has('expected_version') ? $request->integer('expected_version') : null;

        try {
            $result = DB::transaction(function () use ($request, $audit, $category, $value, $expected, $before): array {
                $result = $this->settings->put($category, $value, $expected, $request->user()->id);
                $audit->record($request, 'platform.settings.'.$category.'.changed', null, null, [
                    'category' => $category,
                    'before' => $before,
                    'after' => $result['value'],
                ]);
                return $result;
            });
        } catch (\RuntimeException $exception) {
            abort(409, $exception->getMessage());
        }

        return response()->json(['data' => $result]);
    }

    public function uploadBranding(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate([
            'asset_key' => ['required', Rule::in(['logo', 'dark_logo', 'favicon'])],
            'file' => ['required', 'file', 'max:2048', 'mimetypes:image/png,image/jpeg,image/webp,image/x-icon,image/vnd.microsoft.icon'],
        ]);
        $file = $data['file'];
        $path = $file->storeAs('platform/branding', Str::uuid().'.'.$file->extension(), 'public');
        abort_unless($path, 422, 'The branding asset could not be stored.');
        $old = PlatformBrandingAsset::query()->where('asset_key', $data['asset_key'])->first();
        $asset = PlatformBrandingAsset::updateOrCreate(
            ['asset_key' => $data['asset_key']],
            ['path' => $path, 'mime_type' => (string) $file->getMimeType(), 'size_bytes' => (int) $file->getSize(), 'sha256' => hash_file('sha256', $file->getRealPath()), 'updated_by' => $request->user()->id],
        );
        if ($old && $old->path !== $path) Storage::disk('public')->delete($old->path);
        $audit->record($request, 'platform.settings.branding.asset_replaced', null, $asset, ['asset_key' => $asset->asset_key, 'mime_type' => $asset->mime_type, 'size_bytes' => $asset->size_bytes]);
        return response()->json(['data' => $this->settings->get('branding')]);
    }

    public function removeBranding(Request $request, string $assetKey, AuditLogger $audit): JsonResponse
    {
        abort_unless(in_array($assetKey, ['logo', 'dark_logo', 'favicon'], true), 404);
        $asset = PlatformBrandingAsset::query()->where('asset_key', $assetKey)->first();
        if ($asset) {
            Storage::disk('public')->delete($asset->path);
            $asset->delete();
            $audit->record($request, 'platform.settings.branding.asset_removed', null, null, ['asset_key' => $assetKey]);
        }
        return response()->json(['data' => $this->settings->get('branding')]);
    }

    public function emailTest(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['recipient' => ['required', 'email', 'max:255']]);
        $email = $this->settings->get('email')['value'];
        $subject = 'ERP Cloud platform email test';
        Mail::raw('This is a platform email configuration test. It contains no authentication or recovery links.', function ($message) use ($data, $email, $subject): void {
            $message->to($data['recipient'])->subject($subject)->from($email['sender_email'], $email['sender_name']);
            if (filled($email['reply_to'])) $message->replyTo($email['reply_to']);
        });
        $audit->record($request, 'platform.settings.email.test_sent', null, null, ['recipient_domain' => Str::afterLast($data['recipient'], '@'), 'mailer' => config('mail.default'), 'delivery_claim' => config('mail.default') === 'log' ? 'logged_not_delivered' : 'transport_accepted_not_inbox_verified']);
        return response()->json(['data' => ['status' => config('mail.default') === 'log' ? 'logged_not_delivered' : 'transport_accepted_not_inbox_verified', 'mailer' => config('mail.default'), 'message' => config('mail.default') === 'log' ? 'The message was written to the log transport; no external email was sent.' : 'The configured mail transport accepted the message. Inbox delivery is not verified.']]);
    }

    public function templates(): JsonResponse
    {
        $stored = PlatformEmailTemplate::query()->orderBy('template_key')->orderBy('locale')->get()->keyBy(fn (PlatformEmailTemplate $template): string => $template->template_key.'|'.$template->locale);
        $rows = [];
        foreach ($this->settings->templateDefaults() as $key => $default) {
            foreach (['en', 'my'] as $locale) {
                $template = $stored->get($key.'|'.$locale);
                $rows[] = ['id' => $template?->id, 'template_key' => $key, 'locale' => $locale, 'subject' => $template?->subject ?? $default['subject'], 'body' => $template?->body ?? $default['body'], 'status' => $template?->status ?? 'draft', 'version' => $template?->version ?? 0, 'published_at' => $template?->published_at?->toISOString()];
            }
        }
        return response()->json(['data' => ['templates' => $rows, 'allowed_variables' => $this->settings->allowedTemplateVariables()]]);
    }

    public function updateTemplate(Request $request, string $templateKey, string $locale, AuditLogger $audit): JsonResponse
    {
        abort_unless(array_key_exists($templateKey, $this->settings->templateDefaults()), 404);
        abort_unless(in_array($locale, ['en', 'my'], true), 404);
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:20000'], 'status' => ['sometimes', Rule::in(['draft', 'published'])], 'expected_version' => ['nullable', 'integer', 'min:0']]);
        $this->assertSafeTemplate($data['subject'], $data['body']);
        $template = DB::transaction(function () use ($request, $data, $templateKey, $locale): PlatformEmailTemplate {
            $template = PlatformEmailTemplate::query()->where('template_key', $templateKey)->where('locale', $locale)->lockForUpdate()->first();
            $version = $template?->version ?? 0;
            if (array_key_exists('expected_version', $data) && $data['expected_version'] !== null && (int) $data['expected_version'] !== $version) abort(409, 'This email template changed in another session. Refresh and try again.');
            if ($template) DB::table('platform_email_template_versions')->insert(['id' => (string) Str::uuid(), 'template_id' => $template->id, 'version' => $version, 'subject' => $template->subject, 'body' => $template->body, 'status' => $template->status, 'created_by' => $request->user()->id, 'created_at' => now()]);
            return PlatformEmailTemplate::updateOrCreate(['template_key' => $templateKey, 'locale' => $locale], ['subject' => $data['subject'], 'body' => $data['body'], 'status' => $data['status'] ?? 'draft', 'version' => $version + 1, 'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : $template?->published_at, 'updated_by' => $request->user()->id]);
        });
        $audit->record($request, 'platform.settings.email_template.changed', null, $template, ['template_key' => $templateKey, 'locale' => $locale, 'status' => $template->status, 'version' => $template->version]);
        return response()->json(['data' => $template]);
    }

    public function status(): JsonResponse
    {
        return response()->json(['data' => [
            'maintenance' => $this->settings->get('maintenance')['value'],
            'backups' => ['available' => false, 'provider' => config('platform.backup_provider'), 'status' => 'unknown', 'reason' => 'No verified backup observer is configured.', 'rpo' => config('platform.backup_rpo'), 'rto' => config('platform.backup_rto')],
            'integrations' => [
                'mail' => ['enabled' => true, 'status' => $this->settings->mailConfigurationReady() ? 'configured' : 'not_configured', 'mailer' => config('mail.default'), 'last_check' => null],
                'storage' => ['enabled' => true, 'status' => config('filesystems.default') ? 'configured' : 'not_configured', 'disk' => config('filesystems.default'), 'last_check' => null],
                'queue' => ['enabled' => config('queue.default') !== 'sync', 'status' => config('queue.default') === 'sync' ? 'synchronous' : 'configured', 'driver' => config('queue.default'), 'last_check' => null],
            ],
        ]]);
    }

    public function auditHistory(Request $request): JsonResponse
    {
        return app(PlatformAuditController::class)->index($request->merge(['search' => 'platform.settings.']));
    }

    /** @return array<string, mixed> */
    private function validateCategory(Request $request, string $category, array $value): array
    {
        $rules = match ($category) {
            'general' => ['display_name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500'], 'support_email' => ['nullable', 'email', 'max:255'], 'support_phone' => ['nullable', 'string', 'max:40'], 'organization_contact' => ['nullable', 'string', 'max:500'], 'default_locale' => ['required', Rule::in(['en', 'my'])], 'default_timezone' => ['required', 'timezone'], 'date_format' => ['required', 'string', 'max:40'], 'time_format' => ['required', 'string', 'max:40'], 'application_url_display' => ['nullable', 'url', 'max:255']],
            'branding' => ['primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'default_theme' => ['required', Rule::in(['light', 'dark', 'system'])], 'sidebar_branding' => ['required', 'boolean'], 'login_branding' => ['required', 'boolean'], 'browser_title' => ['required', 'string', 'max:150']],
            'email' => ['sender_name' => ['required', 'string', 'max:150'], 'sender_email' => ['required', 'email', 'max:255'], 'reply_to' => ['nullable', 'email', 'max:255']],
            'localization' => ['default_locale' => ['required', Rule::in(['en', 'my'])], 'default_timezone' => ['required', 'timezone'], 'date_format' => ['required', 'string', 'max:40'], 'time_format' => ['required', 'string', 'max:40']],
            'business' => ['public_registration_enabled' => ['required', 'boolean'], 'require_email_verification' => ['required', 'boolean'], 'require_platform_approval' => ['required', 'boolean'], 'default_initial_status' => [Rule::in(['pending', 'active'])], 'default_owner_role' => ['required', 'string', 'max:80'], 'invitation_expiration_days' => ['required', 'integer', 'min:1', 'max:90'], 'business_logo_required' => ['required', 'boolean']],
            'subscriptions' => ['default_plan_id' => ['nullable', 'uuid'], 'trial_eligible' => ['required', 'boolean'], 'trial_duration_days' => ['required', 'integer', 'min:0', 'max:365'], 'renewal_reminder_days' => ['required', 'integer', 'min:0', 'max:90'], 'grace_period_days' => ['required', 'integer', 'min:0', 'max:90'], 'default_billing_currency' => ['required', 'string', 'size:3'], 'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'], 'expiration_behavior' => [Rule::in(['restrict_new_operations', 'read_only'])]],
            'notifications' => array_fill_keys(['email_enabled', 'in_app_enabled', 'business_approval', 'subscription', 'payment', 'security_alerts', 'system_health', 'scheduled_reports'], ['required', 'boolean']),
            'maintenance' => ['notice_enabled' => ['required', 'boolean'], 'notice' => ['nullable', 'string', 'max:2000'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at']],
            default => throw ValidationException::withMessages(['category' => 'This platform settings category is not editable.']),
        };
        return Validator::make($value, $rules)->validate();
    }

    private function assertSafeTemplate(string $subject, string $body): void
    {
        if (preg_match('/<\?(?:php)?|@(?:if|foreach|include|component|php|eval)\b/i', $subject.'\n'.$body)) throw ValidationException::withMessages(['body' => 'Executable template expressions are not allowed.']);
        preg_match_all('/{{\s*([a-z_]+)\s*}}/', $subject.'\n'.$body, $matches);
        $unknown = array_diff(array_unique($matches[1] ?? []), $this->settings->allowedTemplateVariables());
        if ($unknown) throw ValidationException::withMessages(['body' => 'Unsupported template variable: '.implode(', ', $unknown)]);
    }

    /** @param array<string, mixed> $value */
    private function safeDiff(string $category, array $value): array
    {
        if ($category === 'email') unset($value['mailer'], $value['configuration_ready'], $value['external_delivery']);
        return $value;
    }
}
