# Pricing Rules — Weight Engine Contract

Reference implementation (client preview): [`prototype/assets/js/weight-pricing.js`](../prototype/assets/js/weight-pricing.js).
Tests: [`tests/weight-pricing.test.mjs`](../tests/weight-pricing.test.mjs). The Laravel `Pricing` module (Phase 4) must pass the same matrix.

## Units
- Canonical quantity: **integer grams**. `kg` input → `round(kg × 1000)` after precision validation (max 3 decimals).
- Money: **integer paisa**. `৳650/kg` = `65000` paisa per kg.

## Calculation (server authoritative)
```
line_paisa = round_half_up(requested_grams × per_kg_paisa / 1000)
```
Discounts are applied once, after the line subtotal. The client only previews; the backend recalculates on add-to-cart, quote approval and checkout.

## Display
- `< 1000 g` → `700 g`
- `≥ 1000 g` → `1 kg`, `1.25 kg` (decimal style) or `1 kg 250 g` (mixed style), applied consistently.
- No forced round-up when crossing 1000 g.

## Admin-controlled per SKU / variant
- `pricing_mode`: `FIXED | VARIANT | WEIGHT | QUOTE_ONLY`
- `per_kg_paisa`, `min_grams`, `max_grams`, `step_grams`, `presets[]`, sealed-pack constraints
- `tiers[]`: `{ from_grams, per_kg_paisa }` — applied on **total grams**, not cart rows
- Effective / expiry dates

## Price precedence
`contract (assigned customer) → role tier (business/reseller) → campaign → standard`

Stacking: only the highest-precedence applicable source sets the base rate; bundle discounts do not stack with weight-tier discounts on the same line (documented exclusion).

## Test matrix (must pass)

| Scenario | Expected |
|----------|----------|
| 700 g at ৳650/kg | ৳455 |
| 100 / 900 / 1000 g at ৳650/kg | ৳65 / ৳585 / ৳650 |
| 999 g → 1000 g | label changes g → kg; price logic unchanged |
| 1250 g | `1.25 kg` / `1 kg 250 g`; ৳812.50 |
| min 250 g, buyer enters 100 g | validation error, no cart line |
| step 50 g, buyer enters 725 g | error + nearest valid suggestions (700/750); not silently changed |
| 700 g requested, 650 g in stock | rejected, suggest 650 g |
| B2B tier from 5 kg | correct rate applied at ≥ 5000 g |
| promo expires mid-checkout | new quote, explicit acceptance before payment (Phase 5) |
| concurrent carts | only reserved stock sold (Phase 4 server) |
