# BACKUP_RECOVERY_RUNBOOK.md — Phase 12 (evidence-based)

## What exists (verified)

- `backup_verification_records` table: `backup_taken_at`, `backup_type`
  (`pg_dump | base_backup | wal | files`), `storage_location`, `checksum`, `size_bytes`,
  `verification_status` (`registered | restore_verified | failed`), `restore_tested_at`,
  recorded_by. All rows are **operator-registered evidence**, never auto-generated —
  there is no backup agent in this repository.
- `GET /api/v1/platform/operations/backups` — status derived ONLY from records:
  `not_configured` (no records), `reported` (record ≤48h old, not failed), `stale` (>48h),
  `degraded` (failed). Response explicitly includes
  `scheduling: not_configured_until_the_rpo_policy_is_provisioned…` and
  `policy: {rpo: not_measured, rto: not_measured, offsite_copy: not_configured, encryption: not_verified}`.
- `POST /api/v1/platform/operations/backups` — register evidence (platform super-admin, audited).
- Overview card surfaces the same status; nothing fabricates success.

## Rules (enforced by design)

1. A `registered` record is **NOT** a working backup. Only an executed restore drill in an isolated environment may set `restore_verified` + `restore_tested_at`. The UI says this on the register form.
2. Restore drills never target production; there is no dashboard restore action that can touch a live database (none exists in the code).
3. `pg_dump`/`pg_restore` are logical snapshots only — **they do not provide PITR**. WAL-archiving (`base_backup | wal` types exist as record categories) is the requirement for point-in-time recovery and is not provisioned.
4. No credentials or checksums in audit logs are redacted via `SensitiveDataRedactor`; do not paste tokens into notes.

## Procedures to be run by an operator (manual, not automated here)

1. Take the backup on the DB host, e.g. `pg_dump -Fc -U erp -f erp_$(date +%F).dump erp` (no credentials included in this repo).
2. Copy to an offsite target you own; record SHA-256 as `checksum` via the API.
3. For a real drill: restore into a **disposable** database (`CREATE DATABASE erp_restore;` → `pg_restore -d erp_restore …`), run a smoke check (migrate:status, sample selects), then register with `verification_status: restore_verified` and `restore_tested_at`. Delete the disposable DB afterwards.
4. Files/assets backups (`storage/app/...` private disk incl. support attachments) — directory-level copy, record with `backup_type: files`.

## Current blockers

- No scheduled backup job exists in cron/systemd in this environment (`schedule:list` shows only automation/monitor/security/prune jobs).
- No offsite copy, encryption-at-rest config or retention rule is provisioned. RPO/RTO remain **not measured** — targets need a business decision plus real infrastructure.
