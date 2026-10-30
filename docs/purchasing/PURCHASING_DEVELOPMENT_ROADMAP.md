# Purchasing Development Roadmap

## Completed

- Phase 1: Purchasing workspace foundation and navigation.
- Phase 2: Purchase Order CRUD, decimal-safe totals, detail view, and print output.
- Phase 3: Multi-level Purchase Order approval, authorization, audit history, and notifications.
- Phase 4: Goods Receipt drafts, partial/full receiving, warehouse/location validation, accepted/rejected/damaged quantities, immutable stock posting, movement history, and GRN print output.
- Phase 5: Supplier Bill drafts, 2-way/3-way matching, approval/posting gates, explicit AP journal mapping, supplier payments, AP allocations, aging register, and supplier credit-note posting.
- Supplier Phase 1: company-aware supplier directory, generated supplier codes, profiles, contacts, addresses, status controls and Purchase Order eligibility validation.
- Phase 6: Receipt-anchored Purchase Returns, approval-gated Return to Supplier dispatch, inventory-safe idempotent movements, debit/refund records, supplier performance read models, procurement analytics, and Purchase Returns UI.
- Phase 7: Company-scoped Purchasing Settings, atomic document numbering, receiving/matching policies, safe automation dispatch, notification preferences, audit views, RLS, and production-readiness verification.

## Phase 6 — Inventory receiving controls and documents

Priority: P1

Dependencies: Phase 4 receipt posting and the existing inventory ledger.

Acceptance criteria:

- Batch/lot and serial master records are introduced only with product-level tracking configuration.
- Serial uniqueness is enforced across the authoritative inventory scope.
- Quality inspection and quarantine release are auditable and cannot make rejected stock available accidentally.
- Receipt attachments are private, validated, permission-checked, and auditable.
- Posted receipt corrections use an authorized reversal service and compensating ledger movements.
- UOM conversions are explicit and mixed-unit reports never combine incompatible units.
- Concurrency, tenant isolation, warehouse authorization, and duplicate posting tests pass.

## Phase 5 known limitations

- Posting requires explicit company `accounts_payable` mapping, line-level expense/inventory/asset accounts, an open fiscal period, and a configured currency; it does not guess account mappings.
- AP totals remain unavailable for mixed currencies instead of adding currencies without an exchange-rate policy.
- Bill attachments, payment attachments, configurable matching tolerances, provider-backed payment verification, and multi-level Supplier Bill approval policy administration remain deferred.
- Supplier credit notes currently support a safe draft/post accounting adjustment but not line-level credit allocation or reversal UI.

## Current known limitations

- Phase 6 return cases require a posted Goods Receipt and approved dispatch; supplier credit allocation, refund accounting verification, configurable performance weights, and queued export formats are not yet complete.
- Performance delivery scores remain explicitly unavailable until expected/actual delivery data and a configured minimum sample policy exist; analytics keeps currency groups separate.

- Phase 4 captures optional batch/serial and inspection metadata, but the existing schema does not yet provide lot/serial master or quarantine tables.
- Rejected/damaged and pending quantities are shown per receipt line; mixed-unit summary totals are intentionally unavailable.
- Receipt document attachments and posted-receipt reversal are deferred until the required secure storage and ledger-correction workflows are designed.
- Browser-level visual and accessibility verification was not run in this environment.

## Phase 7 readiness

- Status: CONDITIONALLY READY for controlled staging validation; not approved for production deployment.
- Blocking follow-up: add dedicated Phase 7 feature/concurrency tests, configure real scheduler/queue workers and email provider observability, run browser responsive/dark-mode checks, and perform an isolated backup restore test.

## Suppliers Phase 2

- [x] Supplier onboarding application schema and dashboard
- [x] Company-scoped document requirements and private document metadata
- [x] Document review history and eligibility checks
- [x] Existing approval engine integration
- [ ] Scheduler-backed supplier expiry reminders
- [ ] Full onboarding form and document-review UI

## Suppliers Phase 3

- [x] Company-scoped supplier financial profiles
- [x] Encrypted and masked supplier beneficiary accounts
- [x] Manual maker-checker beneficiary verification
- [x] Controlled default beneficiary selection
- [x] Payment holds enforced at payment posting
- [x] AP summary integration from existing posted ledgers
- [ ] External bank verification integration
- [ ] Scheduler-backed financial document expiry reminders
- [ ] Full financial profile and bank-account management UI
