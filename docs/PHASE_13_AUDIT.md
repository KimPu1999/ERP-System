# ERP Cloud Phase 13 Audit

Audit date: 2026-11-03

## Scope

This audit covers the existing repository before Phase 13 changes. It prioritizes customer onboarding, self-service access, subscription limits, and tenant isolation. No production infrastructure or destructive database operation was used.

## Verified existing functionality

- Authentication, email verification, MFA enrollment, platform/business login separation, and Sanctum session authentication exist.
- Tenant creation is transactional and idempotent for an already active owner membership in `TenantOnboardingController`.
- Tenant membership, company membership, branch policies, tenant context middleware, PostgreSQL RLS migrations, and tenant-context queue middleware exist.
- Companies, branches, staff invitations, roles, support tickets, subscription plans, tenant subscriptions, invoices, payments, platform analytics, and platform monitoring have existing models/controllers/routes.
- The React application already has workspace access, company setup, organization, support, platform billing, analytics, and monitoring pages.
- English/Burmese translations and light/dark UI styling are present.

## Priority findings

### P0 — Customer onboarding is only a workspace bootstrap

The current business flow is `/workspace-access` plus `/company`. It creates or links a tenant and shows a three-state workspace overview, but there is no authoritative multi-step onboarding progress service, persisted step history, prerequisite validation, resumable `/business/getting-started` route, or server-side completion API.

Impact: businesses cannot see verified readiness across company, accounting, catalog, staff, and enabled modules; frontend state could not safely represent completion.

### P0 — Business self-service portal is incomplete

The current API exposes tenant context, company/organization, support, subscription, and invitations through existing routes, but there is no coherent `/business/account`, `/business/settings`, `/business/subscription`, `/business/billing`, `/business/team`, or `/business/usage` surface and no single tenant-scoped portal contract.

Impact: functionality exists in separate screens but is difficult to discover and lacks a consistent self-service boundary.

### P0 — Entitlement enforcement is not centralized for all requested limits

`SubscriptionService::limit()` exists and is used in selected controllers, but the audit must verify every company, branch, warehouse, active-user, and module mutation. The method accepts a caller-supplied current count and does not itself lock the tenant/subscription while reading usage, so concurrent requests can overrun a limit unless callers provide a transaction/lock.

Impact: plan limits may be inconsistently enforced and are vulnerable to race conditions.

### P1 — Growth analytics are operational, not Phase 13 growth analytics

Platform analytics currently covers businesses, users, subscriptions, revenue, usage, exports, and dashboard growth. Dedicated onboarding funnel, adoption taxonomy, customer-success, retention, cohort, and churn endpoints are absent. Historical lifecycle events must not be inferred from current status.

Impact: the requested growth metrics cannot be produced reliably until event definitions and durable event sources are added.

### P1 — Tenant isolation foundations exist but require endpoint-by-endpoint coverage

`ResolveTenant` validates the `X-Tenant-Id` header against active membership and tenant status, and RLS migrations/jobs exist. Sensitive controllers still need a systematic audit for resource ownership, company/branch scope, exports, cache keys, and queued tenant context. Platform access must not be treated as implicit access to private tenant accounting data.

### P1 — Scaling evidence is incomplete

Platform monitoring and capacity documentation exist, but this audit found no reproducible Phase 13 performance baseline artifact covering API percentiles, slow queries, queue latency, connection saturation, and large-report duration. No infrastructure expansion is justified from the current repository evidence.

## Missing or incomplete Phase 13 artifacts

- Durable, privacy-conscious usage event taxonomy for meaningful module actions.
- Onboarding/adoption/customer-success/retention analytics contracts.
- Configurable retention policy for new analytics/temporary data.
- Staging performance baseline and load-test results.
- Phase 13 security/concurrency regression coverage.

## First implementation slice

1. Add an authoritative tenant-scoped onboarding progress service and additive persistence model.
2. Expose `GET /api/v1/business/onboarding` and idempotent step completion with policy and membership checks.
3. Add the `/business/getting-started` page using live readiness data, required/optional states, resume behavior, and EN/MM labels.
4. Add feature tests for prerequisites, idempotency, progress calculation, spoof prevention, and cross-tenant denial.
5. Audit and repair entitlement call sites before adding new growth analytics.

Implementation status: the additive `onboarding_progress` table, server-side `OnboardingProgressService`, tenant-scoped API, React getting-started page with resumable action links, frontend API contract, and onboarding isolation/idempotency tests have been added. Completion records now retain the authoritative source type/id and active-product readiness is recalculated from current data. A tenant-scoped self-service read model is available through `/business/self-service`, with account update, billing invoice/payment views, invoice detail, and permission-checked team listing endpoints. PostgreSQL-backed feature tests now run successfully with the local database.

Subscription limit hardening: company and branch creation now use `SubscriptionService::withinLimit()` to lock the tenant subscription and evaluate the usage count in the same transaction as the insert. Warehouse creation, invitation acceptance, and optional module enabling use the same enforcement path. Legacy tenants without a provisioned subscription retain existing operations until billing provisioning is completed; provisioned subscriptions are still quota-checked atomically.

Additional verified call-site repairs: warehouse creation now uses the same locked limit transaction for the `warehouses` feature; invitation acceptance uses the locked `active_users` limit while creating the tenant/company membership; optional module enabling checks the matching plan feature when a subscription exists. Legacy tenants without a provisioned subscription retain the existing platform-module management behavior until billing provisioning is completed.

Security verification: `php artisan route:list --path=api/v1/business -v` confirms all onboarding/self-service routes use Sanctum authentication, active-user enforcement, email verification, and `ResolveTenant`; mutating account and step-completion routes are throttled. Completion writes a `business.onboarding.step_completed` audit event with only the step key and schema version. Phase 13 onboarding progress and platform invoice items now also have PostgreSQL tenant RLS policies.

## Blockers and verification limits

- The test environment requires an approved local-database connection for PostgreSQL-backed Laravel tests; the full suite was executed successfully with that access.
- No staging monitoring or load-test source was provided; performance claims must remain unverified.
- The existing repository contains generated/log/cache artifacts; these were not treated as implementation evidence.

## Suggested sequence

Onboarding vertical slice → self-service read models → entitlement/concurrency audit → tenant isolation test matrix → durable analytics events → growth dashboards → staging performance baseline → retention and rollout documentation.

Verification result: `php artisan test --compact` passed with 201 tests and 968 assertions. The focused onboarding/self-service/RLS checks pass with 7 tests and 34 assertions; frontend Vitest passes 4 tests, TypeScript compilation passes, and the production frontend build passes with existing large ECharts/index chunk warnings. Local additive migrations `000015` and `000016` are applied.
