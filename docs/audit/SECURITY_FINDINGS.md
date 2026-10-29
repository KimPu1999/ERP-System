# Security Findings

## Critical / release-blocking findings

### S-001 — Production mail configuration can expose verification/reset tokens (P1)

Evidence: `backend/.env.example` sets `MAIL_MAILER=log`; auth routes include password reset and signed email verification in `backend/routes/api.php:60-67`.

Impact: If the example/local mail configuration is carried into a shared or production environment, verification and reset URLs may be written to application logs. Anyone with log access could use a live token before expiry.

Required action: enforce a real mail transport in deployment configuration, redact URLs from logs, rotate any tokens exposed in non-development logs, and add a release check that rejects `MAIL_MAILER=log` outside local/testing.

### S-002 — Dashboard/company scope can produce incorrect business reporting (P1)

Evidence: `DashboardController.php:41-69` applies company/branch filters only to selected inventory/approval queries; `invoiceTotal`, `purchaseTotal`, `grossProfit`, `salesOrders`, chart, recent-sales, and top-product queries use tenant ID only. Sales/procurement migrations (`2026_10_29_000022`, `2026_10_29_000024`) lack consistent company/branch/currency fields on transaction headers.

Impact: A user can receive a valid response containing numbers from other companies or branches in the same tenant. This is a data-integrity and authorization-boundary concern even if it does not expose another tenant.

Required action: model and enforce transaction company/branch/currency scope, then add cross-company and cross-branch tests for every dashboard aggregate.

## High-priority findings

### S-003 — Permission enforcement is not uniformly visible at the route boundary (P1)

Evidence: the tenant group in `routes/api.php:91` enforces `auth:sanctum`, `verified`, and `tenant`; individual controllers sometimes call `AuthorizationService`, but there is no universal permission middleware. `AuthorizationService.php:10-15` provides scoped checks, but coverage must be proven endpoint by endpoint.

Risk: A read or mutation endpoint that forgets a controller-level permission check may be usable by any active tenant member.

Required action: create an endpoint-to-permission map, use policies/middleware for default-deny behavior, and test unauthorized read and write access for every module.

### S-004 — Deployment workflow does not deploy or verify the backend (P1)

Evidence: `.github/workflows/deploy.yml:24-31` builds the web artifact and then prints “invoke the deployment runner”; no API/container/database migration/deploy step is present.

Risk: A green/manual workflow can be mistaken for a deployed release. Backend configuration, migrations, secrets, health checks, and rollback are outside the controlled workflow.

Required action: add a documented, authenticated deployment runner with migration safety, health checks, rollback, and audit output.

### S-005 — Dependency vulnerability status is unknown (P1)

Evidence: `composer audit` and `npm audit --audit-level=high` both failed to reach their registries due DNS/network resolution errors in this audit environment.

Risk: No current advisory result is available.

Required action: rerun in CI or a networked release environment and retain the result as a release artifact.

## Controls observed

- Sanctum stateful API configuration exists in `backend/config/sanctum.php`.
- CORS is restricted to configured frontend URLs plus local development origins in `backend/config/cors.php`; credentials are enabled and must be checked against production origins.
- `ResolveTenant` validates UUID format, active membership, sets request tenant context, and clears it in `finally` (`backend/app/Http/Middleware/ResolveTenant.php:12-24`).
- `RequirePlatformSuperAdmin` checks `is_platform_super_admin` and sets a platform context (`backend/app/Http/Middleware/RequirePlatformSuperAdmin.php:10-18`).
- Most tenant tables have tenant RLS migrations; inventory/procurement/accounting/HR/optional/approval/billing RLS migrations are present.
- Inventory movement and accounting posting services use transactions, idempotency keys, scoped lookups, and audit-related records in the inspected paths.
- Security headers middleware is appended globally in `backend/bootstrap/app.php`.

## Not verified

- Production cookie, proxy, TLS, session, and CORS behavior.
- File upload malware scanning/content validation beyond controller inspection.
- Complete platform-vs-tenant authorization matrix.
- MFA enrollment/recovery and session revocation in a browser.
- Security advisory status due unavailable registries.
