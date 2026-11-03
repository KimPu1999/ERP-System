# RELEASE_OPERATIONS.md — Phase 12

## Reality check

There is **no automated deployment gate reached from the UI**. `release_records`
records verified release facts after the fact:

- `version`, `environment` (`staging | production`), `status` (`deployed | rolled_back | aborted`), `release_notes`, `known_issues`, `rollback_reference`, `deployed_at`, recorded_by.
- `GET /api/v1/platform/operations/releases` → `latest_version: 'not_configured'` until a record exists (tested); `POST` records are audited (platform super-admin).
- Overview card shows the latest record or `not_configured_until_release_records_are_registered`.

## Intended release workflow (process document)

```
Development → CI Validation (GitHub Actions) → Staging deployment
→ Integration tests → Security checks → Release approval (sign-off)
→ Production deployment → Post-release verification → release_records entry
```

Rules already visible in this phase:

- Producing a `release_records` row is not deployment — CI/CD triggers remain behind GitHub Actions approvals, never an ordinary UI button.
- Rollback: application rollback must respect schema compatibility (migrations are forward-only here); the **database is never rolled back destructively** — reversal follows the documented reversal procedures per financial modules instead.
- Deployment tokens never appear in the UI; CI secrets are never echoed.

## Verified commands (validation before release)

```
php artisan about
php artisan migrate:status
php artisan route:list
php artisan schedule:list
php artisan queue:failed
php -l <changed files>
DB_DATABASE=erp_test php artisan test     # 194 passed / 934 assertions on 2026-11-03
cd apps/web && npm run check && npm run lint && npm run test -- --run && npm run build
```

## Gap

No live staging/production pipeline is available in this environment — the staging step is documented, not executed. Deployment to real infrastructure remains an outstanding blocker.
