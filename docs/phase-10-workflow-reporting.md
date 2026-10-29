# Phase 10 — Approvals, Reporting and Automation

Phase 10 adds shared tenant-scoped workflow infrastructure.

## Approval engine

- Definitions and ordered approval steps are reusable by scenario.
- Steps support permission, company, department, minimum, and maximum amount constraints.
- Requests persist document type/id, requester, amount, current level, and actions.
- Self-approval is rejected when the definition disallows it.
- Delegations are time-bounded and scenario-aware.
- Eligible approvers receive in-app notifications through a queued delivery job.

## Reporting and jobs

- Report exports are queued on Redis and stored under private storage paths.
- Downloads require authentication, tenant context, and ownership of the export.
- No public report URLs are generated.
- Export jobs use tenant context middleware and update queued/completed/failed state.
- Automation runs use idempotency keys and persist attempts/errors.
- Scheduled report definitions are stored with tenant/company filters and cron expressions.

## API

- `/api/v1/approvals/definitions`
- `/api/v1/approvals/requests`
- `/api/v1/approvals/inbox`
- `/api/v1/approvals/requests/{approvalRequest}/decision`
- `/api/v1/approvals/delegations`
- `/api/v1/notifications`
- `/api/v1/reports/exports`
- `/api/v1/reports/exports/{export}/download`
- `/api/v1/reports/schedules`
- `/api/v1/automation/rules`

Domain adapters can submit purchasing, leave, finance, expense, and future scenarios using the same approval request contract.
