# CAPACITY_SCALING_PLAN.md — Phase 12

## What is measured today (real data only)

Operations overview `capacity` block + `/platform/operations/database`:

- PostgreSQL database size (`pg_database_size`), live connection count vs `max_connections`.
- Host disk when Phase 7 `servers` observations exist; otherwise no value is shown.
- Queue backlog counts from Phase 7 observations.
- Tenant growth: Phase 11 analytics `/platform/analytics/*` (registered tenant counts) — not duplicated here.

**Not measured (no instrumentation/config):** CPU, memory, per-endpoint P50/P95/P99, request throughput, worker capacity, bandwidth. There is no APM agent in the environment. The overview shows these as absent rather than estimated.

## Order of attack when capacity becomes real

1. Fix verified bottlenecks first (see POSTGRESQL_PERFORMANCE.md) — never scale before measuring.
2. Tune caching that already exists (Phase 7 cache service) — no new cache layer without justification.
3. Grow queue worker processes (requires a process supervisor that does not exist yet).
4. Revisit PostgreSQL `max_connections` + pooler before adding app instances (single instance assumption today).
5. Add app instances only after verifying shared state: sessions are DB-backed, cache/locks through the configured store, uploads on local disk will need object storage first, scheduler is not multi-instance safe (`withoutOverlapping` guards single-host only).

## Explicit constraints

- `storage/app` private disk (support attachments) blocks stateless horizontal scaling until converted to object storage.
- No websocket/realtime infrastructure is in use, so none is checked.
- Horizontal scaling is **not implemented** and should not be claimed by any deployment.
