# Phase 16 — Enterprise Maturity Assessment

Scope: `maturity_assessments` — dimension-level maturity assessment with mandatory evidence.

## Rubric

Five levels: `not_assessed`, `initial`, `developing`, `managed`, `optimized`.

## Dimensions (12)

1. Functional reliability
2. Security
3. Data integrity
4. Tenant isolation
5. Test coverage
6. Accessibility
7. Localization
8. Documentation
9. Operational readiness
10. Performance
11. Release quality
12. Customer support

## Assessment rules

- Each assessment records `dimension`, `period` (e.g. `2026-Q4`), `level`, **evidence (mandatory, minimum 20 characters)**, optional `gaps` and `improvement_actions`, and the reviewer.
- Evidence shorter than 20 characters is rejected server-side (422) and blocked in the UI. An assessment without evidence is not an assessment — it is an opinion.
- Re-assessing the same dimension for the same period supersedes the previous record; history is retained in the `history` payload.

## No aggregate score

**No overall maturity score is computed or published.** Averaging across dimensions would imply a precision the rubric does not have and would invite gaming. The API returns per-dimension levels, the rubric, and the list of dimensions not yet assessed (`missing_dimensions`). The UI shows "Not yet assessed" dimensions explicitly.

## Current state (from the baseline audit, 2026-11-04)

The pre-Phase-16 assessment in `PRODUCT_MATURITY_BASELINE.md` rates the platform between **Initial** (reliability, performance, release management) and **Managed** (tenant isolation), with accessibility **Not Assessed**. Phase 16 itself moves documentation and release management up; the next assessments must be recorded through this system with evidence, not carried over from the audit prose.

## API surface

`GET /platform/improvement/maturity`, `POST /platform/improvement/maturity`. Platform-side, role-authorized, audited.

## Verification

Covered by `ImprovementPhase16Test.php`: assessment creation, evidence-length rejection, missing-dimension reporting, and history retention.
