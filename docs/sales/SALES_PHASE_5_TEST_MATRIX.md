# Sales Phase 5 — Test Matrix

| Test area | Coverage | Result |
|---|---|---|
| PHP syntax | Controller, models, migration | Passed |
| Route registration | Sales order endpoints | Passed; 18 routes registered |
| TypeScript | Frontend type check | Passed |
| ESLint | Frontend lint | Passed |
| Production build | Vite build | Passed; chunk-size advisory only |
| Draft stock invariant | Code review / test target | Requires PostgreSQL fixture |
| Row-locked reservation | Code review / test target | Requires PostgreSQL fixture |
| Duplicate confirmation | Code review / test target | Requires PostgreSQL fixture |
| Cancellation release | Code review / test target | Requires PostgreSQL fixture |
| Tenant/company isolation | Code review / existing policy coverage | Requires PostgreSQL fixture |
| Database migration execution | Not executed | Blocked: PostgreSQL test database unavailable in this environment |

No database-backed Phase 5 test is reported as passed without execution.
