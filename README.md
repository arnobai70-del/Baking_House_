# NadimShop.com

> Renamed from **Baking House** on 2026-11-22 (decision D-070). The shop name is configurable (`APP_NAME`); the domain is not
> confirmed yet and is only ever taken from `APP_URL`. The repository, folders, namespaces and database names keep their
> original technical names on purpose.

Bangladesh's one-stop supply marketplace for **bakery, restaurant, café, Chinese kitchen and commercial food-service** buyers.
Retail, Business (restaurant/café) and Reseller journeys on one owned-stock e-commerce platform that is ready to become a marketplace.

> Status: **Phase 1 prototype built, Phase 2 Laravel core built.** No real catalog data yet.

## Repository layout

```
docs/          Phase 0 handoff package (requirements, decisions, tokens, pricing rules, roles, image policy, roadmap)
prototype/     Phase 1 interactive HTML/CSS/JS prototype (open prototype/index.html in a browser)
  assets/css/tokens.css         Design tokens (navy / teal / orange, motion durations)
  assets/js/weight-pricing.js   Weight-pricing engine (client preview; server version mirrors it in Phase 4)
  assets/js/cart-core.js        Pure cart repricing, merging, bundles
  assets/js/data.js             MOCK catalog — illustrative only, never publish
scripts/serve.mjs  Zero-dependency static server
tests/         Prototype tests (node:test) — weight matrix + cart repricing
platform/      Laravel 13 application (Phase 2+) — see platform/README.md
.github/       CI: Node tests, Pint, PHPUnit
```

## Quick start

```bash
npm test     # weight-pricing matrix + cart repricing tests (node:test, no dependencies)
npm start    # prototype at http://localhost:5173  (ES modules need http://, not file://)
```

Use the **View as** selector in the top bar to switch between Guest, Retail, Business, Reseller and Admin and see role isolation.

## Key rules (from the approved blueprint v1.1)

- Palette: navy `#103B56`, teal `#087D88`, orange CTA `#F18B0A`, white surfaces. **No red accent.**
- Weight products: integer grams as canonical quantity; < 1000 g shown in grams, ≥ 1000 g shown in kg. ৳650/kg × 700 g = **৳455**.
- Money in integer paisa — never binary floats. Server is authoritative; client only previews.
- **Real photography only.** No AI-generated images. Missing photos use a neutral "photo pending" placeholder.

See [docs/roadmap.md](docs/roadmap.md) for the phase plan and gates.
