# Staging runbook and production-readiness checklist (Phase 9)

> **Not executed.** Staging and production deployment both need separate owner authorization.
> This file describes what to do once authorization is given. Live payments stay off in every environment
> (`PAYMENTS_MODE=test`) until provider contracts exist and the owner approves them.

## 1. Server requirements

| Item | Requirement |
|---|---|
| PHP | 8.3 FPM with OPcache (`opcache.validate_timestamps=0` in production), extensions: pdo_mysql, mbstring, intl, gd (with WebP; AVIF optional), fileinfo, openssl |
| Database | MySQL 8 / MariaDB 10.6+ (SQLite is a readiness FAIL for production) |
| Web server | Nginx or Apache, document root `platform/public`, HTTPS only (HTTP → HTTPS redirect), gzip / brotli for HTML, CSS, JS |
| Static caching | `Cache-Control: public, max-age=31536000, immutable` for `/css`, `/js` (URLs carry a content hash) and `/storage/media` |
| Cron | `* * * * * cd /path/to/platform && php artisan schedule:run >> /dev/null 2>&1` |
| Queue | Not required today (no queued jobs); add a worker when a mail / SMS provider is connected with queued delivery |
| Backups | Daily database dump and `storage/app` (private originals of product photos), stored off-server, restore tested |

## 2. Environment

1. Copy `platform/.env.staging.example` to `.env` on the server, **never** into Git.
2. Fill: `APP_URL` (https), database credentials, `TRUSTED_PROXIES` (load-balancer IPs), `STAGING_GATE_USER`.
3. Tester password: `php artisan tinker --execute 'echo bcrypt("<password>");'` → `STAGING_GATE_PASSWORD_HASH`.
   Share the password with testers out of band (not in Git, chat logs or tickets).
4. `php artisan key:generate` (once per environment; a new key logs everyone out and invalidates encrypted data).
5. Keep: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `FORCE_HTTPS=true`, `SEO_INDEXING_ENABLED=false`,
   `PAYMENTS_MODE=test`, `MAIL_MAILER=log`, `SMS_DRIVER=log`.
6. Seller / bank fields stay blank until the owner supplies verified details. Nothing is invented.

## 3. Deploy steps (staging)

```bash
git fetch && git checkout <approved tag or main commit>
cd platform
composer install --no-dev --optimize-autoloader     # Laravel Boost (dev tool) is NOT installed
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan storage:link
php artisan optimize                                  # config, routes, views, events cached
php artisan app:readiness --target=staging            # must report 0 failure(s); exit code 1 stops the deploy
```

First deploy only: create the owner's super-admin account (not the demo seeder). `LocalDemoSeeder` may be used on staging for
testers but **never** on production (readiness fails production when `@example.test` accounts exist).
The super admin must enrol two-step verification at first sign-in (readiness checks it).

Rollback: check out the previous commit, `composer install --no-dev`, `php artisan optimize`. Migrations in Phases 1–9 are additive.
Run `php artisan migrate:rollback` only after a database backup.

## 4. Readiness preflight

`php artisan app:readiness --target=staging|production` reads configuration and the database and changes nothing.

| Result | Meaning |
|---|---|
| PASS | Correct |
| WARN | Accepted for now, listed in the report (e.g. mail / SMS log-only, seller details pending, OPcache seen from CLI) |
| FAIL | Blocks the deploy (exit code 1) |

Blocking on staging / production: debug on, missing key, non-https URL, HTTPS not forced, insecure cookie, live payments,
missing storage link, no GD WebP, scheduler heartbeat older than 5 min, permissions not seeded, no super admin, a super admin
without MFA. Staging also: indexing on, staging gate off. Production also: SQLite, demo accounts.

The scheduler writes a heartbeat every minute; run readiness again a couple of minutes after enabling cron.

## 5. Security controls in place

- **Headers on every response:** `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy:
  strict-origin-when-cross-origin`, `Permissions-Policy` (camera, microphone, geolocation, payment off),
  `Cross-Origin-Opener-Policy: same-origin`, HSTS (1 year, subdomains) over HTTPS only.
- **Content-Security-Policy** (off only when `APP_DEBUG=true`): `default-src 'self'`; scripts only from this site (no inline);
  styles self + inline; fonts self-hosted (`public/fonts`, Phase 9.5); images self + `data:`; `frame-ancestors 'none'`; `object-src 'none'`;
  `form-action 'self'`. **When live payment adapters are built, add the provider hosts to `form-action` / `connect-src`.**
- **Staging gate:** HTTP Basic, bcrypt hash, constant-time user check, fails closed when no hash is set, `noindex` on the 401.
  `/up` (health check) and `/api/v1/webhooks/*` stay open (provider signatures are verified there).
- **Robots:** `X-Robots-Tag: noindex, nofollow` on every response while indexing is off.
- Existing controls: staff MFA (TOTP), role / permission separation, rate limits (login, API, checkout, quotations, uploads,
  contact), audit log, private storage for unapproved photos, Composer audit in CI.

## 6. Staging acceptance (for testers)

- Guest, retail, business, reseller, admin and super-admin journeys in English and Bangla (`/bn/…`).
- Checkout in test mode for each payment method, saved addresses, order tracking.
- Photo upload → second-reviewer approval → public rendition.
- Real-network performance measurement (Phase 8 figures are local lab numbers only).
- Phones: 360 / 390 px and a tablet. Local verification passed (see `qa-phase9.md`).

## 7. Production (separate authorization)

Everything above with `APP_ENV=production`, `STAGING_GATE_ENABLED=false`, then the launch blockers in `roadmap.md`
resolved, then `SEO_INDEXING_ENABLED=true` only on the owner's launch approval.
