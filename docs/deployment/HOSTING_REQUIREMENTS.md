# Hosting Requirements — Enterprise ERP Cloud

Derived only from verified repo facts. No provider prices are stated; obtain current price/suppliers at procurement time.

## 1. Application host (Option A — self-managed VPS)

| Item | Requirement | Source of truth |
|---|---|---|
| OS | Ubuntu 24.04 LTS or Debian 12 — supported, with PHP 8.4 packages available | CI parity (PHP 8.4.1) |
| PHP | 8.4 with `ext-bcmath`, `ext-pcntl`, `pdo_pgsql`, `ext-zip`, `phpredis`, FPM | `composer.json` (`php ^8.3`, bcmath), jobs use pcntl, `backend/Dockerfile` |
| Composer | v2, installed to `/usr/bin/composer` | `composer.lock` workflow |
| Web server | Nginx with the FastCGI (FPM) SAPI; `infrastructure/nginx/api.conf` template | repo |
| Process manager | Supervisor for `queue:work` (timeout 120 s ⇒ `stopwaitsecs` 130) | `infrastructure/supervisor/erp-backend.ini` |
| Scheduler | One cron entry executing `php artisan schedule:run` each minute, flock-serialized | `infrastructure/cron/erp-scheduler.cron`; scheduler commands defined in `routes/console.php` |
| Firewall | Only 80/443 public; SSH restricted to key auth; **no 5432/6379 exposure** | applicable tasking/spec security model + `docker-compose.yml` constraints |
| Unprivileged deploy user | `erp-deploy` (no sudo except whitelisted reloads); PHP-FPM pool to run as a separate unprivileged user | deployment-runner contract in `BACKEND_DEPLOYMENT.md` |
| Log rotation | logrotate for `/var/log/erp-backend/*`, nginx logs; log retention aligned to `platform:security-prune` behaviour | `routes/console.php` |
| Time sync | systemd-timesyncd or ntpd; DB writes use `now()`-style timestamps — clock skew affects audit ordering | Scheduler/audit immutability requirements |
| Storage layout | `/var/www/erp-api/{releases,shared,current}`; `shared/storage` + `shared/.env` | `infrastructure/deploy/backend-release.sh` |

## 2. PostgreSQL requirements (Option A)

| Item | Requirement |
|---|---|
| Version | PostgreSQL 17 (matches CI service pin, `postgres:17`) |
| TLS | `DB_SSLMODE=verify-full` (release-check requires `require|verify-ca|verify-full`) |
| Roles | `erp_app` (runtime, no DDL), `erp_migrator` (DDL, only during approved migrations), `erp_backup` (pg_dump, no writes) |
| Network |Private/VPC only; never public 5432 |
| Timeouts | `statement_timeout`, `idle_in_transaction_session_timeout` per `POSTGRESQL_PRODUCTION.md` |
| Backups | Encrypted offsite (`age`) + restore drill evidence |
| Extension/RFS constraints | RLS required — confirm the hosting tier allows RLS policies |

## 3. Frontend host

- Static hosting (`Vercel` in prepared docs) must support:
  - SPA rewrite fallback (`apps/web/vercel.json` rewrites `/.*` to `/index.html`);
  - immutable asset caching (vercel.json headers);
  - build via lockfile: `npm ci` → `npm run check && npm run lint && npm run build`.
- Preview/prod builds get `VITE_API_URL`/`VITE_API_ORIGIN` from the frontend host's env vars — **public values only** (see `FRONTEND_DEPLOYMENT.md`).

## 4. Redis (cache + queue)

- Private only; `CACHE_STORE=redis` + `QUEUE_CONNECTION=redis`.
- Password required in production (`REDIS_PASSWORD`); no public port.
- Persistence (RDB) desired for queue resilience; workers are retry-safe (`tries=3`).
- Sessions live in the database (`SESSION_DRIVER=database`), so a Redis flush does not log users out — but it **resets `withoutOverlapping` scheduler/worker locks and purchasing-automation hourly deduplication**, so never flush casually in production (`routes/console.php` references).

## 5. E-mail provider

A real SMTP/managed transport with verified sending domain (SPF/DKIM/DMARC). `MAIL_MAILER=log` is refused in production by `ValidateProductionConfiguration`. Requirements detailed in `EMAIL_STORAGE_SETUP.md`.

## 6. File storage

Ephemeral container storage is prohibited for uploads; use the private-disk pattern in `EMAIL_STORAGE_SETUP.md` (object storage) with authorization-gated download links.

## 7. Capacity planning initial sizing (staging baseline, to be revisited with monitoring data)

- Single app node suffices for Go-Live: nginx + fpm (2 workers) + 2 queue workers + DB on a managed/private instance.
- Do not add capacity claims beyond actual measured load: `Platform Health` MonitoringService currently reports DB/cache/queue status; use it to observe saturation before resizing.
