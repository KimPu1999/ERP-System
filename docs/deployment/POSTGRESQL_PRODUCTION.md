# PostgreSQL Production — Enterprise ERP Cloud

Grounded in the actual schema and migrations. No production database exists yet; this is the creation and validation procedure.

## 1. Create the production database and roles

```sql
-- As the cluster administrator (over private network only):
CREATE DATABASE erp_prod ENCODING 'UTF8' LC_COLLATE 'C.UTF-8' LC_CTYPE 'C.UTF-8' TEMPLATE template0;

-- Least-privilege runtime role: DML only
CREATE ROLE erp_app LOGIN PASSWORD '<secret-from-manager>';
GRANT CONNECT ON DATABASE erp_prod TO erp_app;
GRANT USAGE ON SCHEMA public TO erp_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO erp_app;
USAGE, SELECT, and EXECUTE privileges on future sequences/functions must be granted via ALTER DEFAULT PRIVILEGES by the owner just before the first migration.

-- Controlled migration role (used ONLY during approved releases, not at runtime)
CREATE ROLE erp_migrator LOGIN PASSWORD '<secret-from-manager>';
-- erp_migrator owns schema objects and therefore must be the one running migrations;
-- alternatively the database owner role is used interactively and revocably.

-- Read-only backup role
CREATE ROLE erp_backup LOGIN PASSWORD '<secret-from-manager>';
GRANT CONNECT ON DATABASE erp_prod TO erp_backup;
GRANT USAGE ON SCHEMA public TO erp_backup;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO erp_backup;
```

The application connects as `erp_app` normally. The deploy runner temporarily connects as `erp_migrator` for approved migrations, then the connection is dropped. Never run the web tier with the migration role.

## 2. Security posture

| Control | Target |
|---|---|
| Network exposure | Private VPC/host-network only. **Never publish 5432**; remote access via SSH tunnel while validating |
| TLS | `ssl = on`, server certificate; app uses `DB_SSLMODE=verify-full` (release-check requirement) |
| Runtime privileges | `erp_app` cannot CREATE/DROP tables, schemas, extensions, or modify PostgreSQL RLS internals |
| RLS | Enabled by migrations (e.g. `enable_tenant_row_level_security`, inventory/procurement RLS, `enforce_platform_audit_immutability`). **Do not bypass RLS**; the RLS policies are schema-owned (co-owned by the migrator role that also deployed them) — validation procedure in §5. Note these migrations exist and are applied in this repo's databases; behaviour must be re-validated on the fresh production instance. |
| Timeouts | Per-DB: `statement_timeout = '15s'`, `idle_in_transaction_session_timeout = '30s'`, `lock_timeout = '3s'`; queue worker connections may need an exception reviewed per deployment (long transactions during posting are bounded by business logic) |
| Connections | `erp_app` connection cap (`ALTER ROLE erp_app CONNECTION LIMIT 40` as designed with the host's max_connections), plus application pooling via database-side `poolers` when available; monitor saturation via `pg_stat_activity`/MonitoringService DB check |
| Auditing | PostgreSQL logs streamed off-host with retention; application-level audit lives in `platform_audit_logs`/`security_events` (immutable by migration), archived by `platform:security-prune` (`routes/console.php`) |

Never attach the production application to a developer machine's PostgreSQL (the repo's Docker DB password is `erp-local-only` — it is CI/dev-only by explicit design).

## 3. Migration validation procedure (before first production release)

```bash
# On the staging/prod DB host, read-only status:
psql -U erp_migrator -d erp_prod -h <host> -c "SELECT count(*) FROM pg_tables WHERE schemaname='public';"
DB_USERNAME=erp_migrator php artisan migrate:status   # every migration must be "Ran" — never apply a partial set
```

Then verify, queries identical to those used in internal audits (read-only):

| Verification | Query sketch |
|---|---|
| Tenant isolation | RLS policies exist on tenant-scoped tables: `SELECT tablename, policyname FROM pg_policies;` — cross-tenant feature tests (backend suite) must pass against representative data |
| Foreign keys | Enumerate FKs via `SELECT conrelid::regclass, conname FROM pg_constraint WHERE contype='f';` — none dropped |
| Critical uniqueness | users.email, tenant memberships (tenant,user) unique, supplier invoice idempotency, audit log immutability |
| Platform audit tables | platform_audit_logs, audit_log_archives present, read-only to erp_app except pruning command's safeguarded writes (`app.allow_audit_prune` context guard in console.php) |

## 4. Application data migration policy from local environments

1. No developer or personal data enters production without an approved data mapping plan and PII review (pre-registered: developer-local emails use `example.test`/`example.com` fixtures).
2. Preferred path: production starts from an empty schema; real tenants onboard via the actual registration flow (email verification enforced end-to-end).
3. Bulk import, if approved, uses a reviewed ID-preserving plan with an immutable audit of the operation; never a raw dev-dump restore.

## 5. Backup and restore

See `BACKUP_RECOVERY_RUNBOOK.md` — script-verified path: `pg_dump --format=custom` → `age` encryption → offsite versioned storage → isolated restore drill with post-restore `migrate:status` + health + tenant-isolation + accounting invariance checks.

## 6. Constraints already shipped in repos that the production role must respect

- `stock movements` immutability trigger (`immutability_trigger_for_stock_movements`) — restrict/verify the trigger presence and non-bypass even from the migrator role.
- Duplicate-posting/idempotency migrations (`add_platform_invoice_idempotency`, goods receipts, purchase approvals) with unique constraints; `erp_app` breaching them is a bug, not a feature.
- `erp:release-check` requires `DB_SSLMODE ∈ {require, verify-ca, verify-full}` — this is CLI-checked on staging and production before every launch.
