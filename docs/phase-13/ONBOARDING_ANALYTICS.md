# Phase 13 Growth Analytics — Metric Definitions and Event Taxonomy

Implementation date: 2026-11-04
Backend service: `backend/app/Services/PlatformGrowthService.php`
Controller: `backend/app/Http/Controllers/Api/V1/PlatformGrowthController.php`
Routes: `backend/routes/api.php` (group `api/v1/platform/growth/*`, middleware `auth:sanctum`, `active.user`, `verified`, `platform.admin`)
Frontend page: `apps/web/src/pages/PlatformGrowthCenterPage.tsx` (modes: overview, onboarding, adoption, customer-success, retention)

## Data sources

Growth analytics are computed only from durable backend records:

| Source | Used for |
| --- | --- |
| `tenants` | Registration cohorts, lifecycle state |
| `tenant_memberships` + `users.email_verified_at` | Verified registrations |
| `onboarding_progress` (unique per `tenant_id, step_key`) | Onboarding started/completed, activation rate, time to activation |
| `platform_audit_logs` (immutable) | Approvals, subscription renewals/cancellations/changes, module actions |
| `tenant_subscriptions` | Trial snapshot, renewal-window risk |
| `platform_payments` (status `failed`) | Involuntary payment-failure signals |
| `support_tickets` | Support backlog signals |

Current status is never presented as historical data. Metrics that cannot be computed from the existing schema are reported with `available: false` and a documented reason. Nothing is inferred from snapshots.

## Privacy rules

- Aggregates only: no invoice contents, transaction details, personal documents, credentials, or free-text ticket bodies are exposed to growth endpoints.
- Every business is counted once per metric; setup retries collapse because `onboarding_progress` is unique per `(tenant_id, step_key)` and audit-based counting is deduplicated by tenant / auditable id.
- Reporting timezone is `Asia/Yangon` (same as `PlatformAnalyticsService::REPORTING_TIMEZONE`).

## Onboarding metrics (`/platform/growth/onboarding`)

Cohort: tenants created inside the selected window (`from` inclusive, `to` exclusive, reporting timezone). Filters: `plan_id`, `business_type`, window, granularity.

| Metric | Eligibility / denominator |
| --- | --- |
| `registrations` | All cohort tenants, deduplicated by tenant id |
| `verified_registrations` | Cohort tenants with ≥1 active membership whose user email is verified *as of the request*. Historical verified-at timing is not retained, so this is explicitly not a historical count |
| `approved_businesses` | Distinct cohort tenants with a `platform.tenant.status_changed` audit event. Availability depends on audit coverage; before the first recorded audit event the value is `available: false` with the coverage note |
| `onboarding_started` | Cohort tenants with ≥1 `onboarding_progress` row. Rows exist only after a verified step completion, so retries never double-count |
| `onboarding_completed` | Cohort tenants with a completed `workspace_readiness` row (written only by `OnboardingProgressService` after authoritative backend checks) |
| `activation_rate_percent` | `onboarding_completed / registrations × 100`. `null` when the cohort is empty |
| `required_setup_completion_rate` | Mean completed-required-step ratio across cohort businesses holding ≥1 progress row; `coverage` shows what fraction of the cohort the rate describes. Required step keys mirror `OnboardingProgressService` (9 steps incl. `workspace_readiness`) |
| `abandoned_or_stalled` | Cohort tenants with no completed `workspace_readiness` and no progress activity within `ABANDONMENT_STALL_DAYS = 14` days, counted once per tenant |
| `time_to_activation` | Days between tenant creation and `workspace_readiness.completed_at` for recorded completions only; reports sample size, and `available: false` when the sample is empty |

## Module adoption (`/platform/growth/adoption`)

- `enabled_modules` is a configuration count (`tenant_modules.enabled`). It is **never** presented as usage.
- `recorded_module_actions` counts distinct businesses with successful tenant-scoped mutations recorded in the immutable platform audit log in the window, matched by the documented action-prefix taxonomy below. Audit metadata is redacted upstream (`SensitiveDataRedactor`).
- `module_opened_events` reports `available: false` — no view/open instrumentation exists yet, and enabling or acting in a module is never treated as a page open.

Action-prefix taxonomy (`ACTION_TAXONOMY` in `PlatformGrowthService`):

| Module key | Audit action prefixes |
| --- | --- |
| `catalog` | `product_`, `unit_`, `price_list` |
| `inventory` | `inventory.`, `warehouse`, `stock_` |
| `purchasing` | `purchase_`, `goods_receipt`, `supplier_` |
| `sales` | `sales.`, `quotation`, `fulfillment` |
| `accounting` | `journal_`, `fiscal`, `account_`, `ledger`, `financial_report` |
| `payroll` | `payroll`, `employee_`, `attendance` |
| `crm` | `customer`, `lead`, `opportunity`, `campaign` |

## Customer success (`/platform/growth/customer-success`)

Attention queue: businesses without a completed `workspace_readiness` row (optionally filtered by status / plan / search). Row fields are facts only: lifecycle status, verified onboarding completion, open support tickets (`open`, `in_progress`, `waiting_customer`, `waiting_support`), current subscription state (with grace-state billing follow-up flag), and the latest recorded audit activity timestamp. Missing values render as null / `—`.

There is intentionally **no** health score, no renewal prediction, and no automatic restriction — the endpoint returns `signals_documentation` explaining this.

## Retention & churn (`/platform/growth/retention`)

| Signal | Definition |
| --- | --- |
| `renewals_in_window` | Distinct businesses with a `platform.tenant.subscription_renewed` audit event |
| `cancellations_in_window` | Distinct businesses with a `platform.tenant.subscription_canceled` audit event |
| `plan_or_cycle_changes_in_window` | Distinct businesses with a `platform.tenant.subscription_changed` audit event |
| `involuntary_payment_failures` | Distinct businesses with a failed `platform_payments` record; never merged into voluntary churn |
| `customer_churn_rate_percent` | `cancellations / (cancellations + renewals) × 100`, only when both outcomes exist; otherwise `available: false` with a reason (no fabricated 0%) |
| `cohorts_current_state` | Registration-month cohorts with the share whose *current* status is active — explicitly labeled a current-state snapshot, not a retention curve |

Unavailable by design (reason returned in payload):

- `trial_conversion` — the schema stores current subscription state plus selected lifecycle audit events, not a complete immutable state-transition ledger.
- `revenue_retention` — no historical recurring-value ledger exists; current-state MRR snapshots cannot be used to fabricate month-over-month retention.
- Revenue churn, cancellation reasons, reactivation counts — require the same missing event history.

## Reasons a metric can be unavailable

1. `No registered businesses in the selected cohort.` — empty cohort.
2. `No onboarding progress rows exist in this cohort yet…` — no authoritative step data.
3. Audit coverage note — approval/counts rely on immutable audit events whose history starts with the first recorded event.
4. `No confirmed churn or renewal events in the window yet…` — absence of events, reported as null instead of 0%.
5. Transition-ledger note — trial conversion requires history the schema does not store.

## Frontend

`PlatformGrowthCenterPage` renders every unavailable signal as `—` plus the localized `growth.unavailableValue` reason. Definitions are rendered from the payload so the UI can never contradict the backend, and English/Burmese translations live in `apps/web/src/lib/i18n.tsx` (`growthEnglish`, `growthBurmese`).
