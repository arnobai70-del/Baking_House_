<?php

namespace Database\Seeders;

use App\Enums\PricingMode;
use App\Enums\ProductStatus;
use App\Enums\SellingUnit;
use App\Models\CakeLibraryItem;
use App\Models\CakeOption;
use App\Models\CakeProduct;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Services\Cakes\CakeLibrary;
use App\Services\Cakes\CakeSettings;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEVELOPMENT ONLY: one designable cake product with clearly marked TEST prices, so the custom-cake
 * flow can be tried end to end before the owner supplies real prices. Every option has is_test = true, which
 * blocks checkout outside local / testing and fails the production readiness check.
 *
 *   php artisan db:seed --class=LocalCakeTestSeeder
 *
 * Re-running is safe (updates in place). Never called from DatabaseSeeder.
 */
class LocalCakeTestSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('LocalCakeTestSeeder skipped: it only runs when APP_ENV=local (current: '.app()->environment().').');

            return;
        }

        $parent = Category::query()->where('slug', 'cake-decoration')->first();
        $category = Category::query()->updateOrCreate(['slug' => 'custom-cakes'], [
            'name' => 'Custom cakes', 'name_bn' => 'কাস্টম কেক', 'parent_id' => $parent?->id, 'icon' => 'cake', 'is_active' => true, 'sort_order' => 90,
            'summary' => 'Design your own cake (TEST prices)',
        ]);

        $product = Product::query()->updateOrCreate(['slug' => 'custom-cake'], [
            'name' => 'Custom Cake (designer)', 'name_bn' => 'কাস্টম কেক (ডিজাইনার)', 'primary_category_id' => $category->id,
            'short_description' => 'Design your own cake: shape, weight, flavour, cream and message. TEST prices for development only.',
            'description' => 'Development listing. Prices, recipes and production capacity must be confirmed before launch.',
            'specifications' => ['Data status' => 'TEST — not verified'], 'search_keywords' => 'custom cake, birthday cake, designer cake',
            'status' => ProductStatus::Active, 'is_featured' => false,
        ]);
        $product->categories()->sync(array_filter([$category->id, $parent?->id]));
        $product->variants()->updateOrCreate(['sku' => 'BH-CAKE-0001'], [
            'name' => 'Custom cake', 'pricing_mode' => PricingMode::QuoteOnly, 'selling_unit' => SellingUnit::Piece, 'stock_quantity' => 0, 'is_active' => true, 'sort_order' => 0,
        ]);

        CakeProduct::query()->updateOrCreate(['product_id' => $product->id], [
            'min_grams' => 500, 'max_grams' => 3000, 'step_grams' => 500, 'max_tiers' => 3,
            'allow_eggless' => false, 'allow_sugar_free' => false, 'allow_both_diets' => false, // owner: off until recipes are verified
            'ingredients' => 'TEST: flour, sugar, eggs, butter, milk, cocoa, cream (to be confirmed by the bakery).',
            'allergens' => 'TEST: gluten, eggs, milk; may contain nuts (to be confirmed).',
        ]);

        // [group, code, name, name_bn, price_type, taka, complexity, requires_quote]
        $options = [
            ['flavour', 'vanilla', 'Vanilla', 'ভ্যানিলা', 'per_kg', 1200, 'simple', false],
            ['flavour', 'chocolate', 'Chocolate', 'চকলেট', 'per_kg', 1400, 'simple', false],
            ['flavour', 'red-velvet', 'Red velvet', 'রেড ভেলভেট', 'per_kg', 1600, 'standard', false],
            ['flavour', 'black-forest', 'Black forest', 'ব্ল্যাক ফরেস্ট', 'per_kg', 1500, 'standard', false],
            ['shape', 'round', 'Round', 'গোল', 'flat', 0, 'simple', false],
            ['shape', 'square', 'Square', 'চারকোনা', 'flat', 100, 'simple', false],
            ['shape', 'rectangle', 'Rectangle', 'আয়তাকার', 'flat', 150, 'simple', false],
            ['shape', 'heart', 'Heart', 'হার্ট', 'flat', 250, 'standard', false],
            ['shape', 'number', 'Number / letter', 'সংখ্যা / অক্ষর', 'flat', 500, 'complex', false],
            ['tier', '2', '2 tiers', '২ স্তর', 'flat', 1500, 'complex', false],
            ['tier', '3', '3 tiers', '৩ স্তর', 'flat', 3000, 'complex', true],
            ['filling', 'ganache', 'Chocolate ganache filling', 'চকলেট গানাশ ফিলিং', 'per_kg', 150, 'standard', false],
            ['filling', 'fruit', 'Fresh fruit filling', 'তাজা ফলের ফিলিং', 'per_kg', 200, 'standard', false],
            ['cream', 'buttercream', 'Buttercream', 'বাটারক্রিম', 'per_kg', 100, 'standard', false],
            ['cream', 'fondant', 'Fondant covering', 'ফন্ডেন্ট কভারিং', 'per_kg', 400, 'complex', false],
            ['diet', 'eggless', 'Eggless', 'ডিম ছাড়া', 'per_kg', 150, 'standard', false],
            ['diet', 'sugar_free', 'Sugar-free', 'চিনি ছাড়া', 'per_kg', 200, 'standard', false],
            ['message', 'standard', 'Custom message', 'কাস্টম বার্তা', 'flat', 50, 'simple', false],
            ['print', 'standard', 'Edible photo print', 'খাওয়ার উপযোগী ছবি প্রিন্ট', 'flat', 300, 'standard', false],
            ['extra', 'fresh-fruit', 'Fresh fruit topping', 'তাজা ফলের টপিং', 'flat', 250, 'standard', false],
            ['drip', 'chocolate', 'Chocolate drip', 'চকলেট ড্রিপ', 'flat', 200, 'standard', false],
            ['drip', 'caramel', 'Caramel drip', 'ক্যারামেল ড্রিপ', 'flat', 220, 'standard', false],
            ['drip', 'coloured', 'Coloured drip', 'রঙিন ড্রিপ', 'flat', 250, 'standard', false],
            ['colour', 'custom', 'Custom cream colour', 'নিজের পছন্দের ক্রিমের রং', 'flat', 100, 'simple', false],
        ];
        $dripColours = ['chocolate' => '#4a2a1a', 'caramel' => '#c47a2c', 'coloured' => '#e86f9a'];
        foreach ($options as $order => [$group, $code, $name, $nameBn, $type, $taka, $complexity, $quote]) {
            CakeOption::query()->updateOrCreate(['cake_product_id' => null, 'group' => $group, 'code' => $code], [
                'name' => $name, 'name_bn' => $nameBn, 'price_type' => $type, 'price_paisa' => $taka * 100, 'is_test' => true,
                'requires_quote' => $quote, 'complexity' => $complexity, 'is_active' => true, 'sort_order' => $order,
                'meta' => $group === 'drip' ? ['colour' => $dripColours[$code]] : null,
            ]);
        }
        // Part 1 sold the drip as an "extra"; it is its own style group now.
        CakeOption::query()->whereNull('cake_product_id')->where('group', 'extra')->where('code', 'chocolate-drip')->update(['is_active' => false]);

        $this->library();

        app(CakeSettings::class)->save(['cake.orders_open' => true, 'cake.daily_capacity' => 10]);

        PickupLocation::query()->updateOrCreate(['name' => 'TEST pickup point'], [
            'name_bn' => 'টেস্ট পিকআপ পয়েন্ট', 'address' => 'TEST address — replace before launch', 'hours' => '10:00–20:00 (TEST)', 'is_active' => true,
        ]);

        $dhaka = DeliveryZone::forDistrict('Dhaka');
        $dhaka?->forceFill(['cake_delivery_enabled' => true, 'cake_charge_paisa' => 15000, 'cake_tiered_allowed' => false])->save();

        $this->command?->info('Custom cake TEST data ready: /custom-cake (all prices marked TEST; Dhaka cake delivery and one TEST pickup point enabled locally).');
    }

    /**
     * TEST design library: our own simple vector shapes only (no characters, logos or third-party artwork).
     */
    private function library(): void
    {
        $circle = fn (float $cx, float $cy, float $r): string => sprintf('M%s %s a%s %s 0 1 0 %s 0 a%s %s 0 1 0 %s 0 Z', $cx - $r, $cy, $r, $r, 2 * $r, $r, $r, -2 * $r);
        $star = 'M50 5 L61 38 L96 38 L68 59 L79 93 L50 72 L21 93 L32 59 L4 38 L39 38 Z';
        $heart = 'M50 88 C20 66 5 48 5 30 C5 16 16 6 29 6 C39 6 46 12 50 20 C54 12 61 6 71 6 C84 6 95 16 95 30 C95 48 80 66 50 88 Z';
        $balloon = [['d' => 'M50 8 C70 8 82 24 82 42 C82 60 66 74 50 76 C34 74 18 60 18 42 C18 24 30 8 50 8 Z', 'fill' => 'accent'],
            ['d' => 'M45 75 L55 75 L50 82 Z', 'fill' => 'accent'], ['d' => 'M49 82 C45 88 53 92 49 98 L51 98 C55 92 47 88 51 82 Z', 'fill' => '#5b6b78'],
            ['d' => 'M34 30 C36 22 42 18 48 17 C42 22 38 27 37 34 Z', 'fill' => '#ffffff']];
        $flower = collect(range(0, 4))->map(fn (int $i) => ['d' => $circle(50 + 22 * cos(deg2rad($i * 72 - 90)), 50 + 22 * sin(deg2rad($i * 72 - 90)), 17), 'fill' => 'accent'])
            ->push(['d' => $circle(50, 50, 12), 'fill' => '#f5c542'])->all();
        $leaf = [['d' => 'M50 95 C20 70 15 35 50 5 C85 35 80 70 50 95 Z', 'fill' => '#5f9e6e'], ['d' => 'M49 88 L49 16 L51 16 L51 88 Z', 'fill' => '#3f7a50']];
        $butterfly = [['d' => $circle(32, 36, 20), 'fill' => 'accent'], ['d' => $circle(68, 36, 20), 'fill' => 'accent'],
            ['d' => $circle(36, 68, 13), 'fill' => 'accent'], ['d' => $circle(64, 68, 13), 'fill' => 'accent'], ['d' => 'M47 22 L53 22 L53 84 L47 84 Z', 'fill' => '#3b3b3b']];
        $gift = [['d' => 'M15 42 L85 42 L85 92 L15 92 Z', 'fill' => 'accent'], ['d' => 'M10 30 L90 30 L90 44 L10 44 Z', 'fill' => 'accent'],
            ['d' => 'M46 30 L54 30 L54 92 L46 92 Z', 'fill' => '#ffffff'], ['d' => $circle(42, 22, 8), 'fill' => '#ffffff'], ['d' => $circle(58, 22, 8), 'fill' => '#ffffff']];
        $sun = collect(range(0, 7))->map(function (int $i) {
            $a = deg2rad($i * 45);
            $p = fn (float $r, float $o) => round(50 + $r * cos($a + $o), 1).' '.round(50 + $r * sin($a + $o), 1);

            return ['d' => 'M'.$p(30, -0.18).' L'.$p(46, 0).' L'.$p(30, 0.18).' Z', 'fill' => '#f5a623'];
        })->push(['d' => $circle(50, 50, 24), 'fill' => '#f5c542'])->all();
        $crown = [['d' => 'M10 78 L10 30 L30 50 L50 16 L70 50 L90 30 L90 78 Z', 'fill' => '#e5b83a'], ['d' => 'M10 78 L90 78 L90 90 L10 90 Z', 'fill' => '#c9972a'],
            ['d' => $circle(50, 62, 6), 'fill' => 'accent']];
        $sprinkles = collect([[12, 20, 0.6], [70, 14, -0.4], [40, 40, 1.1], [82, 52, 0.2], [20, 70, -0.9], [58, 78, 0.5], [30, 48, 0.1], [76, 86, -1.2]])
            ->map(function (array $s, int $i) {
                [$x, $y, $a] = $s;
                $dx = round(7 * cos($a), 1);
                $dy = round(7 * sin($a), 1);
                $nx = round(-2 * sin($a), 1);
                $ny = round(2 * cos($a), 1);

                return ['d' => sprintf('M%s %s L%s %s L%s %s L%s %s Z', $x - $dx, $y - $dy, $x + $dx, $y + $dy, $x + $dx + $nx, $y + $dy + $ny, $x - $dx + $nx, $y - $dy + $ny),
                    'fill' => ['#e86f9a', '#4fb3bf', '#f5c542', '#8e6bd1'][$i % 4]];
            })->all();

        // [kind, code, name, name_bn, tags, taka, complexity, shapes|null, payload]
        $items = [
            ['palette', 'classic', 'Classic cream', 'ক্লাসিক ক্রিম', ['white', 'simple'], 0, 'simple', null, ['colours' => ['#fffaf3', '#f3e5d0', '#d9b99b', '#103b56', '#f4c2c2', '#c9e4de']]],
            ['palette', 'pastel', 'Pastel party', 'প্যাস্টেল পার্টি', ['pastel', 'birthday', 'kids'], 0, 'simple', null, ['colours' => ['#f7d6e0', '#c9e4de', '#fbe7a1', '#d7c9f0', '#bde0fe', '#ffffff']]],
            ['palette', 'royal', 'Royal celebration', 'রাজকীয় উৎসব', ['wedding', 'anniversary', 'gold'], 50, 'standard', null, ['colours' => ['#1d3557', '#e5b83a', '#ffffff', '#7a1f3d', '#f3e5d0']]],
            ['border', 'shell', 'Shell piping', 'শেল পাইপিং', ['classic', 'piping'], 100, 'standard', null, ['style' => 'shell', 'colour' => 'accent']],
            ['border', 'bead', 'Pearl beads', 'মুক্তার দানা', ['elegant', 'wedding'], 80, 'simple', null, ['style' => 'bead', 'colour' => 'accent']],
            ['border', 'rope', 'Rope twist', 'দড়ির প্যাঁচ', ['classic'], 80, 'standard', null, ['style' => 'rope', 'colour' => 'accent']],
            ['border', 'ruffle', 'Ruffle', 'রাফল', ['frilly', 'romantic'], 120, 'standard', null, ['style' => 'ruffle', 'colour' => 'accent']],
            ['border', 'dots', 'Dotted piping', 'ডট পাইপিং', ['simple', 'kids'], 50, 'simple', null, ['style' => 'dots', 'colour' => 'accent']],
            ['pattern', 'polka', 'Polka dots', 'পোলকা ডট', ['kids', 'fun'], 120, 'standard', null, ['style' => 'dots', 'colour' => 'accent']],
            ['pattern', 'stripes', 'Stripes', 'স্ট্রাইপ', ['modern'], 120, 'standard', null, ['style' => 'stripes', 'colour' => 'accent']],
            ['pattern', 'zigzag', 'Zigzag', 'জিগজ্যাগ', ['fun', 'kids'], 140, 'standard', null, ['style' => 'zigzag', 'colour' => 'accent']],
            ['pattern', 'hearts', 'Little hearts', 'ছোট হার্ট', ['love', 'anniversary'], 150, 'standard', null, ['style' => 'hearts', 'colour' => 'accent']],
            ['pattern', 'stars', 'Little stars', 'ছোট তারা', ['kids', 'birthday'], 150, 'standard', null, ['style' => 'stars', 'colour' => 'accent']],
            ['pattern', 'scallop', 'Scallops', 'স্ক্যালপ', ['elegant'], 150, 'complex', ['round', 'square'], ['style' => 'scallop', 'colour' => 'accent']],
            ['font', 'classic-sans', 'Clear sans (English & বাংলা)', 'পরিষ্কার স্যান্স (English ও বাংলা)', ['bangla', 'english'], 0, 'simple', null, ['family' => 'Noto Sans', 'stack' => '"Noto Sans", "Noto Sans Bengali", sans-serif', 'weight' => 700, 'scripts' => ['en', 'bn']]],
            ['font', 'elegant-serif', 'Elegant serif', 'এলিগ্যান্ট সেরিফ', ['wedding', 'english'], 0, 'simple', null, ['family' => 'Georgia', 'stack' => 'Georgia, "Times New Roman", "Noto Sans Bengali", serif', 'weight' => 400, 'scripts' => ['en']]],
            ['font', 'playful', 'Playful handwriting', 'খেলাচ্ছলে হাতের লেখা', ['kids', 'english'], 0, 'simple', null, ['family' => 'Comic', 'stack' => '"Comic Sans MS", "Segoe Print", "Noto Sans Bengali", cursive', 'weight' => 700, 'scripts' => ['en']]],
            ['topper', 'star', 'Star topper', 'তারা টপার', ['star', 'birthday', 'kids'], 150, 'standard', null, ['viewBox' => '0 0 100 100', 'paths' => [['d' => $star, 'fill' => '#f5c542']]]],
            ['topper', 'heart', 'Heart topper', 'হার্ট টপার', ['love', 'anniversary'], 150, 'standard', null, ['viewBox' => '0 0 100 100', 'paths' => [['d' => $heart, 'fill' => 'accent']]]],
            ['topper', 'balloon', 'Balloon topper', 'বেলুন টপার', ['birthday', 'kids', 'party'], 180, 'standard', null, ['viewBox' => '0 0 100 100', 'paths' => $balloon]],
            ['topper', 'crown', 'Crown topper', 'মুকুট টপার', ['princess', 'king', 'birthday'], 250, 'complex', null, ['viewBox' => '0 0 100 100', 'paths' => $crown]],
            ['decoration', 'flower', 'Sugar flower', 'চিনির ফুল', ['flower', 'garden', 'wedding'], 120, 'standard', null, ['viewBox' => '0 0 100 100', 'paths' => $flower]],
            ['decoration', 'leaf', 'Leaf', 'পাতা', ['garden', 'nature'], 60, 'simple', null, ['viewBox' => '0 0 100 100', 'paths' => $leaf]],
            ['decoration', 'butterfly', 'Butterfly', 'প্রজাপতি', ['garden', 'kids'], 100, 'standard', null, ['viewBox' => '0 0 100 100', 'paths' => $butterfly]],
            ['decoration', 'sprinkles', 'Sprinkle burst', 'স্প্রিংকল', ['fun', 'kids', 'birthday'], 40, 'simple', null, ['viewBox' => '0 0 100 100', 'paths' => $sprinkles]],
            ['sticker_pack', 'party', 'Party sticker pack', 'পার্টি স্টিকার প্যাক', ['birthday', 'party', 'kids'], 200, 'standard', null, ['stickers' => [
                ['name' => 'Balloon', 'viewBox' => '0 0 100 100', 'paths' => $balloon], ['name' => 'Gift', 'viewBox' => '0 0 100 100', 'paths' => $gift],
                ['name' => 'Star', 'viewBox' => '0 0 100 100', 'paths' => [['d' => $star, 'fill' => '#f5c542']]]]]],
            ['sticker_pack', 'garden', 'Garden sticker pack', 'বাগান স্টিকার প্যাক', ['garden', 'nature', 'spring'], 180, 'standard', null, ['stickers' => [
                ['name' => 'Flower', 'viewBox' => '0 0 100 100', 'paths' => $flower], ['name' => 'Leaf', 'viewBox' => '0 0 100 100', 'paths' => $leaf],
                ['name' => 'Sun', 'viewBox' => '0 0 100 100', 'paths' => $sun], ['name' => 'Butterfly', 'viewBox' => '0 0 100 100', 'paths' => $butterfly]]]],
            ['template', 'birthday-stars', 'Happy birthday stars', 'শুভ জন্মদিন তারা', ['birthday', 'kids', 'stars'], 0, 'standard', null, ['layers' => [
                ['type' => 'text', 'text' => 'Happy Birthday', 'font' => 'classic-sans', 'x' => 0.5, 'y' => 0.36, 'size' => 0.09, 'fill' => '#103b56', 'curve' => 0.45],
                ['type' => 'text', 'text' => 'Ayaan', 'font' => 'playful', 'x' => 0.5, 'y' => 0.6, 'size' => 0.12, 'fill' => '#e86f9a'],
                ['type' => 'item', 'kind' => 'topper', 'item' => 'star', 'x' => 0.24, 'y' => 0.62, 'w' => 0.14, 'rotation' => -12],
                ['type' => 'item', 'kind' => 'topper', 'item' => 'star', 'x' => 0.76, 'y' => 0.62, 'w' => 0.14, 'rotation' => 12]]]],
            ['template', 'anniversary-hearts', 'Anniversary hearts', 'বিবাহবার্ষিকী হার্ট', ['anniversary', 'love', 'wedding'], 0, 'standard', null, ['layers' => [
                ['type' => 'item', 'kind' => 'topper', 'item' => 'heart', 'x' => 0.5, 'y' => 0.46, 'w' => 0.42],
                ['type' => 'text', 'text' => 'Happy Anniversary', 'font' => 'elegant-serif', 'x' => 0.5, 'y' => 0.78, 'size' => 0.07, 'fill' => '#7a1f3d']]]],
            ['template', 'shubho-jonmodin', 'শুভ জন্মদিন (Bangla)', 'শুভ জন্মদিন', ['bangla', 'birthday'], 0, 'standard', null, ['layers' => [
                ['type' => 'text', 'text' => 'শুভ জন্মদিন', 'font' => 'classic-sans', 'x' => 0.5, 'y' => 0.42, 'size' => 0.1, 'fill' => '#103b56', 'curve' => 0.35],
                ['type' => 'item', 'kind' => 'decoration', 'item' => 'flower', 'x' => 0.5, 'y' => 0.7, 'w' => 0.18]]]],
        ];

        $library = app(CakeLibrary::class);
        foreach ($items as $order => [$kind, $code, $name, $nameBn, $tags, $taka, $complexity, $shapes, $payload]) {
            CakeLibraryItem::query()->updateOrCreate(['kind' => $kind, 'code' => $code], [
                'name' => $name, 'name_bn' => $nameBn, 'tags' => $tags, 'price_paisa' => $taka * 100, 'is_test' => true, 'complexity' => $complexity,
                'allowed_shapes' => $shapes, 'payload' => $library->normalisePayload($kind, $payload), 'is_active' => true, 'sort_order' => $order,
            ]);
        }
    }
}
