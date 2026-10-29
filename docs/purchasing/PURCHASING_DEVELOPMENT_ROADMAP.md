# Purchasing Development Roadmap

## Completed

- Phase 1: Purchasing workspace foundation and navigation.
- Phase 2: Purchase Order CRUD, decimal-safe totals, detail view, and print output.
- Phase 3: Multi-level Purchase Order approval, authorization, audit history, and notifications.
- Phase 4: Goods Receipt drafts, partial/full receiving, warehouse/location validation, accepted/rejected/damaged quantities, immutable stock posting, movement history, and GRN print output.

## Phase 5 — Inventory receiving controls and documents

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

## Current known limitations

- Phase 4 captures optional batch/serial and inspection metadata, but the existing schema does not yet provide lot/serial master or quarantine tables.
- Rejected/damaged and pending quantities are shown per receipt line; mixed-unit summary totals are intentionally unavailable.
- Receipt document attachments and posted-receipt reversal are deferred until the required secure storage and ledger-correction workflows are designed.
- Browser-level visual and accessibility verification was not run in this environment.
