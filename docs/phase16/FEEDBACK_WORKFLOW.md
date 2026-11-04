# Phase 16 — Customer Feedback Workflow

Scope: platform-side handling of product feedback submitted by tenant users (`product_feedback`), including triage, internal/public notes, duplicate handling, conversion, and satisfaction collection.

## Sources of feedback

| Source | Mechanism |
|---|---|
| Business self-service form | `POST /api/v1/business/feedback` (authenticated tenant user, throttled 15/min, optional attachment ≤ 5 MB with mime allow-list) |
| Support ticket linkage | Optional `support_ticket_id` on a feedback record; platform agents can link/unlink from the feedback detail view |

Feedback is always tenant-scoped. The tenant relationship is set server-side from the authenticated context — never from client input.

## Status lifecycle

`submitted → under_review → accepted → planned → resolved → closed`, with `needs_information` and `declined` as side states.

Transitions are enforced server-side against `ProductFeedback::TRANSITIONS`. Invalid jumps return HTTP 422. Every transition is written to `feedback_status_history` with the actor, previous status, new status and an optional reason.

Rules enforced in `PlatformImprovementController`:

- `resolved` requires a non-empty `resolution_outcome`.
- `closed` is only reachable from `resolved` or `declined`.
- A closed record cannot be reopened through the transition API (a new feedback entry must be submitted).

## Duplicate handling

Duplicates are **closed with an audit trail, never deleted or hard-blocked**:

- On submission, a `content_hash` (SHA-256 of tenant + category + normalized subject) is stored.
- The platform detail view lists possible duplicates (same hash, still open).
- An agent marks a duplicate via `PATCH /feedback/{id}` with `duplicate_of_id`; self-duplication is rejected.
- The duplicate is transitioned to `closed` with the reason recorded as `duplicate of <number>`.

## Notes and privacy

`feedback_notes` carry a `visibility` of `internal` or `public`:

- `internal` notes are visible only on platform endpoints.
- `public` notes are returned to the business on `GET /business/feedback/{id}`.
- The business detail response never includes internal notes, `content_hash`, duplicate candidates, or assignee internals.

## Conversion

`POST /platform/improvement/feedback/{id}/convert` converts an eligible feedback entry (not already converted, not closed as duplicate) into either a feature request or a defect record. The conversion writes `converted_request_id` / `converted_defect_id` on the feedback and an event on the target record, preserving the traceability chain feedback → request/defect → roadmap item → release.

## Satisfaction collection

After a feedback entry reaches `resolved` (or `closed` from `resolved`), the submitting tenant user may submit exactly one satisfaction rating (`POST /business/feedback/{id}/satisfaction`, score 1–5, throttled 10/min). The endpoint rejects:

- rating before resolution,
- a second rating from the same user for the same feedback.

Responses are stored in `satisfaction_responses` with tenant, user, score, optional comment, and the feedback context. See `CUSTOMER_SATISFACTION.md` for the measurement policy.

## API surface

Platform (role-authorized, audited): `GET/PATCH /platform/improvement/feedback`, `GET /platform/improvement/feedback/{id}`, `POST .../transition`, `POST .../convert`, `POST .../notes`.
Business: `GET/POST /business/feedback`, `GET /business/feedback/{id}`, `POST .../comments`, `POST .../satisfaction`.

## Verification

Covered by `backend/tests/Feature/ImprovementPhase16Test.php`: submission, tenant scoping, transition validation (422 on illegal jump), duplicate closure with audit trail, note visibility, conversion, and satisfaction gating.
