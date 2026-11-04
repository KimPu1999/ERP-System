# Platform Super Admin Guide (English)

**Audience:** Enterprise ERP Cloud Platform Super Administrators.
**Verified basis:** Every screen named below exists in the shipped frontend (`apps/web/src` route table) and its backing API exists in `backend/routes/api.php`. Nothing here describes an unimplemented button.
**Version:** 2026-11-04 (Phase 15).

## 1. Getting access

1. Registration happens on the normal public app; access to `/platform/*` is granted only afterwards.
2. A platform operator with shell access runs `php artisan platform:provision-admin {email} --yes`.
   Requirements enforced by the command: the user already exists, is `active`, not suspended, and has a **verified email**. The command refuses otherwise.
3. There is **no public super-admin signup** — if you can reach `/platform/login` but cannot get past it, your `is_platform_super_admin` flag is not set; request provisioning.

## 2. Login and MFA

1. Go to `/platform/login` (this is separate from the business login at `/login`).
2. Sign in. If MFA enrollment is required by policy you will be routed through the MFA enrollment screens:
   enroll → scan the TOTP secret in your authenticator app → confirm a 6-digit code → **download the one-time recovery codes** (shown once).
3. Recovery codes are single-use. Use one only if your authenticator is unavailable; enrollment state afterwards depends on security settings.
4. Disable/enable MFA changes require a step-up confirmation (`/auth/step-up`); the UI never silently changes MFA state.
5. Forgot password: `/platform/forgot-password` → email link → `/platform/reset-password`.

## 3. Control Center overview — `/platform/control`

Shows business-wide statistics, pending approvals, subscription summaries and links into monitoring.
**Expect only real data:** the platform does not fabricate demo numbers. Empty metric areas indicate there is genuinely no data yet.

## 4. Domain administration

| Task | Screen | What you can do |
|---|---|---|
| Browse businesses | `/platform/businesses` | Search, filter by status, open a tenant's full record and activity trail |
| Approve / reject applications | business detail screen | Status transitions are validated server-side; reasons are stored |
| Suspend / reactivate | business detail | Suspended tenants fail-closed at the tenant resolver — their users cannot operate until reactivated |
| Ownership transfer & memberships | business detail | Only with authorization; last-admin operations are protected (revoking the final admin is refused) |
| Business admins | `/platform/business-admins` | Invite, view, scope and revoke business administrators |
| Platform admins | `/platform/administrators`, `/platform/invitations` | Provisioned by the CLI (as above); invitations management for platform staff |
| Audit trail | `/platform/audit-logs` | Every platform action with actor + context; keep this screen open during sensitive changes |
| Platform reports | `/platform/reports/financial`, `/platform/reports/exports` | Report requests run through the export pipeline; large reports are generated as queued exports |

## 5. Analytics

`/platform/analytics` with sub-views: businesses, users, revenue, subscriptions, usage,
`/platform/growth` with onboarding / adoption / retention / customer-success sub-views.
All figures derive from platform tables; verify discrepancies against `/platform/audit-logs` before escalating.

## 6. Billing

| Task | Screen |
|---|---|
| Manage plans (create, edit, archive) | `/platform/invoices` + billing/plan management entry points |
| Subscriptions (assign plan, renew, cancel) | `/platform/payments` and tenant subscription screen |
| Invoice lifecycle | `/platform/invoices` |
| Payment verification | `/platform/payments` — verification is an audited, verify-once action (duplicate capture is blocked server-side) |

⚠️ Payment verification must operate against **sandbox providers only** until live processors are separately authorized in writing.

## 7. Security Center — `/platform/security`

- Security policy (MFA enforcement, session policy, step-up rules)
- Security events streams
- **Session revocation** support for incidents (either from the Security Center or a tenant detail screen)
- Security maintenance windows — coordinated with `/platform/operations/maintenance`

## 8. Monitoring — `/platform/monitoring`

Sub-views: api, database, queues, scheduler, errors, email, servers, incidents, settings, and alerts.
Related operations screens: `/platform/operations` (backups, costs, database, queues, maintenance).

Rule: monitoring shows telemetry only when collection is actually configured. If a sub-view is empty, treat that as "not collecting", not as "all healthy", and raise a defect instead of assuming the service is fine.

## 9. Support tickets

Business users raise tickets at `/business/support`. Platform agents handle them in
`/platform/*` support views (ticket lists, replies, close/reopen, attachments with ACL-controlled downloads, internal notes).
Time-bounded access grants (`/support/access-grants` API) allow explicit, audited, expiring access to a tenant's data when troubleshooting — approve grants only when necessary and revoke promptly at resolution.

## 10. Safe operational escalation

1. **Never** run destructive commands against a data-bearing database without a second authorized operator present and a fresh backup (see `docs/phase15/INCIDENT_RUNBOOKS.md`).
2. Data-modifying migrations on RLS-governed tables must set the platform context first (`SET app.platform_admin = 'true'`) as documented in the migration header.
3. Anything that cannot be described in the audit trail should not be done — if a fix requires an unverifiable step, document it in an incident record first.
4. Escalation path: on-call Platform Administrator → Security Owner (authentication/suspicion incidents) → Deployment Operator (infra incidents). Current owners are listed in `docs/phase15/RESPONSIBILITY_MATRIX.md`.

## 11. Quick-start checklist

- [ ] Account provisioned by `platform:provision-admin` (email verified, active)
- [ ] MFA enrolled; recovery codes stored in a vault
- [ ] Completed a full read-only tour of `/platform/control` → businesses → monitoring → audit-logs
- [ ] Knows the sandbox-only billing rule and the last-admin protection rule
- [ ] Has reviewed `RESPONSIBILITY_MATRIX.md` and knows their escalation contacts

## 12. FAQ

**Q: Login works but every `/platform/*` page 403s.** A: `is_platform_super_admin` not set — request provisioning; do not try to self-grant.
**Q: Verification email not arriving.** A: Check `/platform/monitoring/email`; delivery state is logged with hashed addresses. If SMTP is unstable, the mail may fall back to the app log transport — an operator reads the log; never send production secrets this way.
**Q: Can I "just migrate" past RLS?** A: No. RLS-protected tables require the documented platform context; bypassing isolation is a security gate failure.
**Q: I don't see statistics.** A: The platform shows real data only; check whether the feature is enabled and collectors configured (see §8 rule).
