# Decisions Log

Ambiguities are recorded here as decisions — never silently guessed.

## Made

| ID | Date | Decision | Reason |
|----|------|----------|--------|
| D-001 | 2026-11-20 | Stack: Laravel + PHP 8.3 + MySQL 8/PostgreSQL + Redis + Blade/Livewire/Alpine + Tailwind + Vite | SEO-friendly SSR, CMS, strong order model (blueprint §08) |
| D-002 | 2026-11-20 | Modular monolith, no microservices | Blueprint §08 |
| D-003 | 2026-11-20 | Money stored as integer paisa (1 BDT = 100 paisa) | Never binary floats (§05) |
| D-004 | 2026-11-20 | Weight stored as integer grams | §04, §05 |
| D-005 | 2026-11-20 | Line rounding: half-up to nearest paisa at line boundary | Provisional — owner must confirm final BDT rounding policy |
| D-006 | 2026-11-20 | Step validation: quantity must be a multiple of `step_grams` | Provisional; alternative is `(grams - min) % step` |
| D-007 | 2026-11-20 | Prototype uses neutral "photo pending" placeholders, no AI imagery | Image policy addendum A |
| D-009 | 2026-11-20 | Cart stores identity + quantity only; totals always recomputed from catalog | Client never supplies authoritative amount (§10) |
| D-010 | 2026-11-20 | One cart line per product+unit; repeat adds merge grams | Tier thresholds on total grams, unique cart-item identity (§05, §09) |
| D-011 | 2026-11-20 | Bundle children priced at standard rate; no stacking with role tiers | Avoid discount double counting (§06) |
| D-012 | 2026-11-20 | Placeholder shipping ৳80 inside Dhaka / ৳150 outside, labelled as placeholder | Courier rates not confirmed (§11) |
| D-013 | 2026-11-20 | Laravel app lives in `platform/`; Laravel 13 / PHP 8.3; no extra Composer packages yet | Keep deps approved one by one |
| D-014 | 2026-11-20 | Customer type = approved organization membership, not a user role; staff = RBAC roles | Business/reseller isolation by `organization_id` (§07) |
| D-015 | 2026-11-20 | Phone is the primary login (normalised `01XXXXXXXXX`), email optional | Bangladesh buyers |
| D-016 | 2026-11-20 | No `Gate::before` super-admin bypass; super admin holds all permissions as data | Every check explicit and auditable |
| D-017 | 2026-11-20 | Users with several approved orgs must pick a context explicitly | §07 explicit context switch |
| D-018 | 2026-11-20 | Sanctum tokens: abilities clamped server-side; 30-day customer / 8-hour staff expiry | Owner-approved API auth |
| D-019 | 2026-11-20 | Storefront reuses the approved prototype CSS design system; Tailwind/Vite bundling deferred to Phase 8 | Preserves the approved UI exactly; no build step yet |
| D-020 | 2026-11-20 | Search indexing off by default (`SEO_INDEXING_ENABLED`); filtered/sorted/search pages always noindex | Mock data must never be indexed |
| D-021 | 2026-11-20 | Weight variants use selling unit `gram`; pack/box/carton carry `pack_size` | Blueprint §04 units |
| D-022 | 2026-11-20 | First price on a new variant logs `product.price_set`; later edits log `product.price_changed` with a required reason | Readable audit trail |
| D-023 | 2026-11-20 | Product photos render only when `review_status = approved`; otherwise a labelled placeholder | Real-images-only policy |
| D-024 | 2026-11-20 | Category tree limited to two levels | Simple navigation and queries |
| D-025 | 2026-11-20 | Staff MFA: TOTP only (no SMS), per-session verification newer than enrollment, staff API tokens need a fresh code | Owner security requirements |
| D-026 | 2026-11-20 | Price precedence contract → business/reseller → campaign → retail volume → standard; tiers on total quantity | Blueprint §05–06, same as tested engine |
| D-027 | 2026-11-20 | Stock changes only via single conditional SQL UPDATEs (no read-then-write); ledger is append-only | Race-safe on SQLite and MySQL |
| D-028 | 2026-11-20 | Reservations hold stock for 15 minutes by default, then auto-release | Abandoned checkouts must not lock stock |
| D-029 | 2026-11-20 | One non-stackable promotion per order (best for customer); trade-priced and bundle lines excluded; only super admin may mark stackable | Prevent unauthorised discount combinations |
| D-030 | 2026-11-20 | Storefront language chosen per session, limited to admin-enabled languages; before enabling search indexing with Bangla, move to URL-prefixed locales with hreflang | Avoid duplicate-content SEO issues |
| D-031 | 2026-11-20 | Prices display with Western digits in both languages | Common in BD e-commerce; owner to confirm |
| D-032 | 2026-11-20 | Rounding: ROUND_HALF_UP to the paisa per order line; order total = sum(rounded lines) − discount + delivery; integer paisa everywhere | Owner instruction (Phase 5) |
| D-033 | 2026-11-20 | Online payments hold stock 15 min; COD and bank transfer allocate stock at placement | Bank transfers need time for staff verification |
| D-034 | 2026-11-20 | Late payment: reallocate if stock exists, else "payment review"; payment after cancellation → refund due | Never confirm against unavailable stock |
| D-035 | 2026-11-20 | Checkout re-confirms when price, stock, offer or delivery changes (fingerprint) | Explicit acceptance before payment (§05) |
| D-036 | 2026-11-20 | Payment adapters are test-mode only; live mode refuses to run | No unverified production endpoints |
| D-037 | 2026-11-20 | Guest order access via a one-time key (hash stored) plus order-number + phone tracking | Guest privacy |
| D-038 | 2026-11-20 | Cancelling an order restores promotion usage | Fair usage limits |
| D-039 | 2026-11-20 | Order transactions start with the stock UPDATE and retry concurrency errors | Found by multi-process checkout test |
| D-040 | 2026-11-20 | Organization roles: owner / buyer / viewer; viewers cannot order for the organization | Accounts staff need read access without buying rights |
| D-041 | 2026-11-20 | Accepted quotation = one fixed cart line (quantity and price locked, no coupons), ordered once; cancellation reopens it while valid | "Changing grams after quote" abuse case |
| D-042 | 2026-11-20 | Quotes do not reserve stock; stock is reserved at checkout and a short line blocks checkout | Never confirm against unavailable stock |
| D-043 | 2026-11-20 | Team invitations are one-time, email-bound, 7-day links; only the hash is stored | Forwarded links must be useless |
| D-044 | 2026-11-20 | Opening a portal switches the buying context to that organization | Users in both a business and a reseller org always see matching prices |
| D-045 | 2026-11-20 | Portal invoices are order invoices, not VAT (Mushak) invoices, until VAT details are provided | No unverified legal documents |
| D-046 | 2026-11-20 | No credit terms or approval thresholds online in Phase 6 | Needs owner policy decision |
| D-047 | 2026-11-20 | Product photos: private until approved; public renditions exist only for approved photos | Unapproved images cannot be published by mistake |
| D-048 | 2026-11-20 | Photo approval needs a second staff member (MEDIA_REQUIRE_SECOND_REVIEWER) plus complete provenance and an authenticity attestation | Real-images-only policy |
| D-049 | 2026-11-20 | AI images are controlled by provenance, attestation, metadata markers and human review; no automatic "AI detector" is claimed | Software detection is unreliable |
| D-050 | 2026-11-20 | Report definitions: gross = line subtotal; net sales = gross − discounts; billed = net sales + delivery; net revenue = billed − refunds recorded in period; orders by placed date, cancelled excluded | Owner instruction to separate the figures |
| D-051 | 2026-11-20 | Money in CRM / reports requires reports.financial (or orders.manage for customer spend) | Restricted financial records |
| D-052 | 2026-11-20 | Unassigned RFQ and application leads form a shared CRM queue; claims are conditional | Owner default: shared follow-up queue |
| D-053 | 2026-11-20 | Business credit, spending-limit approvals and VAT invoices stay disabled | Owner defaults (Phase 7) |
| D-054 | 2026-11-20 | Financial reports: super admin only by default; staff via the explicit Finance role; CRM spend needs the same permission | Owner instruction (Phase 8) |
| D-055 | 2026-11-20 | Information pages (about, policies, contact details) are staff-written from owner content and public only when published; nothing is pre-filled | No invented legal text |
| D-056 | 2026-11-20 | Contact & support messages become CRM leads in the shared queue | One follow-up process |
| D-057 | 2026-11-20 | Muted text #5a6c79 and teal text-on-tint #076f79 (same hues, darker step) | WCAG 2.2 AA contrast |
| D-058 | 2026-11-20 | X-Robots-Tag noindex on all responses while indexing is off; on private paths when on | Covers non-HTML responses |
| D-059 | 2026-11-21 | Language in the URL for public pages: English unprefixed, Bangla under `/bn/…` (home, shop, search, combos, landing pages, contact, order tracking, category / product / combo / information pages); hreflang en / bn / x-default; both languages in the sitemap; `/bn/*` is 404 while Bangla is disabled. Cart, checkout, account, portal and admin follow the session language | Indexable Bangla pages; existing English URLs unchanged |
| D-060 | 2026-11-21 | Retail customers get a saved address book (max 20, one default) sharing the organization address service; checkout saves an address only when the customer ticks the box, never for guests or business buyers | One implementation for both books; no silent data collection |
| D-061 | 2026-11-21 | Content-Security-Policy: scripts from this site only (no inline scripts, enforced by a test); images from this site only; CSP is off when APP_DEBUG is on. Payment provider hosts must be added to `form-action` / `connect-src` when live adapters are built | XSS defence in depth; real-images-only also at browser level |
| D-062 | 2026-11-21 | Staging is private behind HTTP Basic (bcrypt hash in env, fails closed); `/up` and provider webhooks stay reachable | Testers only; webhooks verify their own signatures |
| D-063 | 2026-11-21 | `php artisan app:readiness --target=staging|production` must report 0 FAIL before any deploy; live payments are always a FAIL | Repeatable preflight |
| D-064 | 2026-11-22 | Typeface: Noto Sans (Latin) with Noto Sans Bengali, self-hosted variable WOFF2 (SIL OFL); Latin uses `font-display: optional`, Bangla `swap`; font preloads only on Bangla pages (measured A/B, qa-phase9.5.md); no third-party font requests (CSP `font-src 'self'`) | Matching Latin/Bangla design, consistent rendering, measured loading |
| D-065 | 2026-11-22 | Orange is reserved for the purchase path (add to cart, checkout, place order, pay) and the homepage hero action; other actions are navy or outline | Primary action stands out |
| D-066 | 2026-11-22 | Admin and organization portals render as workspaces: no category bar, product search, header cart, trust strip or marketing footer; "View store" link instead | Tools, not shop windows |
| D-067 | 2026-11-22 | The staff-curated flag is labelled "Featured" (badge, home section, "Featured first" sort); the sort URL value stays `popular` | No unmeasured popularity claims; links unchanged |
| D-068 | 2026-11-22 | Homepage hero shows the departments with live product counts (same counts as shop filters) instead of an image slot | Real catalog data, no placeholder as first impression |
| D-069 | 2026-11-22 | Admin dashboard shows four work-queue counts, each only with the permission of its screen; no money figures | Least privilege |
| D-070 | 2026-11-22 | Public name changed from Baking House to NadimShop.com via configuration (`APP_NAME` → `brand.name`, `seo.site_name`); domain stays in `APP_URL` until ownership and DNS are confirmed; repository, folders, namespaces, database tables, package names and the Phase 1 prototype keep their names; Git history untouched | Owner rebrand; no unnecessary technical renames |
| D-071 | 2026-11-22 | Licensed stock photos as labelled "Representative image" for generic products only (14 products); equipment, the 6-cavity mould and combos keep placeholders; approved product photos take precedence; homepage hero redesigned around a licensed bakery photo (owner-approved layout change) | Owner instruction; honest labelling instead of impersonating SKUs |
| D-072 | 2026-11-22 | Homepage v2: hero photo on the right ~55% only (no department card), text on solid navy; real photos in both audience banners and on all 7 category tiles (one light, neutral set); product stock photos become preview-only (launch check L-IMG-1, readiness fails on production), must show the product itself on a light neutral background — wok, GN pan, chafing dish, round cake pan and squeeze bottle revert to placeholders | Owner brief (Tasks 1–4); no photo that misrepresents the product |
| D-074 | 2026-11-22 | Image rule changed by the owner: AI-generated images allowed everywhere (scenes and products), no label, no AI-specific launch check; still recorded in `IMAGE_CREDITS.md`. Licensed-stock launch check L-IMG-1 and the photo review workflow unchanged | Owner decision; misrepresentation risk for product images noted and accepted |
| D-075 | 2026-11-22 | Premium visual pass (branch `feature/premium-visual`): mixed kitchen hero with a 5-point list, service facts under the hero, department tiles with icons and live counts, featured products as department tabs, café/catering band, homepage combo, product cards with stock line and honest actions (direct Add to cart only for single fixed-price in-stock items), category photo headers, gallery thumbnails, admin orders-per-day chart (counts only), portal quick actions. Media workflow accepts declared AI images (`ai_generated` source) with the same review. No image was AI-generated (no tool available); prompts in `docs/image-asset-manifest.md` | Owner brief; pricing, stock, checkout, auth, roles and APIs untouched |
| D-076 | 2026-11-22 | Homepage unified (owner reference): full-width dark hero (bright bakery-kitchen photo, Unsplash u7egjbn8tzk, + cake inset) with department cards overlapping its lower edge as the page's only department list; service facts once; featured tabs exclude restaurant / equipment, which get their own block with café / catering aisles; no product appears twice; bakery / catering banners removed (they only repeated department links); business band as entry points. Project-wide motion: cross-page view transitions, hero stroke draw, staggered department cards, sliding tab underline, panel / gallery fades, status slide-in, button press, chart growth, KPI count-up — all off for reduced motion | Owner brief: one journey, no duplication, unique animation |
| D-077 | 2026-11-23 | Hero uses the owner's own AI-generated design preview scene (cropped to the image area, no upscaling; untouched original kept outside Git) to match the approved preview; text column anchored to the content edge with the scene filling the rest; category bar always fits on one row (icons from 1440 px with a wider bar, two-line labels at 961–1279 px) | Owner instruction "same as the AI preview"; first AI image in use under D-074, provenance recorded |
| D-078 | 2026-11-23 | Service-facts strip (delivery, payment methods, wholesale pricing, request a quote) removed from every page | Owner instruction; the facts were still unverified ("pending") |
| D-079 | 2026-11-24 | Cinematic motion layer on the existing motion (no library): hero pointer parallax (scene ≤8 px, stroke counter-moves), slow scene breathing and stroke float (paused off screen), magnetic hero CTAs (≤4 px), card hover only on real-hover devices, price swap + glow (final prices only), staggered mega-menu columns, category underline, header lift, filter loading bar; no decorative motion on checkout / payment; all off for reduced motion | Owner brief; transform / opacity only, rAF-coalesced pointer input, IntersectionObserver instead of scroll listeners |
| D-080 | 2026-11-24 | `/business` landing photo replaced: the dark stove shot (Pexels 6375558) gives way to a bright restaurant kitchen line with pendant lights, pans and plate shelves (Pexels 4947388, licensed real photo; no AI image generated — no tool available) | Owner review: "premium image" |
| D-081 | 2026-11-24 | Custom cakes, Part 1: a cake is a normal catalogue product (quote-only variant + cake profile) priced by admin options (per-kg flavour, shape, tier, filling, cream, diet, message, print, extras); each design is one cart line through the existing cart, checkout, order and payment pipeline (no second shop). Dates respect lead time (48 h default, 24 h simple if enabled, 72 h complex), daily capacity, closed / urgent dates (Asia/Dhaka). Advance (default 50 %, 0–100 %, full payment per cake) is the first payment; order becomes "advance paid, balance due" and staff record the balance. Pickup locations and per-zone cake delivery rules. New and repriced options are TEST until the super admin approves them; TEST prices block checkout outside local / testing and fail the readiness check. Eggless / sugar-free off by default | Owner brief + answers |
| D-082 | 2026-11-24 | Custom cakes, Part 2: 6-step designer with a lazy-loaded Three.js 3D preview as the main view (2D SVG drawing only on low-memory devices, without WebGL or with reduced motion) and a Konva top-design editor (Bangla / English text with fonts, outline and curve; customer photos with crop, rotate, circle mask, brightness; library items; layers, snap guides, undo / redo; print-quality check in plain dpi arithmetic). Three.js r160 and Konva 9.3.6 are stored locally (MIT, checksums checked) because the site's CSP allows scripts from our own domain only. Colours, drip, side pattern, borders and every library item / photo / text are priced by the existing server pricer; library items are TEST until the owner approves them. Customer photos stay on the private disk, need a rights confirmation, are never part of share links, and each print is approved by order staff; the order keeps a 3D snapshot, the top design and a printable baker job sheet | Owner brief (Part 2) |
| D-008 | 2026-11-20 | Single seller (owned stock) at launch; vendor extension later | §17 |

## Engineering assumptions for open business inputs
Commercial decisions (partners, hosting, policies, budget) are tracked privately by the owner, not in this public repository.
Until they are confirmed, engineering uses these safe defaults:

- Develop locally and on staging only; no production deployment without explicit owner approval.
- Mock catalog data only, clearly labelled, never indexed or published.
- Localization-ready (English + Bangla fields); languages switchable in admin.
- Integer grams with configurable minimum/step; explicit rounding policy (D-005).
- Tax / VAT handling configurable, not hard-coded.
- Business / reseller access by manual approval with protected price lists.
- Payment and courier integrations through provider-neutral adapters in sandbox mode; shipping charges are placeholders.
- Refund, returns and delivery policies shown as "pending" until approved.
- Optional AI features isolated, opt-in, after the core release.
- Real licensed photography only; placeholders until approved.
