# Phase 11 — SaaS Subscriptions and Platform Control

Phase 11 adds a platform control-plane billing boundary. Platform invoices and provider payments are separate from tenant accounting journals.

## Billing controls

- Tenants receive a configurable free-trial subscription during onboarding.
- Plans expose feature/entitlement values such as seats, companies, branches, and storage.
- Company and branch creation checks entitlement limits before writes.
- Plan changes use row locks to avoid concurrent subscription state errors.
- Trial expiry transitions to `past_due` with a configurable grace period.
- Cancellation changes subscription state and never deletes tenant data.
- Provider webhooks require HMAC signature verification and are idempotent by provider event ID.
- Provider configuration is deployment-controlled through environment variables.
- Card details are never stored; only provider identifiers and verified payment metadata are retained.

## Support and data access

- Support access is represented by time-bounded, reasoned grants.
- Grant creation and approval are audited.
- Tenant exports are queued, private, authenticated, and ownership-checked.

## API

- Platform: `/api/v1/platform/billing/plans`, `/subscriptions`, `/revenue`
- Webhook: `POST /api/v1/billing/webhooks/{provider}`
- Tenant subscription: `/api/v1/subscription`
- Support: `/api/v1/support/tickets`, `/api/v1/platform/support/access-grants`
- Tenant export: `/api/v1/tenant-exports`

Set `BILLING_PROVIDER`, `BILLING_TRIAL_DAYS`, `BILLING_GRACE_DAYS`, and provider webhook secrets in deployment configuration. Do not commit secrets.
