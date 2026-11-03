# COST_MONITORING.md — Phase 12

## Design

`operational_cost_records` (manual evidence):

- `occurred_on`, provider, category, description, `amount` decimal(16,4) **never floats in app logic**, ISO currency (3 letters), `cost_type` (`actual | estimate | forecast`), `source` (`manual | provider_export`).
- No provider billing integration is configured — the API returns `provider_integration: 'not_configured_all_costs_are_manual_records'`; no provider prices are invented anywhere.

## Currency rule (tested)

Sums are produced **per currency code and per cost_type** (`summaries_by_currency`).
No cross-currency aggregation is computed; the response states
`currency_policy: No cross-currency aggregation; sums are per currency code.`
An MMK record can never be added to a USD total.

## API + UI

- `GET /api/v1/platform/operations/costs` (filters: currency, from, to; server-side pagination) and `POST` to register a record (audited).
- `/platform/operations/costs`: register form (defaults to today, actual), summaries by currency and by provider.

## Gaps

- Budget thresholds, alerts and forecasts: **not implemented** (need approved budget data).
- Provider-automated billing import: not configured; if ever added it must not reuse operator credentials and must keep `source: provider_export` separate from manual rows.
