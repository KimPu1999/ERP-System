# Phase 12 release checklist

- [ ] `php artisan erp:release-check` passes with production configuration.
- [ ] Dependency audits report no unaccepted high/critical findings.
- [ ] Tenant isolation and IDOR/RBAC regression tests pass using two tenants and a non-owner DB role.
- [ ] Accounting balance, period locking, reversal, and duplicate-posting tests pass.
- [ ] Inventory concurrent posting and duplicate receiving tests pass.
- [ ] Encrypted backup and isolated restore drill passes; RPO/RTO evidence recorded.
- [ ] Staging HTTPS, email, Redis, database TLS, Docker image scanning, logs, metrics, alerts, queues, and scheduler are operational.
- [ ] Staging end-to-end workflows pass.
- [ ] Migration dry run and rollback plan are reviewed; no destructive migration is unapproved.
- [ ] A named approver records the release decision.

Until every gate above is checked with evidence, production release is blocked.
