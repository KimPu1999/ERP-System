# ERP Cloud — Broken or Misleading Functionality

The following items are supported by source evidence. They were not silently marked complete.

| Severity | Area | Problem | Evidence | Recommended repair | Verification |
|---|---|---|---|---|---|
| P1 | Product catalog | Import CSV and Export CSV buttons render but have no event handlers; product search input is not connected to filtering | `apps/web/src/pages/ProductCatalogPage.tsx` | Implement authorized, bounded import/export workflows or remove/disable the controls until available | Browser click test, API validation, tenant isolation test |
| P1 | Header | Language switcher and notification icon are decorative; global search has no behavior | `apps/web/src/layouts/AppHeader.tsx` | Add real routes/mutations or visibly mark features unavailable; do not imply functionality | Browser interaction and accessibility test |
| P1 | Module pages | Several pages display data but do not expose the backend create/edit/transition operations | `InventoryPage.tsx`, `HrPage.tsx`, `AccountingPage.tsx`, `WorkflowPage.tsx`, `OptionalModulesPage.tsx` | Implement module-specific workflows in later phases | API/UI integration tests |
| P1 | Browser verification | Render, console errors, network failures, responsive behavior and theme contrast have not been verified in a browser | No browser-control target available | Run browser smoke suite against `localhost:5173` and `localhost:8000` | Playwright/browser checks |
| P2 | Frontend error consistency | Simple pages use inconsistent empty/loading/error patterns and often show no loading row before data arrives | Source inspection of simple page components | Standardize shared states and table skeletons | Component tests and browser review |
| P2 | Build performance | Vite reports a 1.65 MB minified JS entry chunk and a chunk-size warning | `npm run build` output | Add route-level code splitting and measured bundle budgets | Build budget check |
| P2 | Platform UI | Tenant Management is read-only despite platform APIs supporting more operations | `TenantManagement.tsx` | Add authorized actions only after workflow and audit requirements are defined | Platform role tests |

## Not classified as broken

- “No active workspace access is available for this account” is a legitimate access state when the user has no active membership. `WorkspaceAccessController`, `WorkspaceAccessPage`, and `AuthenticationAccessTest` distinguish no membership, pending invitations, restricted memberships, inactive workspaces and platform admins. It should not be fixed by granting permissions automatically.
- The `401`, `404`, `429`, and verification responses previously observed are consistent with authentication, route, rate-limit, and token lifecycle states; the audit did not reproduce them through browser interaction. They remain browser/environment verification items, not confirmed regressions.
- Dashboard financial metrics returning an unavailable state when no base accounting currency or branch-compatible ledger basis exists is correct behavior, not fake-zero data.
