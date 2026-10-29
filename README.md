# enterprise-erp

Enterprise ERP Cloud is a modular-monolith SaaS foundation. Phase 11 adds separate platform subscriptions, entitlements, trials, provider webhooks, support access, tenant exports, and platform controls over the existing ERP domains. Commercial subscriptions and unrelated business modules are intentionally excluded.
ructure
adfafadf
```text
apps/web        React, TypeScript, Vite, Tailwind, shadcn/ui-compatible components
backend         Laravel 13 API, Sanctum, PostgreSQL, Redis
docs            setup and architecture notes
infrastructure  Docker Compose for local PostgreSQL and Redis
```

## Quick start

```bash
cp backend/.env.example backend/.env
cp apps/web/.env.example apps/web/.env
docker compose -f infrastructure/docker-compose.yml up -d

cd backend && composer install && php artisan key:generate && php artisan migrate && php artisan serve
cd apps/web && npm install && npm run dev
```

The API health endpoint is `GET http://localhost:8000/api/v1/health`.

See [docs/setup.md](docs/setup.md) for checks, environment variables, tenant context, and troubleshooting.
