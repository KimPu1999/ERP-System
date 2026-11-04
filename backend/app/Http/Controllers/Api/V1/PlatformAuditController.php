<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{MonitoringAlert, PlatformAuditLog, SecurityEvent, SecuritySetting, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class PlatformAuditController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'action' => ['nullable', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:50'], 'result' => ['nullable', 'string', 'max:30'], 'resource_type' => ['nullable', 'string', 'max:150'], 'actor' => ['nullable', 'uuid'], 'tenant_id' => ['nullable', 'uuid'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100']]);
        $query = PlatformAuditLog::query()->with('actor:id,name,email')->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where('action', 'ilike', "%{$search}%");
        }
        if ($action = trim((string) $request->query('action'))) {
            $query->where('action', $action);
        }
        foreach (['category', 'result', 'resource_type'] as $filter) if ($value = trim((string) $request->query($filter))) $query->where($filter, $value);
        if ($actor = trim((string) $request->query('actor'))) $query->where('actor_user_id', $actor);
        if ($tenant = trim((string) $request->query('tenant_id'))) $query->where('tenant_id', $tenant);
        if ($from = $request->query('from')) $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $from)->startOfDay());
        if ($to = $request->query('to')) $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $to)->endOfDay());
        $logs = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        $logs->through(fn (PlatformAuditLog $log): array => [
            'id' => $log->id,
            'action' => $log->action,
            'category' => $log->category,
            'result' => $log->result,
            'request_id' => $log->request_id,
            'actor' => $log->actor?->only(['id', 'name', 'email']),
            'tenant_id' => $log->tenant_id,
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id,
            'metadata' => $log->metadata,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at?->toISOString(),
        ]);
        return response()->json(['data' => $logs]);
    }

    public function security(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'max:120'], 'event' => ['nullable', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:50'], 'result' => ['nullable', 'string', 'max:30'], 'user_id' => ['nullable', 'uuid'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100']]);
        $query = SecurityEvent::query()->with('user:id,name,email')->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('event', 'ilike', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%"));
            });
        }
        if ($event = trim((string) $request->query('event'))) $query->where('event', $event);
        foreach (['category', 'result', 'user_id'] as $filter) if ($value = trim((string) $request->query($filter))) $query->where($filter, $value);
        if ($from = $request->query('from')) $query->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $from)->startOfDay());
        if ($to = $request->query('to')) $query->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $to)->endOfDay());
        $events = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        $events->through(fn (SecurityEvent $event): array => [
            'id' => $event->id,
            'event' => $event->event,
            'category' => $event->category,
            'result' => $event->result,
            'request_id' => $event->request_id,
            'user' => $event->user?->only(['id', 'name', 'email']),
            'tenant_id' => $event->tenant_id,
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'metadata' => $event->metadata,
            'created_at' => $event->created_at?->toISOString(),
        ]);
        return response()->json(['data' => $events]);
    }

    public function overview(): JsonResponse
    {
        $since = now()->subDay();
        $platformUsers = User::where('is_platform_super_admin', true);
        $totalAdmins = (clone $platformUsers)->count();
        $mfaAdmins = (clone $platformUsers)->whereNotNull('mfa_enabled_at')->count();
        $activeSessions = DB::table('sessions')->whereNotNull('user_id')->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)->count();
        $failed = SecurityEvent::where('category', 'authentication')->where('result', 'failure')->where('created_at', '>=', $since)->count();
        $permissionChanges = PlatformAuditLog::where('category', 'authorization')->where('created_at', '>=', $since)->count();
        $openAlerts = MonitoringAlert::whereIn('status', ['open', 'acknowledged'])->count();
        $recent = SecurityEvent::with('user:id,name,email')->latest()->limit(10)->get()->map(fn (SecurityEvent $event): array => ['id' => $event->id, 'event' => $event->event, 'result' => $event->result, 'user' => $event->user?->only(['id', 'name', 'email']), 'created_at' => $event->created_at?->toISOString()]);
        return response()->json(['data' => ['metrics' => ['failed_login_attempts_24h' => $failed, 'active_sessions' => $activeSessions, 'mfa_enrollment' => ['enrolled' => $mfaAdmins, 'total' => $totalAdmins, 'coverage' => $totalAdmins ? round($mfaAdmins / $totalAdmins * 100, 2) : null], 'permission_changes_24h' => $permissionChanges, 'open_security_alerts' => $openAlerts], 'posture' => $totalAdmins === 0 ? 'unknown' : ($mfaAdmins === $totalAdmins ? 'protected' : 'attention_required'), 'posture_basis' => ['mfa_coverage' => 'Platform administrators with confirmed MFA enrollment divided by active platform administrators.', 'failed_logins' => 'Authentication security events with failure result during the previous 24 hours.', 'active_sessions' => 'Database sessions with activity inside the configured session lifetime.', 'alerts' => 'Open or acknowledged monitoring alerts; no security score is inferred.'], 'recent_events' => $recent, 'monitoring_available' => Schema::hasTable('monitoring_alerts')]]);
    }

    public function loginActivity(Request $request): JsonResponse
    {
        return $this->security($request);
    }

    public function sessions(Request $request): JsonResponse
    {
        $current = $request->session()->getId();
        $sessions = DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(['id', 'ip_address', 'user_agent', 'last_activity']);
        return response()->json(['data' => $sessions->map(fn ($session): array => ['id' => hash_hmac('sha256', $session->id, (string) config('app.key')), 'ip_address' => $session->ip_address, 'user_agent' => mb_substr((string) $session->user_agent, 0, 240), 'last_activity' => date(DATE_ATOM, (int) $session->last_activity), 'current' => hash_equals($session->id, $current)])]);
    }

    public function mfa(Request $request): JsonResponse
    {
        $users = User::where('is_platform_super_admin', true)->get(['id', 'name', 'email', 'mfa_enabled_at', 'last_login_at']);
        return response()->json(['data' => ['totp_available' => true, 'policy' => 'Platform Super Admin MFA is required for protected platform operations; enrollment is confirmed per account.', 'admins' => $users->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'enrolled' => (bool) $user->mfa_enabled_at, 'enabled_at' => $user->mfa_enabled_at?->toISOString(), 'last_login_at' => $user->last_login_at?->toISOString()])]]);
    }

    public function permissions(): JsonResponse
    {
        return response()->json(['data' => ['roles' => DB::table('roles')->select(['id', 'name', 'slug', 'tenant_id'])->orderBy('name')->get(), 'permissions' => DB::table('permissions')->select(['id', 'name', 'description'])->orderBy('name')->get(), 'recent_changes' => PlatformAuditLog::where('category', 'authorization')->latest()->limit(50)->get(['id', 'action', 'actor_user_id', 'auditable_id', 'created_at'])]]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', 'in:open,acknowledged,resolved'], 'severity' => ['nullable', 'in:informational,warning,critical'], 'per_page' => ['nullable', 'integer', 'min:10', 'max:100']]);
        $query = MonitoringAlert::query()->latest('last_observed_at');
        if ($status = $request->query('status')) $query->where('status', $status);
        if ($severity = $request->query('severity')) $query->where('severity', $severity);
        $page = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        return response()->json(['data' => $page]);
    }

    public function settings(Request $request): JsonResponse
    {
        $defaults = ['platform_mfa_required' => app(\App\Services\SecurityPolicy::class)->platformMfaRequired(), 'business_admin_mfa_required' => false, 'session_timeout_minutes' => (int) config('session.lifetime'), 'step_up_validity_minutes' => 10, 'audit_retention_days' => 365];
        $stored = SecuritySetting::query()->whereIn('key', ['business_admin_mfa_required', 'session_timeout_minutes', 'step_up_validity_minutes', 'audit_retention_days'])->get()->mapWithKeys(fn (SecuritySetting $setting): array => [$setting->key => $setting->value['value'] ?? $setting->value])->all();
        return response()->json(['data' => array_replace($defaults, $stored)]);
    }

    public function updateSettings(Request $request, \App\Services\AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['business_admin_mfa_required' => ['sometimes', 'boolean'], 'session_timeout_minutes' => ['sometimes', 'integer', 'min:15', 'max:10080'], 'step_up_validity_minutes' => ['sometimes', 'integer', 'min:1', 'max:60'], 'audit_retention_days' => ['sometimes', 'integer', 'min:30', 'max:3650']]);
        if ($request->filled('platform_mfa_required')) abort_unless((bool) $request->boolean('platform_mfa_required') === app(\App\Services\SecurityPolicy::class)->platformMfaRequired(), 422, 'Platform MFA enforcement is environment-controlled and cannot be changed from the application.');
        abort_unless((int) $request->session()->get('security.step_up_until', 0) > now()->timestamp, 428, 'Recent authentication is required for this action.');
        foreach ($data as $key => $value) SecuritySetting::updateOrCreate(['key' => $key], ['value' => ['value' => $value], 'updated_by' => $request->user()->id]);
        $audit->record($request, 'security.settings.changed', null, null, ['keys' => array_keys($data)]);
        return $this->settings($request);
    }
}
