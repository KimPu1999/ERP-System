# Suppliers Phase 7 Rollout Checklist

- [ ] Review additive migration 2026_10_30_000041_suppliers_phase7_intelligence.php.
- [ ] Run the migration in an isolated staging database.
- [ ] Verify tenant context and RLS policies with the production database role.
- [ ] Configure and observe queue workers before enabling scheduled exports.
- [ ] Configure scheduler health checks and bounded retries.
- [ ] Review report permissions and recipient authorization.
- [ ] Confirm private report storage and retention.
- [ ] Execute supplier portal access review with business owners.
- [ ] Run database-backed integration and concurrency tests.
- [ ] Complete browser responsive and dark-mode UAT.
- [ ] Obtain human approval before enabling automation rules or deploying.

Current assessment: CONDITIONALLY READY for staging review; NOT production-ready until the unchecked operational and database tests are completed.
