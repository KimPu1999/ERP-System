<?php

namespace App\Services;

use App\Models\SecurityEvent;
use Illuminate\Http\Request;

final class SecurityEventLogger
{
    public function __construct(private readonly SensitiveDataRedactor $redactor) {}

    public function record(Request $request, string $event, ?string $userId = null, ?string $tenantId = null, array $metadata = []): void
    {
        $category = str_starts_with($event, 'auth.') || str_starts_with($event, 'mfa.') ? 'authentication' : (str_starts_with($event, 'permission.') ? 'authorization' : 'security');
        $result = str_contains($event, 'failed') || str_contains($event, 'blocked') || str_contains($event, 'denied') ? 'failure' : 'success';
        try {
            $record = SecurityEvent::create(['user_id' => $userId ?? $request->user()?->id, 'tenant_id' => $tenantId, 'event' => $event, 'category' => $category, 'result' => $result, 'request_id' => $request->header('X-Request-ID') ?: $request->header('X-Correlation-ID'), 'ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000), 'metadata' => $this->redactor->redact($metadata)]);
            try { app(SecurityAlertService::class)->evaluate($record); } catch (\Throwable) { /* Alert collection must never break authentication. */ }
        } catch (\Throwable) { /* Security telemetry must never break authentication. */ }
    }
}
