# Phase 15 — UAT Master Plan (Enterprise ERP Cloud)

**Status:** Framework prepared; execution pending staging deployment (BLK-2).
**Environments:** Isolated UAT environment only (see §5). Automated test separation is non-negotiable: **a passing PHPUnit/Pest/Vitest test never marks a UAT case passed.**

## 1. Objectives

1. Evidence-based human acceptance of every implemented module by the role designed to use it.
2. Separate user acceptance from automated coverage, with defect linkage both ways.
3. Produce the sign-off record required by Gate 12 only if all mandatory UAT scenarios pass.

## 2. Roles & environments

| Role | UAT scope | Test accounts |
|---|---|---|
| UAT Coordinator | Owns catalog, schedule, progress tracking, sign-off collection | `uat-coordinator@uat.erp.local` |
| Platform Super Admin tester | Steps 3-type scenarios (auth, platform mgmt, billing) | Synthetic platform admin provisioned by `platform:provision-admin` onto the UAT DB |
| Business Admin tester | Steps 4-type scenarios (ERP workflows) | Synthetic tenant admin |
| Business staff testers (scoped roles) | Role-limited checks | Synthetic staff with scoped role assignments |

Accounts are **created fresh in the UAT database by the setup script**. No real credentials. No real payment credentials ever.

## 3. UAT record schema

Every case in `UAT_TEST_CASES.md` carries:

| Field | Definition |
|---|---|
| UAT Case ID | `UAT-<MODULE>-<NNN>` |
| Module | Platform / Auth / Onboarding / Products / Purchasing / Inventory / Sales / Accounting / Billing / Support / Security |
| Test Scenario | Short title |
| Preconditions | Environment + data + role setup required |
| Actor/Role | Exact permission scope used |
| Steps | Reproducible, ordered |
| Expected Result | Backend (API/DB observable) + frontend (UI observable) |
| Actual Result | Filled by tester at execution |
| Test Environment | `uat` (isolated DB + staging app) — never `dev`, never production |
| Test Data Reference | Fixture ID from data plan (§5) |
| Execution Date | ISO date |
| Tester | Role pseudonym until person assigned (`Unassigned`) |
| Evidence Reference | Screenshot name / API transcript reference |
| Status | Not Tested / In Progress / Passed / Failed / Blocked |
| Linked Defect | DEF-15-xxx |
| Approval/Sign-off | Sign-off line, only for Passed + evidence-attached cases |

Workflow: `Not Tested → In Progress → Passed/Failed/Blocked`. A Failed case is retested after its linked defect is Fixed; retest history recorded in `UAT_EXECUTION_REPORT.md`.

## 4. Coverage model

- **Catalog:** `UAT_TEST_CASES.md` — full grid of platform + business scenarios, each with status `Not Tested` until executed by a human tester.
- **Execution:** `UAT_EXECUTION_REPORT.md` — run log (who/when/what), retest history, pass-rate math.
- **Defects:** `UAT_DEFECT_REGISTER.md` — one row per failure, process Open → Assigned → In Progress → Fixed → Ready for Retest → Verified → Closed (reopen on failed retest).
- **Sign-off:** `UAT_SIGNOFF.md` — per-signatory acceptance lines with authenticated-identity note; placeholders until real people.

Existing infrastructure reuse decision: the repo has per-module UAT *plans* (e.g. `docs/purchasing/PHASE_8_UAT_PLAN.md`) and a full audit trail, but **no UAT execution register with defects/sign-off**. Therefore Phase 15 introduces a minimal docs-based register (this file set) instead of building or duplicating a testing application. Revisit only if the volume of defects exceeds ~100.

## 5. UAT environment & data plan (summary; full detail in setup script header)

1. **Isolated database** `erp_uat` on the local/staging PostgreSQL instance. Never `erp` (dev business data), never `erp_test` (automation), never production.
2. Setup script `scripts/uat/setup_uat.sh`: creates `erp_uat` if absent, runs migrations fresh, provisions synthetic platform admin, one synthetic tenant (`UAT42`-deterministic slugs), companies/branches, staff, catalog, suppliers, customers, and one **sandbox/non-payable** billing scenario. All records marked `uat-` prefixed emails (`@uat.erp.local`) for unambiguous teardown.
3. Teardown script `scripts/uat/teardown_uat.sh`: **destructive only against `erp_uat`** — hard-checks database name matches `uat` before any drop; refuses any other name. Never to be run against `erp`/`erp_test`/production.
4. **Deterministic fixtures:** fixed slugs/emails/prices so runs are repeatable and reconciliation expected-values are computable.
5. Personal data: none. Real personal data is barred from staging without an approved sanitized process (none exists yet → barred).

## 6. Execution schedule gates

UAT execution may start only when:
1. A staging build of the app runs against the isolated UAT environment (BLK-2 resolution), OR an operator explicitly approves a localhost-UAT variant.
2. The setup script has run cleanly and teardown has been dry-verified.
3. Testers are assigned (names may remain `Unassigned` keep-output roles until staffing).

## 7. Acceptance rules

- A module's UAT is Accepted when all its mandatory cases are `Passed` with evidence.
- Severity rules for defects per `UAT_DEFECT_REGISTER.md`; any open P0/P1 against a module blocks its acceptance.
- UAT acceptance contributes to Gates 4–7; Gate 12 (Final Approval) additionally requires backup/recovery evidence and authorized release approval — UAT alone is never sufficient.
