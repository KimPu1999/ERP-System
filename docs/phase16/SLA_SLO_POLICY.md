# Phase 16 — SLA / SLO Policy

Scope: service level objectives (`service_level_objectives`) measured from Phase 7 monitoring observations, and service level agreements tracked on support tickets (Phase 12).

## SLO model

Each SLO defines:

- `name`, `sli_type` (`availability | success_ratio | latency | queue_completion`),
- `metric_source` — the monitoring observable it is computed from,
- `target` (e.g. `99.9` percent),
- `window_days` (rolling measurement window),
- optional approval (approver + timestamp) before it is treated as authoritative.

## Measurement model (implemented, not aspirational)

Given the observations recorded by Phase 7 monitoring for the SLO's metric source within the window:

- **Observed level** = percentage of observations with success statuses (e.g. `ok`/`healthy`) over all observations in the window.
- **Error budget** = `(1 − target/100) × window_seconds` — the total down-seconds the objective tolerates.
- **Budget used** = down-observations × median observation interval. When the observation interval cannot be established, budget usage is reported as `unknown` rather than guessed.
- **Status**: `meeting` (observed ≥ target), `at_risk` (within 0.5 percentage points), `violated` (below), or `unknown` (fewer observations than the minimum sample or no data).

`GET /platform/improvement/reliability` returns each SLO with its observed value, sample count, budget usage, and a `measurement_model` block documenting these formulas. **No uptime percentage is ever fabricated**: with zero observations the observed value is `null` and the status is `unknown`.

## Relationship to SLAs

Support-ticket SLAs (first response / resolution minutes per priority, Phase 12) are contractual per-tenant commitments; SLOs are internal engineering targets for platform services. They are reported separately and never mixed into a single "uptime" number.

## Change control

Creating or updating an SLO is a platform-side, audited operation (`POST/PATCH /platform/improvement/reliability/slos`). Changing a target or window mid-window is recorded as an event so historical reporting remains interpretable.

## Verification

Covered by `ImprovementPhase16Test.php`: SLO creation, evaluation from seeded observations (meeting/violated states), unknown state with no observations, and error-budget computation.
