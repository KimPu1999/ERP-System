<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function record(Request $request, string $action, ?string $tenantId, mixed $subject = null, array $metadata = []): void
    {
        if ($subject) {
            foreach (['company_id', 'supplier_id', 'branch_id'] as $key) {
                if (!array_key_exists($key, $metadata) && isset($subject->{$key})) $metadata[$key] = (string) $subject->{$key};
            }
        }
        $category = str_contains($action, 'security') || str_contains($action, 'mfa') || str_contains($action, 'session') ? 'security' : (str_contains($action, 'permission') || str_contains($action, 'role') ? 'authorization' : 'system');
        PlatformAuditLog::create(['actor_user_id' => $request->user()?->id, 'tenant_id' => $tenantId, 'action' => $action, 'category' => $category, 'result' => 'success', 'request_id' => $request->header('X-Request-ID') ?: $request->header('X-Correlation-ID'), 'actor_type' => $request->user() ? get_class($request->user()) : 'system', 'resource_type' => $subject ? $subject::class : null, 'auditable_type' => $subject ? $subject::class : null, 'auditable_id' => $subject?->id, 'metadata' => $this->redactor->redact($metadata), 'ip_address' => $request->ip()]);
    }
}
