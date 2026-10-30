# Accounting Phase 4 — Financial Statements and Reporting

## Implemented

- Central `FinancialReportingService` reads posted `journal_lines` and base-currency amounts only.
- Trial Balance returns opening, period, and closing debit/credit columns plus a debit/credit reconciliation flag.
- Profit & Loss returns revenue, expenses, and net profit by posted account classification.
- Balance Sheet returns assets, liabilities, equity, accounting-equation difference, and balance status.
- `GET /api/v1/accounting/reports/statements` enforces company scope and `reports.view` or `accounting.view` authorization.
- Financial Reports UI is available at `/accounting/reports` with company/date/report controls, reconciled status, empty/error/loading states, responsive tables, and print support.
- Historical inactive accounts remain available to reports so deactivation does not erase posted reporting history.

## Reporting policy

Reports exclude drafts, rejected entries, and other non-posted ledger activity. The API identifies the result as `base_currency_posted_ledger`; the UI does not combine unrelated sales, payment, or inventory totals into official accounting statements.

## Verification status

Frontend typecheck, lint, unit tests, production build, PHP syntax checks, and route registration were executed successfully. The targeted Laravel accounting feature test could not reach PostgreSQL (`127.0.0.1:5432`, operation not permitted), so known-fixture reconciliation, reversal, opening-balance, historical, and tenant-isolation tests remain unverified until the test database is available.

Current-period earnings and annual closing transfer policy should be confirmed against the tenant's fiscal-close configuration before production financial sign-off. No migration was executed in this phase.
