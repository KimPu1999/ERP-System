<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function record(Request $request, string $action, ?string $tenantId, mixed $subject = null, array $metadata = []): void
    {
        if ($subject) {
            foreach (['company_id', 'supplier_id', 'branch_id'] as $key) {
                if (!array_key_exists($key, $metadata) && isset($subject->{$key})) $metadata[$key] = (string) $subject->{$key};
            }
        }
        PlatformAuditLog::create(['actor_user_id' => $request->user()?->id, 'tenant_id' => $tenantId, 'action' => $action, 'auditable_type' => $subject ? $subject::class : null, 'auditable_id' => $subject?->id, 'metadata' => $metadata, 'ip_address' => $request->ip()]);
    }
}
