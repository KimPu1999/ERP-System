# Phase 15 — Pilot Customer Acceptance Plan

**Status: NOT STARTED — no pilot business is enrolled, and no acceptance can be claimed.** This document defines the controlled pilot program; enabling it (and enabling real tenant access) is a Release Owner decision gated by `RELEASE_GATES.md`.

## 1. Eligibility & authorization

- A pilot business must be **explicitly authorized** in writing by the Release Owner (test or pilot businesses only; no unrelated/production tenants).
- Pilot scope limited to a small set (recommend ≤3 for the first wave).
- Feature flags (per-tenant module flags already exist at `/tenants/{tenant}/modules` API level) must enable exactly the agreed modules — nothing broader.

## 2. Enrollment checklist per pilot tenant

| Step | Verified by |
|---|---|
| Tenant account created through real registration/onboarding (no importer shortcuts) | Platform Admin |
| Platform-side approval, plan/subscription assignment, and sandbox billing state | Platform Admin |
| First business admin invited & accepted (scoped membership) | Platform Admin |
| Module flags set per authorization | Platform Admin |
| Support access route verified (`/business/support` raised by pilot) | Support Lead |

## 3. Pilot observations to collect (weekly template)

| Tenant | Week | Onboarding friction | Functional defects | Workflow friction | Support tickets | Feedback quote | Required improvements | Defect refs |
|---|---|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — | — | — |

## 4. Pilot acceptance criteria (per tenant)

1. Registration→onboarding completed unaided within the target time.
2. Core flows executed at least once productively: product setup, purchasing (PO→GR→bill) OR sales (quotation→order→invoice→payment) depending on enabled modules, staff roles management.
3. Accounting (if enabled) shows a tie-out on the tenant's books (TB balanced).
4. Notifications delivered (ES/EN per tenant choice, no delivery warnings).
5. Subscription entitlements behaved as configured (upsell/refusal correct).
6. No data loss or tenant-access incidents.

All failures are filed in `UAT_DEFECT_REGISTER.md` as `DEF-15-*` rows and block tenant acceptance until fixed & retested.

## 5. Explicit non-goals

- A successful pilot does **not** prove production scale (no load evidence collected during pilot by design — capacity planning is a separate Phase 12 workstream).
- Pilot tenants never see unrelated tenants' data — verified by the standing cross-tenant probe at each wave's start.
- Pilot must not be used to run unsandboxed payments.

## 6. Wave sign-off (ER template — do not fill without evidence)

| Wave | Tenants | Start | End | Defects found / still open | Accepted? | Acceptance authority | Date |
|---|---|---|---|---|---|---|---|
| 1 | none enrolled | — | — | 0 / 0 | **NOT REACHED** | Release Owner: Unassigned | — |
