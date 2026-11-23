# QA — premium visual pass (D-075), 22 Nov 2026

Branch `feature/premium-visual`. Baseline: `qa-artifacts/visual-audit-2026-11-22/AUDIT.md`. Reference: page 2 of the
blueprint v1.1 PDF ("Approved Baking House UI – Final Reference"), supplied by the owner. It is not stored in the
repository.

## What changed

**Homepage**
- **Hero (v3, after owner review):** light split design — navy text on white; a bright commercial-kitchen photo
  (Pexels 34276646) with a bakery inset (Pexels 8478181), so restaurant and bakery read together. It replaces the dark
  kitchen-line hero, which the owner did not approve, and the earlier bread shelves (bakery-only).
- **Hero list:** five verified departments and buying features instead of the paragraph.
- **Service facts:** directly under the hero. Same text as before; the "pending" notes stay.
- **Department tiles:** each tile has an icon badge and a live product count.
- **Featured products:** shown by department in accessible tabs (every panel stays visible without JavaScript). The tab
  that opens first is the department with the most photographed products.
- **Café, catering & takeaway band:** links to the real sub-categories.
- **Combo card:** the first live combo, priced on the server.
- **Business band:** icons added.

**Product cards**
- A stock line.
- The selling unit ("/ piece", "/ kg", "/ pack of 25").
- An orange "Add to cart" only for a single fixed-price, in-stock item, using the existing endpoint.
- Otherwise "Choose weight", "Choose options", "Request quote" or "Out of stock".

**Category pages**
- A department photo in the header.
- The product count.

**Product page**
- Every approved image is in the gallery; the thumbnails switch the main image in place.

**Dashboards**
- **Admin:** an orders-per-day chart for the last 14 days (real records, counts only) and quick actions gated by
  permission.
- **Business / reseller portals:** a role colour and quick actions (same destinations as the portal navigation).

**Global**
- Short pages keep the footer low.
- The masthead fits at 320 px.
- The checkout payment rows have a hover state.

**Images (D-074)**
- The media workflow has an `ai_generated` source. Files whose metadata declares AI must use it; all other checks and
  the second-person approval are unchanged.
- No image was generated (no tool in this environment). Prompts and slots are in `docs/image-asset-manifest.md`.
- New licensed photos: hero (Pexels 34276646 + 8478181) and the catering band (Pexels 2337843). Both are recorded in
  `IMAGE_CREDITS.md` with checksums.

**Untouched:** pricing / gram–kg engine, stock and reservations, checkout rules, payments, refunds, CRM logic, roles,
authentication, MFA, organization isolation, API contracts and financial records.

## Results

| Check | Result |
|---|---|
| Laravel tests | **393 passed** (2197 assertions) |
| Prototype tests (`npm test`) | 20 / 20 |
| Pint | clean |
| `composer audit` | no advisories |
| Browser, 8 widths (320, 360, 375, 390, 500, 768, 1024, 1366) | home (EN + BN), shop, 2 categories, 2 products, business landing, cart, checkout, account, business / reseller / admin dashboards: **no overflow, no CSP violations, no JS errors** |

## Performance

Production-like local server (OPcache, 4 workers), Edge lab runs. Mobile is emulated slow-4G at 1.6 Mbps with a 4×
CPU slowdown. Median of 3–5 runs.

| Page | Desktop cold LCP | Mobile cold LCP | Mobile warm LCP | CLS |
|---|---|---|---|---|
| Home | 1.1 s | 3.8–4.3 s | 0.7–0.8 s | 0 |
| Shop | 0.8 s | 2.8 s | 0.6 s | 0 |
| Category (Restaurant) | 0.7 s | 1.8 s | 0.5 s | 0 |
| Product (chocolate) | 0.6 s | 1.7 s | 0.6 s | 0.007 |

**Homepage cold load on mobile:**
- First paint is ~3.2 s. The local router serves HTML (88 KB) and CSS uncompressed; production gzip / brotli (staging
  runbook) removes most of that.
- The photo-free baseline already had a 3.1 s first paint.
- The hero (1200 w, 105 KB, `fetchpriority=high`) adds ~0.6 s after first paint.
- Tile photos are served at 600 w on phones.

## Still open

- Real or AI product images for 10 placeholder products, plus galleries for all 19.
- A combo photo.
- The ideal mixed hero scene (prompt #1 in the manifest).
- Developer copy in the demo catalogue data: "Illustrative development copy…" on categories and "Development
  placeholder description" on products. It is data, so edit it in Admin or the seeder.
- The header department icon row (the text category bar is kept, to avoid overflow at 1024 px).
- Payment provider logos (need brand permission).

## Homepage unified (D-076, branch `feature/homepage-unified`)

**Audit before:** departments appeared six times:
- the header bar;
- the hero list;
- the category tiles with sub-links;
- the featured tabs;
- two banners that only linked to two departments;
- the café / catering chips.

"Request a quote" and "business pricing" were repeated in the service strip and the business cards.

**Journey now:**
1. Hero, with the department cards (photo, live count) overlapping its lower edge.
2. Service facts, once.
3. Featured products by department: bakery, ingredients, decoration, packaging, bulk.
4. Restaurant, café & commercial kitchen: its own products and aisles.
5. A server-priced combo.
6. Business / reseller entry points.

The controller never shows a product in two blocks (tested). The page is ~340 px shorter at 1366 px.

**Motion** (transform / opacity only; all off with `prefers-reduced-motion`):
- cross-page view transitions, with the header kept still;
- the hero photo settles (scale only, so LCP is not delayed);
- the orange stroke draws under the headline;
- the cake inset and the department cards rise in sequence;
- the tab underline slides;
- tab panels and gallery images fade;
- status messages slide in;
- buttons give on press;
- admin chart bars grow;
- KPI whole numbers count up.

**Checks:**
- 393 tests passed; Pint clean.
- 8 widths, EN + BN, home / shop / product / admin: no overflow, CSP or JS errors.
- Production-like vitals, home: desktop LCP 0.7 s (was 1.1 s); mobile slow-4G cold 3.1 s (was 3.8–4.3 s), warm 0.5 s; CLS 0.

## Cinematic motion (D-079, branch `feature/cinematic-motion`)

**Reused, not duplicated:**
- hero entrance and the stroke draw;
- scroll reveal (below-the-fold content only, once per element, nothing hidden without JS);
- card lift and image zoom;
- mega-menu and drawer transitions;
- the price `tick`;
- the cart badge bump;
- busy buttons and dimmed results;
- page view transitions.

**Removed:**
- a duplicate `draw` keyframe;
- dead cake-inset rules.

**Added** (`public/js/motion.js` + `public/css/storefront.css`):
- **Hero parallax:** the scene moves up to 8 px; the stroke counter-moves.
- **Floating:** slow scene breathing (8 s) and a stroke float (6 s), paused off screen.
- **Magnetic CTAs:** the two hero buttons, up to 4 px within 40 px of the pointer.
- **Hover:** cards and department tiles only on devices with a real hover.
- **Price:** swap and glow (150–360 ms; only final prices are ever shown).
- **Navigation:** staggered mega-menu columns, the category underline and the header lift.
- **Loading:** a filter loading bar.
- **Opt-outs:** pointer effects need a fine pointer; nothing decorative on checkout / payment pages; everything is off for
  reduced motion.

**Browser-verified** (Edge, CDP):

| Interaction | Result |
|---|---|
| Parallax | scene −7.2 px, stroke +3.2 px |
| Magnetic | 3.2 px near, 0 px far |
| Header | lifts on scroll |
| Hero animations off screen | paused |
| 700 g at ৳650/kg | ৳455 with the swap and glow |
| 1000 g | shows "1 kg" |
| Reduced motion | no movement |
| Touch | no pointer effects, no overflow |

Evidence (filmstrip, parallax and magnetic frames) is in `qa-artifacts/cinematic-motion/`.

**Checks:**
- 393 tests passed; prototype tests 20/20; Pint clean; composer audit clean.
- 8 widths, EN + BN, public and logged-in pages: clean.

**Performance:**
- LCP and CLS are unchanged or slightly better.
- Mobile cold blocking time is noisy on this machine. On the product page the mean is ~110–160 ms after vs ~90 ms
  before; both have spikes over 230 ms. That stays under 200 ms; `:has()` was ruled out by A/B.
