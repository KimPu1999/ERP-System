# Phase 16 — Feature Request Policy

Scope: how feature requests (`feature_requests`) are triaged, estimated, prioritized and linked to the roadmap and releases.

## Intake

Requests enter the system through two audited paths:

1. Direct platform creation (`POST /platform/improvement/requests`) with title, description, module, priority and optional business impact / requester reference.
2. Conversion from customer feedback (`POST /platform/improvement/feedback/{id}/convert` with `type=feature_request`), which records the originating feedback on the request (`source_feedback`).

Each request gets a human-readable `request_key` (e.g. `FR-0001`) generated server-side.

## Status lifecycle

`new → triaged → in_development → in_testing → released`, with `deferred` and `rejected` as terminal/side states. Transitions are validated against `FeatureRequest::TRANSITIONS`; illegal jumps return 422 and every change appends to `feature_request_events` with actor and payload.

## Effort estimation policy

`effort_estimate` is nullable and is treated as **declared data, never derived data**:

- The field is only set when a human explicitly provides it (at creation with `effort_estimated`, or later via update).
- The UI renders `not_estimated` when no estimate exists. No automatic story-point, t-shirt-size or time inference is performed anywhere in the codebase.
- Changing the estimate appends an audited event.

## Priority changes

Priority (`critical | high | medium | low`) can be changed at any non-terminal status. A priority change without a `priority_change_reason` is rejected — the reason is stored on the event so prioritization decisions remain reviewable.

## Linkage

- `target_release_id` may reference a release record; `release_evidence` is populated when the request reaches `released` through a deployed release.
- Roadmap items may be created from requests (`source_type=feature_request`, `source_id`).
- Defects and requests are intentionally separate workflows: a request describes desired capability, a defect describes deviation from specified behavior (see `BUG_TRIAGE_POLICY.md`).

## API surface

`GET/POST /platform/improvement/requests`, `GET/PATCH /platform/improvement/requests/{id}`. All platform-side, role-authorized and audited. There is deliberately **no business-facing request endpoint** — customers express demand through feedback; platform staff decide what becomes a request.

## Verification

Covered by `ImprovementPhase16Test.php`: creation with and without effort estimate, illegal transition rejection, priority-change reason enforcement, and conversion from feedback.
