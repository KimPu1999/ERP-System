# SECURITY_MAINTENANCE.md — Phase 12

## Principle

Phase 8 Security Center (`/platform/security`) stays the single source of truth for
security monitoring. The Operations Center explicitly does **not** re-aggregate it
(overview `security_maintenance.integration = 'phase_8_security_center'`, with a link);
this avoids two diverging views of the same signals.

## Verified reuse

- Dependency/patch posture, access reviews, security events: Phase 8 center endpoints and UI.
- `php artisan platform:security-prune` scheduled at `30 2 * * *` (verified `schedule:list`).
- Audit logging: every Phase 12 privileged action goes through `AuditLogger` with `SensitiveDataRedactor` (plaintext secrets are redacted before storage).

## What Phase 12 adds (on the support surface)

- Ticket attachments are stored on the private disk with a MIME allowlist (pdf/png/jpeg/txt/csv/zip ≤10MB) and authorization-checked downloads; unsafe uploads rejected by validation (tested).
- Internal note privacy: business users can never read platform-internal notes (tested).
- Cross-tenant ticket access returns 404 (tested) on top of RLS policies on `support_messages`/`support_attachments`.
- Diagnostic access to tenant data is NOT granted by explicit-authorization grants: `support_access_grants` exists from the earlier phase but is currently **dormant** (no endpoint uses it for authorization, and it does not open tenant financial data).

## Known gaps (honest)

- No automated dependency-vulnerability scanner scheduled (no `composer audit` / npm audit cron); run manually at update time.
- TLS expiries and stale-admin reviews depend on Phase 8 workflows, not on new automation.
- Frigate-style runtime WAF/scanner integration does not exist.
