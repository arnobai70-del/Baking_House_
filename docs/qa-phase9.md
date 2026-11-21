# Phase 9 — staging preparation and production-readiness record

Local verification on 2026-11-21 (Windows, PHP 8.3, SQLite, `php -S`, `APP_DEBUG=false`). **Nothing was deployed.**

## 1. Automated checks

| Check | Result |
|---|---|
| PHPUnit (`php artisan test`) | 371 tests, 2035 assertions, all passing |
| Laravel Pint | passed |
| Composer audit | no security vulnerability advisories |
| Prototype tests (`npm test`) | 20 / 20 passing (weight-pricing parity file unchanged) |
| `php artisan view:cache` | all Blade templates compile |
| `php artisan app:readiness` (local) | 0 failures, 16 warnings (expected for a dev machine) |
| `php artisan app:readiness --target=staging` (on this dev machine) | correctly FAILS: debug on, http URL, HTTPS not forced, insecure cookie, no gate, no storage link, no cron, demo super admin without MFA |

New Phase 9 tests: `LocalizedUrlsTest` (language URLs, hreflang, sitemap, robots, switcher), `CustomerAddressesTest`
(6 tests: one default, isolation, limit, checkout pre-fill, save once, never for business buyers or guests),
`SecurityHardeningTest` (7 tests: headers, CSP, no inline scripts in any view, debug skip, staging gate, forced HTTPS, readiness).

## 2. Browser verification (local Edge, DevTools protocol)

Each page × viewport (360, 390, 768, 1024, 1366 px) is checked for horizontal overflow, CSP violations and JavaScript errors,
and a screenshot is taken.

| Audience | Pages | Result |
|---|---|---|
| Guest (EN + `/bn`) | home, shop, product, `/bn`, `/bn/shop`, `/bn/product/…`, cart, contact, business landing, login, register, order tracking | 60 / 60 clean |
| Business owner | account, saved addresses, profile, business dashboard, bulk order, quotations, orders, checkout | 40 / 40 clean |
| Reseller | dashboard, price list | 10 / 10 clean |
| Admin (MFA) | dashboard, orders, products, reports, CRM, media, pages | 35 / 35 clean |

`/admin/staff` correctly returns 403 for the admin role (super admin only).

### Defects found and fixed
1. **Hidden mega menu widened the page at 360–390 px** → hidden below 960 px (the drawer replaces it).
2. **Signed-in header overflowed by 25–127 px on phones** (Admin + Log out buttons) → below 560 px they move into
   the menu drawer (My account, Admin, Log out).
3. **Portal / admin tables stretched the whole page** → grid columns get `min-width: 0`, and `.table-wrap` contains
   absolutely positioned screen-reader labels (they escaped the scroll wrapper).

## 3. Security headers (verified by test and in the browser)

CSP with scripts from this site only and zero violations on all pages above; inline scripts moved to
`public/js/admin-charts.js` and `public/js/print.js`. HSTS only over HTTPS. Details: `staging-runbook.md` §5.

## 4. Bangla

635 strings, all in `docs/bangla-review.csv` as **NEEDS NATIVE-SPEAKER APPROVAL** (12 added in Phase 9). Demo catalog
names without a Bangla translation fall back to English (e.g. a subcategory name in a `/bn` breadcrumb).

## 5. Not measured / caveats

- Real-network performance and TLS behaviour need the staging server; Phase 8 figures are local lab numbers.
- The HTTP Basic gate, trusted proxies and HSTS were verified by tests, not behind a real load balancer.
- Screen-reader walkthrough on real devices (TalkBack / VoiceOver) is still a staging task.
