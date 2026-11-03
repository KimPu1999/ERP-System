# Email & File Storage Setup — Enterprise ERP Cloud

## 1. E-mail (production)

### Current verified state
- Mail config read path: `config/mail.php` — scheme-based SMTP (`MAIL_SCHEME=smtp|smtps`) per `MAIL_HOST/PORT/USERNAME/PASSWORD/MAIL_EHLO_DOMAIN/MAIL_FROM_*`.
- Transactional senders in `backend/app/Mail/`, `Notification` classes (verification e-mail, password reset, platform invitation flow, business invitations via `Invitation` flows, platform notifications).
- Production guard: `MAIL_MAILER=log` triggers a 503 in production through `ValidateProductionConfiguration`, and phase logs show the explicit "not_delivered / non_delivery_transport" markers (see `backend/storage/logs/laravel.log` 2026-11-03) — **a successful in-app dispatch through the log mailer is NOT delivery.**
- Phase 10 evidence: the SMTP code path is exercised by tests (accept-by-configured-transport on one branch, delivery-failure WARNING on another) — but no real SMTP provider has ever accepted mail from this project. This must be exercised before launch.

### Setup procedure (requires user approval to register a provider)
1. Choose a real SMTP provider; register the sending domain.
2. DNS records (added only with approval): SPF (include provider), DKIM selectors, DMARC (`p=none` staging, tighten at launch), MX if mailboxes are hosted.
3. Fill mail env vars from the secret manager (host/port/username/password/from).
4. Staging qualification matrix (execute and record in `STAGING_TEST_RESULTS.md`):

| Message | Trigger | Evidence expected |
|---|---|---|
| Business registration + email verification | run registration in staging | SMTP provider dashboard accept + inbox receipt (verification link never logged) |
| Password reset | request recovery | inbox receipt, link bearing staging origin |
| Business invitation | issue invitation | inbox receipt by recipient e-mail |
| Subscription notification | issue billing record | checked in provider logs |
| Invoice email | generate via billing flow | attach private-store link, not inline secret |
| Security alert | trigger security-event test | alert routing to operator mailbox |

5. Never claim "delivered" from an Acceptance result — only provider transport acceptance AND inbox evidence count. Link-bearing monitoring surfaced in `EMAIL` logs must be scrubbed (no reset/verification links in monitoring/log storage).

## 2. File storage

### Current disk definition (verified in `config/filesystems.php` inventory behavior)
- Private storage for invoices/documents/reports; public uploads where intentionally public (product images/logos served through the app's authorized paths, never by raw bucket link).
- **Do not** keep business-critical uploads on ephemeral app volume: Option A topology denies serving `storage/` directly through nginx (`infrastructure/nginx/api.conf` has a deny rule for `/storage`).

### Target design (staging→production, matches `.env.production.example`)
- Object storage (S3-compatible) for the `private` disk, plus an `offsite` location for encrypted DB backups.
- Authorization-gated downloads: Laravel routes stream/signed-URL from the private disk only after RBAC + tenant checks (consistent with the repo's policy architecture; expires-in signed URLs for larger artifacts).
- Upload security: validated by backend validators; private documents never listed publicly; `SECURITY_MAX_EXPORT_ROWS` guards report/export sizes.
- Backups: DB dumps + object-storage versioning + offsite (second location) — consolidated targets and evidence rules in `BACKUP_RECOVERY_RUNBOOK.md`.
- Verify in staging: upload persists across app-server restarts; tenant A cannot fetch tenant B artifacts; deletion of an object leaves no orphan linkage in audit trail.
