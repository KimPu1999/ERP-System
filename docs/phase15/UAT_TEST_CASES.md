# Phase 15 — UAT Test Case Catalog

**Instruction to testers:** Every case starts as `Not Tested`. Fill `Actual Result`, `Tester`, `Execution Date`, `Evidence Reference` and `Linked Defect` AT EXECUTION TIME in `UAT_EXECUTION_REPORT.md`. Expected results state BOTH frontend (FE) and backend (BE) observables. All endpoints below are verified to exist in `backend/routes/api.php` (766 lines) — do not invent routes.

Global preconditions (all cases): isolated UAT environment built by `scripts/uat/setup_uat.sh`; app build against `erp_uat`; tester authenticated with the stated role.

Statuses: `NT` Not Tested · `IP` In Progress · `P` Passed · `F` Failed · `B` Blocked.

## A. Platform Super Admin (auth & platform overview)

| ID | Scenario | Actor | Steps (abbreviated) | Expected (BE + FE) | Route/evidence anchor | Status |
|---|---|---|---|---|---|---|
| UAT-PLAT-001 | Super Admin login | Platform admin | Platform login page → credentials → submit | BE: session cookie + SF inability of business login for platform admin. FE: lands on platform overview, no errors | `/auth/login` (platform guard via `is_platform_super_admin`), `RequirePlatformSuperAdmin` middleware | NT |
| UAT-PLAT-002 | Email verification | Platform admin | Trigger resend → read transport (staging: SMTP; fallback: app `settings/email/test` self-test) | BE: verification records written; delivery state logged with hashed address. FE: clear delivery status feedback | `/auth/email/resend`, `/settings/email/test` | NT |
| UAT-PLAT-003 | MFA verification | Platform admin | Enroll TOTP → confirm code → login again with TOTP step-up → view recovery codes | BE: enrollment confirmed, session requires MFA, recovery codes generated & shown once. FE: 6 security.mfa UI steps localized | `/auth/mfa/enroll`, `/auth/mfa/confirm`, `/auth/mfa/recovery-codes` | NT |
| UAT-PLAT-004 | MFA recovery login | Platform admin | Login with recovery code instead of TOTP | BE: code single-use invalidated; next login demands re-enrollment per policy | `/auth/mfa/*` | NT |
| UAT-PLAT-005 | MFA disable requires re-auth/step-up | Platform admin | Attempt disable without step-up, then with | BE: step-up enforced. FE: no silent disable | `/auth/mfa/disable`, `/auth/step-up` | NT |
| UAT-PLAT-006 | Password reset | Business staff (reset applies platform-side too) | Request reset → use reset link | BE: single-use token; old sessions policy. FE: clear success/error messages | PasswordResetController routes | NT |
| UAT-PLAT-007 | Logout + session expiration | Platform admin | Logout → attempt protected GET; separately: use expired session | BE: revocation; 401 after expiry. FE: redirect to login | `/auth/logout`, `/auth/sessions` | NT |
| UAT-PLAT-008 | Platform overview shows real statistics only | Platform admin | Open overview | BE/F: counts equal synthetic UAT dataset counts (deterministic). No fabricated numbers; empty states when no data | `/analytics/overview` | NT |
| UAT-PLAT-009 | Pending approval counts reconcile | Platform admin | Compare overview pending-apps count with list count | Both equal | `/analytics/overview`, `/tenants` | NT |
| UAT-PLAT-010 | Subscription summaries & monitoring links | Platform admin | Open overview → click monitoring/alert entry | correct target pages; no dead links | `/analytics/subscriptions`, `/alerts` | NT |

## B. Business management & admin administration (platform)

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-PLAT-011 | Create business | Platform admin | Register synthetic tenant via setup or admin create | BE: tenant + subscription persisted; RLS context bound. FE: appears in businesses list | `/tenants`, PlatformTenantController | NT |
| UAT-PLAT-012 | Approve application | Platform admin | Approve pending synthetic onboarding | BE: status transition + audit log row. FE: status badge updates | `/tenants/{tenant}/status` | NT |
| UAT-PLAT-013 | Reject application with reason | Platform admin | Reject + reason | BE: stored; sub-owner notified per config. FE: rejected state | `/tenants/{tenant}/status` | NT |
| UAT-PLAT-014 | Suspend / reactivate | Platform admin | Suspend → attempts by tenant fail; reactivate → works | BE: suspended members blocked by `ResolveTenant` (fail-closed). FE: gate message | `/tenants/{tenant}/status` | NT |
| UAT-PLAT-015 | View business details & activity | Platform admin | Open tenant detail, activity tab | BE: audit trail paginated. FE: renders without console errors | `/tenants/{tenant}`, `/tenants/{tenant}/activity` | NT |
| UAT-PLAT-016 | Invite Business Admin | Platform admin | Invite synthetic admin email | BE: invitation + token hash stored. FE: listed under tenant administrators | `/tenants/{tenant}/administrators/invitations` | NT |
| UAT-PLAT-017 | Accept invitation (token) | Invitee | Use tokenized link (fresh guest session) | BE: guest token-preview RLS grant works (`app.invitation_token`); accept creates scoped membership. FE: preview shows correct company before auth | `/workspace-access` preview + InvitationController | NT |
| UAT-PLAT-018 | Assign scoped membership (company/branch) | Platform admin | Assign role limited to company A/branch 1 | BE: `role_assignments` with scope columns. FE: invitee sees only that scope | `/tenants/{tenant}/administrators` | NT |
| UAT-PLAT-019 | Revoke business access | Platform admin | Revoke membership → revoked user retries API access | BE: 403 + fail-closed. FE: session context cleared | `DELETE /tenants/{tenant}/memberships/{membership}` | NT |
| UAT-PLAT-020 | Last-admin protection | Platform admin | Attempt revoke/ownership transfer that would orphan tenant | BE: rejected with explicit reason. FE: explanatory validation | `/tenants/{tenant}/memberships/{membership}`, `/tenants/{tenant}/ownership-transfer` | NT |
| UAT-PLAT-021 | Business admin invitations resend/revoke | Business admin | Resend + revoke synthetic invite | BE: token rotation/invalidation | `/tenants/{tenant}/administrators/invitations/{invitation}/resend|revoke` | NT |

## C. Platform billing (sandbox only — never real money)

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-PLAT-022 | Create/assign plan | Platform admin | Create synthetic plan; assign to UAT tenant | BE: plan + subscription created. FE: list reflects | `/billing/plans`, `/tenants/{tenant}/subscription` | NT |
| UAT-PLAT-023 | Trial eligibility | Platform admin | Evaluate synthetic eligibility → enroll | BE: correct entitlement + window. FE: visible state | `/billing/subscriptions` | NT |
| UAT-PLAT-024 | Invoice lifecycle | Platform admin | Generate subscription invoice → view → (sandbox) record payment | BE: invoice states persisted; totals in decimal. FE: lifecycle badges | `/billing/invoices` | NT |
| UAT-PLAT-025 | Payment verification (sandbox reference only) | Platform admin | Verify with non-payable synthetic reference, then attempt duplicate verify | BE: first verify succeeds, duplicate rejected (idempotency). FE: audit trail | `/billing/payments`, `/billing/payments/{payment}/verify` | NT |
| UAT-PLAT-026 | Subscription renewal | Platform admin | Renew synthetic subscription | BE: new period row. FE: state update | `/tenants/{tenant}/subscription/renew` | NT |
| UAT-PLAT-027 | Entitlement enforcement | Business admin | Exceed synthetic plan limit → create next record | BE: 403/422 with limit reason. FE: actionable error | `/business/entitlements`, `/business/usage` | NT |

## D. Platform security, operations, support

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-PLAT-028 | Session revocation (other sessions) | Platform admin | Revoke all other sessions | BE: others 401. FE: device list updates | `/auth/sessions/revoke-others` | NT |
| UAT-PLAT-029 | Platform audit log integrity | Platform admin | Perform 2 actions → find both in audit with actor + context | Complete, ordered, tamper-evident (hash if implemented) | `/audit-logs`, `/security/events` | NT |
| UAT-PLAT-030 | Monitoring & alerts | Platform admin | Generate synthetic alert → acknowledge → resolve | States mutate; history retained | `/alerts`, `/alerts/{alert}/acknowledge` | NT |
| UAT-PLAT-031 | Support ticket management | Platform admin | Open (business side) → pick up → reply → close → reopen | Full lifecycle both sides; attachments downloadable with ACL | `/support/tickets` (platform side) | NT |
| UAT-PLAT-032 | Support access grant (bounded) | Platform admin | Request→approve time-bounded tenant access grant | Grant scoped + expiring; expired access denied | `/support/access-grants`, `/support/access-grants/{grant}/approve` | NT |
| UAT-PLAT-033 | Settings authorization | Platform admin | Attempt settings change with insufficient role (use staff) | BE: 403 policy. FE: no controls shown | `/settings/{category}` | NT |
| UAT-PLAT-034 | Security settings + step-up | Platform admin | Change security policy requires step-up confirm | Enforced | `/settings/{{security}}`, `/auth/step-up` | NT |

## E. Business Admin UAT (tenant workspace)

### Account & onboarding

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-BIZ-001 | Business login | Business admin | Business login (different app surface from platform) | Lands in workspace; platform-admin-only email rejected here | `/business/login` | NT |
| UAT-BIZ-002 | Guided onboarding completion | Business admin | Complete steps flagged by onboarding controller | Each `complete` persists; progress reflects | `/business/onboarding`, `/business/onboarding/{step}/complete` | NT |
| UAT-BIZ-003 | Company profile & branding | Business admin | Update settings/branding asset | Persisted; used in UI | `/business/self-service`, `/settings/branding/assets` | NT |
| UAT-BIZ-004 | Branch setup | Business admin | Create branch under company, then branch manager candidates view | Scoped correctly | `/companies/{company}/branches` | NT |
| UAT-BIZ-005 | Staff invitation & permission assignment | Business admin | Invite synthetic staff member with scoped role | Role assignment created with company/branch scope; staff sees only scope | `/company/staff` equivalent (StaffMembershipPhaseTest anchor) | NT |
| UAT-BIZ-006 | Account settings | Business admin | Update workspace settings | Persisted; audit logged | `/settings`, `/settings/audit-history` | NT |

### Products

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-BIZ-007 | Catalog references (category/unit/warehouse prerequisites) | Business admin | Create category, unit, warehouse per fixture catalog | Creation order enforced; helpful guidance errors when prerequisite missing | `/catalog/references` | NT |
| UAT-BIZ-008 | Products + variant | Business admin | Create product with variant(s) | Persisted in decimal quantities; listed | product routes (ProductCategoryController, productroutes) | NT |

### Suppliers / purchasing

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-BIZ-009 | Supplier creation | Business admin | Create synthetic supplier + contact + address | Persisted, active | `/suppliers` | NT |
| UAT-BIZ-010 | Purchase order lifecycle | Purchasing staff | Create PO → submit → (approver) approve | Each transition validated; approver distinct from creator | `/purchases/orders` (anchor: `PurchaseOrderApprovalPhaseTest`) | NT |
| UAT-BIZ-011 | Goods receipt against PO | Warehouse staff | GR from approved PO with quantity + uom | Stock increases; stock movement ledger row created; OB/insufficiency block implicit | `/purchases/receipts` (anchor: `GoodsReceiptPhaseTest`) | NT |
| UAT-BIZ-012 | Supplier bill 3-way match & posting | Accounting staff | Submit bill → match GR/invoice → approve → post | Posted to GL with balanced journal; supplier AP balance increases | `/supplier-bills`, `/supplier-bills/{bill}/post` | NT |

### Inventory

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-BIZ-013 | Warehouse + locations | Business admin | Create warehouse, add location | Scoped to tenant; listed | `/warehouses`, `/warehouses/{warehouse}/locations` | NT |
| UAT-BIZ-014 | Stock transfer | Warehouse staff | Transfer qty between locations | -src/+dst movement rows; equals transfer; `inventory_balances` updated atomically | `/inventory/transfers` (anchor: InventoryController) | NT |
| UAT-BIZ-015 | Stock adjustment | Warehouse staff | Post increment/decrement adjustment with reason | Ledger row created; balance matches | inventory adjustment routes | NT |
| UAT-BIZ-016 | Stock ledger reconciliation | Business admin | Reconcile ledger rows to balance | Ledger sum equals balance (fixture expected value known) | inventory ledger view | NT |

### Sales

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-BIZ-017 | Customer creation | Business staff | Create synthetic customer | Persisted scoped | `/sales/customers` | NT |
| UAT-BIZ-018 | Quotation lifecycle | Sales staff | Submit → send → approve → convert to order | Each transition validated + audited; PDF generation works | `/sales/quotations` | NT |
| UAT-BIZ-019 | Sales order confirm + fulfilment | Sales staff | Confirm → reservations → picking → packing → ship | Stock reserved on confirm, relieved on ship | `/sales/orders/{order}/confirm`, `/sales/fulfillments/*` | NT |
| UAT-BIZ-020 | Invoice generation from order | Sales staff | Use `invoices` on confirmed/delivered order | Invoice totals = rate×qty via decimal math (fixture expected) | `/sales/orders/{order}/invoices` | NT |
| UAT-BIZ-021 | Payment recording + allocation | Payments staff | Record payment (sandbox) → verify → allocate across invoice | No double-capture; AR balance reduces by amount allocated | `/sales/payments`, `/sales/payments/{payment}/allocate` | NT |
| UAT-BIZ-022 | Sales return with receipt | Sales staff | Create return → receive | Stock return movement; AR/credit note downstream | `/sales/returns`, `/sales/returns/{return}/receive` | NT |

### Accounting

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-BIZ-023 | Base currency + fiscal year | Business admin | Set base currency; create FY; generate periods | Persisted; single active open metadata | `/accounting/currencies`, `/accounting/fiscal-years` | NT |
| UAT-BIZ-024 | Chart of Accounts | Business admin | Create synthetic accounts (asset/liability/equity/income/expense) | Persisted; hierarchy viewable | `/accounting/accounts` | NT |
| UAT-BIZ-025 | Journal entry workflow | Accounting staff | Create → submit → approve → post → reverse | Reverse creates balancing-opposite journal; post requires submit+approve; reject path also exercised once | `/accounting/journals` + state routes | NT |
| UAT-BIZ-026 | GL / Trial balance | Business admin | Open ledger and trial balance | Trial balance debits == credits (fixture-known). Ledger lines link to posted journals only (draft/excluded) | `/accounting/reports/trial-balance`, `/accounting/reports/financial` | NT |
| UAT-BIZ-027 | P&L + Balance Sheet | Business admin | Render statements for FY | Period-close state honored; totals tie to TB | `/accounting/reports/statements` | NT |
| UAT-BIZ-028 | Period lock discipline | Business admin | Archive/lock closed period → attempt post into it | Rejected by validation | `/accounting/periods/{period}/lock` | NT |

### Optional modules (only if enabled for UAT tenant)

| ID | Scenario | Actor | Steps | Expected | Route | Status |
|---|---|---|---|---|---|---|
| UAT-OPT-001 | POS session flow | POS operator | Open POS session → record sandbox sale → close | POS sales correspond to sales invoices; cash ledger consistent | PosController routes | NT |
| UAT-OPT-002 | HR/Payroll sample run | HR staff | Create employee → generate synthetic payroll run | Payroll posts balanced journal to GL | `/accounting/hr` equivalent (anchor: HrPayrollPhaseTest) | NT |
| UAT-OPT-003 | Projects sample | PM | Create project → log entry/activity → complete | Persisted scoped | `/projects` equivalent (anchor: OptionalModulesPhaseTest) | NT |
| UAT-OPT-004 | Fixed Assets sample | Accounting staff | Register asset → post depreciation schedule entry | Balanced journal; NBV accords | `/accounting/assets` equivalent | NT |

## F. Cross-tenant security (API-level mandatory; FE evidence secondary)

All cases use TWO synthetic tenants seeded by setup script (Tenant A default, Tenant B probe).

| ID | Scenario | Actor | Steps | Expected | Status |
|---|---|---|---|---|---|
| UAT-ISO-001 | Tenant A cannot READ Tenant B | Tenant A admin | Direct GET of Tenant B resource IDs via API | 0 rows / 404 fail-closed — not found ≠ authorized | NT |
| UAT-ISO-002 | Tenant A cannot MODIFY Tenant B | Tenant A admin | PUT/PATCH/DELETE against Tenant B resource | 403/404; no rows mutated (verify B row unchanged) | NT |
| UAT-ISO-003 | Tenant A cannot EXPORT Tenant B | Tenant A admin | Request tenant export scoped to B | Denied; export manifest only includes A | `/tenant-exports`, `/tenants/export` | NT |
| UAT-ISO-004 | Membership grant requires authorization | Tenant A staff (non-admin) | Attempt grant admin to self | 403; no `role_assignments` row | NT |
| UAT-ISO-005 | Revoked access stops working | Revoked staff | Use pre-revocation token/session | 401/403 immediately | NT |
| UAT-ISO-006 | DB-level RLS holds without app middleware (belt) | Coordinator (psql) | Set `app.tenant_id` to B in a DB session as A's rows | Read = 0 rows from A; insert violates policy (mirrors RlsEnforcementTest at UAT-level evidence) | NT |
| UAT-ISO-007 | Suspended tenant restrictions | Platform admin | Suspend tenant A → A's users/all routes | Fail-closed per `ResolveTenant`; well-formed FE gate | NT |
| UAT-ISO-008 | File/download ACL | Tenant A admin | Attempt download of Tenant B attachment/export | Denied; A's own downloads succeed | `/support/tickets/{ticket}/attachments/{attachment}`, `/tenant-exports/{export}/download` | NT |

## G. Financial acceptance

| ID | Scenario | Steps | Expected | Status |
|---|---|---|---|---|
| UAT-FIN-001 | Invoice totals with tax+discount | Compute expected (fixture values) → compare UI + API + DB decimal | Exact equality (no drift within decimal places) | NT |
| UAT-FIN-002 | Payment reconciliation | Allocate partials across 2 invoices; compute AR | AR = invoice total - total allocated | NT |
| UAT-FIN-003 | Journal debit==credit always | Every post in E.12 scenario | Sum(dr)==sum(cr) at all times (query GL) | NT |
| UAT-FIN-004 | TB → statements tie-out | Re-compute P&L+BS from TB | Sections equal TB-derived totals | NT |
| UAT-FIN-005 | Inventory valuation | Compute fitur expected (cost×qty) from ledger | Equals valuation view | NT |
| UAT-FIN-006 | Supplier AP balance | Sum posted supplier bills minus payments | Equals supplier balance view | NT |
| UAT-FIN-007 | Duplicate invoice prevention | Double-submit invoice request | Idempotency/second rejected; no duplicate row | NT |
| UAT-FIN-008 | Duplicate payment prevention | Same payment payload twice | Second rejected (idempotency key / verify-once) | NT |
| UAT-FIN-009 | Duplicate stock movement prevention | Resubmit GR | Second refused or net-zero with idempotency record | NT |
| UAT-FIN-010 | Unbalanced journal block | Attempt posting publication with dr≠cr | Rejected validation, no row | NT |
| UAT-FIN-011 | Trial billing | Sandbox method only | No live charge; audit reveals what was attempted | NT |

## H. UI/UX & language acceptance

| ID | Scenario | Expectation | Status |
|---|---|---|---|
| UAT-UI-001 | Desktop 1440px pass all pages in §A–§E | No overlap/clipping; controls active | NT |
| UAT-UI-002 | Tablet 768px pass critical flows | Form + tables usable | NT |
| UAT-UI-003 | Mobile 375px landing/auth/support | No horizontal scroll traps on core pages | NT |
| UAT-UI-004 | Dark mode toggle on each page family | Readable contrast; no invisible text | NT |
| UAT-UI-005 | Light mode toggle | As above | NT |
| UAT-UI-006 | English all menus | No untranslated keys (0 console `Missing translation` in normal flows) | NT |
| UAT-UI-007 | Burmese all menus | Full coverage; no `as never` raw keys — VERIFY known ERP-P3-2 gap; log missing keys as defects, do not waive | NT |
| UAT-UI-008 | Search, filter, sort, pagination live runs on products/companies/sales lists | server-side or client correct; reset on param change | NT |
| UAT-UI-009 | Keyboard navigation auth→onboarding | Tab order; focus visible; Enter submits | NT |
| UAT-UI-010 | Validation & retry guidance | Field-level errors actionable; form restores input | NT |
| UAT-UI-011 | Loading/empty/error states real felt | Skeleton on datasets; empty state explains prerequisites | NT |
| UAT-UI-012 | Buttons perform actual actions | Spot-check every primary button on business flows (no dead controls) | NT |

## Execution rules recap

1. UAT runs against the **UAT environment** with **synthetic data only**.
2. Each execution entry in `UAT_EXECUTION_REPORT.md` captures who/when/result/evidence.
3. Failure → open defect row (with severity per register) → fix by dev → regression run → retest by tester.
4. Sign-off collected in `UAT_SIGNOFF.md` only for Passed cases with evidence.
5. **No case may be marked Passed because an automated test passed.** Automated suites inform defects regression only.
