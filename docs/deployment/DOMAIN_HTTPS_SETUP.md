# Domain & HTTPS Setup — Enterprise ERP Cloud

Detailed as a checklist: DNS is NOT touched during preparation (Phase 11 hard rule). Execute after explicit user approval, in this order.

## 1. Planned routing

| Hostname | Component | Notes |
|---|---|---|
| `app.example.com` | React frontend (Vercel or equivalent static host) | SPA at `/`; certificate managed by the frontend host |
| `api.example.com` | Laravel API (nginx → php-fpm) | TLS via server-side ACME automation |
| (Optional later) `portal.example.com` | Supplier portal paths remain under `/api/v1` supplier-portal routes; no separate origin needed | `ResolveSupplierPortalTenant` middleware exists |

`example.com` below is a placeholder — substitute the registered domain at execution time (never hardcode it into code).

## 2. DNS records (pre-verified, apply only at go-live)

| Record | Type | Value | TTL | When |
|---|---|---|---|---|
| `app.example.com` | A / CNAME | frontend host (Vercel edge or chosen IP) | 300s | staging first |
| `api.example.com` | A | VPS IPv4 (and AAAA if IPv6 exists) behind firewall allowing 80/443 | 300s | staging first |
| DNSSEC/SPF/DKIM/DMARC records | TXT | per `EMAIL_STORAGE_SETUP.md` §3 | — | before first live e-mail, staging include: staging subdomain prefix |

Ownership verification: add the frontend host's TXT/CNAME challenge record first to a staging hostname; never point apex at staging.

## 3. Certificates

| Environment | Issuer | Renewal |
|---|---|---|
| Frontend (app host) | Automatic (Vercel/managed) | Automatic |
| API server | ACME (`certbot`/`acme.sh`) HTTP-01 via port 80 or DNS-01 | daily systemd timer + renewal hook `systemctl reload nginx`; monitor expiry externally (within 30 days threshold) |

Nginx template: `infrastructure/nginx/api.conf` (TLS blocks commented; SSL parameters: TLSv1.2+1.3, HSTS `max-age=15552000`, HTTP→HTTPS redirect, ACME webroot passthrough).

## 4. Cookie & CORS contract across origins

- `SESSION_SAME_SITE=lax` + `SESSION_SECURE_COOKIE=true` + `SESSION_ENCRYPT=true` (middleware-verified).
- `SESSION_DOMAIN=example.com` so both `app.` and `api.` subdomains administer the session safely; frontend client (`apps/web/src/lib/api.ts`) sends `credentials: include` to `VITE_API_ORIGIN`.
- Cookies work with `app.example.com` and `api.example.com`; never serve a frontend on `example.com` alongside an API on a different apex.
- `SANCTUM_STATEFUL_DOMAINS=app.example.com,api.example.com` — omit any wildcard list; the first-check default `localhost` must be removed in production (.env.production.example shows the target values).

## 5. Verification steps (staging first, then production)

| Test | Expected |
|---|---|
| `curl -I https://api.example.com/api/v1/health` | 200, valid cert, HSTS header |
| `curl -I http://api.example.com` | 301/308 → https |
| Browser: CSRF handshake | XSRF-TOKEN + session cookies are Secure/HttpOnly/SameSite=Lax |
| Login from `app.example.com` | authenticated (cookie round-trip succeeds) |
| Certificate renewal | forced renewal (`--force-renewal` in staging) completes; timer active; monitoring has expiry check |
| Mixed content | page loads only https assets (frontend bundle must point at https API) |
| Cross-origin preflight | preflight against `/api/v1/auth/login` from app origin → 2xx (post-deploy smoke gate #4) |
