<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{MonitoringAlert, MonitoringIncident};
use App\Services\MonitoringService;
use Illuminate\Http\{JsonResponse, Request};

final class PlatformMonitoringController
{
    public function __construct(private readonly MonitoringService $monitoring)
    {
    }

    public function overview(): JsonResponse { return response()->json(['data' => $this->monitoring->overview()]); }
    public function service(string $service): JsonResponse { return response()->json(['data' => $this->monitoring->detail($service)]); }
    public function errors(Request $request): JsonResponse { return response()->json(['data' => $this->monitoring->errors($request)]); }
    public function alerts(Request $request): JsonResponse { return response()->json(['data' => $this->monitoring->alerts($request)]); }
    public function incidents(Request $request): JsonResponse { return response()->json(['data' => $this->monitoring->incidents($request)]); }
    public function createIncident(Request $request): JsonResponse { return response()->json(['data' => $this->monitoring->createIncident($request)], 201); }
    public function settings(): JsonResponse { $setting = $this->monitoring->settings(); return response()->json(['data' => ['enabled' => $setting->enabled, 'thresholds' => $setting->thresholds, 'notification_preferences' => $setting->notification_preferences, 'retention_days' => $setting->retention_days, 'refresh_seconds' => $setting->refresh_seconds]]); }
    public function updateSettings(Request $request): JsonResponse { $setting = $this->monitoring->updateSettings($request); return response()->json(['data' => ['enabled' => $setting->enabled, 'thresholds' => $setting->thresholds, 'notification_preferences' => $setting->notification_preferences, 'retention_days' => $setting->retention_days, 'refresh_seconds' => $setting->refresh_seconds]]); }
    public function acknowledgeAlert(Request $request, MonitoringAlert $alert): JsonResponse { return response()->json(['data' => $this->monitoring->acknowledgeAlert($request, $alert)]); }
    public function resolveAlert(Request $request, MonitoringAlert $alert): JsonResponse { return response()->json(['data' => $this->monitoring->resolveAlert($request, $alert)]); }
    public function acknowledgeIncident(Request $request, MonitoringIncident $incident): JsonResponse { return response()->json(['data' => $this->monitoring->acknowledgeIncident($request, $incident)]); }
    public function resolveIncident(Request $request, MonitoringIncident $incident): JsonResponse { return response()->json(['data' => $this->monitoring->resolveIncident($request, $incident)]); }
    public function addIncidentNote(Request $request, MonitoringIncident $incident): JsonResponse { return response()->json(['data' => $this->monitoring->addIncidentNote($request, $incident)]); }
}
