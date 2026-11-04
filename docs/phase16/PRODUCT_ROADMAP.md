# Phase 16 — Product Roadmap Policy

Scope: `roadmap_items` — the internal product roadmap and its customer-visible subset.

## Visibility model

Every roadmap item has a `visibility` of:

- `internal` — visible only on platform endpoints. Used for confidential work, including all security-related items.
- `customer_visible` — additionally requires a non-empty `customer_summary`. The public endpoint returns the **summary only**, never the internal description, dependencies, completion criteria or owner.

The business endpoint `GET /business/roadmap` returns only items with `visibility=customer_visible` **and** an approved/active status. Internal targets, security work and unpublished items are excluded by query, not by post-filtering.

## Status lifecycle

`backlog → in_progress → ready_for_release → released` (plus deferred). The `released` transition is gated: it requires a linked release record that is actually `deployed` (deployment evidence present). Marking an item released without deployed-release evidence returns 422.

## Creation rules

`POST /platform/improvement/roadmap` requires title and priority; `customer_summary` is mandatory when visibility is `customer_visible`. Items may be created from feature requests (source linkage) or standalone.

## What the roadmap is not

- It is not a delivery promise: `target_quarter` is informational and never shown to customers as a commitment date.
- It is not a substitute for release notes: shipped work is communicated through `RELEASE_MANAGEMENT.md`, which carries per-version notes in English and Burmese.

## API surface

Platform: `GET/POST /platform/improvement/roadmap`, `PATCH /platform/improvement/roadmap/{id}`.
Business: `GET /business/roadmap` (approved, customer-visible subset only).

## Verification

Covered by `ImprovementPhase16Test.php`: customer-summary requirement for public visibility, exclusion of internal items from the business endpoint, and the release-evidence gate on the `released` transition.
