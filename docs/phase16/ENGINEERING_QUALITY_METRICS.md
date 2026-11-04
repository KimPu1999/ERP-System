# Phase 16 — Engineering Quality Metrics

Scope: how engineering quality is measured, and — equally important — which metrics are **not yet measurable** and are therefore reported as `unknown` / `not_configured` rather than estimated.

## Metrics and their current data sources

| Metric | Definition | Status in this codebase |
|---|---|---|
| Defect reopen rate | Reopened defects ÷ closed defects (from `defect_records.reopened_count`) | **Measured** — computed from real defect records |
| Defect escape rate | Defects found in production ÷ total closed defects (by environment) | **Measured** — computed from real defect records |
| Deployment frequency | Deployed releases per period (from `release_records` with deployment evidence) | **Measured** — computed from real release records |
| Change fail rate | Releases in `failed`/`rolled_back` ÷ releases that reached `deploying` | **Measured** — computed from real release records |
| Change lead time | Requires commit/PR timestamp ingestion from the VCS/CI system | **Not configured** — no CI ingestion is wired; reported as unknown |
| Recovery time (failed deployment → restoration) | Requires deployment-event timestamps from CI/CD | **Not configured** — reported as unknown |
| Automated test failure trends | Requires CI test-result ingestion | **Not configured** — reported as unknown; the local suite result (237 passing) is a point-in-time fact, not a trend |

`GET /platform/improvement/quality` returns each metric with an explicit `state` (`measured` or `not_configured`) and, when `not_configured`, the reason. The UI renders "Not configured" — it never shows 0% or a fabricated number for missing data.

## Explicit non-metrics

- **Git commit counts are never used as a productivity metric**, for individuals or teams. The API and UI both state this.
- No per-developer defect attribution is recorded or displayed. Defect records track the defect, not the person.

## Improvement loop

Metrics feed the monthly performance/SLO review and the biweekly iteration review (`IMPROVEMENT_CADENCE.md`). When CI/CD ingestion is added later, it must populate these endpoints from pipeline events — not from manual entry — before any `not_configured` state is flipped to `measured`.

## Verification

Covered by `ImprovementPhase16Test.php`: the quality endpoint reports measured rates from seeded defects/releases and returns `not_configured` (not zero) for metrics without data sources.
