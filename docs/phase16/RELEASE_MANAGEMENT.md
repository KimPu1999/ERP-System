# Phase 16 — Release Management

Scope: the release lifecycle built on top of the Phase 12 `release_records` table, extended additively in Phase 16.

## Lifecycle

`draft → ready_for_qa → qa_passed → approved → deploying → deployed`, with `failed`, `rolled_back` and `aborted` as terminal/side states. Transitions are validated against `ReleaseRecord::TRANSITIONS`; illegal jumps return 422.

## Deployment evidence gate

A release is **never marked `deployed` without an actual deployment reference**. `PATCH /platform/improvement/releases/{id}` with `status=deployed` requires a non-empty `deployment_ref`; otherwise 422. On success, `deployed_at` is stamped server-side.

The `deployed_at` column was made nullable by the Phase 16 migration precisely so that "not yet deployed" is representable — a release in `draft`/`qa_passed` states has no deployment timestamp, and none is fabricated.

## Publishing rules

A release becomes customer-visible (`customer_visible=true`) only when:

- English `release_notes` are present, and
- `audience` is `business` or `both`.

Publishing without notes returns 422. Burmese notes (`release_notes_my`) are optional but encouraged; the business UI renders them when present. `known_issues` are recorded honestly — a release note that hides known limitations is treated as a defect of the release process itself.

Structured sections (`new_features`, `bug_fixes`, `required_user_actions`) may be supplied as `release_notes_sections`.

## Business-facing surface

`GET /business/releases` returns only published, customer-visible releases with their notes and known limitations. Draft, failed, internal-only and platform-audience releases are excluded by query.

## Linkage

- `linked_features` / `linked_fixes` reference feature request keys and defect numbers shipped in the release.
- Roadmap items transition to `released` only via a deployed release (see `PRODUCT_ROADMAP.md`).
- The improvement overview shows the current release from the most recent `deployed` record — or states that none exists.

## API surface

Platform: `GET /platform/improvement/releases`, `GET/PATCH /platform/improvement/releases/{id}`.
Business: `GET /business/releases`.

## Verification

Covered by `ImprovementPhase16Test.php`: lifecycle transitions, deploy-without-evidence rejection, publish-without-notes rejection, and the business endpoint returning only published releases.
