# Suppliers Phase 4 — Audit and Implementation Record

## Scope

Supplier catalog products, internal product mapping, purchase price lists, quantity tiers, supplier contracts, purchase-order constraints, and company-scoped APIs.

## Existing implementation audit

- **Verified/reused:** Product and ProductVariant master data, AccountingCurrency, Supplier company scope, ProcurementNumberService, ApprovalService, private SupplierDocument storage, PurchaseOrder and PurchaseOrderItem models.
- **Added:** Supplier catalog product identities, mapping-safe internal variant references, mapping-compatible PO snapshots, supplier purchase price lists, quantity tiers, supplier contracts and immutable contract version snapshots.
- **Not duplicated:** Sales price lists, inventory ledger, accounting/AP, supplier master data, or approval engine.
- **Unverified in this environment:** PostgreSQL migration execution and database-backed integration tests because the configured PostgreSQL test service was unavailable.

## Controls implemented

- Tenant/company/supplier validation on catalog and pricing writes.
- Supplier SKU uniqueness within tenant/company/supplier scope.
- Internal variant ownership validation.
- MOQ, order multiple, availability, and discontinued-item checks at PO validation.
- PO snapshots preserve supplier SKU, UOM, constraints, lead time and source references.
- Price-list activation requires an approved state; resolution reports the source and rejects conflicting active prices.
- Private supplier documents can reference the new catalog/price-list/contract records.
- Contracts and price lists are auditable and use backend-generated numbers.

## Known gaps

- Full catalog product selector and internal product mapping UI, contract editor, tier editor, renewal workflow, document upload UI, and dedicated approval decision endpoints remain follow-up work.
- The existing approval definitions must contain supplier_price_list and supplier_contract scenarios before submission can succeed; the system fails closed if they are absent.
- PO price replacement from the central supplier-price resolver is not automatic; submitted PO prices remain authoritative after catalog constraints are revalidated.
