<?php

namespace App\Services;

use App\Models\{MonitoringAlert, MonitoringErrorGroup, MonitoringIncident, MonitoringIncidentEvent, MonitoringObservation, MonitoringSetting};
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use Throwable;

final class MonitoringService
{
    public const STATUSES = ['healthy', 'degraded', 'down', 'unknown', 'maintenance'];

    private const DEFAULT_THRESHOLDS = [
        'stale_after_seconds' => 300,
        'queue_backlog_warning' => 100,
        'queue_backlog_critical' => 1000,
        'disk_warning_percent' => 80,
        'disk_critical_percent' => 90,
        'api_error_rate_warning' => 5,
        'api_error_rate_critical' => 10,
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function settings(): MonitoringSetting
    {
        $setting = MonitoringSetting::firstOrCreate(
            ['setting_key' => 'platform'],
            ['enabled' => true, 'thresholds' => self::DEFAULT_THRESHOLDS, 'notification_preferences' => ['in_app' => true], 'retention_days' => 30, 'refresh_seconds' => 30]
        );
        $thresholds = array_replace(self::DEFAULT_THRESHOLDS, $setting->thresholds ?? []);
        if ($setting->thresholds !== $thresholds) {
            $setting->forceFill(['thresholds' => $thresholds])->save();
        }
        return $setting->fresh();
    }

    public function collect(): array
    {
        $settings = $this->settings();
        if (! $settings->enabled) {
            $this->observe('collector', 'enabled', 'maintenance', 'Platform monitoring collection is disabled.', null, []);
            return ['status' => 'maintenance', 'observed_at' => now('UTC')->toISOString()];
        }

        $this->observe('collector', 'run', 'healthy', 'Platform monitoring collection started.', null, ['collector_heartbeat' => true]);
        $this->observeDatabase();
        $this->observeCache();
        $this->observeQueue($settings);
        $this->observeEmail();
        $this->observeScheduler();
        $this->observeApi();
        $this->observeServer($settings);
        $this->prune($settings);

        return ['status' => $this->overallStatus(), 'observed_at' => now('UTC')->toISOString()];
    }

    public function overview(): array
    {
        $settings = $this->settings();
        $latest = $this->latestObservations();
        $services = [];
        foreach ($latest as $observation) {
            $services[$observation->service]['checks'][] = $this->observationPayload($observation, $settings);
        }
        foreach ($services as $service => &$payload) {
            $payload['status'] = $this->aggregateStatus(collect($payload['checks'])->pluck('status')->all());
            $payload['last_observed_at'] = collect($payload['checks'])->pluck('observed_at')->filter()->sortDesc()->first();
        }
        unset($payload);
        foreach (['database', 'cache', 'queue', 'email', 'scheduler', 'api', 'servers'] as $service) {
            if (! isset($services[$service])) $services[$service] = ['status' => 'unknown', 'last_observed_at' => null, 'checks' => []];
        }

        $openAlerts = MonitoringAlert::query()->whereIn('status', ['open', 'acknowledged'])->get(['severity', 'status']);
        $lastSuccessful = MonitoringObservation::query()->where('status', 'healthy')->latest('observed_at')->value('observed_at');
        $latestVerified = MonitoringObservation::query()->whereIn('status', ['healthy', 'degraded', 'down'])->latest('observed_at')->value('observed_at');
        $incidents = MonitoringIncident::query()->whereIn('status', ['open', 'acknowledged'])->latest('started_at')->limit(8)->get();

        return [
            'overall' => $settings->enabled ? $this->aggregateStatus(collect($services)->pluck('status')->all()) : 'maintenance',
            'last_successful_check' => $lastSuccessful ? CarbonImmutable::parse($lastSuccessful)->toISOString() : null,
            'latest_verified_check' => $latestVerified ? CarbonImmutable::parse($latestVerified)->toISOString() : null,
            'services' => collect($services)->map(fn (array $value, string $key): array => ['service' => $key] + $value)->values()->all(),
            'open_alerts' => [
                'total' => $openAlerts->count(),
                'critical' => $openAlerts->where('severity', 'critical')->count(),
                'warning' => $openAlerts->where('severity', 'warning')->count(),
                'info' => $openAlerts->where('severity', 'info')->count(),
            ],
            'recent_incidents' => $incidents->map(fn (MonitoringIncident $incident): array => $this->incidentPayload($incident))->values()->all(),
            'collector' => [
                'enabled' => (bool) $settings->enabled,
                'refresh_seconds' => (int) $settings->refresh_seconds,
                'last_run_at' => $this->latestCollectorRun(),
                'schedule' => 'every minute',
            ],
            'metric_definitions' => [
                'healthy' => 'A verified observation passed its check.',
                'degraded' => 'A verified observation crossed a configured warning threshold.',
                'down' => 'A verified dependency check failed or crossed a critical threshold.',
                'unknown' => 'No safe observation exists, the observation is stale, or independent telemetry is not configured.',
                'maintenance' => 'Collection is intentionally disabled by platform settings.',
            ],
            'limitations' => [
                'api' => 'Request latency, throughput, uptime, and error-rate metrics are unavailable until request instrumentation is installed.',
                'queue' => 'Worker heartbeat and Redis/Horizon telemetry are not configured; queue readiness is not inferred from configuration alone.',
                'email' => 'Configured mail transport acceptance and delivery are not tested by the monitor.',
                'server' => 'Only local disk capacity is measured; CPU, memory, and external host telemetry are unavailable.',
            ],
        ];
    }

    public function legacyHealth(): array
    {
        $overview = $this->overview();
        $checks = [];
        foreach (['database', 'cache', 'queue', 'scheduler'] as $service) {
            $checks[$service] = collect($overview['services'])->firstWhere('service', $service) ?? ['status' => 'unknown', 'detail' => 'No stored observation is available.'];
            $checks[$service]['status'] = $checks[$service]['status'] === 'down' ? 'unavailable' : $checks[$service]['status'];
        }
        $checks['mail'] = collect($overview['services'])->firstWhere('service', 'email') ?? ['status' => 'unknown', 'detail' => 'No stored observation is available.'];
        return ['status' => $overview['overall'], 'checked_at' => $overview['latest_verified_check'] ?? now('UTC')->toISOString(), 'checks' => $checks];
    }

    public function detail(string $service): array
    {
        return match ($service) {
            'api' => $this->apiDetail(),
            'database' => $this->databaseDetail(),
            'queues' => $this->queueDetail(),
            'email' => $this->emailDetail(),
            'scheduler' => $this->schedulerDetail(),
            'servers' => $this->serverDetail(),
            default => abort(404, 'Unknown monitoring service.'),
        };
    }

    public function errors(Request $request): LengthAwarePaginator
    {
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $query = MonitoringErrorGroup::query();
        if ($request->filled('search')) {
            $term = '%'.addcslashes((string) $request->query('search'), '%_').'%';
            $query->where(fn (Builder $inner) => $inner->where('message', 'ilike', $term)->orWhere('exception_class', 'ilike', $term)->orWhere('route', 'ilike', $term));
        }
        if ($request->filled('severity')) $query->where('severity', $request->query('severity'));
        return $this->paginate($query->latest('last_seen_at'), $perPage, fn (MonitoringErrorGroup $error): array => [
            'id' => $error->id, 'fingerprint' => $error->fingerprint, 'exception_class' => $error->exception_class, 'message' => $error->message, 'route' => $error->route, 'severity' => $error->severity, 'occurrence_count' => (int) $error->occurrence_count, 'first_seen_at' => $error->first_seen_at?->toISOString(), 'last_seen_at' => $error->last_seen_at?->toISOString(), 'resolved_at' => $error->resolved_at?->toISOString(),
        ]);
    }

    public function alerts(Request $request): LengthAwarePaginator
    {
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $query = MonitoringAlert::query();
        if ($request->filled('search')) { $term = '%'.addcslashes((string) $request->query('search'), '%_').'%'; $query->where(fn (Builder $inner) => $inner->where('title', 'ilike', $term)->orWhere('description', 'ilike', $term)); }
        foreach (['status', 'severity', 'service'] as $filter) if ($request->filled($filter)) $query->where($filter, $request->query($filter));
        return $this->paginate($query->latest('last_observed_at'), $perPage, fn (MonitoringAlert $alert): array => $this->alertPayload($alert));
    }

    public function incidents(Request $request): LengthAwarePaginator
    {
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));
        $query = MonitoringIncident::query()->with(['events' => fn ($q) => $q->latest()->limit(10), 'events.actor:id,name,email']);
        foreach (['status', 'severity'] as $filter) if ($request->filled($filter)) $query->where($filter, $request->query($filter));
        return $this->paginate($query->latest('started_at'), $perPage, fn (MonitoringIncident $incident): array => $this->incidentPayload($incident));
    }

    public function createIncident(Request $request): MonitoringIncident
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:4000'],
            'severity' => ['required', 'in:info,warning,critical'],
            'affected_services' => ['nullable', 'array', 'max:20'],
            'affected_services.*' => ['string', 'max:60'],
        ]);
        $incident = MonitoringIncident::create($data + ['status' => 'open', 'started_at' => now()]);
        MonitoringIncidentEvent::create(['incident_id' => $incident->id, 'actor_user_id' => $request->user()->id, 'event' => 'created', 'notes' => null]);
        $this->audit->record($request, 'platform.monitoring.incident_created', null, $incident, ['severity' => $incident->severity]);
        return $incident->fresh('events');
    }

    public function updateSettings(Request $request): MonitoringSetting
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'retention_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'refresh_seconds' => ['sometimes', 'integer', 'min:10', 'max:300'],
            'thresholds' => ['sometimes', 'array'],
            'thresholds.stale_after_seconds' => ['sometimes', 'integer', 'min:60', 'max:86400'],
            'thresholds.queue_backlog_warning' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'thresholds.queue_backlog_critical' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'thresholds.disk_warning_percent' => ['sometimes', 'numeric', 'min:1', 'max:99'],
            'thresholds.disk_critical_percent' => ['sometimes', 'numeric', 'min:1', 'max:100'],
        ]);
        $setting = $this->settings();
        $thresholds = array_replace($setting->thresholds ?? self::DEFAULT_THRESHOLDS, $data['thresholds'] ?? []);
        $setting->update(array_filter(['enabled' => $data['enabled'] ?? null, 'retention_days' => $data['retention_days'] ?? null, 'refresh_seconds' => $data['refresh_seconds'] ?? null], static fn ($value): bool => $value !== null) + ['thresholds' => $thresholds]);
        $this->audit->record($request, 'platform.monitoring.settings_updated', null, $setting, ['changed_keys' => array_keys($data)]);
        return $setting->fresh();
    }

    public function acknowledgeAlert(Request $request, MonitoringAlert $alert): MonitoringAlert
    {
        abort_if($alert->status === 'resolved', 422, 'A resolved alert cannot be acknowledged.');
        $alert->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);
        $this->audit->record($request, 'platform.monitoring.alert_acknowledged', null, $alert, ['fingerprint' => $alert->fingerprint]);
        return $alert->fresh();
    }

    public function resolveAlert(Request $request, MonitoringAlert $alert): MonitoringAlert
    {
        abort_if($alert->status === 'resolved', 422, 'The alert is already resolved.');
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);
        $alert->update(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'metadata' => array_merge($alert->metadata ?? [], ['resolution_notes' => $data['notes'] ?? null])]);
        $this->audit->record($request, 'platform.monitoring.alert_resolved', null, $alert, ['fingerprint' => $alert->fingerprint]);
        return $alert->fresh();
    }

    public function acknowledgeIncident(Request $request, MonitoringIncident $incident): MonitoringIncident
    {
        abort_if($incident->status === 'resolved', 422, 'A resolved incident cannot be acknowledged.');
        $incident->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);
        $this->incidentEvent($request, $incident, 'acknowledged');
        return $incident->fresh('events');
    }

    public function resolveIncident(Request $request, MonitoringIncident $incident): MonitoringIncident
    {
        abort_if($incident->status === 'resolved', 422, 'The incident is already resolved.');
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $incident->update(['status' => 'resolved', 'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'resolution_notes' => $data['notes']]);
        $this->incidentEvent($request, $incident, 'resolved', $data['notes']);
        return $incident->fresh('events');
    }

    public function addIncidentNote(Request $request, MonitoringIncident $incident): MonitoringIncident
    {
        abort_if($incident->status === 'resolved', 422, 'A note cannot be added to a resolved incident.');
        $data = $request->validate(['notes' => ['required', 'string', 'max:2000']]);
        $this->incidentEvent($request, $incident, 'note', $data['notes']);
        return $incident->fresh('events');
    }

    public function recordException(Throwable $exception, ?Request $request = null): void
    {
        try {
            if (! Schema::hasTable('monitoring_error_groups')) return;
            $message = $this->safeMessage($exception->getMessage());
            $route = $request?->route()?->getName() ?: ($request?->route()?->uri() ?: null);
            $fingerprint = hash('sha256', $exception::class.'|'.$route.'|'.$message);
            $existing = MonitoringErrorGroup::where('fingerprint', $fingerprint)->first();
            if ($existing) {
                $existing->update(['occurrence_count' => $existing->occurrence_count + 1, 'last_seen_at' => now(), 'resolved_at' => null]);
                return;
            }
            MonitoringErrorGroup::create(['fingerprint' => $fingerprint, 'exception_class' => class_basename($exception), 'message' => $message, 'route' => $route, 'severity' => $this->exceptionSeverity($exception), 'occurrence_count' => 1, 'first_seen_at' => now(), 'last_seen_at' => now(), 'metadata' => ['method' => $request?->method(), 'status' => method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : null]]);
        } catch (Throwable) {
            // Error reporting must never turn a primary request failure into a second failure.
        }
    }

    private function observeDatabase(): void
    {
        $started = microtime(true);
        try {
            DB::selectOne('select 1');
            $ms = $this->elapsed($started);
            $this->observe('database', 'connectivity', $ms > 1000 ? 'degraded' : 'healthy', 'PostgreSQL connectivity check completed.', $ms, ['driver' => DB::connection()->getDriverName()]);
        } catch (Throwable $e) {
            $this->observe('database', 'connectivity', 'down', 'PostgreSQL connectivity check failed.', $this->elapsed($started), ['exception' => class_basename($e)]);
        }
    }

    private function observeCache(): void
    {
        $started = microtime(true);
        try {
            $key = 'platform-monitor-'.bin2hex(random_bytes(12));
            Cache::store()->put($key, true, 5);
            $healthy = Cache::store()->get($key) === true;
            Cache::store()->forget($key);
            $this->observe('cache', 'connectivity', $healthy ? 'healthy' : 'down', $healthy ? 'Cache round-trip completed.' : 'Cache round-trip returned an unexpected value.', $this->elapsed($started), ['store' => config('cache.default')]);
        } catch (Throwable $e) {
            $this->observe('cache', 'connectivity', 'down', 'Cache round-trip failed.', $this->elapsed($started), ['exception' => class_basename($e)]);
        }
    }

    private function observeQueue(MonitoringSetting $settings): void
    {
        $driver = (string) config('queue.default');
        if ($driver === 'sync') {
            $this->observe('queue', 'worker', 'unknown', 'The synchronous queue driver is configured; no worker heartbeat can be observed.', null, ['driver' => $driver, 'worker_heartbeat' => false]);
            return;
        }
        if ($driver === 'database' && Schema::hasTable('jobs')) {
            $backlog = (int) DB::table('jobs')->count();
            $status = $backlog >= (int) ($settings->thresholds['queue_backlog_critical'] ?? 1000) ? 'down' : ($backlog >= (int) ($settings->thresholds['queue_backlog_warning'] ?? 100) ? 'degraded' : 'unknown');
            $this->observe('queue', 'backlog', $status, $status === 'unknown' ? 'Database queue backlog is measured, but worker heartbeat is not configured.' : 'Database queue backlog crossed a configured threshold.', null, ['driver' => $driver, 'backlog' => $backlog, 'worker_heartbeat' => false]);
            return;
        }
        $this->observe('queue', 'worker', 'unknown', 'Queue driver is configured, but worker heartbeat telemetry is not configured.', null, ['driver' => $driver ?: 'unknown', 'worker_heartbeat' => false]);
    }

    private function observeEmail(): void
    {
        $mailer = (string) config('mail.default');
        $configured = $mailer !== '' && ! in_array($mailer, ['log', 'array'], true);
        $this->observe('email', 'delivery', 'unknown', $configured ? 'Mail transport is configured; acceptance and delivery are not verified.' : 'No external mail transport is configured; delivery cannot be verified.', null, ['mailer' => $mailer ?: 'unknown', 'delivery_verified' => false]);
    }

    private function observeScheduler(): void
    {
        $this->observe('scheduler', 'collector', 'healthy', 'Platform monitoring collector executed; this does not prove an external scheduler is running.', null, ['collector_heartbeat' => true, 'external_scheduler_verified' => false]);
    }

    private function observeApi(): void
    {
        $this->observe('api', 'telemetry', 'unknown', 'The internal collector cannot prove external API availability without request instrumentation.', null, ['instrumentation' => false]);
    }

    private function observeServer(MonitoringSetting $settings): void
    {
        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());
        if (! is_float($total) || ! is_float($free) || $total <= 0) {
            $this->observe('servers', 'disk', 'unknown', 'Local disk capacity is unavailable.', null, ['disk_telemetry' => false]);
        } else {
            $used = 100 * (1 - ($free / $total));
            $critical = (float) ($settings->thresholds['disk_critical_percent'] ?? 90);
            $warning = (float) ($settings->thresholds['disk_warning_percent'] ?? 80);
            $status = $used >= $critical ? 'down' : ($used >= $warning ? 'degraded' : 'healthy');
            $this->observe('servers', 'disk', $status, 'Local application disk usage measured.', null, ['used_percent' => round($used, 2), 'free_bytes' => (int) $free, 'total_bytes' => (int) $total]);
        }
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $this->observe('servers', 'runtime', 'unknown', 'CPU and memory utilization telemetry is not configured.', null, ['load_average' => is_array($load) ? array_map('floatval', array_slice($load, 0, 3)) : null, 'cpu_memory_telemetry' => false]);
    }

    private function observe(string $service, string $check, string $status, string $detail, ?int $responseMs, array $metadata): MonitoringObservation
    {
        $observation = MonitoringObservation::create(['service' => $service, 'check_key' => $check, 'status' => in_array($status, self::STATUSES, true) ? $status : 'unknown', 'observed_at' => now(), 'response_time_ms' => $responseMs, 'detail' => $detail, 'metadata' => $metadata]);
        if (in_array($observation->status, ['down', 'degraded'], true)) {
            $this->upsertAlert($observation);
        } elseif ($observation->status === 'healthy') {
            $this->resolveRecoveredAlert($observation);
        }
        return $observation;
    }

    private function upsertAlert(MonitoringObservation $observation): void
    {
        $fingerprint = hash('sha256', $observation->service.'|'.$observation->check_key.'|health');
        $critical = $observation->status === 'down';
        $alert = MonitoringAlert::firstOrNew(['fingerprint' => $fingerprint]);
        $alert->fill(['service' => $observation->service, 'severity' => $critical ? 'critical' : 'warning', 'title' => ucfirst($observation->service).' '.$observation->check_key.' is '. $observation->status, 'description' => $observation->detail ?: 'A monitoring threshold was crossed.', 'last_observed_at' => $observation->observed_at, 'metadata' => $observation->metadata]);
        if (! $alert->exists) $alert->first_observed_at = $observation->observed_at;
        if ($alert->status === 'resolved') { $alert->status = 'open'; $alert->resolved_by = null; $alert->resolved_at = null; }
        if (! $alert->status) $alert->status = 'open';
        $alert->save();
    }

    private function resolveRecoveredAlert(MonitoringObservation $observation): void
    {
        $fingerprint = hash('sha256', $observation->service.'|'.$observation->check_key.'|health');
        $alert = MonitoringAlert::where('fingerprint', $fingerprint)->whereIn('status', ['open', 'acknowledged'])->first();
        if ($alert) $alert->update(['status' => 'resolved', 'resolved_at' => now(), 'metadata' => array_merge($alert->metadata ?? [], ['recovered_at' => now()->toISOString()])]);
    }

    private function latestObservations(): array
    {
        $latest = [];
        MonitoringObservation::query()->latest('observed_at')->limit(500)->get()->each(function (MonitoringObservation $observation) use (&$latest): void {
            $key = $observation->service.'|'.$observation->check_key;
            if (! isset($latest[$key])) $latest[$key] = $observation;
        });
        return array_values($latest);
    }

    private function observationPayload(MonitoringObservation $observation, MonitoringSetting $settings): array
    {
        $staleAt = $observation->observed_at?->addSeconds((int) ($settings->thresholds['stale_after_seconds'] ?? 300));
        $stale = $staleAt && $staleAt->isPast();
        return ['check_key' => $observation->check_key, 'status' => $stale && $observation->status !== 'maintenance' ? 'unknown' : $observation->status, 'observed_at' => $observation->observed_at?->toISOString(), 'response_time_ms' => $observation->response_time_ms, 'detail' => $stale ? 'The last observation is stale.' : $observation->detail, 'metadata' => $observation->metadata ?? [], 'stale' => $stale];
    }

    private function aggregateStatus(array $statuses): string
    {
        if ($statuses === []) return 'unknown';
        if (in_array('down', $statuses, true)) return 'down';
        if (in_array('degraded', $statuses, true)) return 'degraded';
        if (in_array('unknown', $statuses, true)) return 'unknown';
        if (count(array_unique($statuses)) === 1 && $statuses[0] === 'maintenance') return 'maintenance';
        return 'healthy';
    }

    private function overallStatus(): string
    {
        return $this->overview()['overall'];
    }

    private function alertPayload(MonitoringAlert $alert): array
    {
        return ['id' => $alert->id, 'fingerprint' => $alert->fingerprint, 'service' => $alert->service, 'severity' => $alert->severity, 'title' => $alert->title, 'description' => $alert->description, 'status' => $alert->status, 'first_observed_at' => $alert->first_observed_at?->toISOString(), 'last_observed_at' => $alert->last_observed_at?->toISOString(), 'acknowledged_at' => $alert->acknowledged_at?->toISOString(), 'resolved_at' => $alert->resolved_at?->toISOString(), 'metadata' => $alert->metadata ?? []];
    }

    private function incidentPayload(MonitoringIncident $incident): array
    {
        return ['id' => $incident->id, 'title' => $incident->title, 'description' => $incident->description, 'severity' => $incident->severity, 'status' => $incident->status, 'affected_services' => $incident->affected_services ?? [], 'started_at' => $incident->started_at?->toISOString(), 'acknowledged_at' => $incident->acknowledged_at?->toISOString(), 'resolved_at' => $incident->resolved_at?->toISOString(), 'resolution_notes' => $incident->resolution_notes, 'events' => $incident->relationLoaded('events') ? $incident->events->map(fn (MonitoringIncidentEvent $event): array => ['id' => $event->id, 'event' => $event->event, 'notes' => $event->notes, 'actor' => $event->actor?->only(['id', 'name', 'email']), 'created_at' => $event->created_at?->toISOString()])->values()->all() : []];
    }

    private function incidentEvent(Request $request, MonitoringIncident $incident, string $event, ?string $notes = null): void
    {
        MonitoringIncidentEvent::create(['incident_id' => $incident->id, 'actor_user_id' => $request->user()->id, 'event' => $event, 'notes' => $notes]);
        $this->audit->record($request, 'platform.monitoring.incident_'.$event, null, $incident, ['event' => $event]);
    }

    private function paginate(Builder $query, int $perPage, callable $transform): LengthAwarePaginator
    {
        $page = $query->paginate($perPage);
        $page->getCollection()->transform($transform);
        return $page;
    }

    private function latestCollectorRun(): ?string
    {
        $value = MonitoringObservation::where('service', 'collector')->latest('observed_at')->value('observed_at');
        return $value ? CarbonImmutable::parse($value)->toISOString() : null;
    }

    private function databaseDetail(): array
    {
        $data = ['status' => $this->serviceStatus('database'), 'observations' => $this->serviceObservations('database')];
        try {
            $data['postgresql'] = [
                'database_size_bytes' => (int) (DB::selectOne('select pg_database_size(current_database()) as size')->size ?? 0),
                'active_connections' => (int) (DB::selectOne("select count(*) as count from pg_stat_activity where state = 'active'")->count ?? 0),
                'max_connections' => (int) (DB::selectOne("select setting::int as value from pg_settings where name = 'max_connections'")->value ?? 0),
            ];
            $extension = DB::selectOne("select to_regclass('public.pg_stat_statements') as relation");
            $available = filled($extension?->relation ?? null);
            $data['query_statistics'] = ['available' => $available, 'reason' => $available ? 'Extension is available; query text is intentionally not exposed.' : 'pg_stat_statements is not installed.'];
        } catch (Throwable) {
            $data['postgresql'] = ['available' => false, 'reason' => 'Safe PostgreSQL statistics are unavailable.'];
        }
        return $data;
    }

    private function apiDetail(): array { return ['status' => $this->serviceStatus('api'), 'available' => false, 'reason' => 'Request instrumentation is not installed; latency, throughput, uptime, error-rate, and slow-endpoint metrics are not fabricated.', 'observations' => $this->serviceObservations('api')]; }
    private function queueDetail(): array { $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->latest('failed_at')->limit(50)->get(['uuid', 'connection', 'queue', 'failed_at', 'exception'])->map(fn ($row): array => ['id' => $row->uuid, 'connection' => $row->connection, 'queue' => $row->queue, 'failed_at' => $row->failed_at, 'exception' => $this->safeMessage((string) $row->exception)])->values()->all() : []; return ['status' => $this->serviceStatus('queue'), 'driver' => config('queue.default'), 'worker_heartbeat' => ['available' => false], 'backlog' => Schema::hasTable('jobs') ? DB::table('jobs')->count() : null, 'failed_jobs' => $failed, 'retry_available' => false, 'retry_reason' => 'Automatic retry is not exposed until job idempotency and worker ownership are proven.', 'observations' => $this->serviceObservations('queue')]; }
    private function emailDetail(): array { return ['status' => $this->serviceStatus('email'), 'mailer' => (string) config('mail.default'), 'delivery_verified' => false, 'reason' => 'Transport acceptance and delivery are not verified by this collector.', 'observations' => $this->serviceObservations('email')]; }
    private function schedulerDetail(): array
    {
        $tasks = [];
        try {
            foreach (app(\Illuminate\Console\Scheduling\Schedule::class)->events() as $event) {
                $timezone = $event->timezone ?? null;
                $tasks[] = ['expression' => method_exists($event, 'getExpression') ? $event->getExpression() : null, 'command' => is_string($event->command ?? null) ? $event->command : null, 'timezone' => $timezone instanceof \DateTimeZone ? $timezone->getName() : (is_string($timezone) ? $timezone : null)];
            }
        } catch (Throwable) {
            $tasks = [];
        }
        return ['status' => $this->serviceStatus('scheduler'), 'collector_heartbeat' => $this->latestCollectorRun(), 'external_scheduler_verified' => false, 'scheduled_tasks' => $tasks, 'reason' => 'The collector heartbeat does not prove that an external scheduler process is continuously running.', 'observations' => $this->serviceObservations('scheduler')];
    }
    private function serverDetail(): array { return ['status' => $this->serviceStatus('servers'), 'observations' => $this->serviceObservations('servers'), 'telemetry' => ['disk' => 'available', 'cpu' => 'unavailable', 'memory' => 'unavailable', 'agents' => 'unavailable']]; }
    private function serviceStatus(string $service): string { return $this->aggregateStatus(collect($this->serviceObservations($service))->pluck('status')->all()); }
    private function serviceObservations(string $service): array { $setting = $this->settings(); return collect($this->latestObservations())->filter(fn (MonitoringObservation $item): bool => $item->service === $service)->map(fn (MonitoringObservation $item): array => $this->observationPayload($item, $setting))->values()->all(); }
    private function elapsed(float $started): int { return max(0, (int) round((microtime(true) - $started) * 1000)); }
    private function safeMessage(string $message): string { $message = preg_replace('/(?i)(password|passwd|token|secret|authorization|cookie|api[_ -]?key|sql|bindings)\s*[:=]\s*("[^"]*"|\'[^\']*\'|[^,;\s]+)/', '$1=[redacted]', $message) ?? $message; $message = preg_replace('/(?i)bearer\s+[A-Za-z0-9._-]+/', 'bearer [redacted]', $message) ?? $message; return mb_substr(trim(preg_replace('/\s+/', ' ', $message) ?? ''), 0, 2000); }
    private function exceptionSeverity(Throwable $exception): string { return method_exists($exception, 'getStatusCode') && $exception->getStatusCode() < 500 ? 'warning' : 'error'; }
    private function prune(MonitoringSetting $settings): void { $cutoff = now()->subDays((int) $settings->retention_days); MonitoringObservation::where('created_at', '<', $cutoff)->orderBy('created_at')->limit(1000)->get()->each->delete(); MonitoringErrorGroup::where('last_seen_at', '<', $cutoff)->whereNotNull('resolved_at')->orderBy('last_seen_at')->limit(1000)->get()->each->delete(); }
}
