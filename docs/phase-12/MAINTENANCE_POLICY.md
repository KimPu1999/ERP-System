# MAINTENANCE_POLICY.md — Phase 12

## Two separate mechanisms (do not conflate)

1. **User-facing maintenance notice** — Phase 9 platform setting `maintenance`
   (`notice_enabled`, `notice`, `starts_at`, `ends_at`). Updated at
   `PATCH /api/v1/platform/settings/maintenance`; public read at
   `/api/v1/platform/settings/operational-status`.
   Phase 12 only **reads** it (`GET /platform/operations/maintenance/notice` returns
   `notice_endpoint`; ownership stays with Phase 9).
2. **Infrastructure maintenance windows** — new `maintenance_windows` table:
   title, scope, impact_description, **rollback plan (required)**, status, `notify_businesses`,
   starts/ends, creator and approver.

## `MaintenanceWindow::TRANSITIONS`

```
planned     -> approved | cancelled
approved    -> in_progress | cancelled | planned
in_progress -> completed | cancelled
completed / cancelled -> (terminal)
```

- Quality gates enforced: `planned → in_progress` is rejected (tested), and `approved`
  records `approved_by`/`approved_at` (tested, audited action
  `platform.operations.maintenance_transitioned`).
- Everyone who can reach these endpoints is a verified platform super-admin (`platform.admin` middleware). Tenant members are 403 (tested).
- All create/transition actions go through `AuditLogger`.

## Operational safety rules

- No arbitrary shell commands are exposed by the Operations API — nothing executes system commands.
- Scheduler (`platform:monitor` every minute) and queue workers keep running; there is no maintenance mode that stops them in this codebase. During a window, record the window instead of stopping supervision (documented here so operators don't improvise).
- `notify_businesses` currently only stores intent — **no automatic tenant email is sent from this table**; use the Phase 9 notice + notification template for actual communication (`maintenance_notification` template exists in `SettingsService`).

## Current limitations

- No approval workflow beyond the recorded approver (no second-person review enforcement).
- No automatic "suspends user-facing features" runtime switch besides the Phase 9 notice.
- No calendar UI (list view only at `/platform/operations/maintenance`).
