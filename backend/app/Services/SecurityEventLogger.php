<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;

final class SecurityEventLogger
{
    public function record(Request $request, string $event, ?string $userId = null, ?string $tenantId = null, array $metadata = []): void
    {
        SecurityEvent::create(['user_id' => $userId ?? $request->user()?->id, 'tenant_id' => $tenantId, 'event' => $event, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(), 'metadata' => $metadata]);
    }
}
