# Phase 16 — Feature Flag Rollout Governance

Scope: `feature_rollouts` — controlled, audited rollout of features on top of the existing `feature_flags` tables.

## Authority boundary (critical)

Feature flags are **rollout metadata only**. They never act as authorization:

- Flags never bypass RBAC, policies, subscription limits, or tenant/company scoping. Server-side authorization is always evaluated independently of flag state.
- The rollouts API returns an explicit `flag_authority_note` stating this, and the UI renders it on the rollouts page.
- The kill switch disables a flag everywhere; it **never reverses stored business data**. Any data written while a feature was enabled remains; remediation of data is a separate, audited operation.

## Rollout stages

`development → internal_cohort → pilot → wider_rollout → general_availability`, with `disabled` and `rolled_back` as control states.

Stage advancement gates:

- Moving to `pilot`, `wider_rollout` or `general_availability` requires explicit approval (`approval_status=approved` with an approval note). Unapproved advancement returns 422.
- Each rollout carries an `owner`, optional `audience` scope (platform / tenant list / percentage), `rollout_percentage`, `review_at` date and `monitoring_link` for observability during the rollout.

## Kill switch

`POST /platform/improvement/rollouts/{id}/kill-switch` with a mandatory reason:

- Sets stage to `disabled`, records the reason and actor, and appends a rollout event.
- Intended for incident response when a staged feature misbehaves.

## Tenant targeting

`POST /platform/improvement/rollouts/{id}/tenants` manages explicit tenant membership for cohort rollouts. Tenant scoping is enforced server-side; the platform cannot grant a tenant a feature outside its subscription entitlements (subscription limits remain authoritative).

## Sensitive flags

Flags marked `is_sensitive` (e.g. touching security or billing behavior) require the same approval gates and are additionally reviewed in the security improvement cycle (`SECURITY_IMPROVEMENT.md`). Sensitive rollout work is never published on the customer-visible roadmap.

## API surface

`GET/POST /platform/improvement/rollouts`, `PATCH /platform/improvement/rollouts/{id}`, `POST .../kill-switch`, `POST .../tenants`. All audited.

## Verification

Covered by `ImprovementPhase16Test.php`: rollout creation over an existing flag, unapproved stage-advancement rejection, kill switch with reason, and tenant cohort assignment.
