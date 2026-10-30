# Sales Phase 5 — Sales Orders Audit

## Scope

This phase extends the existing Sales Order, Inventory Balance, Stock Reservation, Delivery Order and Sales Invoice paths. It does not create a second inventory ledger, invoice engine or fulfillment workflow.

## Findings

| Area | Before Phase 5 | Phase 5 result |
|---|---|---|
| Order creation | Present, but minimal scope validation | Extended with company/customer references, metadata and draft-only editing |
| Order reads | Tenant-only pagination | Company/branch/state/search/date filters and eager-loaded data |
| Approval | Directly reserved stock during approval without source idempotency | Compatibility route delegates to controlled confirmation; reservations use a unique source reference |
| Confirmation | Missing | Added row-locked confirmation and reservation-only posting |
| Availability | Missing | Added company/branch-scoped available quantity endpoint |
| Cancellation | Missing | Added reservation release and status history |
| Delivery | Existing downstream consumer | Preserved; it remains responsible for physical inventory deduction |
| Invoicing/payments | Existing downstream APIs | Not created by order creation or confirmation |
| UI | Sales Orders navigation disabled | Register, KPI summary and details/reservation view added |

## Safety decisions

- Draft creation and confirmation never reduce `inventory_balances.quantity` and never create stock movements.
- Confirmation only increments `reserved_quantity` after row locking and availability validation.
- Reservations use `sales-order:{order}:{line}` as an idempotent source reference.
- Cancellation releases active reservations and records a status history event.
- All order queries and mutations resolve tenant context server-side and validate company/branch/customer ownership.

## Remaining gaps

- The project does not yet expose a complete customer-facing order creation form with searchable customer/product selectors; the first UI uses validated IDs while existing selector infrastructure is reused in later iterations.
- The migration and database feature tests require a reachable PostgreSQL test database.
- Full delivery/shipping, invoice, payment and return workflows remain separate phases and were not expanded here.
