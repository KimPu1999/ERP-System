# Purchasing Phase 8 Test Matrix

Status values reflect execution on 2026-10-30.

| Domain | Coverage | Result |
|---|---|---|
| Existing Laravel feature regression suite | 78 tests / 317 assertions | PASS, executed with local PostgreSQL access |
| Purchase orders | Draft, totals, scope, duplicate variants, print authorization | PASS through `PurchaseOrderWorkspaceTest` |
| Approval | Sequential levels, self approval, stale decisions, revision/resubmission | PASS through `PurchaseOrderApprovalPhaseTest` |
| Receiving | Draft isolation, partial/full receipt, over-receiving, idempotency | PASS through `GoodsReceiptPhaseTest` and `ProcurementIsolationTest` |
| Supplier bills/payments | Match, approve, post, allocation and over-allocation | PASS through `SupplierBillsPhase5Test` |
| Tenant isolation | Invalid context, cross-tenant reads/updates | PASS through `TenantIsolationTest` and procurement isolation tests |
| TypeScript | `npm run check` | PASS |
| Frontend lint | `npm run lint` | PASS |
| Frontend unit test | `npm run test -- --run` | PASS, 1 file / 1 test |
| Production build | `npm run build` | PASS; warning: 1.9 MB minified JS chunk |
| PHP syntax/lint | `composer lint` | PASS |
| Dependency audit | `composer audit --format=plain` | BLOCKED: Packagist DNS/cache access unavailable |
| Release configuration gate | `php artisan erp:release-check` | FAIL: production environment, secure session cookies, database TLS |
| Scheduler registration | `php artisan schedule:list` | PASS: hourly purchasing automation registered |
| Phase 6 return E2E | No dedicated executed test | NOT TESTED |
| Phase 7 settings/automation tests | No dedicated executed test | NOT TESTED |
| Phase 8 policy enforcement remediation | Focused purchasing suite | PASS, 9 tests / 51 assertions |
| Concurrency/load tests | No realistic isolated run | NOT TESTED |
| Browser responsive/dark-mode regression | No browser session executed | NOT TESTED |
| UAT business sign-off | Requires authorized users | BLOCKED / NOT SIGNED |
| Backup restore rehearsal | No isolated backup supplied/executed | NOT TESTED |

The first unsandboxed Laravel invocation was blocked at PostgreSQL connection setup; it is not counted as an application failure. The rerun with database access completed successfully.
