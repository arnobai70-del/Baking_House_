# Roadmap & Progress

Each phase: code review, tests, role checks, mobile review, docs, accessibility, regression. No launch until the prior gate passes.

| Phase | Scope | Status |
|-------|-------|--------|
| 0 | Discovery & business rules, docs package | 🟡 docs drafted — owner sign-off pending (see decisions.md) |
| 1 | UX/UI system & interactive prototype | 🟡 prototype built — 13 screens, owner review + real sample photos pending |
| 2 | Repo, DevOps, core Laravel backend, RBAC, audit | 🟡 core built — 62 tests green; staging, backups, error tracker, MFA pending |
| 3 | CMS, catalog, SEO foundation | 🟡 built — awaiting owner review, real photos and catalog data |
| 4 | Pricing, weight engine, promotions, stock | 🟡 weight engine in JS **and** PHP (same matrix); DB-backed rules pending |
| 5 | Retail checkout & operations (MVP gate) | ⬜ |
| 6 | Business & reseller portals | ⬜ |
| 7 | CRM, advanced admin, analytics | ⬜ |
| 8 | Animation polish, SEO, performance | ⬜ |
| 9 | Optional AI & marketplace expansion | ⬜ |
| 10 | UAT, security review, production release | ⬜ |

## Phase 1 — built (prototype/)
| Screen | File | States covered |
|---|---|---|
| Home | index.html | hero, 8 categories, restaurant/bakery panels, product tabs, combo, B2B band |
| Shop / category / search | shop.html | URL-persisted filters & sort, grid/list, skeleton, empty, mobile filter drawer |
| Product | product.html | WEIGHT (chips, custom g/kg, min/step/stock errors + suggestions, role tiers), FIXED, QUOTE_ONLY, RFQ form |
| Cart | cart.html | server-style repricing, inline weight/qty edit, invalid-line fixes, empty |
| Checkout | checkout.html | delivery → payment → confirmation, BD phone validation, changed-total acceptance, single submit |
| Retail / Business / Reseller dashboards | account.html, business.html, reseller.html | role gates, B2B landing + application, quotes, bulk SKU order, rate cards |
| Admin | admin.html | KPIs + chart (sample), price edit with reason + audit, >20% approval, price simulator, media approval queue |
| Support | combos.html, login.html, order-track.html, contact.html | |

Shared: header with mega-menu (Esc/keyboard), debounced search suggestions, mobile drawer with focus trap, mini-cart, toasts (aria-live), mobile tab bar, reduced-motion.

## Phase 1 remaining (gate)
- Owner review of flows on desktop + mobile; collect change requests.
- Replace placeholders with **approved real sample photography** (image policy) before design sign-off.
- Bangla (Noto Sans Bengali) copy pass once bilingual scope is decided.
- Accessibility audit (axe / keyboard pass) and contrast check of final palette.

## Phase 2 — built (platform/)
Laravel 13 on PHP 8.3, SQLite locally / in tests (MySQL 8 available via Laragon).
- Users with BD mobile login (email optional), throttled login (5/min per identifier+IP), deactivation revokes sessions.
- Staff RBAC: `super_admin`, `admin`; permissions from `StaffPermission` enum; role changes super-admin only, never self, always with a reason.
- Organizations (business / reseller): apply → pending → staff approve/reject/suspend with reason; portal access only for approved members;
  organization context re-validated on every request (session tampering and ID swapping tested).
- Append-only `audit_logs` (actor, subject, before/after, reason, IP) with secret redaction.
- `WeightPricing` PHP engine passing the §05 matrix.
- CI: `.github/workflows/ci.yml` — Node prototype tests + Pint + PHPUnit.

## Phase 2 remaining (gate)
- **Needs approval:** Laravel Sanctum for scoped API tokens (`/api/v1`), MFA package for staff.
- Staging environment + hosting decision, MySQL in CI, queue worker, error tracker, nightly backup job with a restore drill.
- Password reset (needs mail/SMS provider), phone OTP verification.
- Media disks (private originals + public derivatives) for the image-provenance workflow.

## API authentication (Sanctum, owner-approved 2026-11-20)
Sanctum v4.5 under `/api/v1`. Token abilities are clamped to what the account may hold (customer / business / reseller / staff).
Customer tokens last 30 days and staff tokens 8 hours. Tokens can be listed and revoked, and deactivated users' tokens are revoked.
`X-Organization-Id` is strictly validated, and issuing and API calls are rate-limited.

## Phase 3 — built (platform/)
- Catalog tables: `brands`, `categories` (two levels), `products`, `category_product`, `product_variants`, `product_images`.
  SKUs are unique (validation plus a database constraint). Selling units are gram (shown as g/kg), piece, pack, box, set and carton, with pack size.
  Pricing modes are fixed, weight and quote-only. Money is integer paisa; weight rules and stock are integer grams.
- Storefront: home, `/shop`, `/category/{slug}`, `/search`, `/product/{slug}` built on the approved design.
  Includes filters, sort, grid/list view and pagination. A variant picker and the live weight calculator use the shared engine (a parity test keeps the copies identical).
- Admin: products and variants (taka input becomes paisa; weight rules are validated against the step), categories and brands.
  Price changes need the `prices.manage` permission and a reason, and are audited with before/after values. Edits publish without code changes.
- SEO: canonical URLs, meta and OpenGraph tags, `sitemap.xml`, dynamic `robots.txt`, and Product/Offer/Breadcrumb/WebSite JSON-LD built from stored data.
  **Indexing is off until `SEO_INDEXING_ENABLED=true` at launch**, and filtered, sorted and search pages are always noindex.
- Public catalog API: `GET /api/v1/categories`, `/products`, `/products/{slug}`, `/search`.
- Development catalog seeder (local only) with a "Development build" banner on every non-production page.

## Phase 3 remaining (gate)
- Owner review of storefront and admin on desktop and mobile.
- **Real catalog data and licensed photos** (product image upload and approval workflow is Phase 7; only approved photos ever render).
- Bangla storefront copy once the launch language is decided (`name_bn` fields exist).
- OpenAPI spec for `/api/v1`.
- CMS-editable home sections and banners (home copy is in templates for now).
