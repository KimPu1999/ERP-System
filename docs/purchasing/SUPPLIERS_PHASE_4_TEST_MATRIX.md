# Suppliers Phase 4 — Test Matrix

| Area | Check | Result |
|---|---|---|
| PHP | New controller, models, migration and PO controller lint | PASS |
| Routes | Supplier catalog, price-list, contract and resolver routes registered | PASS |
| TypeScript | Frontend type check | PASS |
| ESLint | Frontend lint | PASS |
| Production build | Vite build | PASS; existing large-chunk warning |
| Database migration | PostgreSQL execution | NOT TESTED; PostgreSQL service unavailable |
| Catalog CRUD | Database feature tests | BLOCKED by unavailable PostgreSQL test service |
| MOQ/order multiple | Database feature tests | BLOCKED by unavailable PostgreSQL test service |
| Price conflict handling | Database feature tests | BLOCKED by unavailable PostgreSQL test service |
| Tenant/company isolation | Database feature tests | BLOCKED by unavailable PostgreSQL test service |
| Browser responsive/dark mode | Browser verification | NOT TESTED |

No database-backed result is represented as passed without execution.
