# POSTGRESQL_PERFORMANCE.md — Phase 12 (read-only diagnostics)

## What is wired (verified)

`GET /api/v1/platform/operations/database` (platform super-admin only, action audited
`platform.operations.database_inspected`) executes **SELECT-only** queries against
statistics views, each bounded with LIMIT:

- Connections: total, active, lock waits, `max_connections` from `pg_stat_activity` + `pg_settings`.
- Slow queries: active queries > 1 s in `pg_stat_activity`, capped at 20, self PID excluded.
- Lock waits: `pg_locks` joined with `pg_stat_activity` where `NOT granted`, capped at 20.
- Largest tables: `pg_stat_user_tables` ordered by `pg_total_relation_size` (size, live rows, seq vs index scans) — good seq-scan-vs-index-scan signal for missing indexes. Tested: read-only and size-bounded (`PlatformOperationsPhaseTest`).
- Database row: `pg_stat_database` for the current DB (deadlocks, temp files, hit ratio inputs, size).

## Usage discipline

- Avoid running this repeatedly in production at peak: each call touches `pg_stat_activity`; no expensive EXPLAIN is automatic.
- Before adding any index: capture a real slow query from the endpoint, run `EXPLAIN (ANALYZE, BUFFERS)` **manually** on staging with the same data volume, then use a reviewable migration.
- Never create indexes from a dashboard without review; never run destructive schema modifications.

## Index work done in Phase 12 (forward-only)

Support/operations tables got scoped indexes in
`2026_11_03_100001_expand_support_and_operations_tables`:
`support_tickets(tenant_id,status)`, `(tenant_id,created_at)`, `(status,priority)`,
`ticket_number` unique; plus per-table indexes on tickets/messages/attachments,
maintenance, cost records, backup records and releases (dated queries).

## Existing data exclusions

Step 9's "expensive diagnostics" (e.g. `EXPLAIN ANALYZE` automation, historical latency percentiles) are **not implemented**; there is no `pg_stat_statements` extension configured in the local environment, so per-query duration stats are unavailable — the endpoint reports what PostgreSQL exposes without it.
