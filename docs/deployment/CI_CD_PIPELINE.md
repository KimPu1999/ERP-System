# CI/CD Pipeline — Enterprise ERP Cloud

Existing workflow files verified: `.github/workflows/ci.yml`, `.github/workflows/deploy.yml`, contract doc `docs/deployment-runner-contract.md`.

## 1. Pull-request / push CI (existing `ci.yml`, unchanged)

**API job** (PHP 8.4.1, postgres:17 service, env `APP_ENV=testing`, DB `erp_test`):
`composer audit` → `composer lint` → `php artisan migrate --force` → `php artisan test`.

**Web job** (Node 22): `npm ci` → `npm audit --audit-level=high` → `npm run check` → `npm run lint` → `npm run build` → `npm run test -- --run`.

Executed parity exists: today's local suite reproduced the same gates with identical results (176 passed / 842 assertions; web: typecheck+lint+4 vitest+build — see `STAGING_TEST_RESULTS.md`).

## 2. Release pipeline (existing `deploy.yml`, gated)

`workflow_dispatch` with `staging|production` choice + concurrency group `deploy-<env>`:

1. Release documentation gate (`docs/release-checklist.md`, `docs/backup-restore.md` must exist).
2. Frontend artifact build (`npm install && npm run check && npm run build`).
3. API artifact validation (`composer validate`, install, lint, route export `route:list --json`).
4. Deployment contract check: requires the `DEPLOYMENT_RUNNER_URL` environment secret — the actual server-side execution is performed by a protected deployment runner, because credentials and live infrastructure commands deliberately never enter this repository's workflows (secret hygiene rule).

GitHub **environment protections** (to be configured in repo settings by the operator):
- `staging`: auto-deploy eligible; reviewers optional.
- `production`: required reviewers (named approver matches `docs/release-checklist.md`), plus restriction that only `main` runs the workflow.
- Least-privilege: runners get no DB or server credentials; the environment secret references the runner URL only.

## 3. Environment-specific responsibilities decided at execution time

| Stage | Who/what executes | Verification |
|---|---|---|
| Staging deploy | protected runner (via `DEPLOYMENT_RUNNER_URL`) consumes artifact + `infrastructure/deploy/backend-release.sh` with `MIGRATE=1` after backup evidence | post-deploy smoke 6/6 + queue/scheduler verified |
| Production deploy | same runner, `production` GitHub environment approval required | rollback readiness + backup verification + smoke + monitoring green |

Smoke and queue checks live in `infrastructure/deploy/post-deploy-smoke.sh` and `QUEUE_SCHEDULER_SETUP.md`.

## 4. Guardrails encoded in the pipeline

- No `migrate:fresh`/`rollback` anywhere in any workflow.
- No secrets printed (`deploy.yml` checks presence only with `test -n`).
- Release docs + runner contract verification are blocking gates, not decoration.
- Production migration apply requires the explicit release checklist + written approver, per `docs/release-checklist.md` (named "A named approver records the release decision").
