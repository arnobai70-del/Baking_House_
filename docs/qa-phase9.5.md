# Phase 9.5 — UI/UX refinement record

Implementation of the owner-approved groups G1–G10 from `docs/ui-audit-phase9.5.md` (2026-11-21/22). Local verification
only: **nothing was deployed**, payments stay in test mode. Evidence is kept locally in `qa-artifacts/` (not committed):
`phase-9.5-audit/` (before), `phase-9.5-after/G1…G10/` (per group), `phase-9.5-final/` (final full pages, 210 responsive
screenshots, raw measurements).

## 1. What changed

| Group | Change | Main files |
|---|---|---|
| G1 | Self-hosted Noto Sans (Latin, 24 KB variable) + Taka glyph (4 KB) + Noto Sans Bengali (108 KB, Bangla pages only), SIL OFL; Google Fonts removed (CSP `font-src 'self'`); headings 600, prices 700, no 800; sentence-case labels (no uppercase tracking); one `.eyebrow`; tabular figures for prices, weights, KPIs, tables; footer band fixed; 24 px targets | `tokens.css`, `app.css`, `storefront.css`, `layouts/_fonts.blade.php`, `public/fonts/`, `SecurityHeaders.php` |
| G2 | Orange only on the purchase path and the hero "Shop now"; 43 admin / form buttons navy; secondary actions outline | views, `app.css` |
| G3 | Shorter sentence-case headline + one-line offer; hero image slot replaced by a department panel with live counts (same query as shop filters); category tiles list real aisles; audience panels and business cards without empty photo boxes or colour variants; section order unchanged | `home.blade.php`, `HomeController.php`, `app.css` |
| G4 | One badge per card (Sale > Low stock > Quote only > Featured); "Popular" → "Featured" (sort label "Featured first", URL value unchanged); card action "View product" / "Choose weight" without cart icon; search breadcrumb; cart thumbnails icon-only | `product-card.blade.php`, `shop.blade.php`, `ProductListing.php` |
| G5 | Add to cart + Buy now; "Need a larger quantity? Request a quote" link opens the disclosure (new `initQuoteLinks`, pricing JS untouched); larger calculated price; quiet tier note; single disclosure marker; 4:3 placeholder on phones | `product.blade.php`, `storefront.js`, `app.css` |
| G6 | One page heading (title + meta) for account, business, reseller, admin; no greeting or coloured banner; duplicate shortcut cards removed; plain-language subtitles | `account/index`, `portal/layout`, `portal/dashboard`, `portal/bulk`, `portal/prices` |
| G7 | Admin and portals as workspaces: no category bar, product search, header cart, trust strip or marketing footer; "View store" and workspace label; mobile bar Dashboard / Store / Account; admin sidebar 240 px; four admin counts gated by permission (orders needing action, low-stock SKUs, photos awaiting approval, open quotation requests) | `layouts/app.blade.php`, `layouts/admin.blade.php`, `Admin/DashboardController.php`, `admin/dashboard.blade.php` |
| G8 | Money cards 4 + 4 with net revenue spanning two; no bar for zero values; breakdown tables in a 1 : 1 split | `admin/reports/overview.blade.php`, `storefront.css` |
| G9 | Press scale 0.98, one colour transition for interactive chrome; no press / lift under reduced motion; no new entrance animations or libraries | `app.css`, `storefront.css` |
| G10 | Western digit in the demo Bangla wok name (seeder + that one local record) | `DevelopmentCatalogSeeder.php` |

Not changed: routes, APIs, pricing / weight engine and `weight-pricing.js`, inventory, checkout, payments, roles and
permissions, CRM logic, media approval, SEO / language routing. 18 unused Bangla strings removed, 16 added (633 total, all
in `bangla-review.csv` for native review).

## 2. Checks

| Check | Result |
|---|---|
| PHPUnit | 373 tests passing (2 new: admin counts and their permission gating) |
| Pint, `view:cache` | passed |
| Composer audit | no advisories |
| Prototype tests | 20 / 20 |
| Browser: overflow, CSP, JS errors at 360 / 390 / 768 / 1024 / 1366 px | **210 / 210 clean** (guest EN + BN, retail, business, reseller, admin) |
| Accessibility: WCAG AA contrast + 24 px targets, 15 pages incl. checkout, portals, admin | **0 contrast failures, 0 small targets** (baseline: 1–13 small-target warnings per page) |

## 3. Performance (lab, local Edge, alternating rounds, 6 runs per cell)

Method: production-like local server (OPcache, `Cache-Control: immutable` for `/css`, `/js`, `/fonts` as in the runbook),
`main` on a second port as reference. Mobile profile ≈ Lighthouse (4× CPU, 150 ms RTT, 1.6 Mbps). "Cold" = empty cache,
"warm" = repeat view. "Font used" = the family the browser actually rendered for the page heading.

**Font loading decision.** Preload vs no preload were measured in alternating rounds (raw data `font-ab-summary.txt`,
`font-hybrid-summary.txt`):

- English pages: a Latin preload delayed first-visit LCP (desktop home p75 1068 vs 864 ms; product pages slower on both
  devices) while still not guaranteeing the font on first view (33–100 %). Without preload, first views use the system
  font and every later view uses Noto Sans from cache (100 %), with CLS 0. → **no preload on English pages.**
- Bangla pages: without preloads the late Bengali swap shifted the layout (mobile `/bn` CLS p75 **0.183**, a Core Web
  Vitals failure); with both preloads CLS 0.000 and the best Bangla home LCP (mobile p75 2452 vs 2920 ms for Bengali-only
  preload). → **preload Latin + Bengali on Bangla pages.**

**Final configuration vs `main`** (median LCP, p75 in brackets; CLS p75):

| Page | Device | Visit | main | Phase 9.5 |
|---|---|---|---|---|
| `/` | mobile | cold | 1602 (3276) ms, CLS 0 | 2094 (2556) ms, CLS 0 |
| `/` | desktop | cold | 508 (584) ms | 710 (864) ms |
| `/` | mobile | warm | 386 (576) ms | 350 (452) ms |
| `/product/…` | mobile | cold | 1192 (1212) ms | 1298 (1676) ms |
| `/product/…` | mobile | warm | 430 (708) ms | 472 (572) ms |
| `/bn` | mobile | cold | 2234 (2360) ms, **CLS 0.025** | 2174 (2544) ms, **CLS 0.000** |
| `/bn` | desktop | cold | 1022 (1160) ms, **CLS 0.028** | 842 (1012) ms, **CLS 0.011** |
| `/bn/product/…` | mobile | cold | 1304 (1556) ms | 1792 (1852) ms |
| all pages | both | warm | 296–450 ms | 268–506 ms, web font used 100 % |

Honest reading: repeat views are unchanged; Bangla pages lost their layout shift; **first visits on English pages and the
Bangla product page are slower by about 0.1–0.6 s median** (40 KB more: fonts plus the homepage department panel) — all
p75 values stay below 2.6 s on the throttled mobile profile, and every page keeps CLS ≤ 0.011. Real-network numbers must
be taken on staging.

## 4. Remaining UI / launch items

- Real licensed photography (labelled placeholders remain by policy), native Bangla review (633 strings), Bangla names for
  sub-categories (English fallback in tiles).
- Staging measurement on a real network and device screen-reader pass (TalkBack / VoiceOver).
- Launch blockers in `roadmap.md` are unchanged (payments, mail / SMS and password reset, legal / VAT details, policies).
