<?php

namespace App\Services;

use App\Models\PlatformAuditLog;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function record(Request $request, string $action, ?string $tenantId, mixed $subject = null, array $metadata = []): void
    {
        PlatformAuditLog::create(['actor_user_id' => $request->user()?->id, 'tenant_id' => $tenantId, 'action' => $action, 'auditable_type' => $subject ? $subject::class : null, 'auditable_id' => $subject?->id, 'metadata' => $metadata, 'ip_address' => $request->ip()]);
    }
}
