# Phase 15 — Rollback Plan

Extends `docs/deployment/ROLLBACK_PLAN.md` (existing repo plan) with Phase 15 release-decision specifics. **Nobody may execute rollback without a recorded trigger** — the hypercare/monitoring thresholds in `HYPERCARE_PLAN.md` define automatic candidate triggers; the decision remains human (Step 23: no automated GO/revert decisions).

## 1. When rollback (vs. fix-forward)

| Situation | Action |
|---|---|
| Immediate post-deploy error spike, cause unknown | **Rollback** (fast, safest) |
| Security defect introduced by the release | **Rollback** + security incident procedure (`INCIDENT_RUNBOOKS.md` §8/§15) |
| Migration completed but code failing, migration is additive | Fix-forward, restart workers (data untouched) |
| Migration completed but code failing and migration is data-bearing | Rollback **code only**; migration must be shown forward-compatible (repair migration, no destructive back-migration) |
| Post-restore verification failed (runbook 12) | rollback of the restore = re-restore from an earlier dump; two-person rule still applies |

## 2. Backbone mechanics (verified to exist in repo today)

1. **Artifact immutability** — deploy pipeline builds artifacts (`deploy.yml` artifact-producing design per Phase 14 audit G/H); the previous release artifact must always remain retrievable.
2. **Forward-compat migration rule** — enforced since the Phase 14 FORCE-RLS migration notes: migrations must be additive/forward-only; removals land ≥1 phase later. Rollback must never run `migrate:rollback` on published financial/tenant tables.
3. **Worker reload is part of rollback** — old code in memory can rebroke newly migrated DB state only if migrations were destructive; since they are not, reload after restore is safe.

## 3. Rollback procedure (numbered, production runbook)

1. Stop shifting traffic (frontend/backend whichever is applicable); maintenance window if SEV-1.
2. Open incident row (`/platform/monitoring/incidents`), assign owner.
3. Redeploy previous artifact UI backend; verify `$APP_VERSION` matches the last known-good release.
4. Restart queue workers + scheduler so runtime resolves the old schema/code combination.
5. Run post-rollback smoke checks from `CUTOVER_PLAN.md` §Smoke (login, protected read, tenant scoping, queue heartbeat).
6. Record release note + rollback evidence (times, artifact digests, incident id).
7. Post-incident review within 5 business days; feed the defect register if a real defect.

## 4. Database state rollback — special case

Because the migration discipline above is the **only** safe path, "rollback of the database" is limited to:
1. restore drill play (`INCIDENT_RUNBOOKS.md` §12) — isolated scratch first, verification, then promotion; never reset production tables in place;
2. compensating forward migrations (additive fixes) — built by Backend Maintainer, reviewed by DBA.

## 5. Tenant-facing statement template (rollback event)

"Service is temporarily returning to the previous release to address a stability issue. Your data and audit history are unaffected; in-progress operations during the window may need re-submission. Timeline and follow-up will be posted on the status channel."

## 6. Approval authority

- Decision to roll back: Deployment Operator (or Security Owner during P0 containment), informed→Release Owner immediately.
- External comms: Release Owner.
- Evidence recorded in the audit trail (`/audit-logs`) — a rollback executed outside audit visibility is treated as a security process failure itself.
