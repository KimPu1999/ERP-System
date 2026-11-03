# SUPPORT_WORKFLOW.md — Support ticket lifecycle (Phase 12)

Verified against `backend/app/Services/SupportTicketService.php` and the Phase 12 tests.

## Ticket fields

Unique `ticket_number` (`TKT-YYYYMMDD-XXXXXXXX`), tenant, requester, category
(`general|billing|account_access|inventory|sales|purchasing|accounting|integration|data_import`),
subject, description, priority, status, assigned agent, created/updated, first_response_at,
resolved_at, closed_at, SLA due timestamps + breach log.

## Status machine (`SupportTicket::TRANSITIONS`)

```
open            -> in_progress, waiting_customer, resolved, closed
in_progress     -> open, waiting_customer, waiting_support, resolved, closed
waiting_customer-> in_progress, resolved, closed
waiting_support -> in_progress, resolved, closed
resolved        -> closed, open (reopen)
closed          -> open (reopen)
```

Invalid transitions throw HTTP 422. Resolution requires resolution notes
(`Cannot transition ... to resolved` without them). Auto-behaviors:

- First **public** platform reply records `first_response_at` and moves an open ticket to `in_progress`.
- Requester reply while `waiting_customer` auto-resumes work.
- Reopen window: 7 days after closing (`SupportTicketService::REOPEN_WINDOW_DAYS`); after that the requester must create a new ticket.

## Tenant isolation

- Business routes run under the `tenant` middleware; every lookup is `tenant_id = app(TenantContext::id())`; cross-tenant reads return 404 (tested).
- RLS: `support_messages` and `support_attachments` have `*_tenant_isolation` policies using `app.platform_admin` / `app.tenant_id` (same pattern as platform billing).
- Internal notes (`visibility=internal`) exist only for platform posts and are filtered out of every business-side response (tested).
- Attachment downloads pass through authorization on both sides and are audited; platform downloads record `platform.support.attachment_downloaded`.
- MIME allowlist: `pdf, png, jpeg, txt, csv, zip`, 1 byte–10 MB, stored on the private `local` disk (no web access). All privileged changes are audit-logged.

## SLA policies

`support_sla_policies` (priority may be null = catch-all). Policy resolution: exact
priority match, then null-priority fallback, else **no policy** → SLA state
`not_configured` — no universal default is hardcoded. Due timestamps are computed
at ticket creation; with a `business_hours` block (`days/start/end/timezone`) elapsed
time skips outside the window; otherwise 24x7 UTC minutes. Observed states:
`on_track`, `at_risk` (≤30 min to first-response / ≤60 min to resolution), `breached`,
`met`, plus `sla_breach_log` entries recorded when resolution/response lands past target.

**Not yet implemented (honest gaps):** holidays, escalation rules, automatic breach alerts/notifications, per-tenant SLA policy scoping (policies are currently platform-global).

## Platform workflow

`/platform/support` — search/filter (status, priority), assign agent (audited), change priority/category/status, public reply, internal note, CSV export (`GET /platform/support/tickets/export`, audited), SLA policy management (`GET|POST /platform/support/sla-policies`).
