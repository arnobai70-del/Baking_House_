<?php

namespace Database\Seeders;

use App\Enums\PricingMode;
use App\Enums\ProductStatus;
use App\Enums\SellingUnit;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * LOCAL DEVELOPMENT ONLY — illustrative catalog mirroring the Phase 1 prototype.
 * Not real products, prices, stock or brands. Never runs outside the local environment.
 */
class DevelopmentCatalogSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string, 3: string, 4: list<string>}>
     */
    private const CATEGORIES = [
        ['baking-tools', 'Baking Tools', 'Cake & baking tools', 'whisk', ['Cake moulds & pans', 'Turntables', 'Piping & nozzles', 'Silicone moulds']],
        ['ingredients', 'Ingredients', 'Flour, sugar, chocolate', 'wheat', ['Flour & premixes', 'Sugar & sweeteners', 'Cocoa & chocolate']],
        ['cake-decoration', 'Cake Decoration', 'Chocolate, fondant, sprinkles', 'cake', ['Compound chocolate', 'Sprinkles', 'Fondant & icing']],
        ['restaurant-supply', 'Restaurant & Chinese Kitchen', 'Woks, GN pans, serveware', 'wok', ['Woks & Chinese cookware', 'GN pans & containers', 'Serving & buffet']],
        ['kitchen-equipment', 'Commercial Kitchen Equipment', 'Mixers, ovens, fryers', 'oven', ['Mixers', 'Ovens', 'Fryers & warmers']],
        ['packaging', 'Packaging & Takeaway', 'Boxes, bags, cups', 'box', ['Cake boxes', 'Food containers']],
        ['bulk-wholesale', 'Bulk & Wholesale', 'For business buyers', 'pallet', ['Bulk ingredients', 'Carton packs']],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DevelopmentCatalogSeeder skipped: not a local environment.');

            return;
        }

        $subcategories = [];
        foreach (self::CATEGORIES as $order => [$slug, $name, $summary, $icon, $children]) {
            $parent = Category::create([
                'slug' => $slug, 'name' => $name, 'summary' => $summary, 'icon' => $icon, 'sort_order' => $order + 1, 'is_active' => true,
                'description' => "Illustrative development copy for {$name}. Replace with approved category copy before launch.",
            ]);
            foreach ($children as $childOrder => $childName) {
                $subcategories[$childName] = Category::create([
                    'parent_id' => $parent->id, 'slug' => Str::slug($childName), 'name' => $childName, 'icon' => $icon, 'sort_order' => $childOrder + 1, 'is_active' => true,
                ]);
            }
        }

        $brands = collect(['Brand A', 'Brand B', 'Brand C', 'Brand D'])
            ->mapWithKeys(fn (string $name) => [$name => Brand::create(['name' => $name, 'slug' => Str::slug($name), 'is_active' => true])]);

        $weight = fn (int $perKgTaka, int $stockGrams, array $extra = []) => array_merge([
            'pricing_mode' => PricingMode::Weight, 'selling_unit' => SellingUnit::Gram, 'per_kg_paisa' => $perKgTaka * 100,
            'min_grams' => 100, 'max_grams' => 25000, 'step_grams' => 50, 'preset_grams' => [100, 250, 500, 700, 900, 1000],
            'stock_quantity' => $stockGrams, 'low_stock_threshold' => 1000,
        ], $extra);
        $fixed = fn (int $taka, int $stock, array $extra = []) => array_merge([
            'pricing_mode' => PricingMode::Fixed, 'selling_unit' => SellingUnit::Piece, 'price_paisa' => $taka * 100, 'stock_quantity' => $stock, 'low_stock_threshold' => 3,
        ], $extra);
        $quote = fn (int $stock) => ['pricing_mode' => PricingMode::QuoteOnly, 'selling_unit' => SellingUnit::Piece, 'stock_quantity' => $stock];

        $products = [
            ['Dark Compound Chocolate', 'Compound chocolate', 'Brand A', true, 'chocolate, compound, couverture, চকলেট', [['BH-ING-0001', 'Standard', $weight(650, 18450)]]],
            ['Premium All Purpose Flour', 'Flour & premixes', 'Brand B', true, 'flour, maida, atta, ময়দা', [['BH-ING-0002', 'Standard', $weight(115, 240000, ['min_grams' => 500, 'step_grams' => 500, 'max_grams' => 100000, 'preset_grams' => [500, 1000, 2000, 5000]])]]],
            ['Fine Caster Sugar', 'Sugar & sweeteners', null, false, 'sugar, caster, চিনি', [['BH-ING-0003', 'Standard', $weight(160, 90000)]]],
            ['Cocoa Powder (Alkalised)', 'Cocoa & chocolate', 'Brand A', false, 'cocoa, chocolate powder', [['BH-ING-0004', 'Standard', $weight(980, 600, ['low_stock_threshold' => 1000])]]],
            ['Rainbow Sprinkles Mix', 'Sprinkles', 'Brand A', false, 'sprinkles, decoration', [['BH-DEC-0001', 'Standard', $weight(900, 7000)]]],
            ['Stand Mixer 7 L (Commercial)', 'Mixers', 'Brand C', true, 'mixer, stand mixer, planetary', [['BH-EQP-0001', 'Standard', $fixed(62900, 6)]]],
            ['Food Warmer (8 Pan)', 'Fryers & warmers', 'Brand C', false, 'warmer, bain marie', [['BH-EQP-0002', 'Standard', $fixed(10500, 3)]]],
            ['Deep Fryer (Commercial)', 'Fryers & warmers', 'Brand C', false, 'fryer', [['BH-EQP-0003', 'Standard', $fixed(18900, 0)]]],
            ['Deck Oven 2-Deck', 'Ovens', 'Brand C', false, 'oven, deck oven', [['BH-EQP-0004', 'Standard', $quote(2)]]],
            ['GN Pan (Stainless Steel)', 'GN pans & containers', 'Brand D', true, 'gn pan, gastronorm, steel', [
                ['BH-RST-0001-11', '1/1 size', $fixed(2450, 80)],
                ['BH-RST-0001-12', '1/2 size', $fixed(1650, 60)],
            ]],
            ['Carbon Steel Wok 14"', 'Woks & Chinese cookware', null, true, 'wok, chinese, karahi, কড়াই', [['BH-RST-0002', 'Standard', $fixed(1850, 40, ['compare_at_paisa' => 210000])]]],
            ['Squeeze Bottle 700 ml', 'Serving & buffet', null, false, 'squeeze, sauce bottle', [['BH-RST-0003', 'Standard', $fixed(120, 500)]]],
            ['Chafing Dish 9 L', 'Serving & buffet', 'Brand D', false, 'chafing, buffet', [['BH-RST-0005', 'Standard', $fixed(3990, 14)]]],
            ['Cake Turntable 12"', 'Turntables', 'Brand B', false, 'turntable, cake stand', [['BH-BKT-0001', 'Standard', $fixed(1250, 35)]]],
            ['Silicone Mould (6 Cavity)', 'Silicone moulds', null, false, 'mould, mold, silicone, কেক মোল্ড', [['BH-BKT-0002', 'Standard', $fixed(650, 60)]]],
            ['Round Cake Pan 8"', 'Cake moulds & pans', 'Brand B', false, 'cake pan, mould, mold', [['BH-BKT-0003', 'Standard', $fixed(480, 90)]]],
            ['Cake Box 10"', 'Cake boxes', null, false, 'cake box, packaging', [['BH-PKG-0001', 'Pack of 25', $fixed(950, 200, ['selling_unit' => SellingUnit::Pack, 'pack_size' => 25])]]],
            ['Takeaway Container 750 ml', 'Food containers', null, false, 'container, takeaway', [['BH-PKG-0002', 'Pack of 50', $fixed(720, 150, ['selling_unit' => SellingUnit::Pack, 'pack_size' => 50])]]],
            ['Soy Sauce (Bulk)', 'Carton packs', 'Brand D', false, 'soy sauce, chinese, bulk', [['BH-BLK-0001', 'Carton', $quote(40)]]],
        ];

        foreach ($products as [$name, $subcategory, $brand, $featured, $keywords, $variants]) {
            $category = $subcategories[$subcategory];
            $product = Product::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'primary_category_id' => $category->id,
                'brand_id' => $brand ? $brands[$brand]->id : null,
                'short_description' => "Illustrative development listing for {$name}. Not a real product or price.",
                'description' => 'Development placeholder description. Product facts, specifications and photography must be verified before launch.',
                'specifications' => ['Data status' => 'Illustrative — not verified'],
                'search_keywords' => $keywords,
                'status' => ProductStatus::Active,
                'is_featured' => $featured,
            ]);
            $product->categories()->sync([$category->id, $category->parent_id]);

            foreach ($variants as $order => [$sku, $variantName, $attributes]) {
                $product->variants()->create(['sku' => $sku, 'name' => $variantName, 'sort_order' => $order, 'is_active' => true] + $attributes);
            }
        }
    }
}
