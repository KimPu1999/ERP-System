# Phase 16 — Bug Triage Policy

Scope: defect records (`defect_records`) — severity classification, workflow gates, and closure requirements.

## Severity definitions

| Severity | Meaning | Response expectation |
|---|---|---|
| P0 | Data loss, security breach, tenant isolation failure, or full module outage in production | Immediate triage; fix before any other work |
| P1 | Major function broken with a workaround unavailable or unacceptable | Fix in current iteration |
| P2 | Major function broken with acceptable workaround, or minor function broken | Scheduled by priority |
| P3 | Cosmetic, documentation, or minor usability issue | Backlog |

Severity changes require a `severity_change_reason` and are audited (`defect_events`).

## Workflow

`open → assigned → fixed → ready_for_retest → verified → closed`, with `reopen` returning to `open`. Transitions are validated against `DefectRecord::TRANSITIONS`; illegal jumps return 422.

Each defect records environment (`local | staging | production`), reproduction steps, expected vs actual result, and optional error summary. Defects may originate from feedback conversion, monitoring incidents, or support tickets (`source_feedback_id`, `source_incident_id`, `source_ticket_id` preserve the chain).

## Closure gates (non-negotiable)

A defect can only reach `closed` from `verified`, and verification requires:

1. **A recorded retest with result `pass`** (`retest_result` + `retest_verified_at`).
2. **A non-empty `root_cause`.**

Attempting to close an unverified defect, or to verify without a passing retest, returns 422. `fix_version` and `related_change_ref` (PR/commit reference) are recorded when available.

## Reopen policy

A closed defect may be reopened with a mandatory `reopen_reason` (regression or incomplete fix). `reopened_count` is incremented and is one of the inputs to the defect reopen rate in `ENGINEERING_QUALITY_METRICS.md`.

## API surface

`GET/POST /platform/improvement/defects`, `GET/PATCH /platform/improvement/defects/{id}`. Platform-side only, role-authorized and audited.

## Verification

Covered by `ImprovementPhase16Test.php`: creation, illegal-transition rejection, verify-without-pass rejection, close-without-root-cause rejection, and the full open → fixed → retest → verify → close happy path.
