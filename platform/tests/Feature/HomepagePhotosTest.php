<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Homepage as one journey (D-072, D-075, owner reference 2026-11-22): a hero with the LCP photo, department
 * cards as the page's only department list, service facts once, and no product shown in two blocks.
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

    public function test_the_hero_has_one_eager_responsive_photo_a_five_point_list_and_service_facts_below(): void
    {
        $html = $this->get('/')->assertOk()
            ->assertSee('images/hero/bakery-kitchen-800.webp 800w', false)
            ->assertSee('images/hero/bakery-kitchen-1200.webp 1200w', false)
            ->assertSee('images/hero/bakery-kitchen-1600.webp 1600w', false)
            ->assertSee('alt="Bright commercial bakery kitchen with spiral dough mixers, a sheet-pan rack and prep tables"', false)
            ->assertSee('alt="Pastry chef piping cream rosettes onto a cake"', false)
            ->getContent();

        preg_match('/<img class="hero-x-photo"[^>]+>/', $html, $hero);
        $this->assertNotEmpty($hero, 'The hero photo is rendered.');
        $this->assertStringContainsString('fetchpriority="high"', $hero[0]);
        $this->assertStringContainsString('width="1600" height="1143"', $hero[0]);
        $this->assertStringNotContainsString('loading=', $hero[0], 'The LCP image is never lazy-loaded.');

        preg_match('/<ul class="hero-points[^"]*">(.*?)<\/ul>/s', $html, $points);
        $this->assertSame(5, substr_count($points[1] ?? '', '<li>'), 'Five verified departments / buying features.');

        // Service facts appear once, right after the hero (not again above the footer), still marked "pending".
        $this->assertSame(1, substr_count($html, 'class="trust-strip"'));
        $this->assertLessThan(strpos($html, 'class="trust-strip"'), strpos($html, 'class="dept-rail"'));
        $this->assertStringContainsString('Courier partners pending', $html);
    }

    public function test_departments_are_listed_once_as_cards_with_photos_and_live_counts(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(7, substr_count($html, 'class="dept-card"'));
        foreach (self::SLUGS as $slug) {
            $this->assertStringContainsString("images/categories/{$slug}-400.webp 400w", $html);
        }
        $this->assertStringNotContainsString('class="cat-grid"', $html, 'No second department grid.');
        $this->assertStringNotContainsString('class="aud-panel', $html, 'No banners that only repeat department links.');
        $this->assertStringNotContainsString('Representative image', $html, 'Department photos carry no stock-photo label.');
    }

    public function test_no_product_appears_in_two_homepage_blocks(): void
    {
        $restaurant = Category::query()->where('slug', 'restaurant-supply')->firstOrFail();
        $baking = Category::query()->where('slug', 'baking-tools')->firstOrFail();
        $wok = Product::factory()->withFixedVariant()->create(['primary_category_id' => $restaurant->id, 'name' => 'Unique Wok Test']);
        Product::factory()->withFixedVariant()->create(['primary_category_id' => $baking->id, 'name' => 'Unique Tin Test']);
        $wok->categories()->syncWithoutDetaching([$baking->id]); // listed in both departments

        $html = $this->get('/')->assertOk()
            ->assertSee('images/banners/restaurant-wok-1200.webp', false)
            ->assertSee('role="tablist"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '>Unique Wok Test<'), 'Shown once, in the restaurant & commercial block.');
        $this->assertSame(1, substr_count($html, '>Unique Tin Test<'));
        $this->assertStringNotContainsString('id="dpanel-restaurant-supply"', $html, 'Restaurant products are not repeated in the featured tabs.');
        $this->assertLessThan(strpos($html, 'id="commercial-title"'), strpos($html, '>Unique Tin Test<'));
        $this->assertGreaterThan(strpos($html, 'id="commercial-title"'), strpos($html, '>Unique Wok Test<'));
    }

    public function test_bangla_homepage_uses_bangla_alt_text(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);
        $restaurant = Category::query()->where('slug', 'restaurant-supply')->firstOrFail();
        Product::factory()->withFixedVariant()->create(['primary_category_id' => $restaurant->id]);

        $this->get('/bn')->assertOk()
            ->assertSee('alt="স্পাইরাল ডো মিক্সার, শিট-প্যান র‍্যাক ও প্রস্তুতি টেবিলসহ উজ্জ্বল বাণিজ্যিক বেকারি কিচেন"', false)
            ->assertSee('alt="কেকের ওপর ক্রিমের নকশা করছেন পেস্ট্রি শেফ"', false)
            ->assertSee('alt="বাণিজ্যিক রান্নাঘরে উঁচু আগুনে কালো ওক নাড়ছেন রাঁধুনি"', false);
    }
}
