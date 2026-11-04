# Phase 15 — Production Cutover Plan

**Execution status: NOT EXECUTED — and must never be executed without explicit, recorded approval from the Release Owner (Release/Gate authority).** This is a plan document only; no automation is attached to it, on purpose.

## Stage A — Pre-cutover verification

| # | Check | Evidence required | Signatory |
|---|---|---|---|
| A1 | Release artifact versioned and immutable (backend + frontend build digest) | artifact URI + checksum | Deploy Op: Unassigned |
| A2 | CI checks green on the exact commit (backend suite incl. RLS, audits, typecheck, build, tests) | CI run link | Release Owner: Unassigned |
| A3 | Migrations reviewed: list every new migration since last release, each declared forward-only/additive; no destructive/change-history migrations | reviewed diff | DBA: Unassigned |
| A4 | Fresh full backup taken immediately before cutover; ledger entry written | dump id/timestamp/size | DBA: Unassigned |
| A5 | Restore drill completed within retention (BLK-1) | drill record | DBA + Release Owner |
| A6 | Rollback strategy confirmed operable (artifact versioning wired; forward-compat rule holds) | rehearsal evidence | Deploy Op |
| A7 | Monitoring + alert recipients verified live (test alert received by human) | test alert ack screenshot | Platform Admin |
| A8 | Release approval recorded in `GO_NO_GO_DECISION.md` + audit log | signed decision row | Release Owner |
| A9 | Operational owners assigned (on-call for DBA/Deploy/Security at minimum) | `RESPONSIBILITY_MATRIX.md` rows filled | Release Owner |
| A10 | Hypercare plan activated (window defined, owners on call) | hypercare record | Release Owner |

**Gate: A1–A10 all checked ⇒ proceed. Any missing ⇒ NO-GO for this window (see `GO_NO_GO_DECISION.md`).**

## Stage B — Cutover (order matters; no step parallelized with a destructive one)

1. Freeze content/tenant changes: enable maintenance per `INCIDENT_RUNBOOKS.md` §14.
2. Deploy the approved artifact (backend first, then frontend).
3. Apply reviewed migrations — **operator note enforced by the FORCE-RLS migration:** before data-modifying touches on RLS-governed tables, set `SET app.platform_admin = 'true'`.
4. Reload long-running workers (queue workers) so they pick up new code; scheduler restart.
5. Verify configuration secrets present in the new environment (existence only — never print values).
6. Service health checks: `/health` (API), `/platform/monitoring/servers`, pg connectivity.
7. Production-safe smoke checks (see §Smoke below).
8. Shift public traffic (DNS/load-balancer step executed by hand by the named owner; no automatic DNS changes — Step 23 rule).

## Stage C — Post-cutover verification (first 60–120 min)

- Authorized platform login works (correct MFA path).
- Protected resource read via API with a real account.
- Tenant scope verification: Tenant A API access unaffected; a deliberate cross-tenant probe returns denial (read-only, no data mutation).
- Selected critical workflows read-only verified (e.g. open a real invoice detail page of a consenting pilot tenant without posting).
- Queue activity present; scheduler heartbeat current (`/platform/monitoring/scheduler`, `/queues`).
- Email test message delivered (platform `settings/email/test`).
- Alert channel delivery confirmed (test alert).
- Errors & latency reviewed on `/platform/monitoring/api`; rollback trigger thresholds handed to hypercare.
- Record result: release note appended to `GO_NO_GO_DECISION.md` (GO executed / deferred).

## Smoke checks (production-safe, guaranteed)

| Check | Method | Damage risk |
|---|---|---|
| API liveness/readiness | `/health` GET | none |
| Authorized platform login | existing account | none |
| Protected resource read | GET one known-resource list | none |
| Tenant scope probe | cross-tenant GET (expect denial) | none (read-only) |
| Static assets | fetch one hashed asset from frontend bundle | none |
| Storage service readiness | upload-list-delete a scratch object in an internal bucket (or HEAD request) | none |
| Queue worker heartbeat | monitoring view only | none |
| Scheduler freshness | monitoring view only | none |
| Monitoring delivery | test alert to recipients | none |

Prohibited as routine smoke: invoice creation, live payment capture, stock resets, financial posting changes, tenant data mutation. Financial/destructive checks belong to the sandbox workflow only.

## Rollback triggers (watched continuously during/after)

- Error rate above threshold (per hypercare plan §thresholds)
- Login/auth failure spike
- Migration failure or verification failure
- Data-integrity alarm (balanced-journal check failure, duplicate invoice)

On trigger: execute `ROLLBACK_PLAN.md` immediately; incident record opened; post-cutover verification re-run after recovery.
