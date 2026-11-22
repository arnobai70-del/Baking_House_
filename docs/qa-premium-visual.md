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
