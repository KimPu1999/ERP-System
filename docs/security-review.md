# Phase 12 security review

## Controls verified in source

- Tenant context is required by tenant routes, set on the PostgreSQL connection, and cleared in `finally` blocks after requests and tenant-aware jobs.
- Platform routes require the explicit `platform.admin` middleware; support access is represented by an auditable grant rather than unrestricted tenant access.
- Sanctum stateful authentication uses session regeneration, CSRF protection, password confirmation validation, account status checks, and an MFA challenge branch.
- Authorization remains server-side through policies and scoped queries. Client navigation is not a security boundary.
- PostgreSQL RLS migrations exist for tenant-owned tables. Production must use a database role that cannot bypass RLS and must not own the protected tables.
- Security response headers and production release checks are installed.

## Required pre-release evidence

Run the tenant isolation, accounting invariant, inventory concurrency, authentication, and end-to-end suites against PostgreSQL and Redis. Run `php artisan erp:release-check` with production-like environment variables. Run `composer audit` and `npm audit --audit-level=high` and review every finding.

## Known blockers

Production is blocked until all of the following have evidence:

1. A real encrypted backup and restore drill succeeds in an isolated environment.
2. Staging HTTPS, SMTP, Redis authentication/TLS, database TLS, secret rotation, and file permissions are verified.
3. CI dependencies are installed and all backend/frontend tests, migration checks, and security audits pass.
4. RLS is verified with a non-owner application database role; table owners and `BYPASSRLS` roles are excluded from application access.
5. Monitoring, alert routing, queue supervision, failed-job handling, and incident ownership are operational.
6. Full staging end-to-end workflows pass and a release approver records the decision.

No production release should proceed when a critical vulnerability, failed isolation test, failed restore, or unverified accounting/inventory invariant remains open.
