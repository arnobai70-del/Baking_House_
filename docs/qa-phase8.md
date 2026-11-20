# Phase 8 — QA, performance, accessibility and SEO record

Measured on 2026-11-20 on the development machine (Windows 11, local SQLite with the illustrative demo catalog).
Lab measurements only: no field (real-user) data exists yet because the site is not deployed.

## 1. Performance (Core Web Vitals, lab)
Harness: locally installed Microsoft Edge driven over the DevTools protocol (no third-party tools).
Mobile profile ≈ Lighthouse mobile: 412×823 @2.625x, 4× CPU slowdown, 150 ms RTT, 1.6 Mbps down.
Desktop: 1350×940, no throttling. Median of 5 runs. INP needs real interactions, so the lab proxy is
Total Blocking Time (TBT, long tasks after first paint).

### Production-like server (PHP OPcache + `php artisan optimize`), A/B against `main` (before Phase 8)
| Page | Mobile LCP before → after | Desktop LCP before → after | CLS | TBT |
|---|---|---|---|---|
| Home | 1.28 s → 1.09 s | 0.43 s → 0.41 s | 0 | 0 ms |
| Shop | 1.10 s → 1.25 s | 0.39 s → 0.42 s | 0 | 0 ms |
| Product | 1.11 s → 1.09 s | 0.41 s → 0.42 s | 0 / 0.007 | 21 ms |
| Business landing | 1.07 s → 1.10 s | 0.36 s → 0.34 s | 0 | 0 ms |
| Contact (new) | — → 1.03 s | — → 0.38 s | 0 | 0 ms |

Targets (LCP ≤ 2.5 s, CLS ≤ 0.1, INP ≤ 200 ms → TBT proxy) are met in this lab setup on every page measured.
Differences between before and after are within run-to-run noise.

### Server response (TTFB, median of 5, curl)
| Setup | Home | Shop | Product |
|---|---|---|---|
| `php artisan serve` (no OPcache, no caches) | 460 ms | 486 ms | 577 ms |
| PHP built-in server + OPcache + `optimize` | 61 ms | 73 ms | 59 ms |

The development server (`php artisan serve`) is not representative: on it the home page measured 2.35 s
(baseline) and 3.9 s (later run) mobile LCP, driven by 1.5–1.7 s server time. Production must run PHP-FPM
with OPcache and `php artisan optimize`.

### Database
Shop / search went from 31 to 12 queries and category pages from 32 to 20 (category facet N+1 removed).
`PageAuditTest::test_catalog_pages_use_a_bounded_number_of_queries` fails if the shop query count grows with
the number of products or categories.

### Caveats
- No real product photography exists yet; when approved photos are added the LCP element on product and
  shop pages becomes an image. Responsive AVIF / WebP / JPEG renditions with `srcset`, width / height,
  `fetchpriority="high"` on the main product photo and lazy loading elsewhere are in place, but LCP must be
  re-measured with real photos on the staging server.
- Measurements are on loopback; a real network, TLS and CDN choice will change TTFB.

## 2. Accessibility (WCAG 2.2 AA targets)
Automated in CI (`tests/Feature/PageAuditTest.php`), every page for its role (guest, retail, business owner,
reseller, super admin; 95 pages): `<html lang>`, title, exactly one `<h1>`, alt on every image, a label for
every form field, an accessible name for every link / button / summary, no duplicate ids.
Found and fixed: duplicate `id="q"` on 9 pages (header search vs page search), missing `<h1>` on combo pages,
untranslated screen-reader labels, form errors not linked to fields (now `aria-describedby`).

Browser audit (Edge, 390 px mobile viewport, reduced motion) for contrast 1.4.3 and target size 2.5.8 on
home, shop, product, cart, contact, business, login, account, business portal and 8 admin pages:
- Contrast failures before → after: 8 → **0** (muted text token #617481 → #5a6c79, teal text on teal tint
  #087d88 → #076f79, mobile tab-bar active item now navy with an orange indicator).
- Targets under 24 px fixed: language buttons, "view all" links, disclosure toggles, checkboxes (20 px inside a
  32 px label). Remaining flags are product-card titles whose whole card is the click target (false positive).
- Focus: visible teal focus ring; orange on dark (navy / teal) surfaces.
- Motion: every animation uses duration tokens that become 0 ms under `prefers-reduced-motion`; page
  cross-fades only apply when motion is welcome.

Not yet done (needs people, not code): screen-reader walkthrough (NVDA / TalkBack) and keyboard-only
walkthrough by a tester; native Bangla review.

## 3. SEO
- Indexing is **off** (`SEO_INDEXING_ENABLED=false`): every response carries `X-Robots-Tag: noindex, nofollow`
  (new, also covers CSV / images), pages carry `noindex`, robots.txt disallows everything. Development and
  staging stay blocked.
- When enabled: private paths (`/account`, `/admin`, `/business/`, `/reseller/`, checkout, orders …) stay
  noindex. Fixed: `/business` and `/reseller` landing pages were blocked by a prefix rule although they are
  in the sitemap.
- Canonical URLs on all storefront pages (paginated canonicals keep the page number), XML sitemap (home, shop,
  landing pages, contact, published information pages, active categories, visible products), breadcrumb and
  Product JSON-LD (offers in BDT), editable SEO title / description for products, categories and pages,
  alt text in English and Bangla for photos.
- Bangla SEO: language is chosen per session today (D-030). Before enabling indexing with Bangla content,
  move to URL-prefixed locales with hreflang — open decision.

## 4. Bangla
623 storefront / portal strings, Western digits enforced by test (3 stale strings with Bangla digits removed),
placeholders verified by test, English fallback for untranslated catalog fields. All strings are listed in
`docs/bangla-review.csv` as **NEEDS NATIVE-SPEAKER APPROVAL**. Product and category names in the demo catalog are
illustrative. Admin screens stay English (owner decision, Phase 4 authorization).

## 5. Production deployment checklist (not performed — needs separate authorization)
1. PHP 8.3 FPM with OPcache (validate_timestamps=0), GD with WebP (AVIF optional), HTTPS only.
2. `APP_ENV=production`, `APP_DEBUG=false`, new `APP_KEY`, MySQL, `SESSION_SECURE_COOKIE=true`.
3. `php artisan migrate --force`, `php artisan db:seed --class=RolesAndPermissionsSeeder --force`, `php artisan storage:link`, `php artisan optimize`.
4. Scheduler (`schedule:run` every minute) and a queue worker if mail is queued.
5. Web server: gzip / brotli for HTML / CSS / JS; `Cache-Control: public, max-age=31536000, immutable` for
   `/css`, `/js` (URLs carry a content hash) and `/storage/media`.
6. Keep `SEO_INDEXING_ENABLED=false` until the launch is approved; `PAYMENTS_MODE=test` until providers are contracted.
7. Backups for the database and `storage/app` (private originals of product photos).
