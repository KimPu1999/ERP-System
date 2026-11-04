# Phase 14 — Database & Migration Audit

**Audited:** 94 migrations (54 × 2026-10-29, 18 × 2026-10-30, 4 × 2026-11-02, 17 × 2026-11-03, 2 × phase-14 repairs). Local PostgreSQL 17.

## Verified key schema findings

| # | Finding | Status |
|---|---------|--------|
| 1 | **RLS silently bypassed** — all RLS-enabled tables lacked `FORCE ROW LEVEL SECURITY`; the application role `erp` is the table owner, so every tenant-isolation policy was a no-op for the app. Proven on an isolated scratch database (tenant-A rows visible to owner posing tenant B without FORCE; filtered correctly with FORCE; `platform_admin` context restores platform sweeps). | **FIXED** — forward-only migration `2026_11_04_000001_force_tenant_row_level_security` forces RLS on every RLS-governed table (166 tables). |
| 2 | Public invitation preview could never resolve under enforced RLS (no token condition in policy). | **FIXED** — `2026_11_04_000002_invitation_token_preview_access_rls` adds token-hash-granted read through connection-local setting. |
| 3 | Redundant composite indexes: `stock_movements` (`tenant_id, variant_id, location_id` leading columns duplicated across two indexes), `role_assignments` (`tenant_id, user_id` prefix duplicated). | OPEN (P3) — cosmetic write-overhead, safe forward-only `DROP INDEX` candidate. |
| 4 | Unindexed non-tenant FKs, most critically `journal_lines.journal_entry_id` (cascade deletes on the double-entry core), plus item-table FKs. | OPEN (P3) — forward-only index migration proposed. |
| 5 | `role_assignments` lacks a unique constraint preventing duplicate role grants at the same scope. | OPEN (P2) — needs duplicate-cleanup step before constraint (do not attenuate existing rows without review). |
| 6 | `fiscal_periods` unique `[fiscal_year_id, starts_on]` and tenant FK not tenant-leading; `journal_lines` no entry-leading composite beyond `(tenant_id, account_id)`. | OPEN (P3). |
| 7 | Money columns: **zero float/double columns found** — all `decimal(20,6)` / `(24,6)` / `(24,12)` family; one outlier `operational_cost_records.amount decimal(16,4)`. | No action required for correctness; unify on next schema pass. |
| 8 | `users.email` unique is global by design (platform identity); `tenant_subscriptions.tenant_id` unique enforces one subscription per tenant. | VERIFIED CORRECT. |
| 9 | Support-station numbering: `support_tickets_number_unique` is global rather than tenant-scoped. | OPEN (P3) — cosmetic. |
| 10 | `supplier_onboarding` repair migration is guarded (`Schema::hasTable`) and documented; not a live duplicate. | VERIFIED CORRECT. |

## Transactional & seeding rules verified
- No seeder directory; the only data-seeding-in-migration is the reference currency catalog (mk/USD/SGD/THB/EUR) — no fake business data. Flagged: it iterates tenants existing at migration time (documented limitation).
- 106+ tables carry tenant/company/branch scope columns with tenant-leading indexes/unique constraints. `inventory_balances` unique `[variant_id, location_id]` underpins single-stock-row concurrency. Idempotency keys (`journal_entries`, `supplier_payments`, purchase GR/returns) support replay safety.

## Migration application evidence
- Fresh-install path exercised repeatedly by the test suite (RefreshDatabase on `erp_test` runs all 96 migrations including the 2 phase-14 repairs; 220+ tests pass against real PostgreSQL).
- Upgrade path: the two phase-14 migrations are forward-only; no historical migration was rewritten. `FORCE`-migration includes operator note: future data-modifying migrations must set `SET app.platform_admin = 'true'` before touching RLS-governed rows.

## Dev-database incident (transparency)
During root-cause debugging, a scratch PHP script accidentally booted with the dev `.env` and created 3 test tenants, 3 companies, and 1 test user (`wa-debug@example.test`) in the `erp` database. These exact artifacts were identified, reported, and removed precisely; all other rows (4 tenants) were left untouched. No financial records were modified.
