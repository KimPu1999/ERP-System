# OPERATIONS_ARCHITECTURE.md — Operations & Support Center (Phase 12)

Verified 2026-11-03 against the working tree. Local/staging readiness only; no live production claim.

## Scope

A consolidated Operations Center that **aggregates existing verified sources** rather than duplicating them:

| Area | Source of truth | Phase 12 behavior |
|---|---|---|
| Production health / services / queues / scheduler | Phase 7 `MonitoringService` (stored observations) | Read via `OperationsController`, never probed on dashboard reads |
| Incidents | Phase 7 `monitoring_incidents` + `/platform/monitoring/incidents` UI | Thin authorized list endpoint only (`GET /platform/operations/incidents`) |
| Security maintenance | Phase 8 Security Center (`/platform/security`) | Overview links to it; explicitly `not_aggregated_here_to_avoid_duplicating_phase_8` |
| Maintenance user notice | Phase 9 settings `maintenance` category | `/platform/operations/maintenance` shows notice state; edits stay at `PATCH /platform/settings/maintenance` |
| Support tickets | New `SupportTicketService` + expanded `support_tickets` tables | Full lifecycle (below) |
| Backups / costs / releases | New evidence tables (`backup_verification_records`, `operational_cost_records`, `release_records`) | Registered evidence only; unconfigured areas show explicit markers |

## Honest-data rule (enforced)

Every unconfigured area returns an explicit string marker (e.g. `not_configured`,
`not_measured`, `not_configured_until_release_records_are_registered`) instead of a
fabricated number. The backend tests assert these markers
(`PlatformOperationsPhaseTest`, `SupportTicketsPhaseTest`).

## Backend components

- `backend/app/Http/Controllers/Api/V1/OperationsController.php` — overview, database, queues, incidents, maintenance CRUD, backups, costs, releases.
- `backend/app/Http/Controllers/Api/V1/SupportController.php` — business + platform ticket APIs.
- `backend/app/Services/SupportTicketService.php` — status machine, SLA policy resolution, business-hours math, attachment validation.
- `backend/routes/api.php` — routes live inside the existing `verified` + `platform.admin` middleware groups.

## Frontend

- `/platform/operations` (modes: overview, database, queues, maintenance, backups, costs)
- `/platform/support` — platform support ticket console
- `/business/support` — business ticket workspace
- All new strings exist in both EN and Burmese dictionaries; light/dark theme via the existing design system.

## Dependencies / gaps (unchanged)

- No staging or production host; no external uptime monitor; no Horizon/Redis workers; no scheduled real backup job. Blocker list from Phase 11 still applies.
