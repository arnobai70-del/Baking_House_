# Image asset manifest and AI generation prompts (D-074)

Status on 22 Nov 2026 (branch `feature/premium-visual`).

**Limitation:** the Claude Code environment used for this pass has no image-generation tool, so **no image in this
repository was AI-generated**. Every published image today is a licensed real photograph (see `IMAGE_CREDITS.md`).
The prompts below are ready to run in any photorealistic generator. Each result must then go through the normal
route (Admin › Photo review for product images, or a file swap plus an `IMAGE_CREDITS.md` row for scenes).

## How to add a generated image

**Scenes** (hero, banners, category tiles, landing pages):
1. Save the untouched output, with its prompt, tool, version and seed, in `platform/storage/app/private/image-originals/`.
2. Crop and resize to the target files below (WebP; same names, so no code change is needed).
3. Add a row to `IMAGE_CREDITS.md` with the source "AI-generated", the tool, the date and checksums.
4. Visual QA:
   - no warped hands or objects;
   - no garbled text, labels or logos;
   - realistic scale;
   - steel looks like steel;
   - consistent light with the neighbouring images.

**Products:** Admin › Products › Photos.
1. Upload the image.
2. Set "Image source" to **AI-generated image** and put the tool and prompt reference in "creator".
3. Add alt text in English and Bangla.
4. Tick the accuracy confirmation.
5. A second staff member approves it.

Only use AI for a product image when it truthfully shows the real item: same shape, size, colour, material and packaging.

## House style for every prompt

The style suffix below is appended to every prompt:

> photorealistic commercial photography, natural soft daylight with warm accents, realistic materials (brushed
> stainless steel, enamel, kraft paper, wood), true-to-life proportions, sharp focus, shallow depth of field only in
> the background, clean professional composition, neutral colour grading, no people's faces, no text, no logos, no
> brand names, no labels with writing, no watermarks, no illustration, no CGI look

**Negative prompt (where supported):** text, letters, logo, brand, watermark, label writing, extra fingers, distorted
hands, warped utensils, melted objects, duplicate objects, oversaturated, cartoon, 3d render, plastic look, HDR halo.

## Slots

| # | Slot (files in `platform/public/images/`) | Size / ratio | Today | Prompt (append the style suffix) |
|---|---|---|---|---|
| 1 | Homepage hero `hero/kitchen-range-{600,900,1200}.webp` (main, 4:5) + `hero/cake-piping-{400,800}.webp` (inset, 1:1) | 1200×1500 and 800×800; light split layout, text sits beside the photos on white | Licensed photos, Pexels 34276646 + 8478181 | "Bright modern commercial kitchen in Dhaka. On a long stainless prep counter on the right: a professional planetary stand mixer, a carbon-steel wok on a gas burner, trays of fresh pastries and a decorated cake, stacked kraft takeaway boxes and steel GN pans. Left third softly out of focus, clean white tile wall, morning light from the left." |
| 2 | Restaurant banner `banners/restaurant-wok-{600,1200}.webp` | 1200×800 (3:2) | Licensed photo, Pexels 10324702 | "Close view of a black carbon-steel wok tossed over a high gas flame in a restaurant kitchen, stir-fried vegetables in mid-air, chef's gloved hand and forearm only, stainless range, warm flame light." |
| 3 | Bakery banner `banners/bakery-cake-{600,1200}.webp` | 1200×800 | Licensed photo, Pexels 3983578 | "Pastry chef's hands piping cream onto a two-layer celebration cake on an aluminium turntable on a bakery bench, piping bags, palette knife and nozzles beside it, flour-dusted wood, soft window light." |
| 4 | Café / catering band `banners/catering-buffet-{600,1200}.webp` | 1200×800 | Licensed photo, Pexels 2337843 | "Bright café and catering counter: stainless chafing dishes with lids, a tiered pastry stand, white serving bowls and stacked kraft takeaway cups and food containers, light wood and white tile." |
| 5–11 | Category tiles `categories/{slug}.webp` + `-400.webp` | 800×600 (4:3); **one matched set**: same white seamless background, same 30° angle, same soft shadow | Licensed photos (7) | **Common prefix:** "Studio product group on a white seamless background, 30° high angle, soft shadow:" Then, per tile: baking-tools — "round cake tins, a loaf tin, stainless piping nozzles and a piping bag"; ingredients — "bowls of flour, white sugar and cocoa powder with a steel scoop"; cake-decoration — "rainbow sprinkles, white fondant block, edible pearls, a small palette knife"; restaurant-supply — "a black carbon-steel wok, a stainless GN 1/1 pan, a ladle and a spider strainer"; kitchen-equipment — "a silver commercial stand mixer next to a compact countertop convection oven"; packaging — "plain kraft cake boxes, white takeaway containers and paper cups, no print"; bulk-wholesale — "stacked plain brown cartons and two plain woven flour sacks on a pallet". |
| 12 | Category page headers | re-use #5–11 | Same files | — |
| 13 | Combo / bundle card (`bundles/_card` placeholder) | 1200×800 | Placeholder | "Flat lay of a bakery starter kit on light wood: a silver stand mixer, a 12-inch cake turntable, a bag of plain flour and a bowl of chocolate chips, no text." **Must show exactly the combo's real contents.** |
| 14 | Business landing `contextual/business-restaurant-kitchen-*` | 1600×1200 (4:3) | Licensed photo | "Busy but tidy restaurant kitchen pass with stainless shelves, GN pans and plates ready for service, no faces." |
| 15 | Reseller landing `contextual/reseller-warehouse-aisle-*` | 1600×1200 | Licensed photo | "Clean wholesale storeroom with labelled-free cartons, plain sacks and pallets on steel racking, bright even light." |

## Real product photos still outstanding

AI may be used here only if it truthfully shows the real item. The best sources are own photography of the exact
SKU, or a supplier's photos with written permission.

| Product | Main image today | Needed |
|---|---|---|
| Stand Mixer 7 L, Deck Oven 2-Deck, Deep Fryer, Food Warmer 8-Pan | Placeholder | Manufacturer photos of the exact model + 2–3 angles |
| Carbon Steel Wok 14", GN Pan, Chafing Dish 9 L, Round Cake Pan 8", Squeeze Bottle 700 ml, Silicone Mould (6 cavity) | Placeholder | Product-only photo on white + scale shot |
| Dark Compound Chocolate, Flour, Sugar, Cocoa, Sprinkles, Cake Turntable, Cake Box, Takeaway Container, Soy Sauce | Licensed stock photo (preview only) | Own / supplier photo of the exact SKU and pack (launch check L-IMG-1) |
| All 19 | — | Gallery: label / pack close-up, size reference, in-use shot |
