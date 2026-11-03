<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\GeneratePlatformReportExport;
use App\Models\ReportExport;
use App\Services\{AuditLogger, PlatformAnalyticsService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Storage;

final class PlatformAnalyticsController
{
    public function overview(Request $request, PlatformAnalyticsService $analytics): JsonResponse { $this->validateFilters($request); return response()->json(['data' => $analytics->overview($request)]); }
    public function businesses(Request $request, PlatformAnalyticsService $analytics): JsonResponse { $this->validateFilters($request, true); return response()->json(['data' => $analytics->businesses($request)]); }
    public function users(Request $request, PlatformAnalyticsService $analytics): JsonResponse { $this->validateFilters($request); return response()->json(['data' => $analytics->users($request)]); }
    public function subscriptions(Request $request, PlatformAnalyticsService $analytics): JsonResponse { $this->validateFilters($request); return response()->json(['data' => $analytics->subscriptions($request)]); }
    public function revenue(Request $request, PlatformAnalyticsService $analytics): JsonResponse { $this->validateFilters($request); return response()->json(['data' => $analytics->revenue($request)]); }
    public function usage(Request $request, PlatformAnalyticsService $analytics): JsonResponse { $this->validateFilters($request, true); return response()->json(['data' => $analytics->usage($request)]); }

    public function financial(Request $request, PlatformAnalyticsService $analytics): JsonResponse
    {
        $request->validate(['report' => ['nullable', 'in:invoices,outstanding,payments,refunds'], 'tenant_id' => ['nullable', 'uuid'], 'status' => ['nullable', 'in:open,paid,past_due,pending,succeeded,failed'], 'currency_code' => ['nullable', 'alpha', 'size:3'], 'search' => ['nullable', 'string', 'max:120'], 'sort' => ['nullable', 'in:created_at,issued_on,due_on,total,amount,status'], 'direction' => ['nullable', 'in:asc,desc']]);
        $this->validateFilters($request, true);
        return response()->json(['data' => $analytics->financial($request)]);
    }

    public function exports(Request $request): JsonResponse
    { return response()->json(['data' => ReportExport::where('platform_scope', true)->where('requested_by', $request->user()->id)->latest()->paginate(min(50, max(10, $request->integer('per_page', 25))))]); }

    public function requestExport(Request $request, AuditLogger $audit): JsonResponse
    {
        $data = $request->validate(['report_key' => ['required', 'in:invoices,outstanding,payments'], 'format' => ['required', 'in:csv'], 'filters' => ['nullable', 'array'], 'filters.period' => ['nullable', 'in:today,yesterday,last_7_days,last_30_days,this_month,last_month,this_year,daily,weekly,monthly,yearly,custom'], 'filters.granularity' => ['nullable', 'in:daily,weekly,monthly,yearly'], 'filters.from' => ['nullable', 'date_format:Y-m-d'], 'filters.to' => ['nullable', 'date_format:Y-m-d'], 'filters.currency_code' => ['nullable', 'alpha', 'size:3'], 'filters.status' => ['nullable', 'in:open,paid,past_due,pending,succeeded,failed']]);
        $filters = $data['filters'] ?? []; $filters['period'] = $filters['period'] ?? 'monthly';
        $export = ReportExport::create(['tenant_id' => null, 'requested_by' => $request->user()->id, 'platform_scope' => true, 'report_key' => $data['report_key'], 'format' => $data['format'], 'filters' => $filters, 'state' => 'queued']);
        $audit->record($request, 'platform.report_export.requested', null, $export, ['report_key' => $export->report_key, 'format' => $export->format]); GeneratePlatformReportExport::dispatch($export->id);
        return response()->json(['data' => $export], 202);
    }

    public function downloadExport(Request $request, ReportExport $export)
    {
        abort_unless($export->platform_scope && $export->requested_by === $request->user()->id, 404);
        abort_unless($export->state === 'completed' && $export->private_path && Storage::disk('local')->exists($export->private_path), 409, 'The report export is not ready.');
        return Storage::disk('local')->download($export->private_path, 'platform-' . preg_replace('/[^a-z0-9-]+/i', '-', $export->report_key) . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function validateFilters(Request $request, bool $pagination = false): void
    {
        $request->validate(['period' => ['nullable', 'in:today,yesterday,last_7_days,last_30_days,this_month,last_month,this_year,daily,weekly,monthly,yearly,custom'], 'granularity' => ['nullable', 'in:daily,weekly,monthly,yearly'], 'from' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'], 'to' => ['required_if:period,custom', 'nullable', 'date_format:Y-m-d'], 'date_from' => ['nullable', 'date_format:Y-m-d'], 'date_to' => ['nullable', 'date_format:Y-m-d'], 'per_page' => [$pagination ? 'nullable' : 'sometimes', 'integer', 'min:10', 'max:100']]);
    }
}
