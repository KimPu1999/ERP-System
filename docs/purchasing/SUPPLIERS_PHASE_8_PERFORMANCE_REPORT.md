# Suppliers Phase 8 Performance Report

## Measured results

No representative supplier dataset or PostgreSQL query-plan benchmark was available. No performance figures are fabricated.

## Existing safeguards

- Supplier and portal registers use server-side pagination.
- Supplier portal message and PO lists are bounded.
- Report exports run through the existing queue job.
- Analytics queries are scoped by tenant/company and keep currencies separate.

## Required staging measurements

- Directory/search p95 with 10k and 100k suppliers.
- Supplier intelligence aggregate p95 and query count.
- Financial summary and AP exposure query plans.
- Portal PO, conversation and invoice submission p95.
- Queue export throughput and memory.
- Concurrent code generation, ASN submission and automation execution.

Performance budgets must be agreed against the target hosting environment before release.
