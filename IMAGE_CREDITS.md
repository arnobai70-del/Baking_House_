# Image credits

Every photograph on NadimShop.com is recorded here (asset record fields from `docs/image-policy.md`). All photos are
real photographs from one of two sources:
- Pexels, under the [Pexels License](https://www.pexels.com/license/);
- Unsplash, under the [Unsplash License](https://unsplash.com/license) (free tier, not Unsplash+).

Each source page was checked on 2026-11-22.

**Since D-074 (2026-11-22) AI-generated images are also allowed** anywhere on the site. Record each one here like any
other image, with the generating tool as the source and the generation date. In use: the homepage hero scene
(owner-supplied, 23 Nov 2026).

**What both licences allow:**
- free commercial use and editing;
- attribution is optional (given here anyway).

**What they prohibit:**
- selling unaltered copies;
- redistribution on stock-photo sites or in a competing service;
- implying endorsement;
- use in a trademark or business name.

None of these apply here.

**How the photos were processed:** crop and resize only, then WebP encoding. No retouching, compositing or generative
editing. Untouched originals are kept outside Git in `platform/storage/app/private/image-originals/` (git-ignored).
Originals of photos that are no longer used are in its `retired/` folder.

**How the photos were checked:**
- no AI, illustration or render label on any source page;
- camera EXIF on the page where noted;
- no watermarks, no faces, and no other brands' logos or packaging in the published crops.

## 1. Homepage and landing scenes (contextual images; allowed by the image policy)

| Files (`platform/public/images/…`) | Used on | Source | Photographer | Published | Camera | Edits |
|---|---|---|---|---|---|---|
| `hero/supply-scene-{800,1200,1312}.webp` | Homepage hero scene (full-width hero, right ~60%), EN and BN | **AI-generated, supplied by the owner** (homepage design preview, 23 Nov 2026; D-074) | Owner (generating tool not recorded) | 23 Nov 2026 | — | Crop of the preview’s scene area only (x 860–2172, y 60–724; the preview’s own text and menu excluded); resized, no upscaling. Untouched preview kept as `owner-ai-hero-preview-2026-11-23.png` |
| `banners/restaurant-wok-{600,1200}.webp` | Homepage "restaurants, cafés & commercial kitchens" panel | [Pexels 10324702](https://www.pexels.com/photo/10324702/) | Taha Samet Arslan | 22 Nov 2021 | not stated | 3:2 crop around the wok and flame; resized |
| `contextual/business-kitchen-line-{800,1600}.webp` | `/business` landing | [Pexels 4947388](https://www.pexels.com/photo/4947388/) | Maria Orlova | 24 Jul 2020 | not stated | 4:3 centre crop of the 2784×1856 original; resized (replaces the darker stove photo, owner review 24 Nov 2026) |
| `contextual/reseller-warehouse-aisle-{800,1600}.webp` | `/reseller` landing | [Pexels 5156696](https://www.pexels.com/photo/boxes-on-shelves-inside-a-warehouse-5156696/) | [Maor Attias](https://www.pexels.com/@maorattias/) | 21 Aug 2020 | Canon EOS 6D | 4:3 crop that excludes people, a branded forklift and box labels |

## 2. Homepage category tiles (contextual images, one matched set)

All seven tiles share one format:
- light neutral background and a 4:3 crop;
- 800×600, plus 600×450 and 400×300 versions (`<slug>-600.webp`, `<slug>-400.webp`), in `platform/public/images/categories/`;
- decorative (empty alt), because the category title link sits next to each.

| Tile (file = category slug) | Shows | Source | Photographer | Published | Camera |
|---|---|---|---|---|---|
| Baking Tools (`baking-tools`) | muffin tin and round cake tin | [Pexels 16412992](https://www.pexels.com/photo/16412992/) | Beyzaa | 16 Apr 2023 | not stated |
| Ingredients (`ingredients`) | flour, sugar, milk, butter, eggs, whisk | [Pexels 6730181](https://www.pexels.com/photo/6730181/) | Son Tung Tran | 5 Feb 2021 | Canon EOS-1D X Mk II |
| Cake Decoration (`cake-decoration`) | rainbow sprinkles in a bowl | [Pexels 7110179](https://www.pexels.com/photo/7110179/) | Tara Winstead | 11 Mar 2021 | Canon EOS 5D Mk IV |
| Restaurant & Chinese Kitchen (`restaurant-supply`) | stainless GN pans in a counter | [Pexels 7140435](https://www.pexels.com/photo/7140435/) | Julia Filirovska | 14 Mar 2021 | Canon EOS 5D Mk II |
| Commercial Kitchen Equipment (`kitchen-equipment`) | stand mixer on a counter | [Pexels 6996330](https://www.pexels.com/photo/6996330/) | Pavel Danilyuk | 1 Mar 2021 | not stated |
| Packaging & Takeaway (`packaging`) | stacked plain boxes | [Pexels 4271686](https://www.pexels.com/photo/4271686/) | alleksana | 29 Apr 2020 | Sony ILCE-7M3 |
| Bulk & Wholesale (`bulk-wholesale`) | taped cartons on a shelf | [Pexels 12354555](https://www.pexels.com/photo/12354555/) | Ayrat | 2 Jun 2022 | Canon EOS 5D Mk III |

## 3. Product stock photos — PREVIEW ONLY (decision D-071, tightened by D-072)

> **Preview only — replace with own or supplier photo before launch.** Every row in this table is a stand-in for the
> dev preview, and none of them may go live:
> - launch check L-IMG-1 in `docs/image-policy.md` blocks them;
> - `php artisan app:readiness --target=production` fails while any remain.

**What they are:**
- licensed stock photos, shown only for generic products;
- always labelled "Representative image" / "প্রতিনিধিত্বমূলক ছবি";
- not photos of the exact item sold;
- each shows the product itself on a light neutral background, square framed.

**What happens next:**
- An approved own or supplier photo (Admin › Photo review) replaces a stock photo automatically.
- To remove one by hand, delete its line in `platform/config/representative_images.php` and its files.

**Format:** files are in `platform/public/images/representative/`, at 800×800, plus a 400×400 version for cards
(`<slug>-400.webp`).

| Product (file = slug) | Status | Source | Photographer | Published | Camera | Crop |
|---|---|---|---|---|---|---|
| Dark Compound Chocolate (`dark-compound-chocolate`) | preview only — replace with own or supplier photo before launch | [Pexels 675955](https://www.pexels.com/photo/chocolate-chips-on-mug-675955/) | [Snapwire](https://www.pexels.com/@snapwire/) | 7 Nov 2017 | not stated | square on the cup of chips |
| Premium All Purpose Flour (`premium-all-purpose-flour`) | preview only — replace with own or supplier photo before launch | [Pexels 2522610](https://www.pexels.com/photo/2522610/) | zhugewala | 19 Jun 2019 | Canon EOS 5D Mk IV | square on the bowl; excludes eggs and a cucumber |
| Fine Caster Sugar (`fine-caster-sugar`) | preview only — replace with own or supplier photo before launch | [Pexels 11477544](https://www.pexels.com/photo/a-spoonful-of-sugar-11477544/) | [Towfiqu barbhuiya](https://www.pexels.com/@towfiqu-barbhuiya-3440682/) | 14 Mar 2022 | Fujifilm X-T4 | square on the jar |
| Cocoa Powder (Alkalised) (`cocoa-powder-alkalised`) | preview only — replace with own or supplier photo before launch | [Pexels 5078591](https://www.pexels.com/photo/5078591/) | Nataliya Vaitkevich | 10 Aug 2020 | not stated | square on the cocoa spoon; excludes a second powder |
| Rainbow Sprinkles Mix (`rainbow-sprinkles-mix`) | preview only — replace with own or supplier photo before launch | [Pexels 7110170](https://www.pexels.com/photo/7110170/) | Tara Winstead | 11 Mar 2021 | Canon EOS 5D Mk IV | square on the bowl |
| Cake Turntable 12" (`cake-turntable-12`) | preview only — replace with own or supplier photo before launch | [Pexels 7525172](https://www.pexels.com/photo/delicious-homemade-cake-on-round-stand-7525172/) | [SHVETS production](https://www.pexels.com/@shvets-production/) | 15 Apr 2021 | Nikon Z 6 | square on the turntable |
| Cake Box 10" (`cake-box-10`) | preview only — replace with own or supplier photo before launch | [Pexels 31703552](https://www.pexels.com/photo/delicious-brownies-in-kraft-box-on-table-31703552/) | [Natalia S](https://www.pexels.com/@planka/) | 19 Apr 2025 | not stated | square on the box; excludes a second cake |
| Takeaway Container 750 ml (`takeaway-container-750-ml`) | preview only — replace with own or supplier photo before launch | [Unsplash D0FWHT-eW_I](https://unsplash.com/photos/white-plastic-container-on-brown-wooden-table-D0FWHT-eW_I) | Agenlaku Indonesia | 13 Aug 2020 | Samsung SM-G955F | square on the lidded container; excludes a cup |
| Soy Sauce (Bulk) (`soy-sauce-bulk`) | preview only — replace with own or supplier photo before launch | [Pexels 20843711](https://www.pexels.com/photo/chopsticks-over-bowl-of-sauce-20843711/) | [Ivan Vi](https://www.pexels.com/@juanvidas/) | 31 Mar 2024 | Sony ILCE-6400 | square on the dish |

**Kept as placeholders (no stock photo):**
- **Equipment where the model matters:** Stand Mixer 7 L, Deck Oven 2-Deck, Deep Fryer, Food Warmer.
- **No honest light, product-only photo found (2026-11-22 review):**
  - Carbon Steel Wok 14": the previous photo showed food cooking, not the wok.
  - GN Pan: the previous photo was a dark kitchen. The only light GN photo is used on the restaurant tile, and the
    product is featured on the same page.
  - Chafing Dish 9 L: the previous photo showed food.
  - Round Cake Pan 8": the previous photo was dark and showed the cake.
  - Squeeze Bottle 700 ml: the previous photo was dark.
- **Silicone Mould (6 Cavity):** no honest match exists for a 6-cavity tray.
- **Combos:** the combo card.

**Photos rejected during selection:**
- other brands' logos (an Electrolux oven behind a wok flame; branded mixers, flour bags and packaging);
- dark or moody shots;
- photos where food or a dessert hides the product;
- cosmetic bottles instead of condiment bottles;
- labels priced in roubles or Turkish lira;
- a plastics manufacturer's own product photo;
- home-pantry scenes;
- anything on Unsplash+.

**Retired on 2026-11-22 (files deleted, originals moved to `retired/`):**
- the old hero, Pexels 7405059 (a dark display counter);
- the bread-shelves hero, Pexels 35993723 (replaced by a mixed restaurant / kitchen scene so the hero is not bakery-only);
- the `/business` stove photo, Pexels 6375558 (owner review 24 Nov 2026: too dark);
- the dark kitchen-line hero, Pexels 4947388 (owner review: replaced by the light split hero; the photo now serves the `/business` landing, untinted);
- the kitchen-range hero, Pexels 34276646 (owner review: replaced by a bright bakery-production kitchen closer to the approved reference);
- the bakery-kitchen hero (Unsplash u7egjbn8tzk) and the cake inset (Pexels 8478181): replaced on 23 Nov 2026 by the owner's own AI scene;
- the bakery banner (Pexels 3983578) and the catering band (Pexels 2337843): the homepage no longer repeats department links in banners;
- the old flour, Pexels 5734741 (dark background);
- the old cocoa, Pexels 9179843 (texture only);
- the old sprinkles, Pexels 8471600 (blue surface);
- the GN pan, Pexels 34416078;
- the wok, Pexels 8250277;
- the chafing dish, Pexels 5374416;
- the round cake pan, Pexels 14043681;
- the takeaway container, Pexels 37851490 (black background);
- the squeeze bottle, Pexels 5779367.

## 4. Checksums (SHA-256)

| File | SHA-256 |
|---|---|
| `hero/supply-scene-1200.webp` | `d9f0d10f2b104e74d186ed63cd2ffe02593a032a27e58d8a09ec82bac088f53e` |
| `hero/supply-scene-1312.webp` | `f801d8343831c7d98d1284809c1269e3038475d04bc9ef7386a1e14dccae9c65` |
| `hero/supply-scene-800.webp` | `faf2e11ca7e6a5e9d9c5a8491180a645d01e1ef21e8ef0e2a4d48d2c208df00d` |
| `banners/restaurant-wok-1200.webp` | `88aecffb51b5af70ea4708e6b31579cab96524e00c31b74ac4a44c61016e50fb` |
| `banners/restaurant-wok-600.webp` | `991a9d803134df9c0d34b2d36fc79dd3a241fa481fe40890f192784109e5e6f1` |
| `categories/baking-tools-400.webp` | `d02742ddb656d3c6fce074e3a716f1683860b50f76469d7d7473a7b7ca9cab10` |
| `categories/baking-tools-600.webp` | `9d1d84fcf33e165115166017f2c5bd189e54506e269056e8bda93a7ed2fed57d` |
| `categories/baking-tools.webp` | `8a247b2560577fd81ac45de0dadd738ce45437529bd20e994c33648e0dd4873e` |
| `categories/bulk-wholesale-400.webp` | `b45f71dd1a3c77ddbac534bb1f68199c90aeba0890ada13d627818ef176fe314` |
| `categories/bulk-wholesale-600.webp` | `1dbb7b0c28d71f6f27403de6a2784bde72bb75338363410f24b5412ffd315ece` |
| `categories/bulk-wholesale.webp` | `7c5754f719253d56d4e5fa0cf798baa86f821a3543206f7dbaca0124b80fa180` |
| `categories/cake-decoration-400.webp` | `cbc7e50b02ebd7ed33a6d876b4647341c3ecf11952b81a0424816164c26ee865` |
| `categories/cake-decoration-600.webp` | `636853ee5b53ef3e010bf2011a4ce31bd3135cb5500250ed21470eab6a595cb6` |
| `categories/cake-decoration.webp` | `d5e033b5a8212439e63bd5767cc41b769a3927ee1485fa5c5b589167bc854125` |
| `categories/ingredients-400.webp` | `5df9d1c178aa7b172523a35ff9beda01bc4b04aeb5ebb713c00c001bf97fb3d5` |
| `categories/ingredients-600.webp` | `afd47d10c94e1ad9a983fb89a9f8b9114b8bfd3e447aab66a12f5d766ee88f7d` |
| `categories/ingredients.webp` | `e335b113a35e92afc22b885096b345cf83fbeba27ba15107690eb6ee799cc835` |
| `categories/kitchen-equipment-400.webp` | `233f920900ecd2917ac681b57a605e7afc6e651d9cfde2182a9e7edead7d3df0` |
| `categories/kitchen-equipment-600.webp` | `06f18860e5382088b151f2921ef956bcf481f91cd5d3cba43c48576e9520f6b7` |
| `categories/kitchen-equipment.webp` | `fb31d4212adc7cd2a1d153f186bf852699cbc198e689755e1238fd4f12999603` |
| `categories/packaging-400.webp` | `3addce47d80f2500c299c0e62110d7f929d6a73b2cc147f1a8b37cfe0ed21773` |
| `categories/packaging-600.webp` | `72811db850126965ab7cc3576abc48561b56a3eed6e3aa6a4a4eb81abae906a3` |
| `categories/packaging.webp` | `ff2c7faa9eb2431d1a4e147e7e93340b258337b7a4065dfe7ee0d186fc57345c` |
| `categories/restaurant-supply-400.webp` | `ef42f087200e116f2d814b8862e9174e141f539d34e1628c77964b4080c3ce95` |
| `categories/restaurant-supply-600.webp` | `d0021e544cdd5de40e28c082143e4682baceef8f23522dd465535edacafa41a2` |
| `categories/restaurant-supply.webp` | `815e90656936885d3736939a3275ad7dde27ab12ed5e41a30f48c2763c534793` |
| `contextual/business-kitchen-line-1600.webp` | `97fd11f1e10ca92cca1786e20a429d2c406f6c50b8b8f3aee0c2925832b2ba03` |
| `contextual/business-kitchen-line-800.webp` | `b6341898dd9cf688dcfc970ef76f29929699781cc773d00e41f0379160a20838` |
| `contextual/reseller-warehouse-aisle-1600.webp` | `80b47aac3a088df76dd25fd1ba41189299ae15b874fe2c99dd62265ee2777820` |
| `contextual/reseller-warehouse-aisle-800.webp` | `cc4f1ccfc7d92b95f53e176e5cac7a80dd207b2ea9b956e0f8d4a5c57fdbcda0` |
| `representative/cake-box-10-400.webp` | `bc74fb0b656b606748df03510aa6e175c987df85d2ee880d85b4dc1a126b2ae6` |
| `representative/cake-box-10.webp` | `7ef09bfc27cb672757bb439efe5afdd4780bb3d3ce946cf1e7de6db5ca8fa42d` |
| `representative/cake-turntable-12-400.webp` | `ad6d92be12b20bb53f9bc05a4c3aff1369b3e6bdc264740711a1e8f70a4ef9ec` |
| `representative/cake-turntable-12.webp` | `15defc426cb14203bbe7cedca83223cf3a976637ff5fb0e447b243af41e1f6d1` |
| `representative/cocoa-powder-alkalised-400.webp` | `7f487dee35c85987ea2d025d181a58d715d765cb718972ad19b9aa7e7e0212fd` |
| `representative/cocoa-powder-alkalised.webp` | `a5a801a504e2413fd628e7c159666c2b313ac949af52ca7faf6d3b95a8121d82` |
| `representative/dark-compound-chocolate-400.webp` | `ff260cc43a8f3803b14389ffa62f5899a8dc0e5189bd876f08a77b9bbea8fcb3` |
| `representative/dark-compound-chocolate.webp` | `27ead96d54bc5f02c076a0cdb4a7398f9a68e6a1c47b659cc00e9d2edcaeaf52` |
| `representative/fine-caster-sugar-400.webp` | `2107e87e411c8eb94a40ec478b867e3daf91f8a01d3b6a694d181640031d8088` |
| `representative/fine-caster-sugar.webp` | `8b3fe0c407cad015201fec0ed5f70aa556e64e4d61fe73d1f5321042ebaefa92` |
| `representative/premium-all-purpose-flour-400.webp` | `267f1155568d08e173c79a51e7ddcc829614cbc821302dce514bee61ce4f576c` |
| `representative/premium-all-purpose-flour.webp` | `4212d98276fa012ff5ce820070b8219d240304a2d6c55ee30d69de77f0879cb2` |
| `representative/rainbow-sprinkles-mix-400.webp` | `75074e7946804d53885037ed422c0a9aa8b0191fbadb3f61b053b84206347014` |
| `representative/rainbow-sprinkles-mix.webp` | `38187ff8e4f52e508a6f00072ce9d11a4aaf258b262f13244f73288985a3c2d2` |
| `representative/soy-sauce-bulk-400.webp` | `67b7465136a25cd2ac059ea0553f9090fe1a0989c0d9e48bdeaf5a657c5eb622` |
| `representative/soy-sauce-bulk.webp` | `303c948f021fdfb091def92f07e76cb1efebbfe7ab51e110ca70a02a924b935a` |
| `representative/takeaway-container-750-ml-400.webp` | `043c356ec1b61c18fe79b1128a599dd4fdf1e0188e82739dc1eb19c74aca9c93` |
| `representative/takeaway-container-750-ml.webp` | `589717d0d664a64ad9b2d8e36b96188ac5de9dc159f48486470a0a1400b6f2c9` |
| `original: owner-ai-hero-preview-2026-11-23.png` | `009cedd6c6e6b01f896a110961a9903625a54a6a8795eb92f54572c526fd8760` |
| `original: pexels-10324702-taha-samet-arslan.jpg` | `112ffcbbd2fa71e02ba24b3b962cde64c7105aca66d6f3f61d351f3c1b8a8e7e` |
| `original: pexels-11477544-towfiqu-barbhuiya.jpg` | `b64a1a02d6f5de8430966194bdec520f2a1fa657ec8f270315276fa14b221604` |
| `original: pexels-12354555-ayrat.jpg` | `c8b117f9fb7575866e1872531da97564aa5353a532236aff4329a18461dcf550` |
| `original: pexels-16412992-beyzaa.jpg` | `0809f30b61a8d2597cc37a37d8a9f0c85d83db4df5483fdbcf64cff61ec9ff52` |
| `original: pexels-20843711-ivan-vi.jpg` | `3cb106daaffb23d23ebde898299167d311825efe7a6946ae2eb93fcc6aa87b7e` |
| `original: pexels-2522610-zhugewala.jpg` | `c0f10e65367460dabcebc896149f2328b23fff8943f2db589c316ae4358d3ef6` |
| `original: pexels-31703552-natalia-s.jpg` | `002686734dccd6b0679e189f4b5d6715930adcb19c83766084b09885971fd688` |
| `original: pexels-4271686-alleksana.jpg` | `8643d700174a48adc7312bb797b3b8e01b9454b28df5c63d9eb0f126ce0f6c56` |
| `original: pexels-4947388-maria-orlova.jpg` | `3347bc182c792c3903f5ace5abf80596d8d2bf58931a61f19fd83213a54d12df` |
| `original: pexels-5078591-nataliya-vaitkevich.jpg` | `96fec6a13a8b7f94e9d2c26838674ac46dd1b05780836c6fe15ce21d915c6558` |
| `original: pexels-5156696-maor-attias.jpg` | `9ef52386a8fa493454750bb13c00e1b06f805b323d752b45e3a68fefe80df381` |
| `original: pexels-6730181-son-tung-tran.jpg` | `c7afce1cd431fb97656730f6382c8c04dc2d28cecdae874abd66159644738471` |
| `original: pexels-675955-snapwire.jpg` | `be6fdc9423e99cecbe026eb36c048c5e4d0d02d1cfa796c457abf0cb0e062537` |
| `original: pexels-6996330-pavel-danilyuk.jpg` | `d6b5c6af24e36d55e969544031ea052e7e2aea96f525d498a1590473f3e1cb98` |
| `original: pexels-7110170-tara-winstead.jpg` | `6b4dbc2c2eef91c02db5942b148c52bf5f1ac00a414a0101a2f8e92e7151b7e8` |
| `original: pexels-7110179-tara-winstead.jpg` | `0d619ca523431a228bfee729ff9d740ff09b968dcd71a925fbb80af542792630` |
| `original: pexels-7140435-julia-filirovska.jpg` | `5a19fa380a248ebb34ee45000346ec3ef6f3b237f4a147a3c5cc8853e528cab9` |
| `original: pexels-7525172-shvets-production.jpg` | `0856bbbc669d734dd78830283c89d91177306866fa2f9fb4b9278c9aec225ef8` |
| `original: unsplash-D0FWHT-eW_I-agenlaku-indonesia.jpg` | `6ccf6db52efd72ab51edd69804542ffa8fcd41d94c9b87e03066dcd35e0627db` |

If a licence is withdrawn or a photo is reported, delete its file (and, for products, its config line). The page then falls back to the placeholder automatically.
