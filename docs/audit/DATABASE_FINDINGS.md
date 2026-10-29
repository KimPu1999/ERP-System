# Database Findings

## Verified schema state

`php artisan migrate --force` applied the forward-only Phase 1 migration `2026_10_29_000040_add_phase1_transaction_scope.php` successfully. `php artisan migrate:status` previously showed migrations `2026_10_29_000000` through `2026_10_29_000039` as **Ran**; the new migration is now also applied. No reset, wipe, or destructive database command was run.

The migration set includes:

- Core users, tenants, companies, branches, memberships, roles, permissions, assignments, invitations, sessions, security events.
- Inventory catalog, warehouse locations, balances, movements, stock operations, RLS, immutability trigger.
- Procurement and CRM/sales tables plus RLS.
- Accounting, currencies, exchange rates, fiscal periods, journals, RLS, immutability.
- HR/payroll, POS/projects/assets, approvals/reporting/automation, platform billing, production indexes, and invitation security-event RLS.

## Findings

### D-001 — Transaction scope was inconsistent (P1 — partially resolved)

`2026_10_29_000022_create_procurement_tables.php` and `2026_10_29_000024_create_crm_sales_tables.php` put `tenant_id` on purchase and sales records but do not consistently include `company_id`, `branch_id`, or transaction currency. Accounting journal records do include company and currency in `2026_10_29_000028_create_accounting_tables.php`.

Phase 1 adds nullable `company_id`, `branch_id`, `currency_id`, `exchange_rate`, and `base_total` fields to sales quotations/orders/invoices and purchase orders. New sales and purchase writes populate/validate these fields. Existing records remain nullable and require a controlled backfill policy before being considered fully reconciled.

### D-002 — Dashboard fallback currency was synthetic (P1 — resolved in API contract)

The dashboard now returns `currency.configured=false` and a null code when no tenant base currency exists. The frontend shows an explicit configuration warning instead of formatting business data as USD.

Required action: require an initialized tenant base currency for financial KPIs, or return an explicit “currency not configured” state.

### D-003 — Dashboard aggregate date semantics need confirmation (P1 — remains open)

Several dashboard queries filter `created_at` rather than document issue/posting dates (`DashboardController.php:86-105`, `119-138`). Financial reporting generally needs posted/issued dates and documented exclusion rules. The current tests cover empty structure and permission, not accounting reconciliation.

### D-004 — RLS presence is broad but runtime coverage is incomplete (P1)

RLS migrations exist for major module groups, but the audit did not execute direct PostgreSQL policy tests for every table, role, or connection mode. Application-level tenant tests do not prove PostgreSQL RLS behavior under all runtime roles.

### D-005 — Referential scope constraints need review (P1)

Many foreign keys link IDs, while tenant consistency is enforced through application queries/RLS rather than composite tenant-aware foreign keys. Review whether a child row can reference a parent from another tenant through any create/update path.

## Positive database controls observed

- UUID primary keys and tenant IDs are used throughout the inspected schema.
- Inventory movements and accounting journal posting use database transactions and idempotency keys in services.
- Immutability migrations exist for stock movements and accounting/HR areas.
- Production indexes migration exists as `2026_10_29_000038_add_production_indexes.php`.
- Migration status is reproducible locally and no pending migrations were shown.

## Database tests required next

- Direct PostgreSQL RLS tests for every tenant table using representative application roles.
- Cross-tenant and cross-company foreign-key/property tests.
- Financial reconciliation tests using posted dates, cancellation/void rules, returns, taxes, discounts, exchange rates, and base currency.
- Inventory ledger-to-balance reconciliation and concurrent idempotency tests.
- Safe forward migration rehearsal and rollback/backup restore test in a disposable environment.
