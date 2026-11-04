# Phase 15 — Production Readiness Baseline (Evidence-Based)

**Created:** 2026-11-04
**Method:** Direct inspection of the repository, Phase 14 audit/release reports (`docs/phase14/`), migration catalog, test suites, infrastructure configs, CI pipeline config, and `backend/storage/logs/laravel.log`.
**Rule applied:** No area is classified as Verified unless executable evidence exists in this repository. Nothing was assumed from Phases 1–13–14 marketing text; every classification below is traceable to a cited artifact.

---

## 1. System inventory (verified by inspection)

| Component | Location | Evidence of existence |
|---|---|---|
| Laravel backend API | `backend/` (Laravel 10+, PHP, Sanctum, Pest/PHPUnit) | `backend/composer.json`, 42 feature test files, `routes/api.php` |
| React/TypeScript frontend | `apps/web/` (Vite, Tailwind, i18n EN/MM) | `apps/web/src/`, `docs/phase14/TEST_RESULTS.md` runs 9–12 |
| PostgreSQL | Local PG 17; test DB `erp_test` (isolated) | `backend/phpunit.xml.dist` (`DB_DATABASE=erp_test`) |
| Migrations | 96 migrations incl. FORCE-RLS repair | `backend/database/migrations/`, `docs/phase14/DATABASE_AUDIT.md` |
| CI pipeline | `.github/workflows/ci.yml` | `docs/deployment/CI_CD_PIPELINE.md` |
| Infra configs | `infrastructure/` (backup, cron, deploy, nginx, supervisor, docker-compose) | directory listing |
| OpenAPI | `backend/openapi.yaml` | file present |

## 2. Phase 14 handoff audit — critical areas classification

Classifications: **Verified** = executable/test evidence in repo. **Failed** = executed and failed. **Blocked** = cannot proceed without external action/approval. **Not Tested** = no evidence either way.

| # | Critical area | Classification | Evidence / Gap |
|---|---|---|---|
| 1 | Feature audit inventory | **Verified** | `docs/phase14/FINAL_REPORT.md` defect register (P0-1 … P3-4), `docs/audit/MODULE_STATUS_MATRIX.md` |
| 2 | Open defects | **Verified (register exists)** | ERP-P3-1 (redundant/unindexed FKs), ERP-P3-2 (i18n drift ~1500 literals), ERP-P3-3 (repo hygiene, hardcoded test passwords in `backend/mfa-repro-seed*.php`), ERP-P3-4 (infra gaps). No open P0/P1 in register. |
| 3 | Security findings | **Verified (P0 fixed + regression-tested)** | ERP-P0-1: all 166 RLS policies were silent no-ops for the app role (table owner bypass); fixed by forward-only migration `2026_11_04_000001_force_tenant_row_level_security`; `RlsEnforcementTest` 2/2. `docs/phase14/TENANT_SECURITY_AUDIT.md`. |
| 4 | Tenant isolation tests | **Verified** | `RlsEnforcementTest` (2), `TenantIsolationTest` (4), full suite green under enforced RLS (222/0, run 8 of `docs/phase14/TEST_RESULTS.md`). Defense-in-depth gap: app still connects as table **owner** `erp`; non-owner runtime role recommended, not implemented. |
| 5 | PostgreSQL migration status | **Verified (fresh install) / Not Tested (upgrade path)** | All 96 migrations apply cleanly via RefreshDatabase on `erp_test`. Upgrade on a real data-bearing instance deliberately NOT executed (safety rule). Operator approval required before validating upgrade path. |
| 6 | Financial reconciliation tests | **Verified (unit/feature level)** | `AccountingPhaseTest`; double-entry invariants covered by suites; decimal(20,6)/(24,6)/(24,12) columns confirmed in DB audit (no float money columns). Missing: published reconciliation evidence of invoice↔payment↔ledger↔statement tying on a populated dataset (UAT item). |
| 7 | Backend test results | **Verified** | Final Phase 14 run: **222 passed / 0 failed** (1072 assertions, 114.56 s) under enforced RLS. Re-execution in Phase 15 recorded in `UAT_EXECUTION_REPORT.md` §Backend. |
| 8 | Frontend test results | **Verified (partial scope)** | tsc clean, build green (large chunks: echarts ~1145 kB, index ~1521 kB), vitest 4/4, lint clean. Playwright e2e (platform-only, 8 tests) exists but was NOT re-run; business-flow e2e missing. |
| 9 | Staging deployment status | **Blocked** | No staging deployment executed or verifiable from this repo; `deploy.yml` builds artifacts only — external runner contract (`docs/deployment-runner-contract.md`); operator/DNS approval outstanding. |
| 10 | Backup/restore verification | **Failed (evidence missing)→Gate FAIL** | Backup + verify-restore scripts **exist** (`infrastructure/backup/`, `docs/phase-12/BACKUP_RECOVERY_RUNBOOK.md`, `docs/deployment/BACKUP_RECOVERY_RUNBOOK.md`) but **no isolated restore drill has been recorded** in the project evidence trail. Phase 14 Gate I = FAIL. Production launch must not proceed without a recorded drill. |
| 11 | Monitoring coverage | **Verified (config level) / Not Tested (delivery)** | Platform monitoring/ops suites pass (`PlatformMonitoringPhaseTest`, `PlatformOperationsPhaseTest`); Supervisor/cron configs audited. End-to-end alert delivery to real recipients: Not Tested. |
| 12 | Release gate statuses | **Verified (documented, mixed)** | `docs/phase14/RELEASE_GATES.md`: Gates A/C/B/H PASS(evidenced), D/E PASS at automated level, F in progress, **G NOT TESTED, I FAIL, J BLOCKED → NOT production-ready.** |

### New evidence found in this Phase 15 audit (not previously registered)

| Finding | Source | Class |
|---|---|---|
| SMTP email verification delivery failure observed in log: `testing.WARNING: Email verification delivery failed ... status:"failed", exception:"RuntimeException"` (log timestamps 2026-11-04 03:50); log-transport fallback works | `backend/storage/logs/laravel.log` | **P2 defect (DEV-15-001)** — test-environment mail transport instability; masks email-verifiable UAT flows. Platform email self-test with log transport succeeded (04:30). |
| Log transport fallback mechanism verified working (writes verification emails to log with hashed addresses; no PII) | same log | Verified control |
| `backend/mfa-repro-seed.php`, `mfa-repro-seed2.php` + root `mfa-repro-flow.sh` still contain hardcoded test passwords | repo listing + Phase 14 P3-3 | **Open P3** (hygiene; on an isolated dev machine, but violate no-hardcoded-secrets rule) |
| Uncommitted working tree: clean at audit time (branch `main`, `git status` clean) | `git status` | Verified baseline |

## 3. Release blockers (before UAT)

Mandatory items that block a GO decision today:

| Blocker ID | Description | Gate impact |
|---|---|---|
| BLK-1 | **No recorded backup-restore drill** — scripts exist, but no executed `pg_dump` → isolated restore → verification evidence in trail. | Gate 9 FAIL |
| BLK-2 | **No staging deployment** — deploy requires external runner, operator execution and approval; nothing verified on staging. Pre-cursor to all staging UAT. | Gate 11 BLOCKED |
| BLK-3 | **UAT not executed** — full human UAT (platform + business admin) has not been run; only automated suites exist. Acceptance cannot be granted from automation alone. | Gates 4/5/6/7 partially open |
| BLK-4 | **Upgrade-path migration validation not performed** on a real data-bearing instance (requires operator approval and pre-migration backup). | Gate 2 conditional |
| BLK-5 | **Operational ownership unassigned** — RACI owners are placeholders (no names/contacts known; cannot invent). Gate 10/12 depend on assignment. | Gate 12 |
| BLK-6 | **Defense-in-depth gap**: runtime DB role is the table owner (`erp`), relying on FORCE RLS alone. Recommended non-owner runtime role before GA. | Gate 3 PASS (compensated) / hardening item |

Non-blocking P2/P3 defects carry into the UAT defect register (`UAT_DEFECT_REGISTER.md`).

## 4. Classification summary

- **Verified:** schema/RLS enforcement, tenant isolation tests, backend suite, accounting invariants, i18n catalog parity (EN/MM), CI config, no-fake-data rule (no seeder; only reference currency catalog).
- **Failed:** backup/restore drill evidence (Gate I), mail SMTP delivery in test env (defect DEV-15-001).
- **Blocked:** staging deployment (J), upgrade-path validation, operational owner assignment.
- **Not Tested:** Playwright e2e, mobile/responsive + Burmese visual pass, concurrency stress tests, load/failure injection, live monitoring delivery, pilot program.

## 5. Phase 15 carry-forward scope

1. Execute safe local verification (backend suite, typecheck, unit tests, build) — done in this phase; results in `UAT_EXECUTION_REPORT.md`.
2. Stand up the UAT framework + test case catalog on an **isolated UAT environment** (no production/dev data), with safe setup/teardown scripts.
3. Reduce operational risk artifacts: runbooks consolidation, cutover/rollback plans, hypercare plan, release gates, GO/NO-GO frame — all prepared with placeholders, not fabrications.
4. Training materials EN/MM mapped to **actual** implemented routes/features only.

**Baseline verdict:** Platform is **NOT approved for production release** as of this baseline. The application codebase is in the strongest evidenced condition since project start (222/0 backend under enforced RLS), but rollback/recovery evidence, staging execution, and human UAT are absent — exactly the NO-GO conditions listed in `GO_NO_GO_DECISION.md`.
