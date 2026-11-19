/**
 * MOCK CATALOG — illustrative only. Not real products, prices, brands, stock or company data.
 * Never publish. Replaced by the Laravel Catalog/Pricing APIs in Phases 3–4.
 *
 * Money: integer paisa (৳1 = 100). Weight: integer grams.
 * pricingMode: FIXED | WEIGHT | QUOTE_ONLY  (VARIANT arrives with the real catalog)
 * image: null everywhere → "photo pending" placeholder (real-photography policy, docs/image-policy.md)
 */

export const CATEGORIES = [
  { slug: 'baking-tools', name: 'Baking Tools', short: 'Cake & baking tools', icon: 'whisk',
    subs: ['Cake moulds & pans', 'Turntables', 'Piping bags & nozzles', 'Measuring tools', 'Silicone moulds'] },
  { slug: 'ingredients', name: 'Ingredients', short: 'Flour, sugar, chocolate', icon: 'wheat',
    subs: ['Flour & premixes', 'Sugar & sweeteners', 'Baking powder & yeast', 'Dairy & fats', 'Flavours & colours'] },
  { slug: 'cake-decoration', name: 'Cake Decoration', short: 'Chocolate, fondant, sprinkles', icon: 'cake',
    subs: ['Compound chocolate', 'Fondant & icing', 'Sprinkles', 'Cake boards', 'Toppers'] },
  { slug: 'restaurant-supply', name: 'Restaurant & Chinese Kitchen', short: 'Woks, GN pans, serveware', icon: 'wok',
    subs: ['Woks & Chinese cookware', 'GN pans & containers', 'Serving & tableware', 'Squeeze bottles', 'Chef knives'] },
  { slug: 'kitchen-equipment', name: 'Commercial Kitchen Equipment', short: 'Mixers, ovens, fryers', icon: 'oven',
    subs: ['Stand & planetary mixers', 'Ovens', 'Deep fryers', 'Food warmers', 'Refrigeration'] },
  { slug: 'packaging', name: 'Packaging & Takeaway', short: 'Boxes, bags, cups', icon: 'box',
    subs: ['Cake boxes', 'Food containers', 'Paper bags', 'Cups & lids', 'Labels'] },
  { slug: 'bulk-wholesale', name: 'Bulk & Wholesale', short: 'For business buyers', icon: 'pallet',
    subs: ['Bulk ingredients', 'Carton packs', 'Restaurant bulk', 'Request a quote'] },
  { slug: 'combos', name: 'Combo & Bundles', short: 'Starter kits & offers', icon: 'gift',
    subs: ['Bakery starter kits', 'Café kits', 'Seasonal bundles'] },
];

export const BRANDS = ['Brand A', 'Brand B', 'Brand C', 'Brand D', 'Generic'];

const w = (perKgTaka, extra = {}) => ({
  perKgPaisa: perKgTaka * 100,
  minGrams: 100,
  maxGrams: 25000,
  stepGrams: 50,
  presets: [100, 250, 500, 700, 900, 1000],
  ...extra,
});

export const PRODUCTS = [
  { id: 1, slug: 'dark-compound-chocolate', sku: 'BH-ING-0001', name: 'Dark Compound Chocolate', category: 'cake-decoration', brand: 'Brand A',
    pricingMode: 'WEIGHT', weight: w(650, { stockGrams: 18450, tiers: { business: [{ fromGrams: 5000, perKgPaisa: 60000 }], reseller: [{ fromGrams: 2000, perKgPaisa: 61000 }, { fromGrams: 10000, perKgPaisa: 58500 }] } }),
    badges: ['Popular'], icon: 'cake', tags: ['chocolate', 'compound', 'chocolate'],
    desc: 'Compound chocolate for coating, ganache-style fillings and decoration. Sold by weight — choose a preset or type any amount in grams or kg.',
    specs: { Type: 'Dark compound', Form: 'Callets (illustrative)', Storage: 'Cool, dry place', 'Sold by': 'Weight (g / kg)', 'Min / step': '100 g / 50 g' } },
  { id: 2, slug: 'premium-all-purpose-flour', sku: 'BH-ING-0002', name: 'Premium All Purpose Flour', category: 'ingredients', brand: 'Brand B',
    pricingMode: 'WEIGHT', weight: w(115, { minGrams: 500, stepGrams: 500, presets: [500, 1000, 2000, 5000], stockGrams: 240000, tiers: { business: [{ fromGrams: 25000, perKgPaisa: 10200 }], reseller: [{ fromGrams: 10000, perKgPaisa: 10500 }] }, maxGrams: 100000 }),
    badges: [], icon: 'wheat', tags: ['flour', 'maida', 'atta'],
    desc: 'General purpose wheat flour for breads, cakes and pastries.',
    specs: { Type: 'Wheat flour', 'Sold by': 'Weight', 'Min / step': '500 g / 500 g' } },
  { id: 3, slug: 'caster-sugar', sku: 'BH-ING-0003', name: 'Fine Caster Sugar', category: 'ingredients', brand: 'Generic',
    pricingMode: 'WEIGHT', weight: w(160, { stockGrams: 90000, tiers: { business: [{ fromGrams: 10000, perKgPaisa: 14500 }] } }),
    badges: [], icon: 'wheat', tags: ['sugar', 'caster'], desc: 'Fine-grain sugar that dissolves quickly in batters and meringues.',
    specs: { Type: 'Caster sugar', 'Sold by': 'Weight' } },
  { id: 4, slug: 'cocoa-powder', sku: 'BH-ING-0004', name: 'Cocoa Powder (Alkalised)', category: 'ingredients', brand: 'Brand A',
    pricingMode: 'WEIGHT', weight: w(980, { stockGrams: 600 }),
    badges: ['Low stock'], icon: 'wheat', tags: ['cocoa', 'chocolate'], desc: 'Alkalised cocoa for cakes, brownies and drinks.',
    specs: { Type: 'Cocoa powder', 'Sold by': 'Weight' } },
  { id: 5, slug: 'stand-mixer-7l', sku: 'BH-EQP-0001', name: 'Stand Mixer 7 L (Commercial)', category: 'kitchen-equipment', brand: 'Brand C',
    pricingMode: 'FIXED', pricePaisa: 6290000, stock: 6, badges: ['Commercial'], icon: 'mixer', tags: ['mixer', 'stand mixer'],
    desc: 'Planetary stand mixer for small bakeries and cafés.', specs: { Capacity: '7 L', Power: 'See manufacturer sheet', Warranty: 'To be confirmed' } },
  { id: 6, slug: 'gn-pan-1-1', sku: 'BH-RST-0001', name: 'GN Pan 1/1 (Stainless Steel)', category: 'restaurant-supply', brand: 'Brand D',
    pricingMode: 'FIXED', pricePaisa: 245000, stock: 80, badges: [], icon: 'tray', tags: ['gn pan', 'gastronorm', 'steel'],
    desc: 'Gastronorm pan for hot holding, prep and buffet lines.', specs: { Size: '1/1 GN', Material: 'Stainless steel' } },
  { id: 7, slug: 'carbon-steel-wok-14', sku: 'BH-RST-0002', name: 'Carbon Steel Wok 14"', category: 'restaurant-supply', brand: 'Generic',
    pricingMode: 'FIXED', pricePaisa: 185000, compareAtPaisa: 210000, stock: 40, badges: ['Chinese kitchen'], icon: 'wok', tags: ['wok', 'chinese', 'karahi'],
    desc: 'High-heat carbon steel wok for Chinese restaurant stations.', specs: { Diameter: '14 in', Material: 'Carbon steel' } },
  { id: 8, slug: 'squeeze-bottle-700ml', sku: 'BH-RST-0003', name: 'Squeeze Bottle 700 ml', category: 'restaurant-supply', brand: 'Generic',
    pricingMode: 'FIXED', pricePaisa: 12000, stock: 500, badges: [], icon: 'bottle', tags: ['squeeze', 'sauce bottle'],
    desc: 'Food-safe squeeze bottle for sauces and dressings.', specs: { Volume: '700 ml' } },
  { id: 9, slug: 'mixing-bowl-steel', sku: 'BH-RST-0004', name: 'Mixing Bowl (Stainless Steel)', category: 'restaurant-supply', brand: 'Brand D',
    pricingMode: 'FIXED', pricePaisa: 35000, stock: 120, badges: [], icon: 'bowl', tags: ['bowl', 'mixing'],
    desc: 'Deep stainless mixing bowl for prep kitchens and bakeries.', specs: { Material: 'Stainless steel' } },
  { id: 10, slug: 'chafing-dish-9l', sku: 'BH-RST-0005', name: 'Chafing Dish 9 L', category: 'restaurant-supply', brand: 'Brand D',
    pricingMode: 'FIXED', pricePaisa: 399000, stock: 14, badges: [], icon: 'tray', tags: ['chafing', 'buffet'],
    desc: 'Buffet chafing dish with water pan.', specs: { Capacity: '9 L' } },
  { id: 11, slug: 'food-warmer-8-pan', sku: 'BH-EQP-0002', name: 'Food Warmer (8 Pan)', category: 'kitchen-equipment', brand: 'Brand C',
    pricingMode: 'FIXED', pricePaisa: 1050000, stock: 3, badges: [], icon: 'oven', tags: ['warmer', 'bain marie'],
    desc: 'Countertop bain-marie style warmer.', specs: { Pans: '8' } },
  { id: 12, slug: 'deep-fryer-commercial', sku: 'BH-EQP-0003', name: 'Deep Fryer (Commercial)', category: 'kitchen-equipment', brand: 'Brand C',
    pricingMode: 'FIXED', pricePaisa: 1890000, stock: 0, badges: [], icon: 'oven', tags: ['fryer'],
    desc: 'Twin-basket commercial fryer.', specs: { Baskets: '2' } },
  { id: 13, slug: 'deck-oven-2-deck', sku: 'BH-EQP-0004', name: 'Deck Oven 2-Deck', category: 'kitchen-equipment', brand: 'Brand C',
    pricingMode: 'QUOTE_ONLY', stock: 2, badges: ['Quote only'], icon: 'oven', tags: ['oven', 'deck oven'],
    desc: 'Large equipment priced per installation and delivery site. Request a quote.', specs: { Decks: '2', Installation: 'Quoted per site' } },
  { id: 14, slug: 'cake-turntable-12', sku: 'BH-BKT-0001', name: 'Cake Turntable 12"', category: 'baking-tools', brand: 'Brand B',
    pricingMode: 'FIXED', pricePaisa: 125000, stock: 35, badges: [], icon: 'turntable', tags: ['turntable', 'cake stand'],
    desc: 'Rotating turntable for icing and decorating.', specs: { Diameter: '12 in' } },
  { id: 15, slug: 'silicone-mold-6', sku: 'BH-BKT-0002', name: 'Silicone Mould (6 Cavity)', category: 'baking-tools', brand: 'Generic',
    pricingMode: 'FIXED', pricePaisa: 65000, stock: 60, badges: [], icon: 'whisk', tags: ['mould', 'mold', 'silicone'],
    desc: 'Flexible mould for cakes, mousse and chocolates.', specs: { Cavities: '6' } },
  { id: 16, slug: 'round-cake-pan-8', sku: 'BH-BKT-0003', name: 'Round Cake Pan 8"', category: 'baking-tools', brand: 'Brand B',
    pricingMode: 'FIXED', pricePaisa: 48000, stock: 90, badges: [], icon: 'whisk', tags: ['cake pan', 'mould'],
    desc: 'Aluminium round pan with removable base.', specs: { Diameter: '8 in' } },
  { id: 17, slug: 'cake-box-10in-pack', sku: 'BH-PKG-0001', name: 'Cake Box 10" (Pack of 25)', category: 'packaging', brand: 'Generic',
    pricingMode: 'FIXED', pricePaisa: 95000, stock: 200, badges: [], icon: 'box', tags: ['cake box', 'packaging'],
    desc: 'Food-grade folding cake box, sold per pack.', specs: { Size: '10 × 10 in', Pack: '25 boxes' } },
  { id: 18, slug: 'takeaway-container-750', sku: 'BH-PKG-0002', name: 'Takeaway Container 750 ml (Pack of 50)', category: 'packaging', brand: 'Generic',
    pricingMode: 'FIXED', pricePaisa: 72000, stock: 150, badges: ['Restaurant'], icon: 'box', tags: ['container', 'takeaway'],
    desc: 'Microwave-safe containers with lids.', specs: { Volume: '750 ml', Pack: '50' } },
  { id: 19, slug: 'sprinkles-mix', sku: 'BH-DEC-0001', name: 'Rainbow Sprinkles Mix', category: 'cake-decoration', brand: 'Brand A',
    pricingMode: 'WEIGHT', weight: w(900, { stockGrams: 7000 }), badges: [], icon: 'cake', tags: ['sprinkles', 'decoration'],
    desc: 'Mixed sprinkles for cakes and cupcakes.', specs: { 'Sold by': 'Weight' } },
  { id: 20, slug: 'soy-sauce-bulk', sku: 'BH-BLK-0001', name: 'Soy Sauce (Bulk Carton)', category: 'bulk-wholesale', brand: 'Brand D',
    pricingMode: 'QUOTE_ONLY', stock: 40, badges: ['Bulk'], icon: 'pallet', tags: ['soy sauce', 'chinese', 'bulk'],
    desc: 'Carton pack for restaurants. Pricing depends on volume and account.', specs: { Pack: 'Carton (size TBC)' } },
];

export const COMBOS = [
  { slug: 'bakery-starter', name: 'Professional Bakery Starter Bundle', blurb: 'Essential tools & ingredients for small to medium bakeries.',
    items: [{ id: 5, qty: 1 }, { id: 14, qty: 1 }, { id: 15, qty: 2 }, { id: 2, grams: 5000 }, { id: 1, grams: 1000 }],
    discountPct: 10 },
  { slug: 'chinese-kitchen-starter', name: 'Chinese Kitchen Starter Set', blurb: 'Woks, prep and service basics for a new Chinese restaurant station.',
    items: [{ id: 7, qty: 2 }, { id: 6, qty: 4 }, { id: 8, qty: 6 }, { id: 9, qty: 2 }], discountPct: 8 },
];

/** Sample order history for dashboards — illustrative only. */
export const SAMPLE_ORDERS = [
  { ref: 'BH-24-000123', date: '2026-11-12', items: 3, totalPaisa: 845000, status: 'Delivered' },
  { ref: 'BH-24-000141', date: '2026-11-16', items: 1, totalPaisa: 45500, status: 'Shipped' },
  { ref: 'BH-24-000152', date: '2026-11-19', items: 5, totalPaisa: 1229000, status: 'Processing' },
];

export const productById = (id) => PRODUCTS.find((p) => p.id === id);
export const productBySlug = (slug) => PRODUCTS.find((p) => p.slug === slug);
export const categoryBySlug = (slug) => CATEGORIES.find((c) => c.slug === slug);
