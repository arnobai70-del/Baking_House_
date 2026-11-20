<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\Cart;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Portal\BulkOrderService;
use App\Services\Pricing\PricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * Bulk "SKU, quantity" ordering: validated line by line on the server, never silently adjusted.
 */
class BulkOrderTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    public function test_lines_are_parsed_and_checked_with_the_buyers_own_prices(): void
    {
        [$organization, $owner] = $this->businessWith();
        $choc = $this->weightVariant(65000, 50000, 'CHOC');
        $this->unitVariant(100000, 10, 'PAN');
        ProductVariant::factory()->for(Product::factory())->quoteOnly()->create(['sku' => 'OVEN']);
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($choc, [[5000, 55000]])->create();

        $rows = app(BulkOrderService::class)->check(
            "sku,quantity\nchoc, 6kg\nPAN, 2\nNOPE, 1\nOVEN, 1\nPAN, two\nCHOC, 725g\n# comment line\n",
            PricingContext::forOrganization($organization, $owner->id),
        );

        $byLine = collect($rows)->keyBy('line');
        $this->assertCount(6, $rows, 'Header and comment lines are skipped.');
        $this->assertTrue($byLine[2]['ok'] === false, 'CHOC 6 kg + 725 g merge to 6.725 kg, off the 50 g step.');
        $this->assertTrue($byLine[3]['ok']);
        $this->assertSame(200000, $byLine[3]['line_paisa']);
        $this->assertSame('Unknown SKU.', $byLine[4]['error']);
        $this->assertSame('This item is available by quotation only.', $byLine[5]['error']);
        $this->assertSame('Enter a whole quantity.', $byLine[6]['error']);

        $clean = app(BulkOrderService::class)->check('CHOC, 6kg', PricingContext::forOrganization($organization, $owner->id));
        $this->assertSame(330000, $clean[0]['line_paisa'], '6 kg × ৳550/kg business tier.');
        $this->assertSame('business-tier', $clean[0]['rate_source']);
    }

    public function test_valid_lines_are_added_to_the_cart_and_invalid_ones_reported(): void
    {
        [, $owner] = $this->businessWith();
        $this->weightVariant(65000, 50000, 'CHOC');
        $this->unitVariant(100000, 3, 'PAN');

        $this->actingAs($owner)->post('/business/bulk', ['lines' => "CHOC, 2kg\nPAN, 5\nCHOC, 500", 'action' => 'add'])
            ->assertRedirect(route('cart.show'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, '1 product(s)') && str_contains($status, '1 line(s) were not added'));

        $cart = Cart::query()->where('user_id', $owner->id)->sole();
        $this->assertSame([2500], $cart->items()->pluck('quantity')->all(), 'Duplicate SKUs merged; out-of-stock PAN not added.');
    }

    public function test_csv_upload_is_checked_and_rendered(): void
    {
        [, $owner] = $this->businessWith();
        $this->unitVariant(100000, 10, 'PAN');
        $file = UploadedFile::fake()->createWithContent('order.csv', "\xEF\xBB\xBFSKU,Quantity\r\nPAN,4\r\n");

        $this->actingAs($owner)->post('/business/bulk', ['file' => $file, 'action' => 'check'])
            ->assertOk()->assertSee('1 of 1 line(s) OK')->assertSee('৳4,000', false);
    }

    public function test_empty_and_oversized_bulk_orders_are_refused(): void
    {
        [$organization, $owner] = $this->businessWith();
        $context = PricingContext::forOrganization($organization, $owner->id);

        $this->expectException(ValidationException::class);
        try {
            app(BulkOrderService::class)->check("\n\n", $context);
        } catch (ValidationException) {
            app(BulkOrderService::class)->check(str_repeat("PAN, 1\n", BulkOrderService::MAX_LINES + 1), $context);
        }
    }

    public function test_reseller_bulk_uses_reseller_rates_not_business_rates(): void
    {
        [$organization, $user] = $this->resellerWith();
        $pan = $this->unitVariant(100000, 10, 'PAN');
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($pan, [[1, 90000]])->create();
        PriceList::factory()->audience(PriceListAudience::Reseller)->withTiers($pan, [[1, 75000]])->create();

        $rows = app(BulkOrderService::class)->check('PAN, 2', PricingContext::forOrganization($organization, $user->id));

        $this->assertSame(150000, $rows[0]['line_paisa']);
        $this->assertSame('reseller-tier', $rows[0]['rate_source']);
    }
}
