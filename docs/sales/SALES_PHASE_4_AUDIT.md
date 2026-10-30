# Sales Phase 4 — Quotation Audit and Implementation

## Existing audit

The existing quotation implementation supported only tenant-scoped draft creation, basic percentage line discounts/taxes, a simple commercial transition, and draft Sales Order conversion. It did not provide company/branch scope, separated approval status, price lists, version snapshots, response history, or quotation summary metrics.

## Implemented

- Company/branch-aware quotation register and summary endpoint.
- Server-generated quotation numbers and backend decimal totals.
- Tax-code lookup through existing company tax configuration.
- Percentage and fixed line discounts with negative-total protection.
- Price-list entities and read API for future configured pricing rules.
- Quotation versions, approval records, response records and communication schema.
- Draft, pending approval, approved, sent, accepted, declined, expired and cancelled controls.
- Accepted quotation to idempotent draft Sales Order conversion with no inventory/accounting activity.
- Secure quotation detail and printable HTML document endpoint.
- Sales navigation and quotation register/draft UI.

## Deliberate boundaries

Queued customer email, external acceptance tokens, PDF binary generation, attachment uploads, configurable approval thresholds and full price-list administration remain follow-up work because the repository does not contain a compatible mail/PDF/pricing implementation. The printable endpoint reuses the existing server-rendered document approach and does not claim email delivery.

## Verification

- PHP syntax and quotation route registration: passed.
- Frontend TypeScript, ESLint and production build: passed.
- Database-backed quotation tests/migration execution: not executed in this environment because PostgreSQL was unavailable/restricted at `127.0.0.1:5432`.
