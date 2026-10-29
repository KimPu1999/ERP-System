# Dashboard Phase 3 analytics assumptions

- **Revenue trend and growth:** posted/issued sales invoices use `base_total` with `total` as the legacy fallback. Draft, cancelled, and void invoices are excluded. Valid credit notes are subtracted using the linked invoice exchange rate.
- **Comparison periods:** `previous_period` uses an equal-length period immediately before the selected range. A selected chart year uses the same months in the previous year.
- **Financial performance:** income and expenses come from posted `journal_entries` and `journal_lines`, grouped by `chart_accounts.type` and measured with `base_debit`/`base_credit`. Revenue is credit minus debit; expenses are debit minus credit; profit is income minus expenses.
- **Currency:** analytics require a tenant base accounting currency. General-ledger base columns prevent adding source currencies directly. Missing base currency returns an explicit unavailable response.
- **Branch reporting:** the sales-invoice revenue endpoints support branch filters. The general-ledger schema is company-scoped and has no branch dimension, so financial performance returns unavailable for a branch filter rather than implying branch-level precision.
- **Targets:** no configured growth-target source exists in the current schema. The growth widget therefore shows the calculated period comparison and explicitly reports that no target is configured.
- **Access:** all analytics endpoints require Sanctum authentication, verified email, active tenant membership, `reports.view`, and server-side company/branch authorization. Every aggregate is tenant-scoped.
