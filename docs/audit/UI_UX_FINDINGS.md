# UI/UX Findings

## Positive evidence

- React app has separate auth, company, and platform layouts in `apps/web/src/layouts/`.
- Sidebar has grouped, expandable navigation and mobile collapse/overlay behavior in `Sidebar.tsx` and `CompanyLayout.tsx`.
- Login and register pages have inline validation, password show/hide controls, and disabled submit states.
- `CompanyDashboard.tsx` now contains compact KPI cards, date/company/branch filters, charts, recent sales, top products, inventory alerts, pending approvals, empty states, and route-linked quick actions.
- `styles.css` contains shared tokens, responsive rules, light/dark theme variables, cards, tables, and form styles.

## Findings

| ID | Priority | Evidence | Finding |
|---|---|---|---|
| U-001 | P2 | `ProductCatalogPage.tsx` | Search input has no state/query binding; Import CSV and Export CSV buttons have no handlers even though API routes exist at `routes/api.php:108-111`. |
| U-002 | P2 | `AppHeader.tsx` | Header search, language selector, and notification button render but have no action. Theme toggle and profile menu are functional. |
| U-003 | P2 | `ResetPasswordPage.tsx` | Only requests a reset email and uses `window.alert`; no reset-token form/page was verified. |
| U-004 | P2 | `MfaVerifyPage.tsx` | Error feedback uses `window.alert`, which is inconsistent with inline auth error handling and poor for accessibility. |
| U-005 | P2 | `VerifyEmailPage.tsx` | Resend flow is present, but expired/signed-link success/failure handling was not browser-verified. |
| U-006 | P2 | `CompanyDashboard.tsx` / `DashboardController.php` | UI exposes company/branch filters, but several backend aggregates ignore them; this is both UX correctness and data integrity. |
| U-007 | P2 | `apps/web/src/main.test.tsx` | Only one frontend smoke test was found; no route, form, table pagination, chart, mobile, dark-mode, localization, or accessibility tests were found. |
| U-008 | P2 | page inventory | No dedicated complete UI was verified for barcode, projects, fixed assets, or full settings/localization; these appear to be backend/optional-module coverage rather than finished workflows. |
| U-009 | P3 | project-wide inspection | English/Burmese localization is not verified; the header language control is inert and no translation resource system was found in the inspected frontend tree. |

## Placeholder/incomplete page classification

The following are not treated as complete despite having a route/page:

- Product catalog actions/search: visible controls without behavior.
- Password reset: request-only flow without verified token completion.
- Header search/language/notifications: visual controls without handlers.
- Optional modules: shared page is not evidence of complete POS/projects/assets workflows.
- Platform monitoring: diagnostics are separated from company UI, but actual production monitoring integration was not verified.

## Required UI acceptance baseline for the next phase

- Every visible button either performs a documented action or is removed/disabled with an explanation.
- Every async page has loading, empty, error, retry, and unauthorized states.
- Company/branch selectors affect every displayed aggregate consistently.
- Browser checks cover login, registration, verification, password reset, tenant switching, dashboard filters, and key create/approve flows at desktop and mobile widths.
- Keyboard focus, labels, error announcements, and contrast are checked in both themes.
