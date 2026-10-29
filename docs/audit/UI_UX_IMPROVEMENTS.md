# ERP Cloud — UI/UX Audit

## Verified/source-level positives

- Blue/white ERP visual language is centralized in `apps/web/src/styles.css` and reused by the company/platform layouts.
- Sidebar supports grouped navigation, active links, collapse state and mobile-open state in `apps/web/src/layouts/Sidebar.tsx`.
- Header includes company/branch context, theme toggle, profile/logout and platform/company variants in `apps/web/src/layouts/AppHeader.tsx`.
- Auth forms include labels, password visibility controls, validation messages and route-protected flows.
- Dashboard, organization, branch and staff pages include explicit API error/retry or empty-state paths in their source.

## Findings

| Area | Finding | Status | Recommendation |
|---|---|---|---|
| Responsive layout | No browser-level responsive check was available | NOT VERIFIED | Test at desktop, tablet and mobile widths; check tables and sidebar drawer for overflow |
| Dark mode | Theme class toggle exists, but contrast was not visually verified across every table/form | NOT VERIFIED | Run contrast and screenshot review for all layouts |
| Tables | Several pages have compact tables but no server-side pagination or search UI | PARTIAL | Use shared paginated table primitives |
| Forms | Auth/branch/profile/staff forms have useful validation; simpler company/department forms have limited inline error display | PARTIAL | Standardize field-level server error mapping and disabled/submitting states |
| Empty/loading states | Dashboard/organization/branch/staff flows are stronger; inventory/roles/platform/simple pages are inconsistent | PARTIAL | Add shared `LoadingState`, `EmptyState`, and `ErrorState` components |
| Navigation | Route guards correctly separate platform and company layouts; some sidebar labels point to summary pages rather than complete workflows | PARTIAL | Keep labels honest and add feature availability indicators where needed |
| Header controls | Search, language and notifications are visible but not functional | BROKEN/MISLEADING | Implement or remove/disable until supported |
| Product toolbar | Import/export visible without action | BROKEN/MISLEADING | Implement or disable |
| Accessibility | Labels and aria labels are present in many forms; icon-only action coverage should be checked browser-side | NOT VERIFIED | Run keyboard/focus and screen-reader smoke checks |
| Bundle size | Entry chunk exceeds Vite’s 500 kB warning threshold | IMPROVEMENT | Route-level lazy loading and measured split points |

## Design-system guidance for future work

Retain the current tokens: primary `#2563EB`, background `#F8FAFC`, white cards, 1px borders, 10–12px radius, compact spacing and minimal shadows. Fix interaction completeness before broad visual redesign.
