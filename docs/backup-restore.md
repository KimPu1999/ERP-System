# Backup and restore runbook

Backups are encrypted before leaving the database host using `age` and a recipient held by the deployment secret manager. The backup scripts never write an unencrypted dump to disk.

## RPO/RTO targets

The initial service targets are RPO 15 minutes and RTO 2 hours. These are targets, not evidence; revise them after load and restore drills.

## Backup

Set `PGHOST`, `PGPORT`, `PGDATABASE`, `PGUSER`, `PGPASSWORD`, `AGE_RECIPIENT`, and `BACKUP_DIR`, then run `infrastructure/backup/postgres-backup.sh` from a host with `pg_dump` and `age`. Upload the resulting `.dump.age` file to encrypted, versioned object storage with a retention policy.

## Restore drill

Provision an isolated PostgreSQL instance, set `BACKUP_FILE` and `AGE_IDENTITY`, and run `infrastructure/backup/postgres-restore.sh`. Run migrations, `php artisan erp:release-check`, health checks, tenant isolation tests, accounting invariants, and representative staging end-to-end tests. Record start/end time, backup identifier, row-count checks, failed checks, and approver.

Never restore over production without an approved incident/change record and a verified rollback plan.
