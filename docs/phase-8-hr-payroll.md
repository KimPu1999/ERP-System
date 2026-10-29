# Phase 8 — Human Resources and Payroll

Phase 8 introduces a tenant/company-scoped HR boundary. `employees` are separate from `users`; a login account is optional through `employees.user_id`.

## Controls

- Employee, contract, salary, attendance, leave, payroll, and payslip records carry tenant ownership and PostgreSQL RLS.
- HR and payroll actions require dedicated server-side permissions.
- Leave balances are locked during requests and approvals to prevent over-allocation.
- Payroll is calculated from contracts and configurable salary structures/components.
- Payroll transitions are `draft → review → approved → finalized`.
- Finalized payroll and payroll items are database-protected from mutation.
- Payslip access is limited to payroll viewers or the linked employee account.
- No country-specific tax or labor rules are embedded.
- Finalized payroll emits `PayrollFinalizedForAccounting`; it does not create an accounting journal automatically.

## API surface

- `GET|POST /api/v1/hr/employees`
- `GET /api/v1/hr/job-titles`
- `POST /api/v1/hr/leave-requests`
- `POST /api/v1/hr/leave-requests/{leaveRequest}/approve`
- `POST /api/v1/hr/attendance`
- `GET|POST /api/v1/hr/payroll-runs`
- `POST /api/v1/hr/payroll-runs/{run}/transition`
- `GET /api/v1/hr/payslips/{payslip}`

Policy configuration, compliance review, and accounting mapping remain explicit administrative responsibilities.
