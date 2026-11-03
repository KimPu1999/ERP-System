# Backup & Recovery Runbook — Enterprise ERP Cloud

Extends `docs/backup-restore.md` (scripts + targets) with the Phase 11 evidence rules. Nothing in this file claims a backup or restore succeeded until real execution evidence exists (Step 15 rule: never claim unverified backups are valid).

## 1. Targets (recorded, not yet evidenced)

| Target | Value | Status |
|---|---|---|
| RPO | 15 minutes | target; achievable only with WAL archiving/PITR or a managed DB equivalent — **not yet provisioned**; current nightly script cadence would exceed this until wired. Compute post-provisioning actual RPO honestly before go-live. |
| RTO | 2 hours | target; must be evidenced by a timed drill (see §4) |
| Backup frequency | Script supports any cron cadence; scheduling lives with the operator (host-level cron/systemd timer, not the Laravel scheduler) | prepare once host exists |
| Retention | 30 days rolling + monthly keep set (script prunes >30 days; extend to explicit monthly keep-set at the operator's policy) | configured at host |
| Recovery ownership | Named operator (assigned at go-live; also recorded in `docs/incident-response.md`) | requires assignment |

## 2. Backup procedure (scripts verified: `infrastructure/backup/`)

```bash
# Host cron / systemd timer (operator scheduling), environment:
# PGHOST PGPORT PGDATABASE PGUSER PGPASSWORD AGE_RECIPIENT BACKUP_DIR
infrastructure/backup/postgres-backup.sh
# emits: $BACKUP_DIR/<db>-<UTC timestamp>.dump.age   (custom-format pg_dump piped into age;
#        unencrypted dump NEVER touches disk; umask 077; sizes > 0 verified)
```

- Storage: offsite versioned bucket (backup + object storage), server-side encryption, versioning on, **delete rights removed from the writer** (ransom hardening). Second geographic copy is required by the runbook.
- Alerting: cron must invoke failure notification on non-zero exit (silent backup failure is treated as a monitoring gap — the monitoring checklist requires an alert on "backup failures").

## 3. What is backed up

| Asset | Mechanism |
|---|---|
| PostgreSQL data | `pg_dump --format=custom` via script above; full PITR only after WAL-archiving is configured (planned, not provisioned) |
| Uploaded assets / private documents | object-storage versioning + life-cycle-managed offsite copy (see `EMAIL_STORAGE_SETUP.md`) |
| Secrets (.env, APP_KEY, age identity) | secret-manager-exported copies, stored in the operator's vault — never in the encrypted backup bundle itself (age decrypting the DB does not recover APP_KEY) |
| Config references | release record: commit sha, shared .env template version, migration list (`migrate:status` output) at backup time |

## 4. Restore drill (isolated, mandatory before go-live)

Status: **not yet executed** — this remains an open blocker (carries forward the Phase 10 F-004 finding). Required procedure:

```bash
# isolated PostgreSQL instance (staging-restore host or local container), NEVER production:
BACKUP_FILE=<name>.dump.age AGE_IDENTITY=<team identity key> \
PGHOST=<restore-target> PGDATABASE=erp_restore PGUSER=<restore-user> \
infrastructure/backup/postgres-restore.sh
```

Post-restore validation checklist (record pass/fail + row counts in the drill report):

1. `php artisan migrate:status` — all migrations "Ran".
2. `GET /api/v1/health` — database true.
3. Tenant data consistency: two representative tenants; per-tenant counts match a pre-rehearsal fingerprint (documented in the drill record).
4. Accounting invariants: debits == credits per journal; immutability triggers rejected attempts.
5. Critical table counts: users, tenants, platform_audit_logs (`SELECT count(*)`) against the source fingerprint.
6. Record RTO = elapsed minutes from "restore start" to "smoke passed"; RPO = (restore time − newest backed-up transaction) gap.

Restore **over production** is prohibited except under an approved incident record (see `ROLLBACK_PLAN.md` §separation of app rollback vs DB recovery).

## 5. Failure alerting evidence requirements

- Backup job must emit a start + success + failure event; missing success within the interval is itself an alert ("backup not seen") — integrated with the monitoring plan in `PRODUCTION_SECURITY_CHECKLIST.md` §M.
- Offsite copy must verify object checksum post-upload; mismatch alerts and blocks (never overwrite production data — never trust a silently mutated backup).
