# Phase 14 — Release Gates (evidence-based)

| Gate | Status | Evidence |
|------|--------|----------|
| **A — Database** | **PASS (fresh install) / NOT TESTED (upgrade on real instance)** | All 96 migrations apply on the isolated `erp_test` PostgreSQL via the suite's RefreshDatabase (fresh path). Upgrade-path validation against the user's dev `erp` instance deliberately **not performed** (rule: never migrate user data without approval). Two forward-only migrations prepared; upgrade validation pending operator approval. |
| **B — Security** | **PASS for P0/P1 in scope; monitoring continues** | P0 RLS bypass fixed+proven; no other unresolved P0/P1 security defect found in four audits; dependency audits (`composer audit`, `npm audit`) exist in CI. |
| **C — Tenant isolation** | **PASS** | New `RlsEnforcementTest` (DB-level, FORCE verified) + existing `TenantIsolationTest` 4/4 + full suite green under enforced RLS. |
| **D — Core ERP workflows** | **PASS (automated level)** | Purchasing, goods receipt, sales, inventory, accounting, HR suites all green on real PostgreSQL — final run **222 passed / 0 failed** under enforced RLS. End-to-end manual flows and concurrency stress tests: NOT RUN yet. |
| **E — Platform workflows** | **PASS (automated level)** | Business management/analytics/billing/monitoring/operations/security/settings suites green (final run 222/0). |
| **F — UI/UX** | **IN PROGRESS** | tsc clean, build green, vitest 4/4, lint clean, i18n catalog bilingual parity with 9 keys added. Playwright e2e exists (platform-only, 8 tests) — not re-run this session. Mobile/responsive + Burmese visual pass: NOT RUN. |
| **G — Reliability** | **NOT TESTED (in production scope)** | Supervisor/cron/scheduler configs audited (documented); no load/failure injection executed. Backup scripts exist; restore-drill evidence required before declaring PASS. |
| **H — CI/CD** | **PASS (pipeline config audited)** | ci.yml runs real PostgreSQL-backed backend tests + frontend typecheck/lint/build/vitest + dependency audits. Branch protection unverifiable from repo (GitHub settings). deploy.yml builds artifacts only; external runner contract — flagged. |
| **I — Backup & recovery** | **FAIL (evidence missing)** | Scripts exist and verify-restore procedure documented, but no recorded isolated restore drill passed in this project's evidence trail. Production release must not proceed until a drill is recorded. |
| **J — Deployment** | **BLOCKED** | Staging smoke procedure exists; no staging deployment executed or verified from this repo; deploy requires external runner + operator approval. |

## Production readiness call
**NOT production-ready yet** — mandatory gates I (backup restore drill) and J (staging deployment) lack evidence; gate D/E pass at automated level only. Gates A/C/B are evidenced.

## Batch 2 work queue (priority order)
1. Gate I: run backup + isolated restore drill; record evidence.
2. Concurrency tests (STEP 12): receipts/stock/payments/memberships.
3. Schema batch: dup-index removal, FK indexes, `role_assignments` unique constraint (with duplicate audit first).
4. Frontend: bundle-split (echarts 1.14 MB / index 1.52 MB chunks), component tests, business-flow e2e.
5. Repo hygiene: remove `mfa-repro-seed*.php` hardcoded-password scripts (with user confirmation), complete `.env.example`, remove dead code.
