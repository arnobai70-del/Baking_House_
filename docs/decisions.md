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
| D-008 | 2026-11-20 | Single seller (owned stock) at launch; vendor extension later | §17 |

## Open (owner input required)

| Decision | Status | Safe assumption |
|----------|--------|-----------------|
| Domain and hosting | not chosen | local + staging first |
| Launch catalog, prices, brand assets | not supplied | mock data only, never published |
| Bilingual UI (EN/BN) | not confirmed | localization-ready architecture |
| Weight precision / steps / rounding / packs | custom grams confirmed, details open | integer grams, configurable min/step |
| Tax / VAT invoice rules | unconfirmed | configurable |
| Business / reseller eligibility | concept approved | manual approval + protected tiers |
| Payment / courier partners | not selected | provider-neutral adapters, sandbox |
| Refunds / returns / shipping zones | not finalized | draft with owner + legal review |
| Optional AI | requested if feasible | isolated opt-in after core release |
| Budget / team / timeline | undecided | phase gates, no fabricated dates |
| Photo sources & licensing | not decided | Phase 0 must list SKU photo sources |
