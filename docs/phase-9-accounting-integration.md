# Accounting Phase 5 — ERP Financial Integration

## Audit findings

- `AccountingService` is the existing authoritative journal writer and uses database transactions, open-period checks, decimal balancing and tenant/company account validation.
- Supplier Bills, Supplier Payments, Supplier Credits and selected payment workflows already call the posting service with source references and idempotency keys.
- Inventory and sales fulfillment currently create physical stock movements but do not have complete verified accounting journal integration.
- Payroll has finalized payroll state and immutable records, but no verified payroll-to-ledger posting workflow was present.
- Existing `account_mappings` are company-scoped, but there was no complete mapping management/status workspace.
- Failure counts and subledger reconciliation are not yet backed by a dedicated posting-attempt/outbox table; the UI therefore reports them as not verified rather than inventing values.

## Implemented in this phase

- Additive `accounting_integration_settings` table with automatic posting disabled by default.
- Company-scoped mapping API for sales, purchasing, inventory and payroll account keys.
- Backend validation for mapped account tenant/company scope, active status and posting eligibility.
- Integration readiness API with base currency, required mappings, open-period and detected posted-event checks.
- Accounting Integration page at `/accounting/integration` with mapping controls, readiness cards, module status cards and guarded automatic-posting control.
- Automatic posting can only be enabled when base currency, required mappings and an open period are present, and only through the authorized backend mutation.

## Remaining blockers

- Sales invoice/payment, inventory valuation/COGS, and payroll accrual/payment journals require source-specific services and controlled fixtures before automatic posting can be enabled.
- Reconciliation needs authoritative subledger-to-control-account queries for each module.
- Failed posting retry history requires an outbox/posting-attempt table and queue worker integration.
- PostgreSQL is unavailable in the current environment, so migrations and end-to-end financial tests could not be executed.

No production automatic posting was enabled and no financial records were altered.
