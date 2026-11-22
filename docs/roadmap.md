# Roadmap & Progress

Each phase: code review, tests, role checks, mobile review, docs, accessibility, regression. No launch until the prior gate passes.

| Phase | Scope | Status |
|-------|-------|--------|
| 0 | Discovery & business rules, docs package | 🟡 docs drafted — owner sign-off pending |
| 1 | UX/UI system & interactive prototype | 🟡 prototype built — 13 screens, owner review + real sample photos pending |
| 2 | Repo, DevOps, core Laravel backend, RBAC, audit | 🟡 core built — 62 tests green; staging, backups, error tracker, MFA pending |
| 3 | CMS, catalog, SEO foundation | 🟡 built — awaiting owner review, real photos and catalog data |
| 4 | Pricing, weight engine, promotions, stock | 🟡 built — awaiting owner review |
| 5 | Retail checkout & operations (MVP gate) | 🟡 built — test-mode payments, awaiting owner review |
| 6 | Business & reseller portals | ✅ accepted |
| 7 | CRM, advanced admin, analytics | ✅ accepted |
| 8 | Animation polish, SEO, performance | 🟡 built: awaiting owner review (see docs/qa-phase8.md) |
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
- Staging environment, MySQL in CI, queue worker, error tracker, nightly backup job with a restore drill.
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

## Staff MFA (owner-approved 2026-11-20)
TOTP via `pragmarx/google2fa` 9.1.0 and `bacon/bacon-qr-code` 3.1.1, built into the existing login.
- **Login:** a password alone never logs staff in. Verification is per session and must be newer than the current enrollment.
- **Secrets and codes:** secrets are encrypted at rest and codes cannot be replayed. There are 8 single-use recovery codes, stored hashed.
- **Limits and resets:** MFA attempts are rate-limited. A reset needs the password plus a current code, and revokes the user's API tokens.
- **API tokens:** a staff token needs a fresh code for every token.
- **Audit:** enrollment, failures, recovery, resets and token issuance are logged, with secrets redacted.

## Phase 4 — built (platform/)
- **Pricing:** price lists for retail volume, campaign, business, reseller and contract (one organization).
  - Volume tiers apply on total grams or units. Precedence: contract → business/reseller → campaign → retail → standard.
  - Minimum order quantities. Scheduled standard-price changes applied by `pricing:apply-scheduled`, with audit.
  - Server quote API `POST /api/v1/pricing/quote`. Admin price simulator.
- **Inventory:**
  - Append-only stock ledger and reservations with expiry (`inventory:release-expired`).
  - All changes are single guarded SQL `UPDATE`s, so overselling is impossible; a **real multi-process concurrency test** proves it.
  - Low-stock alerts, physical-count reconciliation, and a ledger integrity check (`inventory:verify`, nightly).
- **Promotions:**
  - Coupons and automatic promotions with audience eligibility, schedules, minimum spend, caps, usage and per-customer limits (atomic redemption).
  - Bundles with per-item stock checks.
  - Combination rules: one non-stackable discount; no coupons on trade-priced or bundle lines unless a super admin marks a promotion stackable.
- **Language:** English/Bangla set in Admin → Languages. Bangla lives in `*_bn` columns on the same rows (never duplicated), loads Noto Sans Bengali, and falls back to English.

## Phase 4 remaining (gate)
- Owner review of pricing rules, combination rules and stock workflows.
- Final BDT rounding policy (D-005 still provisional).
- Bangla copy review by a native editor. Admin screens remain English.
- Cart, checkout and order commit of reservations is Phase 5.

## Phase 5 — built (platform/)
- **Cart:**
  - Guest carts use an unguessable cookie token; customers get one cart per account. A guest cart merges into the account cart on sign-in.
  - Carts store quantities only and are re-priced on the server every time, including business, reseller and contract prices.
  - Weight items, unit items and bundles are supported, along with coupons.
- **Checkout:**
  - Details, then a review page with explicit confirmation, then placement.
  - Bangladesh phone validation, all 64 districts, and admin-configured delivery zones (placeholder rates) with a free-delivery threshold.
  - Cash on delivery is limited by order value and zone.
  - A fingerprint check makes the customer re-confirm if anything changes (price, offer, stock, delivery).
  - Duplicate submits produce exactly one order, enforced by a unique checkout token and verified by a multi-process test.
- **Orders:**
  - Unique numbers, and an immutable line and pricing snapshot (rounded per line, total = sum of lines − discount + delivery).
  - Fulfillment status is separate from payment status. Guarded transitions, a timeline, and audit entries.
  - Customer cancellation before processing; staff cancellation before shipping.
  - Cancellation releases reservations and restocks allocated stock through the ledger. Promotion usage is restored.
  - `orders:expire-unpaid` cancels stale unpaid orders.
- **Payments:**
  - bKash, Nagad and card use **test-mode** adapters only, with a simulated hosted page and HMAC-signed callbacks.
  - Callbacks are verified on the server, idempotent (per provider event id), and captured at most once. Amount and currency must match.
  - Bank transfer is confirmed by staff (exact amount); cash on delivery is collected after shipping.
  - Refunds can be partial or full and never exceed the amount paid. There is a reconciliation report. No card data is stored.
  - `live` mode fails until real provider documentation and credentials exist.
- **Late payments:** stock is re-allocated if still available; otherwise the order goes to "payment review", never confirmed against missing stock. A payment that arrives after cancellation is flagged as refund due.
- **Notifications:** SMS uses the "log" driver until a provider is contracted, plus Laravel mail. Recipients are masked in `notification_logs`, and failures never break orders.
- **API:** `/api/v1/cart`, `/checkout/preview`, `/orders` (requires `Idempotency-Key`), `/orders/{number}`, `/webhooks/payments/{provider}`.

## Phase 5 remaining (gate)
- Owner review of the checkout flow and order-handling workflow.
- **Real payment providers:** bKash, Nagad and card merchant agreements, official API documentation, sandbox credentials, then live adapters.
- SMS provider contract; courier rates and zones; bank account details; COD limit; policies (refund, delivery, terms).
- Native Bangla review of the checkout and order text.

## Phase 6 — built (platform/)
- **Separate portals** `/business/*` and `/reseller/*` on the same building blocks. The organization always comes from an approved membership of THAT type (`EnsureOrganizationAccess`), never from input; records addressed by URL are checked against it (404 otherwise). Opening a portal makes that organization the buying context.
- **Team roles:** owner (everything, team), buyer (order, bulk, quotations, addresses), viewer (read-only: orders, prices, invoices, statements). Viewers cannot place organization orders (enforced in checkout, web and API). Invitations are one-time, email-bound, 7-day links (hash stored); at least one owner always remains; removal takes effect immediately.
- **Orders:** every member sees the organization's orders; reorder adds lines at today's prices; printable invoice (order invoice, not a VAT/Mushak invoice until VAT details exist).
- **Bulk order:** paste `SKU, quantity` or upload CSV (max 200 lines); each line validated on the server with the buyer's prices, weight rules and stock; rejected lines are reported, never adjusted; duplicate SKUs merged for tiers.
- **Price list / rate card:** only the organization's contract lists and its own audience's tiers (business vs reseller), with CSV export (reseller catalog tool).
- **RFQ / quotations (business):** one request endpoint (product page, `/business`, portal); public requests are sales leads. Staff issue fixed lines (quantity + price, custom lines allowed) with a validity date; owner/buyer accepts → one cart line at exactly the quoted total (no coupons, quantity locked) → normal checkout; ordered exactly once (conditional UPDATE); cancelling the order reopens a still-valid quote; `quotations:expire` hourly.
- **Saved delivery addresses** per organization, pre-filling checkout. **Monthly statements** (charged / paid / refunded / balance) with CSV export (formula-injection guarded).
- **Admin:** `/admin/quotations` (new `quotations.manage` permission), `/admin/organizations` directory (members, invitations, addresses, contract lists, orders, quotations).
- **API:** `/api/v1/{business|reseller}/orders`, `/prices`, `/bulk/check`, `/bulk` (write), `/api/v1/business/quotations[/{number}[/accept]]`; `{type}:read` / `{type}:write` abilities plus member role.
- **Public pages:** `/business`, `/reseller` landing pages (licensed photo placeholders only), in sitemap.
- Bangla for all customer-facing portal text (coverage test now scans every storefront, checkout, order and portal view).

## Phase 6 remaining (gate)
- Owner review of the portal workflows, quotation terms and invoice wording.
- Legal / VAT details for invoices (BIN, address); credit terms and buyer approval limits (not built; decision needed).
- Sales process owner for RFQ leads; notification provider for quote and invitation messages (log driver until contracted).
- Native Bangla review of portal text.

## Phase 7 — built (platform/)
- **Real-image approval (media.manage / media.approve):** uploads are content-checked (JPEG / PNG / WebP by content, ≥ 600 px, ≤ 8000 px / 40 MP, ≤ 10 MB), refused if their metadata declares AI generation (IPTC/C2PA `trainedAlgorithmicMedia`, generator names) or duplicate (sha256), RE-ENCODED (all metadata incl. GPS removed) and stored on the PRIVATE disk. Approval needs a different staff member, source, rights holder, licence (reference for stock; not expired), English alt text and an authenticity attestation. Only then are public renditions generated (400 / 800 / 1200 px JPEG + WebP + AVIF where GD supports it) and shown through `<picture>`; reject / withdraw / licence expiry (`media:withdraw-expired`, daily) delete them. Ordering, main photo, variant mapping, Bangla alt text, private staff preview, audit log.
- **CRM (crm.access):** leads / opportunities with pipeline (new → contacted → qualified → quoted → won / lost); every RFQ and account application becomes an unassigned lead in the shared queue; conditional claim / assignment (only to CRM staff); quotation events move RFQ leads automatically; append-only communication timeline (note / call / email / SMS / meeting + system entries); follow-up tasks with reminders (`crm:send-reminders`, log driver); tags; customer & organization profiles; segmentation (type, tag, order count, spend, inactivity, district) with CSV export.
- **Reports (reports.view, money with reports.financial):** gross sales → discounts → net sales → delivery → billed → refunds → net revenue; paid / unpaid / cancelled; daily chart; orders by status and payment; retail / business / reseller split; top products and categories (quotation items counted); inventory low / out of stock; coupon & promotion performance; acquisition and repeat purchasing; RFQ funnel and CRM pipeline. Every section exports CSV.
- **Access:** CRM users never see price lists, contract prices or staff accounts; order money appears only with reports.financial or orders.manage.
- **Business defaults applied:** business credit disabled, spending-limit approvals disabled, VAT invoices disabled until BIN / legal details exist, SMS / email log-only, Bangla + English with Western digits.

## Phase 7 remaining (gate)
- Real, licensed product photography to upload and approve (none exists yet; storefront keeps labelled placeholders).
- Owner review of report definitions (gross / net / refunds) and CRM workflow; who holds `reports.financial`.
- Deployment steps: `php artisan storage:link`, re-run `RolesAndPermissionsSeeder` (new permissions), GD with WebP (AVIF optional) on the server.

## Phase 8 — built (platform/)
- QA of every page (automated page audit for guest, retail, business, reseller and super admin; 95 pages).
- Added the missing pages without duplicates: information pages managed by staff (about, contact information, policies; nothing pre-written, published only by staff), Contact & support (messages enter the CRM shared queue), Profile & password, Staff & roles (super admin).
- Financial reports default to the super admin; other staff only via the explicit Finance role (CRM customer spend included).
- Animations: busy / spinner state and double-submit guard on every form, dimmed results while filters load, search skeletons, cart badge bump after add-to-cart, card photo hover, dashboard entry transitions, page cross-fades (motion-safe only); all collapse under reduced motion.
- Performance: category facet N+1 removed, content-hashed asset URLs for long-term caching, measured A/B (docs/qa-phase8.md).
- Accessibility: contrast to WCAG AA, 24 px targets, field-level error messages, translated screen-reader labels.
- SEO: X-Robots-Tag on every response, landing pages no longer blocked, information pages in the sitemap.
- Bangla: full coverage test across customer pages, digits test, `docs/bangla-review.csv` for native review.

## Phase 9 — built (platform/), staging preparation
- **Retail saved addresses:** `/account/addresses` (max 20, one default), checkout pre-fill and opt-in "Save this address"; one `AddressBook` service shared with organization addresses.
- **Language URLs:** Bangla public pages under `/bn/…`, hreflang en / bn / x-default, bilingual sitemap, localized titles and descriptions, language switcher keeps the page (D-059).
- **Mobile:** every public, customer, portal and admin page verified at 360–1366 px; three overflow defects fixed (docs/qa-phase9.md).
- **Security:** CSP (no inline scripts, test-enforced), HSTS over HTTPS, frame / sniffing / referrer / permissions headers, trusted proxies, forced HTTPS.
- **Staging configuration:** `.env.staging.example`, private HTTP Basic gate, `php artisan app:readiness`, scheduler heartbeat, `docs/staging-runbook.md`.

## Phase 9 remaining (gate)
- Owner authorization for the actual staging deployment (server, domain, TLS, database, backups).
- On staging: readiness with 0 FAIL, tester acceptance, real-network performance, device screen-reader pass.

## Phase 9.5 — built (platform/), UI/UX refinement (no redesign)
- Audit: `docs/ui-audit-phase9.5.md` (Unslop method, polish mode). Record: `docs/qa-phase9.5.md`.
- G1 self-hosted Noto Sans + Noto Sans Bengali, weights 400–700, sentence-case labels, tabular figures, footer band fix, 24 px targets.
- G2 orange only on the purchase path and the hero; G3 homepage headline + live department panel, balanced category tiles;
  G4 one badge per card, "Featured" instead of "Popular", honest card actions, search breadcrumb;
  G5 product page: two actions + quote link, stronger calculated price, quiet tier note, 4:3 mobile placeholder;
  G6 one page-heading pattern for account / business / reseller / admin; G7 workspace layout for admin and portals plus
  four permission-gated admin counts; G8 report KPI grid, no zero bars, readable breakdown tables; G9 press and colour
  transitions, reduced-motion safe; G10 Western digit in the demo Bangla product name.

## Launch blockers (owner / business)
1. Real, licensed product photography (none uploaded; placeholders are labelled).
2. Payment provider contracts and live credentials (live payments not authorised; test mode only).
3. Mail and SMS providers (notifications are log-only). **No "forgot password" flow exists yet**: it needs a mail or SMS provider.
4. Seller legal name, address, BIN / VAT details and bank transfer details (invoices say "not a VAT invoice").
5. Owner-written policies and contact details (information pages are empty until staff publish them).
6. Native-speaker review of `docs/bangla-review.csv` (635 strings) and Bangla catalog names.
7. Real catalog, prices and stock (demo data is illustrative and must not reach production).
8. Staging deployment, then production approval; search indexing stays off until launch is approved.
