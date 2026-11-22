# Phase 9.5 — UI/UX audit (before any change)

Audit of the rendered site at commit `252da67` (main), using the Unslop method (heydamir/unslop, CC BY 4.0, by
Damir Sokolovsky). Written before any change; G1–G10 were approved by the owner and implemented in Phase 9.5
(results, measurements and screenshots: `docs/qa-phase9.5.md`).

Evidence (local, not committed, under `qa-artifacts/`):
- `phase-9.5-audit/full/` — 100 full-page screenshots, 50 pages × 390 px and 1366 px (guest, retail, business, reseller, admin).
- `phase-9-baseline/screenshots/` — the 150 Phase 9 first-screen screenshots (360 / 390 / 768 / 1024 / 1366 px).
- `phase-9.5-audit/p95-baseline.json` — lab vitals; `phase-9-baseline/measurements/` — Phase 8 vitals.

Screenshot names below are `<audience>-<page>-<width>.png` in `phase-9.5-audit/full/`.

## 1. Verdict and direction

**Unslop verdict: light slop → polish mode.** The site is mostly designed, with a real brand and no gradients, glows,
emoji icons, gradient text or fake social proof. The signs that remain are habits: the orange accent is on nearly every
control, there are heavy 700–800 headings, uppercase tracked labels, placeholder labels repeated many times, and some
duplicated actions. Under polish mode the palette, layout direction and section order are kept. Only audited signs are
fixed, and bigger ideas are offered as options.

Direction (from the product, not from trends):
- **Product.** A Bangladesh supplier of bakery, cake, restaurant, Chinese-kitchen, packaging and commercial equipment
  goods, sold by the gram, the piece or the carton to home bakers and food businesses.
- **Character.** Exact, well-stocked, warm.
- **World.** A supplier's trade counter: scales, flour sacks, steel pans, carton labels, an order pad.
- **Moments.** (1) The weight calculator on product pages, where the scale readout and price are the richest thing on
  the screen. (2) On the homepage, the department map with real product counts. Everything else stays quiet.
- **Signature.** The scale readout: weights and per-kg prices set as tabular figures in one consistent "weigh label" style,
  used only where weight is involved (product page, cart line, card price for weight items).

Owner constraints that override the skill:
- No image generation. Labelled photo placeholders stay until approved real photos exist.
- No red; error states keep the existing approved error colour.
- No new claims, prices or availability.

## 2. Cross-site findings

| # | Finding | Where | Severity | Kind |
|---|---|---|---|---|
| X1 | **Orange is on almost every control.** It's on 19 card buttons per shop page, Apply filters, All Categories, hero buttons, three homepage CTAs and New product. The real primary action (Add to cart, Proceed to checkout) no longer stands out. | all pages; `guest-shop-1366`, `guest-home-1366` | High | Refinement |
| X2 | **Latin font is never loaded.** `--bh-font` names Inter, but only Noto Sans Bengali is requested, so English renders in each visitor's local fallback and looks different on every device. | `layouts/app.blade.php:38`, `tokens.css:32` | High | Refinement |
| X3 | **Heavy weights:** 800 on 5 rules and 700 on 16; hero, page titles and KPI figures are 800. Hierarchy comes from weight, not from size. | `app.css` | Medium | Refinement |
| X4 | **Uppercase tracked labels:** KPI labels, every table header, admin sidebar section titles, eyebrows, "PHOTO PENDING" / "PENDING" tags. `.eyebrow` is defined twice with different values. | `app.css:200,212,443,448,465`, `storefront.css:46,78` | Medium | Refinement |
| X5 | **Dead band under every footer** (~90 px on desktop). `.site-footer .container` (a 4-column grid with 40/32 px padding) also matches the copyright row. | all pages, bottom; `app.css:182,190` | Medium (bug) | Fix |
| X6 | **Admin and portals use the full storefront chrome:** category mega bar, product search, cart, "Nationwide delivery" trust strip and marketing footer appear around admin tables. | `admin-*-1366` | High (admin) | Refinement of layout use; no route change |
| X7 | **Three dashboard styles.** Retail account has a navy greeting banner and no sidebar; business has a teal banner and a sidebar; admin has a plain title and a sidebar. They look like three products. | `retail-account-1366`, `business-business-dashboard-1366`, `admin-admin-1366` | Medium | Refinement |
| X8 | **Badges:** "Popular" is shown for `is_featured`, a staff flag, so it is a popularity claim without data. Cards can stack two badges (Sale + Popular). | `components/product-card.blade.php:9` | Medium (honesty) | Copy |
| X9 | Small-target warnings (WCAG 2.5.8): topbar "Need help?" link 18 px tall, card name links 19 px, login checkbox 20 px. Contrast passes everywhere. | a11y run | Low | Fix |
| X10 | Demo data: one Bangla product name uses a Bangla numeral (`ওক ১৪"`), against the Western-digits rule. | `DevelopmentCatalogSeeder.php:151` | Low | Data fix |

## 3. Page-by-page findings

**Homepage** (`guest-home-1366`, `guest-home-390`)
- H1 is a 4-line title-case headline in 800 weight with two colours; it states the offer but slowly. → One sentence-case
  headline in two lines and a single colour, with the five department lines kept as the subline list. *Refinement.*
- Right half of the hero is a 480 px empty dashed box, "LICENSED HERO PHOTO PENDING", which is the first thing a visitor
  sees. → Option A: replace it with a code-built department panel (the 7 departments, real product counts, "sold by
  weight" and "bulk / quote" markers taken from the catalog). Option B: keep the placeholder but make it a quiet
  sentence-case label. *A is a refinement that adds no claims.*
- Category tiles: 7 tiles in an 8-column grid leave a hole on the right on desktop and an orphan tile on mobile, and
  every tile wears a "PENDING" circle. → Balanced grid (7 equal columns on desktop, 2 columns plus a full-width last tile
  on mobile), icon-only tiles, one note "Category photos pending" for the whole row.
- Audience panels each carry a second dashed "PHOTO PENDING" box. → Remove the empty boxes and keep text + one action.
- "Buying for a business?" uses three different top-border colours and three button colours. → One card style, one
  secondary button style.
- Mobile: the hero placeholder pushes "Shop by category" below the first screen. With option A the panel is compact.

**Shop / category / search** (`guest-shop-1366`, `guest-category-*`, `guest-search-*`)
- A wall of orange buttons (X1). → Card action becomes a quiet outline button. Orange stays for Add to cart on the
  product page.
- "View & buy" shows a cart icon but only opens the product page; "Choose weight" and "View & buy" read as two different
  actions. → One honest label, "View product", or "Choose weight" for weight items, without a cart icon.
- "PHOTO PENDING" in uppercase on every card. → Smaller sentence-case "Photo pending", same position.
- Search results breadcrumb says "Home › Shop". → "Home › Search". Filter labels wrap awkwardly at 248 px; widen
  the sidebar slightly or tighten the label size.
- Empty search state is good (clear message, two actions). Keep it.

**Product page** (`guest-product-*-1366/390`)
- Strongest page. The weight calculator works and is clear: presets, custom quantity, live calculated price.
- Three side-by-side actions (Add to cart, Buy now, Bulk quote) plus a separate "Request a quote" disclosure below
  repeat the quote action twice. → Keep Add to cart (primary) and Buy now (secondary). "Bulk quote" becomes a text link
  that opens the disclosure.
- Two tinted panels ("Buy more, pay less" teal and "Calculated price" orange) compete. → Calculated price becomes the
  one moment (scale-readout signature); tier info becomes a quiet bordered note.
- Mobile: the square placeholder fills the first screen before the name and price. → Placeholder only: 4:3 on mobile.
  Real photos keep their own ratio.
- Disclosure uses the browser's ▶ marker next to an icon. → One chevron icon.

**Combos, cart, checkout** (`guest-combo*`, `retail-cart-*`, `retail-checkout-*`)
- Cart: the thumbnail cramps the "PHOTO PENDING" text inside 80 px. → Icon only in thumbnails.
- Checkout: clear 4-step progress, good form layout, test-mode labels honest. Keep. Optional: hide the marketing trust
  strip in checkout steps to reduce distraction.

**Account and auth** (`retail-account-*`, `guest-login-*`, `guest-register-*`)
- Greeting headline "Hello, Demo Retail Customer" in a navy banner. → Page title "My account" with name and phone as
  meta.
- "My orders" appears as a shortcut card and as a section on the same page. → Keep the section; the card becomes
  "Track an order" or is dropped.
- Two different button colours side by side (teal, navy) for the two applications. → One secondary style.

**Business / reseller portals** (`business-*`, `reseller-*`)
- Developer wording in the header: "Organization-scoped: bulk orders, quotations, your price list, addresses and team."
  → "Bulk orders, quotations, your price list and team for Demo Restaurant Ltd."
- Teal banner header vs other dashboards (X7). Uppercase KPI labels (X4).
- Four shortcut cards repeat the sidebar links directly underneath it. → Remove, or keep only the one that isn't in
  the sidebar.

**Admin** (`admin-*`)
- Storefront chrome around admin (X6). → Admin and portal pages keep the brand header (logo, language, user, log out,
  "View store") and drop the category bar, product search, cart, trust strip and marketing footer.
- Sidebar: 22 links, uppercase section titles. → Sentence-case section titles; spacing on the 4 px rhythm.
- Dashboard shows only pending applications. Optional: four real counts already in the database (orders awaiting
  action, low-stock SKUs, photos awaiting review, open RFQs), each linking to its screen. *New content, not new data.*
- Reports: 7 KPI cards in a 6-column grid push "Net revenue" onto its own row; zero-value status rows still draw a
  2 px bar (looks like data); the "Retail, business & reseller" table is cut off at the right edge. → KPI grid that fits
  7 (or the money chain as one row with Net revenue highlighted), no bar for zero, scrollable table.
- Product list: clear. Every row shows "Pending" photos, which is honest; make it quieter (text, not a pill) so real
  exceptions (Out, Low) stand out.

**Bangla** (`guest-bn-*`)
- Layouts hold in Bangla. The hero H1 is the LCP element and waits for the Bangla web font (mobile LCP 3.9 s locally;
  254 KB vs 116 KB in English). Font loading is part of group G1.

## 4. Baseline measurements (local `php -S`, no OPcache; compare like with like only)

| Page | Mobile LCP | Desktop LCP | CLS | TBT | Transfer |
|---|---|---|---|---|---|
| `/` | 2440 ms | 1088 ms | 0 | 0 ms | 116 KB / 7 req |
| `/shop` | 1848 ms | 1436 ms | 0 | 0 ms | 124 KB / 7 req |
| `/product/…` | 2552 ms | 1108 ms | 0.007 | 32 ms | 109 KB / 7 req |
| `/bn` | 3904 ms | 3380 ms | 0.029 | 92 ms | 254 KB / 10 req |

Accessibility: 0 contrast failures on 10 audited pages; small-target warnings listed in X9. Responsive: Phase 9 run,
145/145 clean at 360–1366 px.

## 5. Proposed implementation groups (each independently testable)

| Group | Contents | Files | Risk |
|---|---|---|---|
| **G1 Foundations** | Load one Latin family explicitly (see decision D1), cap weights at 600 (700 only for prices), sentence-case labels (X4), one `.eyebrow`, tabular figures for prices and quantities, footer band fix (X5), small-target fixes (X9) | `tokens.css`, `app.css`, `storefront.css`, `layouts/app.blade.php` | Font adds ~20–30 KB (one woff2, `font-display: swap`, preloaded); measured before/after |
| **G2 Accent discipline** | Orange only for the primary purchase action and the hero primary; card, filter and secondary buttons become outline / navy; one secondary style everywhere | CSS + `product-card`, shop filters, home | Low; visual only |
| **G3 Homepage** | Shorter headline, hero option A or B (D2), balanced category grid without "PENDING" circles, audience panels without empty boxes, one business-card style | `storefront/home.blade.php`, CSS, `lang/bn.json` (new strings → `bangla-review.csv`) | Low; counts come from existing catalog queries |
| **G4 Cards & listing** | One badge per card (Sale > Low stock > Quote only > Featured), "Popular" → "Featured" (D3), honest card action, quiet placeholder label, search breadcrumb | `product-card`, `photo-placeholder`, shop / search views | Low |
| **G5 Product page** | Two actions + quote link, calculated price as the one moment, quiet tier note, 4:3 mobile placeholder, chevron disclosure | `storefront/product.blade.php`, CSS | Low; pricing JS untouched (parity test stays) |
| **G6 Dashboards** | One page-header pattern for account / business / reseller / admin (title + meta, no greeting, no coloured banner), remove duplicated shortcut cards, plain-language portal subtitle | account, portal, admin views | Low |
| **G7 Admin & portal chrome** | Lean header for `/admin` and portal pages; no category bar, search, cart, trust strip or marketing footer there; optional real counts on the admin dashboard (D4) | `layouts/app.blade.php` (layout flag), admin dashboard view | Medium: layout used everywhere, so covered by page-audit tests and screenshots |
| **G8 Reports polish** | KPI grid for 7 items, no bar for zero values, scrollable breakdown table | `admin/reports/*`, CSS | Low |
| **G9 Motion check** | Keep the existing purposeful motion (cart bump, price tick, skeletons, toast, chart draw, cross-fade, all reduced-motion safe). Add only a press state (scale 0.98) and colour transitions on buttons and cards; no new entrance animations | CSS | Very low |
| **G10 Data** | Western digit in the demo Bangla wok name | `DevelopmentCatalogSeeder.php` | None |

Not touched in any group: routes, controllers' business logic, pricing / weight engine, inventory, checkout, payments,
roles and permissions, image approval, security headers (no inline scripts are added).

## 6. Decisions needed from the owner

- **D1 Latin font.** (a) Noto Sans, recommended: drawn to match Noto Sans Bengali, so English and Bangla share
  proportions. (b) Inter, as `tokens.css` intended. (c) No web font, declaring the system stack honestly (fastest).
- **D2 Hero.** (A) Code-built department panel with real counts, recommended, or (B) keep the labelled photo placeholder.
- **D3 Badge.** Rename "Popular" to "Featured" (recommended, because it is a staff choice), or remove it.
- **D4 Admin dashboard counts.** Add four counts from existing data (yes / no).
