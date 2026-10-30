# Sales Phase 6 — Test Matrix

| Area | Result | Evidence / limitation |
|---|---|---|
| PHP syntax | PASS | New controllers, models and migration lint clean |
| Route registration | PASS | Fulfillment, shipment, dispatch, tracking, POD and exception routes registered |
| TypeScript check | PASS | `npm run check` |
| Frontend lint | PASS | `npm run lint` |
| Frontend production build | PASS | Vite build completed; existing chunk-size warning remains |
| Draft fulfillment stock invariant | NOT EXECUTED | Requires PostgreSQL fixture |
| Picking/packing no-stock invariant | NOT EXECUTED | Requires PostgreSQL fixture |
| Dispatch ledger posting | NOT EXECUTED | Requires PostgreSQL fixture and isolated inventory fixtures |
| Duplicate dispatch/concurrency | NOT EXECUTED | Requires PostgreSQL locking test |
| Tenant/warehouse isolation | NOT EXECUTED | Requires PostgreSQL authorization fixtures |
| Tracking/POD/exception workflows | NOT EXECUTED | Requires PostgreSQL fixture |

No database-backed fulfillment test is represented as passed without execution.
