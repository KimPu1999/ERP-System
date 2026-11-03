# PHASE_12_AUDIT.md — Post-Launch Operations & Support Audit

Date: 2026-11-03. Audited against the actual repository at working tree state (many Phase 11 artifacts committed; live deployment still not executed).

## Investing method

Inspected: `backend/routes/api.php` (709 lines), `backend/routes/console.php` (scheduler), all controllers/services/models/middleware, `2026_11_03_000007_create_platform_monitoring_tables.php`, `MonitoringService` (480 lines), `SupportController` (existing minimal), support migrations, frontend `App.tsx` routes, `lib/api.ts`, i18n, platform pages, Phase 7/8/9 center pages, Phase 11 deployment docs.

## 1. Working features (verified)

- **Phase 7 monitoring**: `MonitoringService` collects real DB/cache health observations once per minute (`platform:monitor` scheduled), stores `monitoring_observations/alerts/incidents/incident_events/error_groups`, computes stale-aware `unknown` status, redacts secrets, deduplicates errors, audit-logs privileged actions. Platform UI `/platform/monitoring` complete (overview/service/errors/alerts/incidents/settings).
- **Maintenance user-facing notice** already exists as Phase 9 platform setting category `maintenance` (`notice_enabled`, `notice`, `starts_at`, `ends_at`) with `/settings/operational-status` public endpoint.
- **Support seed**: `support_tickets` (subject/description/priority/state only) + `support_access_grants` tables with tenant-isolation RLS; business `GET/POST /support/tickets`; platform ad-hoc `SupportAccessGrant` create/approve; audit-logged.
- **Scheduler**: 4 scheduled commands (`erp:purchasing-automation` hourly, `platform:monitor` everyMinute, `platform:security-prune` daily, sanctum prune) all `withoutOverlapping`.
- **Queue**: database queue driver tables created (2026_11_03_000014); `MonitoringService::queueDetail` reports real backlog/failed jobs and honestly reports worker heartbeat `available:false` and no automatic retry (82% of honesty requirement intact).
- **Platform RBAC**: `RequirePlatformSuperAdmin` middleware + `platform.admin` alias, RLS bypass via `set_config('app.platform_admin')`, audit logging with `SensitiveDataRedactor`.
- **Frontend**: platform/business layouts, react-query pattern, polling, i18n EN/my, monitoring center, security center fully built.

## 2. Partially implemented features

| Feature | Present | Missing |
|---|---|---|
| Support tickets | create/list (tenant-scoped), access grants | ticket numbers, category, status lifecycle, assignment, first response, thread messages, internal notes, attachments, resolution/close windows, platform-side management UI, SLA, reopen |
| Incidents | Phase 7 table + create/ack/resolve/notes, events | owner, severity rules, timeline enforced states, postmortem fields, incident detail page beyond list (resolve requires notes — verify), duplicate prevention by alert fingerprint (alerts dedupe; incidents are manual only) |
| Maintenance | user notice (Phase 9) | planned-window calendar/approval workflow/state machine/history/rollback plan |
| Backups | Phase 9/10 settings + scripts on disk (uncommitted infra) | backup evidence records, verification status, last restore test tracking, operations dashboard |
| Operations center | monitoring center (subset overlap) | consolidated `/platform/operations` dashboard: real aggregation, timestamps, cost/release/database-capacity panels |
| Releases | CI/CD workflow + docs | release_records store + UI |

## 3. Broken features

- None found that claim to work and fail: support endpoints return valid data; migrations all `Ran`; test suite 176/842 green (re-verified this session).
- Minor: `SupportController::tickets()` always paginates 25 with no filters; `SupportAccessGrant::state` transitions lack guard rails (`requested` can be re-requested infinitely — low risk).

## 4. Missing features (to build this phase)

Support: message thread, internal notes, attachments (upload/download authorization, mime allowlist, private disk), status lifecycle + validation, assignment (audited), priority change, priority/status filters, ticket number, export.
SLA: `support_sla_policies` (optional business-hours config; not hardcoded), due timestamps, at-risk/breach tracking, breach history.
Operations: `/platform/operations` dashboard + database/queue/maintenance/backup/cost/release pages as above.
Help center: `/business/help`, `/platform/support/knowledge-base`.

## 5. Infrastructure dependencies

- Local PostgreSQL running; disposable `erp_test` exists for CI-parity tests.
- **Not deployable assumptions**: no staging/production host, no external monitoring SaaS, no backup job actually scheduled in cron on any real server, no Horizon/Redis. Phase 11 blockers B-1..B-5 remain open.

## 6. Security risks

- Support attachments (new) must be mime/extension allowlisted, size-limited, stored under private disk, downloaded only with authorization (otherwise IDOR).
- `SupportAccessGrant` currently grants opaque future "support" access with no scoping to actual controller enforcement — grant objects are stored but not used by any endpoint for authorization (safe today; must be documented as dormant until enforce).
- Threads may contain credentials pasted by users → redactor exists for logs; must avoid echoing raw secrets in exports; attachment storage must not be web-public.
- No csrf on GET-only new endpoints; new POST routes inside existing groups inherit web middleware.

## 7. Operational blockers

- Phase 11 gating blockers B-1..B-5 unchanged (no staging, no restore drill, no mail verification, no external uptime monitor, no deployment runner). This phase must NOT claim production readiness.

## 8. Recommended implementation order (selected)

1. Support ticket lifecycle backend (migration → service → controllers → routes) — core customer-facing feature.
2. Support SLA policy table + computation.
3. Operations center API aggregating verified real data only.
4. Frontend: `PlatformOperationsCenterPage` (overview/database/queues/maintenance/backups/costs/releases), `PlatformSupportTicketsPage`, business `/business/support`, api.ts, routes, sidebar, i18n.
5. Automated tests (support lifecycle, isolation, SLA, operations read-only, cost currency separation).
6. Operations documentation set.
