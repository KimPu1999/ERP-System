# Phase 15 — Post-Launch Hypercare Plan

**Status: PLAN — windows are planning periods, not scheduled tasks.** Activation requires assigned owners (`RESPONSIBILITY_MATRIX.md`) and a recorded release decision (`GO_NO_GO_DECISION.md`).

## 1. Review windows

| Window | Review focus | Review owner | Standing cadence |
|---|---|---|---|
| First 24 h | Deploy integrity, error rates, auth health, queue/scheduler freshness | Deployment Operator | hourly checks during business day + after-hours on-call |
| First 72 h | Financial & billing exceptions, onboarding friction from first real users | Platform Admin | twice daily |
| First 7 days | SLA/support volume, backup cycle verification, tenant isolation probe recurrence | DBA + Security Owner | daily |
| First 30 days | Trend review: error rate, latency, ticket themes; capacity notes | Release Owner | weekly |

## 2. Monitored signals & thresholds (initial; tune after each window)

| Signal | Source | Threshold acting as rollback candidate | Escalation |
|---|---|---|---|
| Login failures spike | `/platform/monitoring` + auth logs | ≥3× 24-h baseline OR failure cluster on one surface | SEV-2; Security Owner |
| HTTP 5xx rate | `/platform/monitoring/api` | >1% sustained 15 min | SEV-2 → consider `ROLLBACK_PLAN.md` |
| API response time (p95) | monitoring | > 2× pre-launch baseline sustained 30 min | SEV-3 |
| PostgreSQL health | `/platform/monitoring/database`, `/operations/database` | connection saturation; slow-query surge | SEV-2; DBA |
| Queue backlog | `/platform/monitoring/queues` | monotonic growth > 30 min | SEV-3 |
| Failed jobs | `queue:failed` | any poison-looping job | SEV-3 |
| Billing exceptions | `/platform/invoices`, `/platform/payments` | duplicate-capture guard triggers, invoice/payment state mismatch | SEV-2; Release Owner informed |
| Business onboarding errors | support tickets + onboarding analytics | clustered failures on a step | SEV-3 |
| Support ticket volume | `/business/support` → platform station | > agreed weekly baseline | scale support shift |
| Backup success | cron notifications + backup ledger | any failed cycle | SEV-2 immediately (RPO risk) |

## 3. Operating definition

- **Incident owner** during hypercare window: named per `RESPONSIBILITY_MATRIX.md` on-call row (currently Unassigned — activation blocked until assigned).
- **Monitoring frequency:** per window cadence above; all five SEV-1 runbooks (`INCIDENT_RUNBOOKS.md` 1, 2, 8, 10, 12) must have rehearsal notes attached before launch week.
- **Escalation thresholds:** table above; every threshold crossing opens an incident row, never a silent fix.
- **Status communication cadence:** SEV-1 every 15 min; SEV-2 every hour; window summaries daily (24 h/72 h) then weekly (7 d/30 d).
- **Rollback decision authority:** Deployment Operator (immediate P0 containment per `ROLLBACK_PLAN.md` §6), Release Owner informed; external comms by Release Owner.
- **Exit criteria (all):** 14 consecutive days with zero SEV-1; thresholds untouched for the last 72 h; backup cycles verified; no unresolved P0/P1 defect; UAT/pilot deferrals resolved or accepted as conditional per `GO_NO_GO_DECISION.md` policy.

## 4. Follow-up linkage

Every major incident must spawn a corrective-action row in the defect register (`UAT_DEFECT_REGISTER.md`) or a runbook-verification note in `INCIDENT_RUNBOOKS.md` drill table, with owner and due date. Hypercare exit is re-evaluated if any action is overdue.
