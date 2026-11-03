# Go-Live Checklist — Enterprise ERP Cloud

DO NOT EXECUTE until every gate below is evidenced. Live DNS changes, production migrations and launch require **explicit user authorization** (Phase 11 hard rule, restated).

## 0. Approval gate (the ONLY step that unblocks execution)

- [ ] User explicitly authorizes: production infrastructure provisioning, DNS changes, migrations, and go-live. (Phase 11 status: NOT granted — no live deployment performed.)

## 1. Release gates (Phase 10 checklist: `docs/release-checklist.md`)

- [x] Backend suite green: 176 passed / 842 assertions / 0 failures (executed 2026-11-03, CI-parity fresh `erp_test`).
- [x] Frontend: typecheck/lint 0 errors, 4 vitest passing, build success (executed 2026-11-03).
- [x] Deploy pipeline gates exist and exercised via CI parity.
- [ ] `composer audit` + `npm audit --audit-level=high` clean at the release commit (CI-enforced; confirm at final commit).
- [ ] F-002/F-004 resolution status decided by approver (E2E business journeys; restore drill).

## 2. Infrastructure & configuration

- [ ] Hosting provider selected and account approved by user (Option A default per `DEPLOYMENT_ARCHITECTURE.md` §4).
- [ ] Production VPS provisioned per `BACKEND_DEPLOYMENT.md` §1 (unprivileged deploy user, firewall, SSH hardening, logrotate, time sync).
- [ ] Production PostgreSQL created per `POSTGRESQL_PRODUCTION.md` (roles erp_app/erp_migrator/erp_backup, TLS verify-full, private network, per-role privileges verified).
- [ ] Redis private + password; no 5432/6379 exposed.
- [ ] Production `.env` assembled from `backend/.env.production.example` checklist via secret manager; `erp:release-check` PASSES on the release (evidence required).
- [ ] Frontend project (Vercel/config)`VITE_API_URL` = production API origin; `vercel.json` SPA fallback active.
- [ ] Object storage bucket (private disk) + offsite backup bucket provisioned.

## 3. Data protection readiness

- [ ] First verified encrypted backup exists (pg_dump → age → offsite) prior to ANY production migration.
- [ ] Restore drill executed in isolation with recorded RPO/RTO evidence (`STAGING_TEST_RESULTS.md` §5 #30).
- [ ] Migration review: release's migrations are expand/contract (`BACKEND_DEPLOYMENT.md` §5); destructive changes have two-release approval.

## 4. Staging acceptance (Step 18 — `STAGING_TEST_RESULTS.md` §5 pending matrix)

- [ ] All 30 staging checks executed with recorded evidence, including queue processing, scheduler freshness, the 7 e-mail message types, monitoring capture of a real induced failure, alarm delivery, and restore drill.
- [ ] Security: cross-tenant denial, suspended-account restriction, expired-session handling re-verified in staging.

## 5. Domain & HTTPS

- [ ] DNS planned per `DOMAIN_HTTPS_SETUP.md` (changes only at go-live, with user approval).
- [ ] Certificates valid + renewal automation verified for both hosts.
- [ ] Cookie/CORS contract verified (`/sanctum/csrf-cookie` Secure flags; credentialed origin only).

## 6. Operations

- [ ] Supervisor running workers (`erp-backend:*` active); cron scheduler installed; `schedule:list` matches `routes/console.php` table.
- [ ] External uptime monitor + alert routing to authorized operators configured; monitoring shows Healthy.
- [ ] Rollback runbook rehearsed (`ROLLBACK_PLAN.md` §2 steps executed in staging).
- [ ] Incident ownership assigned (`docs/incident-response.md`).

## 7. Controlled launch sequence (only after §0–§6)

1. Confirm written deployment approval + release record (approver name).
2. Confirm fresh verified production backup exists.
3. Deploy verified release artifact (same commit that passed staging).
4. Run approved migrations with `BACKUP_EVIDENCE` present (`backend-release.sh` enforces).
5. Restart workers; reload fpm.
6. Post-deploy smoke 6/6 green (`post-deploy-smoke.sh`).
7. Verify platform + business authorized login (read-only first).
8. Verify critical read-only operations (tenants list, health dashboards, reports read) before enabling traffic.
9. Enable public traffic only as planned (DNS/approval).
10. Monitor errors and operational status for the agreed observation window; file the first incident-response review if Disabled states appear.

## 8. Explicitly out of scope today

- Live production deployment, DNS changes, paid service registrations, credential rotation, production data creation — none were executed; all remain user-approval-gated.
