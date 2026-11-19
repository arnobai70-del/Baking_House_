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
