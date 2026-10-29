# Phase 7 — Accounting and Financial Management

Phase 7 adds the tenant/company-aware accounting foundation. Journal posting is explicit and opt-in; no production financial data is migrated or posted.

## Controls

- Journal lines require positive debit or credit values and entries require at least two lines.
- Total debits must equal total credits using BCMath decimal strings.
- Posted journal entries and their lines are immutable at the database trigger layer.
- Corrections use reversal journals linked by `reversal_of_id`.
- Fiscal periods are locked before posting is allowed.
- Account, company, currency, and period ownership is checked server-side.
- Journal idempotency keys prevent duplicate posting under retries/concurrency.
- Accounting tables use PostgreSQL tenant RLS.
- Invoice-issued and payment-verified domain events are emitted for later mapping consumers; this phase does not invent journal mappings.

## Endpoints

- `GET|POST /api/v1/accounting/accounts`
- `GET|POST /api/v1/accounting/periods`
- `POST /api/v1/accounting/periods/{period}/close`
- `POST /api/v1/accounting/journals`
- `POST /api/v1/accounting/journals/{entry}/reverse`
- `GET /api/v1/accounting/reports/trial-balance`
- `GET /api/v1/accounting/reports/financial?type=profit_loss|balance_sheet|cash_flow`
