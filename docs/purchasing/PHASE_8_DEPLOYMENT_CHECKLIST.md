# Purchasing Phase 8 Deployment Checklist

Status: NOT READY for production deployment.

- [ ] Review additive migrations and perform a staging dry run.
- [ ] Confirm `APP_DEBUG=false`, secure cookies, HTTPS and database TLS.
- [ ] Confirm Sanctum/session/CORS/security-header settings for the target domain.
- [ ] Verify least-privilege PostgreSQL role and RLS behavior on staging.
- [ ] Run the full Laravel/PostgreSQL suite in CI and archive dependency audit results.
- [ ] Add and pass Phase 6 return/credit/refund and Phase 7 settings/automation tests.
- [ ] Complete two-tenant RBAC/IDOR/direct-API verification.
- [ ] Implement and verify actual automation behavior before enabling rules.
- [ ] Run representative performance/load and export benchmarks.
- [ ] Complete browser responsive/dark-mode regression.
- [ ] Execute encrypted backup and isolated restore rehearsal; record RPO/RTO.
- [ ] Confirm Redis workers, scheduler, failed-job alerts and log redaction.
- [ ] Complete UAT with named business reviewers; do not infer sign-off.
- [ ] Approve rollback/runbook and obtain explicit human release approval.

The deploy workflow is manually triggered/protected; this audit did not invoke it.
