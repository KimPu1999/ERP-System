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

### P1 — Growth analytics are operational, not Phase 13 growth analytics (addressed 2026-11-04)

Platform analytics covers businesses, users, subscriptions, revenue, usage, exports, and dashboard growth. Dedicated onboarding funnel, adoption taxonomy, customer-success, retention, cohort, and churn endpoints were absent. Historical lifecycle events must not be inferred from current status. **Status: the dedicated growth vertical is now implemented — see the "Growth analytics vertical" section below.**

Impact: the requested growth metrics cannot be produced reliably until event definitions and durable event sources are added.

### P1 — Tenant isolation foundations exist but require endpoint-by-endpoint coverage

`ResolveTenant` validates the `X-Tenant-Id` header against active membership and tenant status, and RLS migrations/jobs exist. Sensitive controllers still need a systematic audit for resource ownership, company/branch scope, exports, cache keys, and queued tenant context. Platform access must not be treated as implicit access to private tenant accounting data.

### P1 — Scaling evidence is incomplete

Platform monitoring and capacity documentation exist, but this audit found no reproducible Phase 13 performance baseline artifact covering API percentiles, slow queries, queue latency, connection saturation, and large-report duration. No infrastructure expansion is justified from the current repository evidence.

## Missing or incomplete Phase 13 artifacts

- ~~Durable, privacy-conscious usage event taxonomy for meaningful module actions.~~ **Done (2026-11-04)** — documented `ACTION_TAXONOMY` over immutable audit events; module-open instrumentation does not exist yet and is reported as unavailable rather than inferred.
- ~~Onboarding/adoption/customer-success/retention analytics contracts.~~ **Done (2026-11-04)** — `PlatformGrowthService` + controller + `/platform/growth/*` routes and UI.
- Configurable retention policy for new analytics/temporary data. (still open)
- Staging performance baseline and load-test results. (still open)
- Phase 13 security/concurrency regression coverage for the remaining tenants-isolation matrix. (partially covered; see remaining gaps)

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

## Growth analytics vertical (implemented 2026-11-04)

The dedicated Phase 13 growth analytics surfaces are implemented as facts-only read models over durable backend records. See `docs/phase-13/ONBOARDING_ANALYTICS.md` for full metric definitions, eligibility rules, the audit-action taxonomy, and the list of unavailable-by-design signals.

- Backend service: `PlatformGrowthService` (overview, onboarding, adoption, customer-success, retention) using only `tenants`, `tenant_memberships` / verified emails, `onboarding_progress`, immutable `platform_audit_logs` events, `tenant_subscriptions`, failed `platform_payments`, and `support_tickets`. No snapshot is presented as history and no percentage is fabricated (empty cohorts return `null` / `available: false` with reasons).
- API: `PlatformGrowthController` with server-side filter validation (period window, granularity enum, `plan_id` UUID, pagination caps) and routes `GET /api/v1/platform/growth/{overview,onboarding,adoption,customer-success,retention}` behind the platform admin middleware group.
- Frontend: `PlatformGrowthCenterPage` with routes `/platform/growth/*`, sidebar entries, typed API helpers, and English/Burmese strings. Unavailable signals render as `—` plus a localized reason; definitions render from the backend payload.
- Customer-success attention queue and onboarding completion come only from authoritative backend data; module "enabled" configuration is never counted as module usage, and setup retries are deduplicated by the unique `(tenant_id, step_key)` progress row.

## Regression repairs performed during Phase 13

The audit baseline initially showed 5 failed / 206 passed tests. Root causes were repaired before feature work:

1. Commit `3e51548` had deleted the MFA routes (`/mfa/verify`, `/auth/mfa/enroll|confirm|recovery-codes|disable`), the `mfa.completed` middleware alias, and MFA policy enforcement from `AuthController::authenticate()` and `RequirePlatformSuperAdmin`. These were restored from commit `18a413c`, including the `202 mfa_required` / `mfa_enrollment_required` login responses and SecurityPolicy-gated MFA disable.
2. `config/security.php` fails closed: `platform_mfa_required` is env-locked via `PLATFORM_MFA_REQUIRED` and can only be disabled in a debug-enabled `local`/`testing` process with `PLATFORM_MFA_REQUIRED=false`. The initial repair left `APP_ENV=testing` out of the honored list, which caused the fail-closed policy to reject every platform login in the test suite (54 failures across platform/authorization tests). The honored environments now include `testing` (phpunit.xml.dist forces `PLATFORM_MFA_REQUIRED=false`); production and staging remain fail-closed.
3. `PlatformAuditController::settings` now reports the immutable env-backed platform MFA policy: weakening attempts are rejected (422) and require step-up authentication.
4. The local `backend/.env` contained a duplicate `MAIL_MAILER` (log, then smtp) which broke delivery-state assertions; the duplicate was removed and `phpunit.xml.dist` forces `MAIL_MAILER=log` so test mail state stays deterministic.

## Blockers and verification limits

- The test environment requires an approved local-database connection for PostgreSQL-backed Laravel tests; the full suite was executed successfully with that access.
- No staging monitoring or load-test source was provided; performance claims must remain unverified.
- The existing repository contains generated/log/cache artifacts; these were not treated as implementation evidence.

## Suggested sequence

Onboarding vertical slice → self-service read models → entitlement/concurrency audit → tenant isolation test matrix → durable analytics events → growth dashboards → staging performance baseline → retention and rollout documentation.

Verification result (2026-11-04, full backend suite): `php artisan test --compact` passed with **220 tests and 1064 assertions, 0 failures**, including the dedicated `PlatformGrowthPhase13Test` (9 tests: cohort retry dedup, onboarding funnel, non-platform 403 rejection, server-side filter validation, module-enabled ≠ usage, facts-only customer-attention rows, churn without fabricated percentages, cohort labeling, and window-boundary isolation) and the updated `PlatformSecurityPhaseTest` (18 tests covering the fail-closed MFA policy). Frontend: TypeScript compilation passes, ESLint passes on all changed files, Vitest passes 4 tests, and the production build succeeds. `npm run i18n:check` exits 1 only from unresolved keys that already existed at `HEAD` (e.g. `business.*`, `security.mfa*`, `platform.queue`, `common.saving`, `ops.failed`) — all `growth.*` keys resolve in both languages. Local additive migrations `000015` and `000016` are applied; the growth vertical adds no new migrations because it reads existing durable tables.
