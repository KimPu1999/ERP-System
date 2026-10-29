# ERP Cloud — Security Findings

## Security controls with direct evidence

- Sanctum/session authentication is applied to protected API routes in `backend/routes/api.php`.
- Email verification is required for tenant business routes.
- Platform routes are protected by `verified` plus `platform.admin`; `RequirePlatformSuperAdmin` checks the persisted super-admin flag.
- `ResolveTenant` requires an authenticated user, a validated `X-Tenant-Id`, active tenant membership, and active tenant status.
- `AuthorizationService` scopes permissions to tenant/company/branch/department assignments.
- UUID foreign keys, tenant/company indexes, RLS migrations and immutable accounting/inventory safeguards are present.
- Invitation tokens are hashed and invitation acceptance is tested for identity, expiry, revocation and reuse.
- Tests cover cross-tenant access, unauthorized branch changes, platform/admin separation, membership lifecycle and privilege escalation.

## Findings

| Severity | Finding | Evidence | Risk | Recommended action |
|---|---|---|---|---|
| Medium | Public health endpoint exposes whether database and cache are available | `routes/api.php:61`, `HealthController.php` | Reveals infrastructure availability to unauthenticated callers; endpoint also performs live checks | Keep a minimal liveness endpoint public if required, but move detailed dependency checks behind platform-admin middleware or an internal probe; add rate limiting/monitoring |
| Medium | No audited platform-super-admin provisioning workflow is documented | `users.is_platform_super_admin`, `RequirePlatformSuperAdmin`; no setup command/seed found | Operators may be tempted to edit production data manually | Add a separately reviewed, audited provisioning runbook/command; never add automatic elevation |
| Medium | Frontend browser security behavior was not verified | Browser target unavailable | CORS, cookie, CSRF, redirects and console/network failures may still regress | Run authenticated browser smoke tests in CI |
| Low | `composer validate` warns that no license is declared | `backend/composer.json` | Release metadata/compliance ambiguity | Add the intended license or `proprietary` metadata |
| Low | Health diagnostics are correctly relocated in the UI but the API boundary is broader than the UI boundary | `HealthPage` is platform-only; `/api/v1/health` is public | UI authorization alone does not protect the API | Align API exposure with the intended platform-monitoring policy |

## No verified critical vulnerability

The available automated suite did not expose a P0 tenant-isolation, authentication bypass, privilege-escalation, or financial-integrity defect. This is not a substitute for a production penetration test. Browser, deployment proxy, TLS, cookie-domain and external-mail-provider behavior remain unverified.

## Required security acceptance criteria

1. Anonymous users cannot read detailed system diagnostics unless the deployment explicitly requires a minimal public liveness probe.
2. Every company/branch query is scoped from authenticated membership and authorization, not a frontend-supplied tenant identifier.
3. Super-admin provisioning is explicit, audited and reversible only by an authorized operator.
4. Browser tests verify session cookies, CSRF, CORS and redirect behavior.
5. Invitation secrets, password hashes, MFA secrets and tokens never appear in API responses or logs.
