# Frontend Deployment — Enterprise ERP Cloud (apps/web)

React 19 + TypeScript + Vite 6 SPA. Prepared for static hosting (Vercel template committed at `apps/web/vercel.json`) with the backend on a separate origin.

## 1. Repository facts

| Fact | Value |
|---|---|
| Build command | `npm run build` (`tsc -b && vite build`) |
| Pre-build gates | `npm run check` (tsc), `npm run lint`, `npm run test -- --run` (vitest) — all green 2026-11-03, see `STAGING_TEST_RESULTS.md` |
| Env vars (public only) | `VITE_API_URL`, `VITE_API_ORIGIN`, optional `VITE_TENANT_ID` (`.env.example`) |
| SPA routing | React Router 7 — needs 200-with-index.html fallback for deep links/refreshes (`vercel.json` rewrites) |
| Bundle notes | Phase 10 vendor split: app chunk 295.62 kB gzip, echarts isolated (385.68 kB gzip) — verified by executed build today |
| ESLint/Playwright | 8-case browser suite (platform shell) green |

## 2. Build pipeline (host-agnostic)

```
npm ci                      # from lockfile — pinned, reproducible
npm run check               # typecheck must be zero-error (CI gate)
npm run lint                # eslint must be zero-error (CI gate)
npm run test -- --run       # unit tests must pass (CI gate)
VITE_API_URL=https://<api-host>/api/v1 \
VITE_API_ORIGIN=https://<api-host> npm run build
```

- Values of `VITE_*` are compiled into shipped JS: treat them as **public configuration only**. Backend secrets, DB credentials, personal tokens must never be prefixed `VITE_` (Step 7 guard, enforced by code review).
- No localhost URLs in any committed build config: `src/lib/api.ts` reads env at runtime; `.env*` files with real domains are nested in the frontend host's project settings, not the repository.

## 3. Vercel-specific procedure (planned — requires account creation approval)

1. Project root: `apps/web` (monorepo subdirectory; framework preset: Vite).
2. Install/build commands: `npm ci` → `npm run build` (as above); install from lockfile only.
3. Environment variables (Production + Preview): `VITE_API_URL`, `VITE_API_ORIGIN` set to the approved API origin — **staging and production separate values** (config: gluing envs together confuses CSRF origins; each env distinct).
4. SPA fallback: `apps/web/vercel.json` rewrites `/.*` → `/index.html` and adds nosniff/DENY/Referrer/Permissions/Strict-Transport-Security headers; hashed asset caching set immutable.
5. Custom domain: `app.example.com` → Vercel project domain (see `DOMAIN_HTTPS_SETUP.md`).
6. Verify HTTPS: certificate automatically issued with the Vercel domain.

## 4. Acceptance checks (run after each deploy, staging first)

| Check | How |
|---|---|
| Platform login page loads | browser: `https://app.example.com/platform/login` |
| Business login page loads | `https://app.example.com/login` |
| Deep link + page refresh (SPA fallback) | open any app deep-link URL then refresh (200, no 404) |
| /api connectivity | login flow actually authenticates against the configured origin |
| 404 handling | unknown route renders the application 404 view (client-side) |
| Cross-origin contract | CSRF endpoint returns cookies; `credentials: include` in api client (existing `src/lib/api.ts`) |
| EN / MM translation toggle + theme toggle + responsive layout | exercised by Playwright suite in staging gloves (mobile viewport cases exist) |

## 5. Verified-by-automation today (2026-11-03)

- `npm run check`, `npm run lint`, `npm run test -- --run`, `npm run build` all pass with exact recorded output in `STAGING_TEST_RESULTS.md`. The production build artifact now exists locally at `apps/web/dist/`.
- Caveat: `npm audit --audit-level=high` is enforced in CI; run it against final staging build to confirm no high/critical advisory remains unresolved.
