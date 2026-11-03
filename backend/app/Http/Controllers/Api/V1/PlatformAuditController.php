<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{PlatformAuditLog, SecurityEvent};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformAuditController
{
    public function index(Request $request): JsonResponse
    {
        $query = PlatformAuditLog::query()->with('actor:id,name,email')->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where('action', 'ilike', "%{$search}%");
        }
        if ($action = trim((string) $request->query('action'))) {
            $query->where('action', $action);
        }
        $logs = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        $logs->through(fn (PlatformAuditLog $log): array => [
            'id' => $log->id,
            'action' => $log->action,
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
        $query = SecurityEvent::query()->with('user:id,name,email')->latest();
        if ($search = trim((string) $request->query('search'))) {
            $query->where('event', 'ilike', "%{$search}%");
        }
        $events = $query->paginate(min(100, max(10, (int) $request->query('per_page', 25))));
        $events->through(fn (SecurityEvent $event): array => [
            'id' => $event->id,
            'event' => $event->event,
            'user' => $event->user?->only(['id', 'name', 'email']),
            'tenant_id' => $event->tenant_id,
            'ip_address' => $event->ip_address,
            'user_agent' => $event->user_agent,
            'metadata' => $event->metadata,
            'created_at' => $event->created_at?->toISOString(),
        ]);
        return response()->json(['data' => $events]);
    }
}
