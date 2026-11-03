# QUEUE_SCHEDULER_OPERATIONS.md — Phase 12 (reuses Phase 7)

## Verified configuration (2026-11-03)

- Queue driver: **database** (`jobs`, `job_batches`, `failed_jobs` tables created by
  migration `2026_11_03_000014_create_database_queue_and_cache_tables`).
- Scheduled commands (`php artisan schedule:list`):
  - `* * * * * php artisan platform:monitor` (Phase 7 collector)
  - `0 * * * * php artisan erp:purchasing-automation`
  - `30 2 * * * php artisan platform:security-prune`
  - `0 3 * * * php artisan sanctum:prune-expired --hours=24`
  - all with `withoutOverlapping` where documented.
- `php artisan queue:failed` → `No failed jobs found.` locally; the table is monitored by the endpoints below.

## Read path (`GET /api/v1/platform/operations/queues` + overview)

Data comes from `MonitoringService::queueDetail` (stored observations):
backlog, driver, status, failed job totals/recent rows, worker heartbeat.

**Honesty contract:** if no worker has ever produced a heartbeat row, the API reports
`worker_heartbeat: {available: false}` and the UI shows "worker heartbeat not proven" —
it does not claim health. No retry button is exposed to the browser (`retry: not_exposed_retry_requires_operator_runbook`).

## Operator procedure (manual)

```
php artisan queue:failed            # inspect
php artisan queue:retry <id>        # retry AFTER the failure cause is fixed
php artisan queue:restart           # safe reload of workers (process manager required)
```

- Retrying payments/invoicing/inventory jobs blindly is unsafe: verify idempotency in the job class first. Existing retry-safety is enforced in job classes with unique middleware keys (`backend/app/Jobs/Concerns`), but **operator discipline remains the control**.
- Never truncate `jobs`/`failed_jobs` on production; retention/deletion requires an approved run.

## Gaps (unchanged)

- No process supervisor (systemd/Horizon) ships in this repo — a worker heartbeat is therefore normally unavailable locally; nothing invents one.
- No queue-wait-time percentile metric (only backlog counts from Phase 7 observations).
- Missed-schedule detection is limited to the collector's `last_run_at` staleness.
