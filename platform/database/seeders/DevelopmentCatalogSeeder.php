<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\PriceListAudience;
use App\Enums\PricingMode;
use App\Enums\ProductStatus;
use App\Enums\SellingUnit;
use App\Models\Brand;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
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

        $this->seedTranslationsAndOffers();
    }

    /**
     * Illustrative Bangla names, a business price list, a coupon and a bundle so Phase 4
     * features can be exercised locally. All mock data.
     */
    private function seedTranslationsAndOffers(): void
    {
        $categoryNames = [
            'baking-tools' => ['বেকিং টুলস', 'কেক ও বেকিং সরঞ্জাম'], 'ingredients' => ['উপকরণ', 'ময়দা, চিনি, চকলেট'],
            'cake-decoration' => ['কেক ডেকোরেশন', 'চকলেট, ফন্ড্যান্ট, স্প্রিংকলস'], 'restaurant-supply' => ['রেস্টুরেন্ট ও চাইনিজ কিচেন', 'ওক, জিএন প্যান, পরিবেশন সামগ্রী'],
            'kitchen-equipment' => ['কমার্শিয়াল কিচেন সরঞ্জাম', 'মিক্সার, ওভেন, ফ্রায়ার'], 'packaging' => ['প্যাকেজিং ও টেকঅ্যাওয়ে', 'বক্স, ব্যাগ, কাপ'],
            'bulk-wholesale' => ['বাল্ক ও পাইকারি', 'ব্যবসায়ী ক্রেতাদের জন্য'],
        ];
        foreach ($categoryNames as $slug => [$name, $summary]) {
            Category::query()->where('slug', $slug)->update(['name_bn' => $name, 'summary_bn' => $summary]);
        }
        foreach (['dark-compound-chocolate' => 'ডার্ক কম্পাউন্ড চকলেট', 'premium-all-purpose-flour' => 'প্রিমিয়াম ময়দা', 'carbon-steel-wok-14' => 'কার্বন স্টিল ওক ১৪"', 'gn-pan-stainless-steel' => 'জিএন প্যান (স্টেইনলেস স্টিল)'] as $slug => $name) {
            Product::query()->where('slug', $slug)->update(['name_bn' => $name, 'short_description_bn' => 'পরীক্ষামূলক তালিকা — আসল পণ্য বা মূল্য নয়।']);
        }

        $chocolate = ProductVariant::query()->where('sku', 'BH-ING-0001')->first();
        $flour = ProductVariant::query()->where('sku', 'BH-ING-0002')->first();
        $mixer = ProductVariant::query()->where('sku', 'BH-EQP-0001')->first();
        $turntable = ProductVariant::query()->where('sku', 'BH-BKT-0001')->first();

        $trade = PriceList::query()->create(['name' => 'Demo business tiers', 'audience' => PriceListAudience::Business, 'is_active' => true, 'notes' => 'Illustrative']);
        $trade->items()->create(['product_variant_id' => $chocolate->id, 'min_quantity' => 1, 'per_kg_paisa' => 63000]);
        $trade->items()->create(['product_variant_id' => $chocolate->id, 'min_quantity' => 5000, 'per_kg_paisa' => 60000]);
        $trade->items()->create(['product_variant_id' => $flour->id, 'min_quantity' => 25000, 'per_kg_paisa' => 10200]);

        $retail = PriceList::query()->create(['name' => 'Demo retail volume', 'audience' => PriceListAudience::Retail, 'is_active' => true]);
        $retail->items()->create(['product_variant_id' => $chocolate->id, 'min_quantity' => 2000, 'per_kg_paisa' => 64000]);

        Promotion::query()->create([
            'name' => 'Demo welcome 5%', 'code' => 'WELCOME5', 'discount_type' => DiscountType::Percentage, 'discount_value' => 500,
            'scope' => 'order', 'min_subtotal_paisa' => 100000, 'audiences' => ['retail'], 'per_customer_limit' => 1, 'is_active' => true,
        ]);

        $bundle = Bundle::query()->create([
            'name' => 'Professional Bakery Starter Bundle', 'name_bn' => 'প্রফেশনাল বেকারি স্টার্টার বান্ডেল', 'slug' => 'bakery-starter',
            'description' => 'Illustrative development bundle.', 'discount_type' => DiscountType::Percentage, 'discount_value' => 1000,
            'audiences' => ['retail', 'business', 'reseller'], 'is_active' => true,
        ]);
        $bundle->items()->create(['product_variant_id' => $mixer->id, 'quantity' => 1]);
        $bundle->items()->create(['product_variant_id' => $turntable->id, 'quantity' => 1]);
        $bundle->items()->create(['product_variant_id' => $flour->id, 'quantity' => 5000]);
        $bundle->items()->create(['product_variant_id' => $chocolate->id, 'quantity' => 1000]);
    }
}
