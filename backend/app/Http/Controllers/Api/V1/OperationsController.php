<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\{BackupVerificationRecord, MaintenanceWindow, MonitoringIncident, MonitoringObservation, OperationalCostRecord, ReleaseRecord, SupportTicket};
use App\Services\{AuditLogger, MonitoringService, SupportTicketService};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;

/**
 * Operations Center: consolidated read-mostly view over existing verified data
 * (Phase 7 monitoring, failed jobs, support backlog, maintenance, backup evidence,
 * cost records and release records). Nothing is fabricated: absent providers or
 * records surface as explicit `not_configured` / unknown markers.
 */
final class OperationsController
{
    public function __construct(private readonly MonitoringService $monitoring, private readonly SupportTicketService $support, private readonly AuditLogger $audit)
    {
    }

    public function overview(): JsonResponse
    {
        $monitoring = $this->monitoring->overview();
        $incidents = MonitoringIncident::whereNull('resolved_at')->orderByDesc('started_at')->limit(5)->get(['id', 'title', 'severity', 'status', 'started_at']);
        $support = $this->supportBacklog();
        $queue = $this->monitoring->detail('queues');
        $scheduler = $this->monitoring->detail('scheduler');
        $backup = $this->backupStatus();
        $maintenance = $this->activeMaintenance();
        $release = ReleaseRecord::orderByDesc('deployed_at')->first();

        return response()->json(['data' => [
            'production_health' => [
                'status' => $monitoring['overall'],
                '_services' => collect($monitoring['services'])->map(fn ($s) => ['service' => $s['service'], 'status' => $s['status'], 'last_observed_at' => $s['last_observed_at']])->values()->all(),
                'last_successful_check' => $monitoring['last_successful_check'],
                'limitations' => $monitoring['limitations'],
            ],
            'active_incidents' => $incidents->map(fn ($i) => $this->serializeIncident($i))->values()->all(),
            'performance_alerts' => [
                'open_total' => $monitoring['open_alerts']['total'],
                'open_critical' => $monitoring['open_alerts']['critical'],
            ],
            'support_tickets' => $support,
            'failed_jobs' => $this->failedJobs(),
            'queue_backlog' => $queue['backlog'],
            'queue_status' => $queue['status'],
            'worker_heartbeat' => $queue['worker_heartbeat'],
            'scheduler_status' => $scheduler['status'],
            'scheduler_collector_last_run_at' => $scheduler['collector_heartbeat']['last_run_at'] ?? null,
            'backups' => $backup,
            'maintenance' => $maintenance,
            'release' => [
                'current_version' => $release?->version,
                'environment' => $release?->environment,
                'deployed_at' => $release?->deployed_at?->toIso8601String(),
                'recorded_at' => $release?->created_at?->toIso8601String(),
                'config' => 'not_configured_until_release_records_are_registered',
            ],
            'security_maintenance' => [
                'integration' => 'phase_8_security_center',
                'path' => '/platform/security',
                'summary' => 'not_aggregated_here_to_avoid_duplicating_phase_8',
            ],
            'capacity' => $this->databaseCapacitySummary(),
        ]]);
    }

    /** Read-only PostgreSQL diagnostics from statistics views; bounded and audited. */
    public function database(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.operations.database_inspected', null);
        $connections = DB::selectOne(<<<'SQL'
            SELECT (SELECT count(*) FROM pg_stat_activity)::int AS total,
                   (SELECT count(*) FROM pg_stat_activity WHERE state = 'active')::int AS active,
                   (SELECT count(*) FROM pg_stat_activity WHERE wait_event_type = 'Lock')::int AS lock_waits,
                   (SELECT setting FROM pg_settings WHERE name = 'max_connections')::int AS max_connections
        SQL);
        $slowQueries = DB::select("SELECT pid, state, wait_event_type, ROUND(EXTRACT(EPOCH FROM (now() - query_start)) * 1000) AS started_ago_ms, LEFT(query, 180) AS query FROM pg_stat_activity WHERE state = 'active' AND now() - query_start > interval '1 second' AND pid <> pg_backend_pid() ORDER BY query_start LIMIT 20");
        $locks = DB::select("SELECT LEFT(a.query, 180) AS blocked_query, l.locktype, l.granted FROM pg_locks l JOIN pg_stat_activity a ON a.pid = l.pid WHERE NOT l.granted LIMIT 20");
        $tableSizes = DB::select(<<<'SQL'
            SELECT s.relname AS table,
                   pg_size_pretty(pg_total_relation_size(s.relid)) AS total_size,
                   pg_total_relation_size(s.relid) AS total_bytes,
                   s.n_live_tup AS live_rows,
                   s.seq_scan AS seq_scans,
                   s.idx_scan AS index_scans
            FROM pg_stat_user_tables s
            ORDER BY pg_total_relation_size(s.relid) DESC
            LIMIT 15
        SQL);
        $dbStats = DB::selectOne(<<<'SQL'
            SELECT datname, numbackends, deadlocks, temp_files, blks_hit, blks_read, xact_commit, xact_rollback, pg_size_pretty(pg_database_size(datname)) AS size
            FROM pg_stat_database WHERE datname = current_database()
        SQL);

        return response()->json(['data' => [
            'connections' => [
                'total' => (int) $connections->total,
                'active' => (int) $connections->active,
                'lock_waits' => (int) $connections->lock_waits,
                'max_connections' => (int) $connections->max_connections,
            ],
            'database' => $dbStats ? (array) $dbStats : ['status' => 'unknown'],
            'slow_queries' => collect($slowQueries)->map(fn ($r) => [
                'pid' => $r->pid,
                'state' => $r->state,
                'wait_event_type' => $r->wait_event_type,
                'started_ago_ms' => (int) round((float) ($r->started_ago_ms ?? 0)),
                'query' => $r->query,
            ])->values()->all(),
            'lock_waits' => collect($locks)->map(fn ($r) => ['query' => $r->blocked_query, 'locktype' => $r->locktype, 'granted' => (bool) $r->granted])->values()->all(),
            'largest_tables' => collect($tableSizes)->map(fn ($r) => [
                'table' => $r->table,
                'total_size' => $r->total_size,
                'total_bytes' => (int) $r->total_bytes,
                'live_rows' => (int) $r->live_rows,
                'seq_scans' => (int) $r->seq_scans,
                'index_scans' => (int) $r->index_scans,
            ])->values()->all(),
            'index_usage' => [
                'note' => 'seq_scans vs index_scans per table (largest tables above). Review query plans before adding indexes.',
            ],
        ]]);
    }

    public function queues(Request $request): JsonResponse
    {
        $detail = $this->monitoring->detail('queues');
        $this->audit->record($request, 'platform.operations.queues_inspected', null);
        return response()->json(['data' => $detail + [
            'pending_jobs' => DB::table('jobs')->count(),
            'failed_jobs_count' => $this->failedJobs()['total'],
            'duplicate_guard' => 'Failed financial or sensitive jobs are not auto-retried from the browser; retry happens through the documented operator procedure only.',
        ]]);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $windows = MaintenanceWindow::with(['creator:id,name,email', 'approver:id,name'])
            ->orderBy('starts_at')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->get();
        return response()->json(['data' => $windows->map(fn ($w) => $this->serializeWindow($w))->values()->all()]);
    }

    public function createMaintenance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'scope' => ['nullable', 'string', 'max:400'],
            'impact_description' => ['nullable', 'string', 'max:4000'],
            'rollback_plan' => ['required', 'string', 'max:4000'],
            'notify_businesses' => ['boolean'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);
        $window = MaintenanceWindow::create($data + ['status' => 'planned', 'created_by' => $request->user()->id]);
        $this->audit->record($request, 'platform.operations.maintenance_created', null, $window, ['title' => $window->title]);
        return response()->json(['data' => $this->serializeWindow($window->load(['creator:id,name,email', 'approver:id,name']))], 201);
    }

    public function transitionMaintenance(Request $request, MaintenanceWindow $maintenance): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:'.implode(',', MaintenanceWindow::STATUSES)]]);
        $to = $data['status'];
        $allowed = MaintenanceWindow::TRANSITIONS[$maintenance->status] ?? [];
        abort_if(! in_array($to, $allowed, true), 422, "Cannot transition maintenance from {$maintenance->status} to {$to}.");
        $attributes = ['status' => $to];
        if ($to === 'approved') {
            $attributes += ['approved_by' => $request->user()->id, 'approved_at' => now()];
        }
        $maintenance->update($attributes);
        $this->audit->record($request, 'platform.operations.maintenance_transitioned', null, $maintenance, ['to' => $to, 'title' => $maintenance->title]);
        return response()->json(['data' => $this->serializeWindow($maintenance->load(['creator:id,name,email', 'approver:id,name']))]);
    }

    public function updateMaintenanceNotice(Request $request): JsonResponse
    {
        // The user-facing notice stays owned by Phase 9 settings (no duplication).
        $value = app(\App\Services\SettingsService::class)->get('maintenance');
        abort_unless(is_array($value), 404);
        return response()->json(['data' => $value, 'notice_endpoint' => 'PATCH /api/v1/platform/settings/maintenance']);
    }

    public function backups(Request $request): JsonResponse
    {
        $this->audit->record($request, 'platform.operations.backups_viewed', null);
        [$status, $latest, $lastRestore] = $this->backupStatusFields();
        return response()->json(['data' => [
            'status' => $status,
            'last_verification' => $latest ? $this->serializeBackup($latest) : null,
            'last_restore_test' => $lastRestore ? ['verified_at' => $lastRestore->restore_tested_at?->toIso8601String(), 'recorded_by' => $lastRestore->recorded_by] : null,
            'records' => collect($this->allBackupRecords())->map(fn ($r) => $this->serializeBackup($r))->values()->all(),
            'scheduling' => 'not_configured_until_the_rpo_policy_is_provisioned_with_backup_agent_infrastructure',
            'policy' => ['rpo' => 'not_measured', 'rto' => 'not_measured', 'offsite_copy' => 'not_configured', 'encryption' => 'not_verified'],
        ]]);
    }

    public function registerBackup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'backup_taken_at' => ['required', 'date'],
            'backup_type' => ['required', 'in:pg_dump,base_backup,wal,files'],
            'storage_location' => ['nullable', 'string', 'max:255'],
            'checksum' => ['nullable', 'string', 'max:128'],
            'size_bytes' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'verification_status' => ['required', 'in:registered,restore_verified,failed'],
            'restore_tested_at' => ['nullable', 'date'],
        ]);
        $record = BackupVerificationRecord::create($data + ['recorded_by' => $request->user()->id]);
        $this->audit->record($request, 'platform.operations.backup_registered', null, null, ['backup_type' => $data['backup_type'], 'verification_status' => $data['verification_status']]);
        return response()->json(['data' => $this->serializeBackup($record)], 201);
    }

    public function costs(Request $request): JsonResponse
    {
        $query = OperationalCostRecord::query()
            ->when($request->query('currency'), fn ($q, $c) => $q->where('currency', $c))
            ->when($request->query('from'), fn ($q, $f) => $q->where('occurred_on', '>=', $f))
            ->when($request->query('to'), fn ($q, $t) => $q->where('occurred_on', '<=', $t));
        $records = $query->orderByDesc('occurred_on')->paginate(min((int) ($request->query('per_page', 25)), 100));
        $byCurrency = DB::table('operational_cost_records')
            ->select('currency', 'cost_type', \Illuminate\Support\Facades\DB::raw('SUM(amount) AS total'))
            ->groupBy('currency', 'cost_type')
            ->orderBy('currency')->get();
        $providers = DB::table('operational_cost_records')
            ->select('provider', DB::raw('SUM(amount) AS total'), 'currency', 'cost_type')
            ->groupBy('provider', 'currency', 'cost_type')
            ->orderByDesc('total')->limit(20)->get();

        return response()->json(['data' => [
            'records' => collect($records->items())->map(fn ($r) => [
                'id' => $r->id,
                'occurred_on' => $r->occurred_on,
                'provider' => $r->provider,
                'category' => $r->category,
                'description' => $r->description,
                'amount' => (float) $r->amount,
                'currency' => $r->currency,
                'cost_type' => $r->cost_type,
                'source' => $r->source,
            ])->all(),
            'meta' => ['current_page' => $records->currentPage(), 'last_page' => $records->lastPage(), 'total' => $records->total()],
            'summaries_by_currency' => collect($byCurrency)->map(fn ($r) => ['currency' => $r->currency, 'cost_type' => $r->cost_type, 'total' => (float) $r->total])->values()->all(),
            'by_provider' => collect($providers)->map(fn ($r) => ['provider' => $r->provider, 'currency' => $r->currency, 'cost_type' => $r->cost_type, 'total' => (float) $r->total])->values()->all(),
            'provider_integration' => 'not_configured_all_costs_are_manual_records',
            'currency_policy' => 'No cross-currency aggregation; sums are per currency code.',
        ]]);
    }

    public function recordCost(Request $request): JsonResponse
    {
        $data = $request->validate([
            'occurred_on' => ['required', 'date'],
            'provider' => ['required', 'string', 'max:80'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'cost_type' => ['required', 'in:actual,estimate,forecast'],
            'source' => ['nullable', 'in:manual,provider_export'],
        ]);
        $record = OperationalCostRecord::create($data + ['recorded_by' => $request->user()->id]);
        $this->audit->record($request, 'platform.operations.cost_recorded', null, null, ['provider' => $record->provider, 'currency' => $record->currency, 'cost_type' => $record->cost_type]);
        return response()->json(['data' => $record->only(['id', 'occurred_on', 'provider', 'category', 'description', 'amount', 'currency', 'cost_type', 'source'])], 201);
    }

    /** Manual authorized release records only — production deployments stay behind the CI/CD approval gate. */
    public function releases(): JsonResponse
    {
        $records = ReleaseRecord::orderByDesc('deployed_at')->limit(50)->get();
        $current = $records->firstWhere('status', 'deployed');
        return response()->json(['data' => [
            'current' => $current ? $this->serializeRelease($current) : null,
            'history' => $records->map(fn ($r) => $this->serializeRelease($r))->values()->all(),
            'latest_version' => $current?->version ?? 'not_configured',
        ]]);
    }

    public function recordRelease(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version' => ['required', 'string', 'max:60'],
            'environment' => ['required', 'in:staging,production'],
            'status' => ['required', 'in:deployed,rolled_back,aborted'],
            'release_notes' => ['nullable', 'string', 'max:4000'],
            'known_issues' => ['nullable', 'string', 'max:4000'],
            'rollback_reference' => ['nullable', 'string', 'max:2000'],
            'deployed_at' => ['required', 'date'],
        ]);
        $record = ReleaseRecord::create($data + ['recorded_by' => $request->user()->id]);
        $this->audit->record($request, 'platform.operations.release_recorded', null, null, ['version' => $record->version, 'environment' => $record->environment, 'status' => $record->status]);
        return response()->json(['data' => $this->serializeRelease($record)], 201);
    }

    public function incidents(Request $request): JsonResponse
    {
        // Phase 7 incidents are the single source of truth; this endpoint is a thin authorized view (no duplication).
        $page = MonitoringIncident::with(['events'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('severity'), fn ($q, $s) => $q->where('severity', $s))
            ->orderByDesc('started_at')
            ->paginate(min((int) ($request->query('per_page', 25)), 100));
        return response()->json(['data' => collect($page->items())->map(fn ($incident) => $this->serializeIncident($incident))->all(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    // ------------------------------------------------------------- internals

    private function supportBacklog(): array
    {
        $open = [ // open lifecycle statuses only
            'open' => (clone SupportTicket::query())->where('status', 'open')->count(),
            'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
            'waiting' => SupportTicket::whereIn('status', ['waiting_customer', 'waiting_support'])->count(),
            'urgent_open' => SupportTicket::where('priority', 'urgent')->whereIn('status', SupportTicketService::OPEN_STATUSES)->count(),
            'breached' => SupportTicket::where(function ($q): void {
                $q->whereNotNull('sla_breach_log')
                    ->orWhere(fn ($b) => $b->whereNull('first_response_at')->where('first_response_due_at', '<', now()))
                    ->orWhere(fn ($b) => $b->whereNull('resolved_at')->whereIn('status', SupportTicketService::OPEN_STATUSES)->where('resolution_due_at', '<', now()));
            })->count(),
            'total' => SupportTicket::whereIn('status', SupportTicketService::OPEN_STATUSES)->count(),
        ];
        return $open;
    }

    private function failedJobs(): array
    {
        $hasTable = \Illuminate\Support\Facades\Schema::hasTable('failed_jobs');
        $total = $hasTable ? DB::table('failed_jobs')->count() : 0;
        $recent = $hasTable
            ? DB::table('failed_jobs')->orderByDesc('failed_at')->limit(5)->get(['uuid', 'queue', 'failed_at'])
            : collect();
        return [
            'total' => $total,
            'recent' => $recent->map(fn ($r) => ['uuid' => $r->uuid, 'queue' => $r->queue, 'failed_at' => $r->failed_at])->values()->all(),
            'retry' => 'not_exposed_retry_requires_operator_runbook',
        ];
    }

    private function backupStatus(): array
    {
        [$status, $latest, $lastRestore] = $this->backupStatusFields();
        return [
            'status' => $status,
            'last_verification' => $latest ? ['taken_at' => $latest->backup_taken_at?->toIso8601String(), 'type' => $latest->backup_type, 'verification_status' => $latest->verification_status] : null,
            'last_restore_test_at' => $lastRestore?->restore_tested_at?->toIso8601String(),
        ];
    }

    private function backupStatusFields(): array
    {
        $hasTable = \Illuminate\Support\Facades\Schema::hasTable('backup_verification_records');
        if (! $hasTable) {
            return ['not_configured', null, null];
        }
        $latest = BackupVerificationRecord::orderByDesc('backup_taken_at')->first();
        $lastRestore = BackupVerificationRecord::whereNotNull('restore_tested_at')->orderByDesc('restore_tested_at')->first();
        if ($latest === null) {
            return ['not_configured', null, null];
        }
        $ageHours = $latest->backup_taken_at?->diffInHours(now());
        $status = $latest->verification_status === 'failed' ? 'degraded' : ($ageHours === null || $ageHours > 48 ? 'stale' : 'reported');
        return [$status, $latest, $lastRestore];
    }

    private function allBackupRecords()
    {
        return BackupVerificationRecord::orderByDesc('backup_taken_at')->limit(50)->get();
    }

    /** Returns the active/upcoming maintenance window or an explicit not-scheduled marker. */
    private function activeMaintenance(): array
    {
        $window = MaintenanceWindow::whereIn('status', ['approved', 'in_progress'])
            ->orderBy('starts_at')->first();
        return [
            'active' => $window ? $this->serializeWindow($window->load('creator:id,name,email', 'approver:id,name')) : null,
            'notice' => app(\App\Services\SettingsService::class)->get('maintenance')['value'] ?? ['notice_enabled' => false, 'configured' => false],
        ];
    }

    private function serializeWindow(MaintenanceWindow $window): array
    {
        return [
            'id' => $window->id,
            'title' => $window->title,
            'scope' => $window->scope,
            'impact_description' => $window->impact_description,
            'rollback_plan' => $window->rollback_plan,
            'status' => $window->status,
            'notify_businesses' => $window->notify_businesses,
            'starts_at' => $window->starts_at?->toIso8601String(),
            'ends_at' => $window->ends_at?->toIso8601String(),
            'approved_at' => $window->approved_at?->toIso8601String(),
            'approved_by' => $window->approver?->only(['id', 'name']),
            'created_by' => $window->creator?->only(['id', 'name', 'email']),
        ];
    }

    private function serializeIncident($incident): array
    {
        return [
            'id' => $incident->id,
            'title' => $incident->title,
            'severity' => $incident->severity,
            'status' => $incident->status,
            'started_at' => $incident->started_at?->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'events' => ($incident->events ?? collect())->map(fn ($e) => ['id' => $e->id, 'event' => $e->event, 'notes' => $e->notes, 'created_at' => $e->created_at?->toIso8601String()])->values()->all(),
        ];
    }

    private function serializeBackup($record): array
    {
        return [
            'id' => $record->id,
            'backup_taken_at' => $record->backup_taken_at?->toIso8601String(),
            'backup_type' => $record->backup_type,
            'storage_location' => $record->storage_location,
            'checksum' => $record->checksum,
            'size_bytes' => $record->size_bytes,
            'verification_status' => $record->verification_status,
            'restore_tested_at' => $record->restore_tested_at?->toIso8601String(),
            'notes' => $record->notes,
            'recorded_at' => $record->created_at?->toIso8601String(),
        ];
    }

    private function serializeRelease($record): array
    {
        return [
            'id' => $record->id,
            'version' => $record->version,
            'environment' => $record->environment,
            'status' => $record->status,
            'release_notes' => $record->release_notes,
            'known_issues' => $record->known_issues,
            'rollback_reference' => $record->rollback_reference,
            'deployed_at' => $record->deployed_at?->toIso8601String(),
            'recorded_at' => $record->created_at?->toIso8601String(),
        ];
    }

    private function databaseCapacitySummary(): array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT pg_size_pretty(pg_database_size(current_database())) AS db_size,
                   (SELECT count(*) FROM pg_stat_activity)::int AS connections,
                   (SELECT setting FROM pg_settings WHERE name = 'max_connections')::int AS max_connections
        SQL);
        $disk = MonitoringObservation::where('service', 'servers')->where('check_key', 'disk_usage')->orderByDesc('observed_at')->first();
        return [
            'database_size' => $row?->db_size ?? 'unknown',
            'connections' => ['total' => (int) ($row->connections ?? 0), 'max' => (int) ($row->max_connections ?? 0)],
            'host_disk' => $disk?->metadata,
            'note' => 'Queue backlog, connection saturation and disk trends are aggregated from existing monitoring observations.',
        ];
    }
}
