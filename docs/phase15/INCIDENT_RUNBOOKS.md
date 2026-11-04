# Phase 15 — Consolidated Incident Runbooks

Extends (does not replace) `docs/phase-12/INCIDENT_RESPONSE_RUNBOOK.md`, `docs/phase-12/BACKUP_RECOVERY_RUNBOOK.md`, `docs/deployment/BACKUP_RECOVERY_RUNBOOK.md`, `docs/deployment/QUEUE_SCHEDULER_SETUP.md` and `docs/deployment/ROLLBACK_PLAN.md`. Severity classes: SEV-1 critical, SEV-2 high, SEV-3 moderate, SEV-4 low (Phase 7 contract). **No raw secrets in any document; all commands reference env vars/secret store, never values.**

## General procedure for every incident

1. Detect via `/platform/monitoring/*` (or user report), 2. record incident row (`/platform/monitoring/incidents`), 3. diagnose read-only, 4. contain, 5. recover, 6. verify, 7. communicate, 8. post-incident review (PIR). Every runbook owner is per `RESPONSIBILITY_MATRIX.md` (currently Unassigned → on-call duty falls on the Platform Admin role by default until assigned).

### Communication template (all severities)

```
[SEV-x] <one-line summary>
Started (UTC):
Detected via:
Current impact (tenants affected / count):
Owner on it: <Unassigned until assigned>
Next update at:
Customer messaging decision: Yes/No
```
Post-incident: customer-facing message drafted by Release Owner; internal PIR within 5 business days, filed under incident record.

---

## 1. Laravel API outage (SEV-1)
- **Symptoms:** 5xx/timeouts, health endpoint (`/health`) failing, queue-backed pages dead.
- **Signal:** `/platform/monitoring/api` error rate; `/servers` status.
- **Owner:** Deployment Operator. **Diagnostics (read-only):** supervisor status, `php artisan erp:status`, server logs via log aggregation, recent deploy diff.
- **Containment:** scale up instances if saturation; isolate a bad deploy (see runbook 13 rollback).
- **Recovery:** restart workers per supervisor config; if deploy-caused, rollback artifact.
- **Verification:** `/health` green; `/platform/monitoring/api` error rate normal; smoke tests from `CUTOVER_PLAN.md` §smoke.
- **Comms:** template above; 15-min cadence while SEV-1.

## 2. PostgreSQL connection failure (SEV-1)
- **Symptoms:** `SQLSTATE[08006]`/connection-refused spikes; app-wide write failures.
- **Signal:** `/platform/monitoring/database`.
- **Owner:** DBA. **Diagnostics:** pg_ctl status, connection counts (`pg_stat_activity` by state), recent config changes, disk space.
- **Containment:** do NOT restart auto-approve; confirm backup freshness first from `/platform/operations/backups`.
- **Recovery:** restart Postgres (maintenance window if possible); failover per environment capability.
- **Verification:** app smoke login + read; monitoring green; queue workers reconnect.
- **Risk note:** reconnect after failover needs worker pool restart — include in verification.

## 3. Database high latency (SEV-2)
- **Symptoms:** slow pages, lock waits, `/platform/operations/database` slow queries.
- **Signal:** slow-query list; connection saturation; disk I/O.
- **Owner:** DBA. **Diagnostics:** EXPLAIN offending queries via `/platform/operations/database` read-only views, `pg_stat_statements`, lock graphs.
- **Containment:** stop noncritical scheduled jobs temporarily; avoid schema changes mid-incident.
- **Recovery:** apply the documented index migration set (ERP-P3-1 batch) in maintenance window; tune config.
- **Verification:** latency p95 below threshold on monitoring.

## 4. Queue backlog (SEV-2)
- **Symptoms:** exports/emails/automations delayed; `/platform/monitoring/queues` backlog rising.
- **Owner:** Platform Admin (delegate Deploy Op for restarts).
- **Diagnostics:** `queue:failed`, backlog graph, worker liveness; check the job that is stuck.
- **Containment:** scale workers; do not truncate queue data.
- **Recovery:** restart workers; re-dispatch failed jobs selectively after cause review.
- **Verification:** backlog drains; SLA on reports/exports normal.

## 5. Failed background jobs (SEV-2/3)
- **Owner:** Backend Maintainer. **Diagnose:** `queue:failed` + exception traces in logs; identify payload corruption vs. infra cause.
- **Containment:** stop re-dispatch loops of a poison job (avoid rapid-cycle churning).
- **Recovery:** fix cause → targeted re-dispatch. **Verification:** success state of affected business objects reconciled (e.g. export produced, journal posted).

## 6. Scheduler missed execution (SEV-2/3)
- **Signal:** `/platform/monitoring/scheduler` staleness; `php artisan schedule:list` vs. expected cadence (`platform:monitor` each minute).
- **Owner:** Platform Admin/Deploy Op. **Diagnostics:** host cron presence, single-instance lock drift, supervisor staleness.
- **Recovery:** repair host cron; run one manual cycle; verify monitoring freshness. **Verification:** `/platform/monitoring/scheduler` heartbeat current.

## 7. Email delivery failure (SEV-2/3)
- **Evidence known:** 2026-11-04 log shows a `RuntimeException` failure with fallback to log transport (DEV-15-001 in register).
- **Owner:** Platform Admin. **Diagnostics:** `/platform/monitoring/email` delivery states; provider-side status; **verification-with-hashed-addresses rule maintained (no PII in logs)**.
- **Containment:** switch to backup transport; do NOT disable the fallback (it is the safety net for verification flows).
- **Recovery:** fix transport credentials/config; resend failed notices where the feature supports it.
- **Verification:** `/platform/monitoring/email` non-failure delivery states increase.

## 8. Suspicious administrator access (SEV-1 if confirmed)
- **Signal:** audit-log anomalies (`/audit-logs`), new admin invitations no one recalls.
- **Owner:** Security Owner (acts alone for containment, informs Release Owner).
- **Containment steps (authorizable instantly):** revoke administration sessions (Security checklist), `/auth/sessions/{session}`; lock affected tenant until triage completes; do not delete audit rows.
- **Recovery:** rotate affected credentials ONLY with approval (Step 23 rule); preserve evidence.
- **Verification:** no further suspicious rows in `/audit-logs`; tenants re-enabled; incident documented.

## 9. Subscription/payment reconciliation (SEV-2)
- **Signal:** billing anomalies in `/platform/invoices`/`/platform/payments`, duplicate-capture refusal events, webhook drift.
- **Owner:** Platform Admin + Release Owner. **Diagnostics:** invoice lifecycle vs. payment verify history (verify-once audit trail), subscription state vs. next-period rows.
- **Containment:** freeze processor changes; quarantine the affected subscription(s) (admin state changes are audited).
- **Recovery:** post manual adjustments via supported flows only — no backdoor SQL against money tables.
- **Verification:** reconciliation tie-out recorded; audit rows attached.

## 10. Storage failure (SEV-1 if attachments/backups affected)
- **Owner:** Deploy Op. **Diagnostics:** disk usage, mount health, object-store error rates. **Containment:** offload writes (read-only mode on tenant uploads if supported). **Recovery:** restore storage from backups with **explicit two-person approval** (see runbook 2/12). **Verification:** attachment access checks pass (both upload and ACL-gated download).

## 11. Backup failure (SEV-2)
- **Signal:** cron notification on non-zero exit; missing new dump files (`postgres-backup.sh` output naming uses UTC timestamps).
- **Owner:** DBA. **Diagnose:** script exit, disk space, age/recipient config (referenced by env, never printed), storage-side quota.
- **Containment/Recovery:** fix cause; rerun; verify the produced artifact is > 0 bytes and encrypted (recorded in restore ledger).
- **Verification:** next scheduled cycle also succeeds — do not declare fixed after a single run.

## 12. Restore procedure (SEV-1; two-person rule)
**Never one-person; never restore over live production data directly.**
1. Freeze writes (maintenance window per `docs/deployment/GO_LIVE_CHECKLIST.md` gates).
2. Restore into an isolated scratch database first using `infrastructure/backup/postgres-restore.sh`
3. Verify: row counts match the backup ledger, term integrity of financial tables, tenant row sampling.
4. Only after verification pass with operator note: promote scratch → production (log: who, when, which backup).
- **Hard rule:** this runbook must never become a "one-click public restore" button in the admin UI.

## 13. Deployment rollback (SEV-1/2)
- Extends `docs/deployment/ROLLBACK_PLAN.md`. **Owner:** Deploy Op.
- Trigger rules: post-deploy error-rate/threshold breach per hypercare plan, or failed smoke checks.
- **Procedure:** stop traffic shifting → redeploy previous artifact (versioned, immutable) → forward-compat migration check (new column backfills are always nullable; removal-only migrations land ≥1 phase later) → restart workers → smoke.
- **Verification & comms:** error-rate normal + message to stakeholders; record in release gate notes.

## 14. Emergency maintenance (SEV-2/SEV-1)
- Via `/platform/operations/maintenance` (config exists). **Procedure:** enable window → broadcast via support + monitoring banner → reduce load (queue pause optional per runbook 4) → execute → disable window → verify incidents resolved. Business-hours rule: SEV-2 windows scheduled; SEV-1 immediate.

## 15. Tenant data exposure incident (SEV-1, worst case)
- **Owner:** Security Owner (declaration) + Release Owner (external comms approval).
- **Containment (ordered):** revoke implicated sessions/tokens → disable affected tenant access if leak scope unknown → preserve logs (never prune mid-incident; audit-prune jobs paused) → assess scope with isolation test suite as reference (`RlsEnforcementTest` patterns).
- **Recovery:** fix root cause (likely policy/middleware) with regression test **before** re-enabling tenants; RLS/auth weakening is never accepted as a fix.
- **Verification:** re-run cross-tenant isolation checks; document per-tenant impact inventory.
- **Comms:** release-owner-approved customer messaging; legal/contractual notification obligations tracked. This runbook defines the ONLY approved play for this severity.

---

### Drills recommended (control gaps, not yet scheduled)
| Drill | Linked runbook | Required before |
|---|---|---|
| Restore drill (timed) | 12 | Gate 9 PASS (BLK-1) |
| High-latency simulation | 3 | Gate 8 |
| Rollback rehearsal | 13 | Gate 12 |
| Tenant exposure tabletop | 15 | Gate 3 + 12 |
