# Requirements — Baking House

Source: *Baking House Final UI and Phased Development Blueprint v1.1* (20 Nov 2026).

## Mission
Bakery, cake decoration, restaurant / Chinese kitchen, commercial food-service equipment, ingredients, packaging, wholesale and reseller
supplies — easy to find, compare and buy in the correct unit, in one place. The homepage's first screen must communicate this full scope;
the site must **not** read as a cake-only shop.

## 13-question register

| # | Question | Decision |
|---|----------|----------|
| 1 | Name | Baking House |
| 2 | Goal | Sell baking, cake-making, Chinese restaurant, café and commercial kitchen tools, ingredients, equipment and supplies |
| 3 | Type | E-commerce; marketplace-ready architecture; third-party vendor onboarding **not yet approved** |
| 4 | Audience | Bangladesh nationwide: home buyers, bakeries, restaurants/cafés, resellers |
| 5 | Features | Auth, search, catalog, pricing, discounts, bundles, cart, checkout, payment, notifications, order tracking, CRM, API, SEO |
| 6 | Roles | Guest, retail customer, commercial account, reseller, admin, super admin (no moderator) |
| 7 | Design | Modern, premium, animated — per approved screenshot |
| 8 | Technology | Laravel modular monolith (see architecture.md) |
| 9 | Admin | CMS, CRM, inventory, orders, price, discount, bundle, business accounts, SEO, analytics, integrations, settings |
| 10 | Storage | Server-based DB + storage; domain/hosting **not yet selected** |
| 11 | Budget | Undecided; cost-aware, scalable stages |
| 12 | Timeline | Phased with release gates |
| 13 | Special | SEO, page speed, secure backend, API-ready, optional AI |

## Additional requirements
- **Weight units:** gram input; < 1000 g shown as grams, ≥ 1000 g as kg (or kg + g). Custom amounts (e.g. 700 g) auto-price. Admin defines tiers and limits.
- **Commercial account:** organization profile, protected dashboard, bulk orders, special price lists, quotations, team users, repeat orders.
- **Reseller account:** separate price lists, approval, repeat-order tools, margins. **No automatic commission/affiliate system** unless approved.
- **Everything editable:** site copy, menus, banners, landing sections, links, policy pages, categories, brands, images, price, inventory — no deploy needed.
- **No unsupported claims:** gateway fees, courier availability, brand partnerships, returns and tax must be validated before go-live.
- **Real photography only** — see [image-policy.md](image-policy.md).

## Site map (public)
`/` · `/shop` · `/category/{slug}` · `/product/{slug}` · `/search?q=` · `/combos` · `/combo/{slug}` · `/offers` · `/business` · `/reseller` ·
`/restaurant-supply` · `/bakery-supply` · `/cart` · `/checkout` · `/order/track` · `/login` · `/register` · `/forgot-password` ·
`/about` · `/contact` · `/help` · `/faq` · `/policies/*` · `/guides` · `/guides/{slug}`

## Portals
- **Retail** `/account` — profile, addresses, orders, wishlist, saved cart, vouchers, reviews, reorder. Public prices only.
- **Business** `/business/dashboard` — verified org, quotes, negotiated price lists, bulk cart, repeat purchasing, invoices, optional team seats/credit terms.
- **Reseller** `/reseller/dashboard` — assigned reseller prices, bulk reorder, catalog tools, statements. No automatic business permissions.
- **Admin** `/admin` — catalog, orders, inventory, pricing, CRM, CMS, SEO, integrations, audit. Super admin owns roles, gateway credentials, sensitive settings.

## Taxonomy (top level)
1. Baking Tools & Cake Supplies 2. Baking / Cooking Ingredients 3. Cake Decoration & Chocolate 4. Restaurant & Chinese Kitchen Supplies
5. Commercial Kitchen Equipment 6. Packaging & Takeaway 7. Bulk & Wholesale 8. Combo & Bundles

## Acceptance stories
1. Retail shopper searches food-grade chocolate, types **700 g**, sees a price preview, orders, gets a verified receipt.
2. Restaurant owner requests a bulk quote (cookware, chocolate, packaging), gets business-only terms, reorders from dashboard.
3. Reseller sees only their assigned rate card and cannot see another business's invoice.
4. Admin changes a price and schedules a discount without code, with an audit record.
5. A visitor immediately understands Baking House serves bakery, restaurant, café, Chinese kitchen **and** commercial kitchens.
