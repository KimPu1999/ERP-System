# Deployment Runner Contract

The repository workflow performs release validation and intentionally does not contain provider-specific credentials or infrastructure commands. The protected deployment runner for `staging` and `production` must:

1. Consume the checked-out commit and the built frontend/API artifacts.
2. Validate production configuration before changing traffic.
3. Run forward-only Laravel migrations with a backup and migration lock.
4. Deploy API and frontend atomically or keep the previous release available.
5. Verify `/api/v1/health`, database connectivity, cache connectivity, and a signed authenticated smoke request.
6. Switch traffic only after health checks pass.
7. Roll back application artifacts and traffic on failed health checks; never silently roll back a successful database migration.
8. Publish commit, migration, health, rollback, and operator identity to the release record.

The workflow requires the protected `DEPLOYMENT_RUNNER_URL` environment secret. This is a release gate, not a substitute for configuring the actual runner.
