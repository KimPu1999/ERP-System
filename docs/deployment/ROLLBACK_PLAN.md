# Rollback Plan — Enterprise ERP Cloud

## 1. Decision criteria (trigger rollback when...)

- Post-deploy smoke fails (any of the 6 gates in `infrastructure/deploy/post-deploy-smoke.sh`).
- Auth contract broken in staging/production (login 5xx, 419 on valid flow).
- Health endpoint reports false flags (database/cache/queue).
- Failed-job spike, scheduler staleness, or monitoring reports Down.
- Error rate/latency exceeds the pre-release baseline observed in monitoring.

Rollback decision owner: release approver named in the release record (`docs/release-checklist.md` requirement).

## 2. Application rollback (fast path, no data loss)

```bash
# 1. Identify the previous healthy release
ls -1 /var/www/erp-api/releases | tail -2
# 2. Atomically restore the previous release with the current shared config
ln -sfn /var/www/erp-api/releases/<prev-id> /var/www/erp-api/.current.new
mv -Tf /var/www/erp-api/.current.new /var/www/erp-api/current
# 3. Clear caches from the previous release's code
cd /var/www/erp-api/current \
  && php artisan optimize:clear \
  && php artisan config:cache && php artisan route:cache
# 4. Restart workers + reload fpm
sudo supervisorctl restart erp-backend:*
sudo systemctl reload php8.4-fpm
# 5. Re-run smoke
API_URL=https://api.example.com EXPECTED_FRONTEND_ORIGIN=https://app.example.com \
  bash infrastructure/deploy/post-deploy-smoke.sh
```

Release retention keeps 5 artifact versions (`backend-release.sh`), so this path is always available for recent releases.

Frontend rollback: redeploy the previous immutable build (frontend host keeps build history; hashed assets are content-addressed, no server-side state to clean).

## 3. Database recovery (SEPARATE procedure — never same command path)

1. **Successful migrations are never auto-reverted.** If a rollback of a migration is needed, that is a **new forward migration** (expand/contract compat) written and reviewed normally.
2. `migrate:rollback` / `migrate:fresh` / `restore-over-production` are prohibited in staging/production without an approved incident record.
3. If the deployed release wrote incompatible data before rollback: assess whether the older code tolerates the new schema (expand/contract strategy makes this true by design); if not, keep running the new release and fix forward rather than rolling back code.
4. Actual data-restore (from snapshot): only under an approved incident record; restore into the isolated target first, validate (BACKUP_RECOVERY_RUNBOOK §4 checklist), then swap over with change-control; never overwrite freshly written production data with an older snapshot automatically.

## 4. Queue compatibility on rollback

- Workers restarted with the rolled-back code (step 2.4) — jobs queued by newer code that the older code cannot handle may fail; triage `failed_jobs`, retry with the repaired release.
- Schema-level idempotency keys (supplier invoice idempotency, duplicate-receipt constraints) prevent double-posting when the queue replays jobs across versions.

## 5. Incident communication & validation

- Record: decision time, trigger evidence, release ids (from→to), operator identity, smoke result. Publish in the release record per `deployment-runner-contract.md` §8.
- Post-rollback validation: health endpoint, read-only platform + business login, representative tenant data read, monitoring status back ≥ Degraded.
- Post-mortem: follow `docs/incident-response.md`; cannot lose immutable audit trail (platform audit logs survive both rollbacks via the shared database).
