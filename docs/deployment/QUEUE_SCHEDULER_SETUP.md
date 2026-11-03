# Queue & Scheduler Setup — Enterprise ERP Cloud

## 1. Queue (verified state)

- Production/staging connection: `QUEUE_CONNECTION=redis` (`.env.production.example`); local uses `sync`; tests force `sync` (`phpunit.xml.dist`). The database queue migration (`create_database_queue_and_cache_tables`) exists as a fallback driver, but the prepared topology is Redis.
- Workers: Supervisor program `erp-queue` (2 processes) — `infrastructure/supervisor/erp-backend.ini`.
- Flags in service: `--sleep=3 --tries=3 --backoff=10 --max-time=3600 --memory=256 --timeout=120`, `stopwaitsecs=130` so `timeout+jobs` finishing aren't SIGKILLed mid-transaction; `stopasgroup`/`killasgroup` for graceful teardown.
- Concurrency: start at 2; raise only with observed backlog (`failed_jobs` count and queue-size metrics from MonitoringService); workers share no state and are restartable.
- Job safety: financial/sensitive operations are idempotent by schema-level keys (supplier invoice idempotency, duplicate-receiving constraints — see migrations); keep `tries` low so retries don't double-post; monitor `failed_jobs` via the `Platform Health` collector and alert at > 0 for financial job queues.
- Restart discipline: **workers must be restarted after every code release** (release script does `supervisorctl restart erp-backend:*`). Never run `queue:work` from a terminal in staging/production.
- Queue failures observable: `failed_jobs` table + monitoring collector + external alert (Step 16 checklist in `PRODUCTION_SECURITY_CHECKLIST.md`).
- Redis must be private, password-authenticated (no 6379 exposure), and occasionally persisted (RDB) — sessions are DB-backed so flushes don't kill sessions, but scheduler `withoutOverlapping` locks and the hourly `erp:purchasing-automation` dedup key do reset on flush.

## 2. Scheduler

Scheduled commands in `backend/routes/console.php` (verified inventory):

| Command | Frequency | Guard |
|---|---|---|
| `erp:purchasing-automation` | hourly | `withoutOverlapping(30)`; non-financial allowlisted supplier rules; cache-stamped dedup `erp:purchasing-automation:last_run` |
| `platform:monitor` | every minute | `withoutOverlapping(1)`; writes MonitoringService collector observations |
| `platform:security-prune` | daily 02:30 | archives audit rows to `audit_log_archives` (hash-verified) then prunes inside `app.allow_audit_prune` context guard |
| `sanctum:prune-expired --hours=24` | daily 03:00 | token hygiene |

Install (single cron entry, `infrastructure/cron/erp-scheduler.cron`):

```bash
sudo cp infrastructure/cron/erp-scheduler.cron /etc/cron.d/erp-scheduler
sudo chown root:root /etc/cron.d/erp-scheduler && sudo chmod 0644 /etc/cron.d/erp-scheduler
```

- `flock -n /tmp/erp-schedule.lock` prevents minute-level pile-up; `withoutOverlapping` cache locks are guarded by shared Redis cache.
- **Multi-server rule**: exactly one host per environment runs the cron (scheduler primary). Cross-host overlap protection is available because locks use the shared Redis cache — but avoid double execution regardless.
- Recovery: cron restarts automatically on next minute; check `/var/log/erp-backend/schedule.log` freshness against `platform:monitor` observations from the MonitoringService (alert on staleness > 5 min as 'scheduler freshness' per monitoring checklist).
- Verify in staging: `php artisan schedule:list` output matches the above table.
