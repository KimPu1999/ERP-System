# Business Administrator Guide (English)

**Audience:** Business (Tenant) Administrators using the Enterprise ERP Cloud workspace.
**Verified basis:** Every screen below exists in the shipped frontend route table; every API referenced exists in `backend/routes/api.php`. Screens not implemented for your tenant are not documented as if they were.
**Version:** 2026-11-04 (Phase 15).

## 1. Account and getting in

- Daily operations: log in at `/login` (business login; do not use `/platform/login`).
- Accepting an admin invitation: open the emailed invite link → `/accept-invitation` → preview (this page works **before** authentication by design) → accept → complete your password/profile.
- Guided onboarding: `/business/getting-started`. Steps mirror your company profile in `/business/account` and `/business/settings`. A step only completes when its backing endpoint confirms; incomplete prerequisites are reported field-by-field.
- Workspace home: `/business/dashboard` (progress, bills, notices).

## 2. Organization

`/organization`

| Screen | Purpose |
|---|---|
| `/organization/structure` | Companies + branches hierarchy |
| `/organization/branches` (and `/branches/:branchId`) | Create/edit branches, assign branch manager candidates from staff |
| `/organization/departments`, `/organization/roles`, `/organization/members` (+ `/members/:memberId`) | Create departments, define scoped roles (company/branch/department scope), invite and manage members. Role scope determines which screens the member sees |

**Last-admin protection:** the system refuses to demote or revoke the only active administrator—the action is blocked with an explanation, not silently absorbed.

**Review permission basics before inviting staff:** least privilege per role. Staff with wrong scope will see an Access Denied page rather than hidden data.

## 3. Catalog

`/catalog` with `/catalog/categories` and `/catalog/references`.

1. Create reference data first: product categories and units in `/catalog/references`. Creating a product without its prerequisite reference is prevented with a helpful message.
2. Create products in `/catalog`; search, filter and bulk-edit are supported. Quantities and prices are stored as exact decimals.

## 4. Purchasing and suppliers

| Step | Screen |
|---|---|
| 1. Create supplier (+ contacts + addresses) | `/suppliers` |
| 2. Create purchase order(s) | `/purchases/orders` |
| 3. Submit → approval (workflow decides who approves) | `/approvals/inbox` |
| 4. Receive against the PO | `/purchases/receipts` (edit/new forms included) |
| 5. Bill arrival: create/submit supplier bill | `/accounts-payable`, `/supplier-bills/new` |
| 6. 3-way match (PO/GR/bill) → approve → post | `/supplier-bills/:supplierBillId` |

Posting a supplier bill creates a balanced double-entry journal automatically. The supplier balance (`/accounts-payable`) therefore equals posted bills minus recorded supplier payments.

## 5. Inventory

`/inventory` — `/inventory/warehouses` for setup, `/inventory/operations` for movements.

- **Warehouse + locations:** create in `/inventory/warehouses`.
- **Stock receiving:** goods receipts (from purchasing) or direct adjustments in `/inventory/operations`.
- **Stock transfer:** select source/destination locations and amounts; the ledger records −in/+out movement rows atomically.
- **Stock ledger reconciliation:** the running ledger's net quantity always equals the current balance per item+location. If not, stop transacting and raise a support ticket — do not edit around it.
- Concurrent edits on the same item+location row serialize through a single balance row; a retry message may appear if two staff save simultaneously.

## 6. Sales

`/crm-sales`

| Scenario | Screen |
|---|---|
| Customers | `/crm-sales/customers` (+ `/customers/:customerId`) |
| Leads & pipeline (if CRM enabled) | `/crm-sales/pipeline`, `/crm-sales/leads`, `/crm-sales/opportunities` |
| Quotation lifecycle | `/crm-sales/quotations` → submit → send → approve → convert to order; `/crm-sales/pdf` generation from the quotation detail screen |
| Sales order lifecycle | `/crm-sales/orders` → confirm (reserves stock) → pick → pack → ship (via `/crm-sales/fulfillment`) |
| Invoice from order | `/crm-sales/invoices` (+ `/invoices/:invoiceId`), print from detail screen |
| Payment recording | `/crm-sales/payments` — record → verify → allocate across invoices. Duplicate capture is refused server-side |
| Returns | `/crm-sales/returns` → receive on the return screen; stock returns in ledger |

Invoice totals are exact-decimal computed (rate × qty, plus configured taxes/discounts). If an invoice total ever disagrees with the same math done by hand, raise a P1 defect — this is the biggest integrity signal in the product.

## 7. Accounting

`/accounting` with `/accounting/journals` and `/accounting/reports`.

1. **Setup:** base currency and fiscal year first; period calendar auto-generated.
2. **Chart of accounts:** create in `/accounting` account management; hierarchy explored via `/accounting/integration` mappings if you use automated integrations.
3. **Manual journals:** create draft in `/accounting/journals` → submit → approve → **post**. Post requires debit == credit and an open (not locked) period. **Reversal:** posted journals are corrected by reversal entries, never by editing history.
4. **Reports:** Trial balance (`/accounting/reports/trial-balance` via Reports screen), General Ledger, Profit & Loss, Balance Sheet. Period close/lock/reopen lives on `/accounting/periods`; closed + locked periods refuse new postings.
5. **Integrity rule:** every financial statement derives only from posted journals. Draft/submit/approval states are never visible in statements.

## 8. Team, roles and access

`/business/team` — invite staff, choose self-service roles, review access history.
Access can be revoked any time; revocation takes effect immediately (fail-closed). Suspended users cannot operate until reinstated by a configured administrator.

## 9. Billing (your subscription)

`/business/billing` — view your plan, usage, entitlements and invoices. Trial eligibility is validated server-side. Entitlement-limited features show the plan limit on the same screen rather than a dead button.

## 10. Support

`/business/support` — raise tickets, attach files, follow SLA status, and view replies. For platform agents this feeds `/platform/*` support tooling.
Internal notes left by platform support are never visible to you (they render only for platform agents); anything platform-only stays on their side.

## 11. Optional modules (only if your subscription/per-tenant flags enable them)

| Module | Screen |
|---|---|
| POS | `/optional-modules/pos` |
| Fixed assets | `/optional-modules/assets` |
| Projects | `/optional-modules/projects` |
| HR | `/hr` |

POS sessions and payroll runs post balanced journals into `/accounting` like every other financial source.

## 12. Troubleshooting quick map

| Symptom | First check | Then |
|---|---|---|
| Can't log in | Correct surface? (`/login` vs `/platform/login`) | Reset password / ask platform admin to check suspension |
| Save button no-op | Required-field validation highlighted? console error? | Retry once; then raise `/business/support` with a screenshot |
| "Access Denied" | Skill/scope assigned covers screen? | Ask your admin to update `/business/team` role scope |
| Ledger doesn't tie | Any unposted journal? locked period? | Re-run reconciliation from `/accounting/reports` |
| Report export missing | Export pipeline is queued; check again shortly | Escalate if queue backlog shown on `/platform/monitoring/queues` (platform side) |

## 13. Quick-start checklist

- [ ] Invitation accepted; MFA (if enforced) enrolled
- [ ] Onboarding steps complete at `/business/getting-started`
- [ ] Company, branch, departments, roles, members set up (`/organization`)
- [ ] Reference data + first products (`/catalog/references`, `/catalog`)
- [ ] First supplier + PO + GR + bill posted (`/suppliers`, `/approvals/inbox`)
- [ ] First customer + quotation → order → invoice → payment cycle run
- [ ] Trial balance + P&L + Balance Sheet opened at least once
- [ ] Staff invited with least-privilege roles; test revocation of a non-critical user
- [ ] Support ticket raised successfully at `/business/support`
