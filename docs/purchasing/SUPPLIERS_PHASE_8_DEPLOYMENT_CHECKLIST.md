# Suppliers Phase 8 Deployment Checklist

## Current decision: NO-GO

- [ ] PostgreSQL integration suite passes in isolated CI/staging.
- [ ] Supplier portal cross-tenant and cross-supplier tests pass with restricted RLS role.
- [ ] Bank security and concurrent verification tests pass.
- [ ] Catalog, contract and PO snapshot tests pass.
- [ ] Queue workers and scheduler execute with tenant scope and idempotency.
- [ ] Supplier report exports are reviewed and authorized.
- [ ] Security findings are reviewed and accepted.
- [ ] Browser accessibility, mobile and dark-mode checks pass.
- [ ] Performance baseline is captured.
- [ ] Backup restore is executed in isolation.
- [ ] Staging smoke tests pass.
- [ ] Business UAT reviewers sign off.
- [ ] Rollback owner and incident contacts are assigned.
- [ ] Explicit deployment authorization is granted.

Never run destructive migrations. Do not deploy automatically.
