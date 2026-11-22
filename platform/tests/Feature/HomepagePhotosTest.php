<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D-072: homepage hero photo (right ~55%, LCP), real photos in the audience banners and on every category tile.
 */
class HomepagePhotosTest extends TestCase
{
    use RefreshDatabase;

    private const SLUGS = ['baking-tools', 'ingredients', 'cake-decoration', 'restaurant-supply', 'kitchen-equipment', 'packaging', 'bulk-wholesale'];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::SLUGS as $order => $slug) {
            Category::factory()->create(['slug' => $slug, 'name' => ucwords(str_replace('-', ' ', $slug)), 'sort_order' => $order]);
        }
    }

    public function test_the_hero_is_one_eager_responsive_photo_with_a_five_point_list_and_service_facts_below(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('images/hero/kitchen-range-600.webp 600w', false)
            ->assertSee('images/hero/kitchen-range-900.webp 900w', false)
            ->assertSee('images/hero/kitchen-range-1200.webp 1200w', false)
            ->assertSee('alt="Commercial kitchen range with stainless shelves of pans and pots"', false)
            ->assertSee('alt="Pastry chef piping cream rosettes onto a cake"', false)
            ->getContent();

        preg_match('/<img class="hero-main"[^>]+>/', $html, $hero);
        $this->assertNotEmpty($hero, 'The hero photo is rendered.');
        $this->assertStringContainsString('fetchpriority="high"', $hero[0]);
        $this->assertStringContainsString('width="1200" height="1500"', $hero[0]);
        $this->assertStringNotContainsString('loading=', $hero[0], 'The LCP image is never lazy-loaded.');

        preg_match('/<ul class="hero-points[^"]*">(.*?)<\/ul>/s', $html, $points);
        $this->assertSame(5, substr_count($points[1] ?? '', '<li>'), 'Five verified departments / buying features.');

        // Service facts move up under the hero (once, not again above the footer) and keep their "pending" notes.
        $this->assertSame(1, substr_count($html, 'class="trust-strip"'));
        $this->assertLessThan(strpos($html, 'id="cat-title"'), strpos($html, 'class="trust-strip"'));
        $this->assertStringContainsString('Courier partners pending', $html);
    }

    public function test_featured_products_are_grouped_by_department_from_real_data(): void
    {
        $category = Category::query()->where('slug', 'restaurant-supply')->firstOrFail();
        $product = Product::factory()->withFixedVariant()->create(['primary_category_id' => $category->id, 'name' => 'GN Pan Test']);

        $this->get('/')->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('id="dpanel-restaurant-supply"', false)
            ->assertSee($product->name)
            ->assertDontSee('id="dpanel-packaging"', false); // a department without products gets no tab
    }

    public function test_banners_and_all_seven_category_tiles_show_a_real_photo(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('images/banners/restaurant-wok-1200.webp', false)
            ->assertSee('images/banners/bakery-cake-1200.webp', false)
            ->getContent();

        foreach (self::SLUGS as $slug) {
            $this->assertStringContainsString("images/categories/{$slug}-400.webp 400w", $html);
        }
        $this->assertSame(7, substr_count($html, 'class="cat-photo"'));
        $this->assertStringNotContainsString('Representative image', $html, 'Category tiles carry no stock-photo label.');
    }

    public function test_bangla_homepage_uses_bangla_alt_text(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);

        $this->get('/bn')->assertOk()
            ->assertSee('alt="স্টেইনলেস তাকে প্যান ও হাঁড়িসহ বাণিজ্যিক কিচেন রেঞ্জ"', false)
            ->assertSee('alt="বাণিজ্যিক রান্নাঘরে উঁচু আগুনে কালো ওক নাড়ছেন রাঁধুনি"', false)
            ->assertSee('alt="কেক টার্নটেবিলে চকলেট ড্রিপ কেক সাজাচ্ছেন পেস্ট্রি শেফ"', false);
    }
}
