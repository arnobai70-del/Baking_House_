# Image Policy (Addendum A/B, amended by the owner)

> **Owner amendment D-074 (2026-11-22) — supersedes the "real photography only" rule below.** AI-generated images
> are allowed anywhere on the site (hero, banners, category tiles, product cards and galleries, landing pages),
> with no label and no AI-specific launch check. Each image is still recorded in `IMAGE_CREDITS.md` (source = the
> generating tool, date) for traceability. Unchanged: the licensed-stock rules below, the stock-photo launch check
> L-IMG-1, the Admin › Photo review workflow, and the rule against copying other sites' photos without permission.
> Advice on record: product images that do not show the exact item sold can mislead buyers; prefer own or supplier
> photos for product pages.

Original rule (Addendum A, 20 Nov 2026), kept for history: no AI-generated, AI-composited, AI-upscaled-with-invented-detail
or synthetic images anywhere: storefront, banners, catalog, category tiles, blogs, dashboards, admin promo assets — desktop
and mobile.

The page-2 concept image in the blueprint is a **layout mockup**, not a photo library.

## Acceptable sources
- Own photography of the exact stocked SKU
- Manufacturer / authorized distributor photos with documented permission
- Licensed real stock photos (contextual banners only — never to impersonate an exact SKU)
- **Amendment (owner decision D-071, 2026-11-22):** licensed real stock photos may also be shown for *generic* products
  (ingredients, cookware, serveware, packaging) as a clearly labelled **"Representative image" / "প্রতিনিধিত্বমূলক ছবি"**
  on the card, product page and image. Not allowed for machines/equipment where the model matters, or for combos.
  An approved own / manufacturer photo always replaces it automatically. Recorded in `IMAGE_CREDITS.md` and
  `platform/config/representative_images.php`.
  **Tightened by D-072 (2026-11-22): these product stock photos are for the dev preview only.** Each must show the
  product itself (not food cooked in it), on a light neutral background with similar framing; where no such photo
  exists the product keeps the placeholder. Each is marked "preview only — replace with own or supplier photo before
  launch" in `IMAGE_CREDITS.md`, and launch check L-IMG-1 below blocks them from production.
- Customer photos with express consent and moderation

## Prohibited
Text-to-image, AI backgrounds/fill, synthetic food, fabricated facilities/teams, scraped vendor photos, watermarked/low-res,
unauthorized trademarks, altered product colors/specs.

## Allowed processing
Optimization, non-generative crop/resize, faithful color correction, background removal that preserves the product,
EXIF removal, thumbnails, `srcset`, WebP/AVIF. Keep untouched originals and source records.

## Missing photo
Neutral "photo pending" placeholder; asset marked pending. Never a fake substitute.

## Asset record fields
`asset_id, product_id?, source_type, source_name, original_file, uploader, capture_date?, license_type, permission_evidence,
attribution_required, usage_scope, expiry_date?, checksum, alt_text, review_status, reviewed_by, reviewed_at, replacement_history`

## Workflow
Upload → source classification → permission evidence → duplicate/hash check → product accuracy check → review → approval → publish.
Expired/revoked license → depublish and flag pages. Launch is **blocked** if any published image is unlicensed or AI-generated.

## Launch checks
- **L-IMG-1 — no preview stock product photos (D-072).** Before launch, every product stock photo must be replaced by
  an own or supplier photo approved in Admin › Photo review, and `platform/config/representative_images.php` must be
  empty (delete its lines and the files in `platform/public/images/representative/`). Enforced:
  `php artisan app:readiness --target=production` fails with "No preview stock product photos" while any entry remains
  (a warning on staging).
- **L-IMG-2 — credits complete.** Every file under `platform/public/images/` has a row and a checksum in
  `IMAGE_CREDITS.md`, and its untouched original is kept outside Git.
