# Phase 16 — Security Improvement Cycle

Scope: how security work flows through the Phase 16 improvement system without leaking sensitive information.

## Confidentiality rule

Security-related work (vulnerability fixes, hardening, dependency updates, incident remediation) is tracked in defects, requests and roadmap items like any other work — **but it is never published**:

- Roadmap items covering security work are created with `visibility=internal`. The business roadmap endpoint excludes them by query.
- Releases containing only security fixes may keep `customer_visible=false` with `audience=platform`.
- Feature flags for security-sensitive behavior are marked `is_sensitive` and follow the same approval gates as any rollout, with additional review in this cycle.

## Intake sources

| Source | Flow |
|---|---|
| Security Center findings (Phase 8) | Audit anomalies and alerts reviewed in the daily critical triage; confirmed issues become P0/P1 defects |
| Monitoring incidents (Phase 7) | Security-classified incidents link to defects via `source_incident_id` |
| Dependency updates | Tracked as requests; currently manual — no automated advisor ingestion is wired (reported as a gap in `PRODUCT_MATURITY_BASELINE.md`) |

## Cadence

- **Daily**: critical triage reviews new P0/P1 defects and open security incidents.
- **Monthly**: the performance/SLO review includes security posture signals from the Security Center.
- **Quarterly**: the roadmap review re-assesses the security dimension of the maturity assessment with recorded evidence.

## Audit trail

Every Phase 16 mutation (feedback transitions, defect closure, rollout changes, release state changes, SLO changes, maturity assessments) is written to the platform audit log via the existing audit service. Security-relevant transitions additionally carry reason strings, so the "why" is preserved alongside the "what".

## Tenant isolation as a security dimension

Tenant isolation is one of the twelve maturity dimensions. Its evidence requirement is satisfied today by the FORCE RLS migration and the isolation regression test suites (`RlsEnforcementTest`, `InventoryIsolationTest`, `ProcurementIsolationTest`) — new Phase 16 tenant-scoped tables ship with the same RLS enforcement.
