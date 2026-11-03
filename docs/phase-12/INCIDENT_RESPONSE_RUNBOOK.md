# INCIDENT_RESPONSE_RUNBOOK.md — Phase 12

Phase 7 monitoring remains the system of record; this runbook documents the **verified** procedures available today.

## Sev levels (Phase 7 contract)

SEV-1 critical / SEV-2 high / SEV-3 moderate / SEV-4 low (free-form `severity` field; no automated impact rules configured).

## Verified commands

| Action | How |
|---|---|
| View incidents | `GET /api/v1/platform/monitoring/incidents` or platform UI `/platform/monitoring/incidents` (create/ack/resolve/notes supported by `PlatformMonitoringController`) |
| Thin aggregate view | `GET /api/v1/platform/operations/incidents` (read-only, paginated) |
| Collect fresh observations | `php artisan platform:monitor` (runs every minute via scheduler; verified `php artisan schedule:list`) |
| List failed jobs | `php artisan queue:failed` (verified, output: `No failed jobs found.` in local DB) |
| Audit trail | `PlatformAuditLog` rows for every acknowledged/resolved incident action |

## Current limitations (do not fake)

- **No paging/notification channel is configured.** Alert-opening notifications do not reach a phone/pager; operators must poll the dashboard or an approved external monitor. Installing one is an open blocker (needs a provider and an approved account).
- **No automatic incident deduplication from alerts.** `monitoring_alerts` dedupes by unique `fingerprint` (enforced by a unique index; covered by a Phase 12 test) but incident creation is a manual operator action.
- **No postmortem-state timeline enforcement.** Phase 7 state model: open → acknowledged → resolved (resolution requires notes). The richer Detected→Triaged→…→Postmortem timeline from the phase brief is NOT implemented.
- **No external availability monitor.** A total-outage detection requires a monitor outside the platform boundary; not configured.

## Escalation outline (manual — process, not software)

1. Open `monitoring_incidents` row with severity and affected services.
2. Acknowledge (records `acknowledged_by`, `acknowledged_at`).
3. Work mitigation; publish public replies in the incident notes or maintenance notice via `/platform/settings/maintenance`.
4. Resolve with recorded resolution notes (endpoint refuses empty resolution).
5. Archive the timeline in `PlatformAuditLog`; no destructive restore from the dashboard.
