# Real Photography Only — Image Policy (Addendum A/B, non-negotiable)

No AI-generated, AI-composited, AI-upscaled-with-invented-detail or synthetic images anywhere: storefront, banners, catalog,
category tiles, blogs, dashboards, admin promo assets — desktop and mobile.

The page-2 concept image in the blueprint is a **layout mockup**, not a photo library.

## Acceptable sources
- Own photography of the exact stocked SKU
- Manufacturer / authorized distributor photos with documented permission
- Licensed real stock photos (contextual banners only — never to impersonate an exact SKU)
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
