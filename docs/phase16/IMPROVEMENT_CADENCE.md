# Phase 16 — Improvement Review Cadence

Scope: `improvement_reviews` — the recorded meeting/review rhythm that turns improvement data into decisions.

## Cadences

| Cadence | Focus | Typical inputs |
|---|---|---|
| Daily | Critical triage | New P0/P1 defects, open security incidents, kill-switch events |
| Weekly | Feedback triage | New feedback, duplicate candidates, conversion decisions |
| Biweekly | Iteration review | Feature request progress, roadmap status, deployed releases |
| Monthly | Performance / SLO review | SLO status, error budgets, engineering quality metrics, monitoring alerts |
| Quarterly | Roadmap review | Roadmap priorities, maturity assessment per dimension, adoption questions |

## Recording discipline

A review record (`POST /platform/improvement/reviews`) captures: cadence, title, review date, findings, decisions, next action, owner and priority. Findings and decisions are free text but **expected to reference real records** (feedback numbers, defect numbers, SLO names) — the review is the decision log that links the data in this phase to actual changes.

Reviews are platform-side, role-authorized and audited. There is no business-facing review endpoint.

## What the cadence is not

- It is not a status-report generator: reviews exist only when held and recorded; the reviews list shows real records or an empty table.
- It does not auto-create action items — the `next_action` field is human-authored.

## Verification

Covered by `ImprovementPhase16Test.php`: review creation with cadence validation and listing with cadence filtering.
