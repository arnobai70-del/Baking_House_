<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Setting;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * D-071: labelled stock photos for generic products only; approved product photos and missing files win.
 */
class RepresentativeImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_generic_products_show_a_labelled_representative_image_on_card_page_and_cart(): void
    {
        $product = Product::factory()->withFixedVariant()->create(['slug' => 'rainbow-sprinkles-mix', 'name' => 'Rainbow Sprinkles Mix']);

        $this->get('/shop')->assertOk()
            ->assertSee('images/representative/rainbow-sprinkles-mix.webp', false)
            ->assertSee('Representative image')
            ->assertSee('alt="Rainbow sprinkles in a white bowl"', false)
            ->assertSee('images/representative/rainbow-sprinkles-mix-400.webp 400w', false)
            ->assertSee('loading="eager"', false); // first row of a listing loads eagerly (it is the LCP element)
        $this->get('/')->assertOk()->assertSee('images/representative/rainbow-sprinkles-mix.webp', false)
            ->assertSee('loading="lazy"', false); // home cards sit below the hero
        $this->get($product->url())->assertOk()
            ->assertSee('images/representative/rainbow-sprinkles-mix.webp', false)
            ->assertSee('Representative image')
            ->assertSee('fetchpriority="high"', false)
            ->assertDontSee('Real product photo pending');

        $this->post('/cart/items', ['variant' => $product->variants()->first()->id, 'unit_quantity' => 1])->assertRedirect();
        $cart = Cart::query()->latest('id')->firstOrFail();
        $this->withCookie(CartService::COOKIE, $cart->token)->get('/cart')->assertOk()
            ->assertSee('images/representative/rainbow-sprinkles-mix.webp', false);
    }

    public function test_equipment_and_unlisted_products_keep_the_placeholder(): void
    {
        // Equipment, plus products whose only stock photos showed food or a dark scene instead of the product itself.
        foreach (['deck-oven-2-deck', 'carbon-steel-wok-14', 'gn-pan-stainless-steel', 'chafing-dish-9-l'] as $slug) {
            $product = Product::factory()->withFixedVariant()->create(['slug' => $slug]);

            $this->get($product->url())->assertOk()
                ->assertSee('Real product photo pending')
                ->assertDontSee('Representative image')
                ->assertDontSee('images/representative/', false);
        }
    }

    public function test_an_approved_product_photo_replaces_the_representative_image(): void
    {
        $product = Product::factory()->withFixedVariant()->create(['slug' => 'premium-all-purpose-flour']);
        ProductImage::factory()->approved()->create(['product_id' => $product->id]);

        $this->get($product->url())->assertOk()->assertDontSee('images/representative/premium-all-purpose-flour.webp', false);
    }

    public function test_a_missing_file_falls_back_to_the_placeholder(): void
    {
        $product = Product::factory()->withFixedVariant()->create(['slug' => 'no-such-photo-yet']);
        config(['representative_images.no-such-photo-yet' => 'Something']);

        $this->get($product->url())->assertOk()->assertSee('Real product photo pending')->assertDontSee('Representative image');
    }

    public function test_preview_stock_photos_block_a_production_release(): void
    {
        Artisan::call('app:readiness', ['--target' => 'production']);
        $this->assertMatchesRegularExpression('/FAIL\s*\|\s*No preview stock product photos/', Artisan::output());

        config(['representative_images' => []]);
        Artisan::call('app:readiness', ['--target' => 'production']);
        $this->assertMatchesRegularExpression('/PASS\s*\|\s*No preview stock product photos/', Artisan::output());
    }

    public function test_bangla_pages_use_the_bangla_label_and_alt_text(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);
        $product = Product::factory()->withFixedVariant()->create(['slug' => 'soy-sauce-bulk']);

        $this->get('/bn/product/'.$product->slug)->assertOk()
            ->assertSee('প্রতিনিধিত্বমূলক ছবি')
            ->assertSee('alt="ছোট পাত্রে সয়া সস"', false);
    }
}
