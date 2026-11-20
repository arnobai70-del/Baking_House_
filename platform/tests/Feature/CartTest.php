<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\Bundle;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

class CartTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    public function test_guest_adds_weight_and_unit_items_and_cart_is_repriced_on_server(): void
    {
        $chocolate = $this->weightVariant();
        $pan = $this->unitVariant();

        $this->post('/cart/items', ['variant' => $chocolate->id, 'weight_quantity' => '0.7', 'unit' => 'kg'])->assertRedirect()->assertCookie(CartService::COOKIE);
        $cart = Cart::query()->sole();
        $this->assertSame(700, $cart->items()->sole()->quantity);

        $this->withCookie(CartService::COOKIE, $cart->token)->post('/cart/items', ['variant' => $pan->id, 'unit_quantity' => 2]);

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/cart')->assertOk()
            ->assertSee('৳455', false)->assertSee('৳2,000', false)->assertSee('৳2,455', false);
    }

    public function test_adding_the_same_weight_item_merges_into_one_line(): void
    {
        $chocolate = $this->weightVariant();
        $cart = $this->cartWith([[$chocolate, 700], [$chocolate, 300]]);

        $this->assertSame(1000, $cart->items()->sole()->quantity);
    }

    public function test_invalid_quantities_are_rejected_not_adjusted(): void
    {
        $chocolate = $this->weightVariant(stockGrams: 1000);

        $this->post('/cart/items', ['variant' => $chocolate->id, 'weight_quantity' => '725'])->assertSessionHasErrors('quantity');
        $this->post('/cart/items', ['variant' => $chocolate->id, 'weight_quantity' => '5000'])->assertSessionHasErrors('quantity');
        $this->post('/cart/items', ['variant' => $chocolate->id, 'weight_quantity' => '70.5'])->assertSessionHasErrors('quantity');
        $this->assertSame(0, CartItem::query()->count());
    }

    public function test_prices_are_never_stored_in_the_cart(): void
    {
        $pan = $this->unitVariant(100000);
        $cart = $this->cartWith([[$pan, 1]]);

        $pan->forceFill(['price_paisa' => 120000])->save();

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/cart')->assertSee('৳1,200', false);
    }

    public function test_guest_cart_merges_into_customer_cart_on_login(): void
    {
        $pan = $this->unitVariant();
        $user = User::factory()->create(['email' => 'cook@example.com']);
        $this->cartWith([[$pan, 1]], $user);
        $guest = $this->cartWith([[$pan, 2]]);

        $this->withCookie(CartService::COOKIE, $guest->token)->post('/login', ['identifier' => 'cook@example.com', 'password' => 'password']);

        $this->assertSame(3, Cart::query()->where('user_id', $user->id)->sole()->items()->sole()->quantity);
        $this->assertNull(Cart::query()->find($guest->id), 'Guest cart is consumed.');
    }

    public function test_cart_items_of_another_cart_cannot_be_changed(): void
    {
        $pan = $this->unitVariant();
        $other = $this->cartWith([[$pan, 1]], User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->patch('/cart/items/'.$other->items()->sole()->id, ['unit_quantity' => 9])->assertNotFound();
        $this->assertSame(1, $other->items()->sole()->quantity);
    }

    public function test_business_member_sees_business_prices_in_cart_retail_does_not(): void
    {
        $pan = $this->unitVariant(100000);
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($pan, [[1, 80000]])->create();
        $owner = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($owner)->create();
        $retail = User::factory()->create();
        $this->cartWith([[$pan, 1]], $owner);
        $this->cartWith([[$pan, 1]], $retail);

        $this->actingAs($owner)->get('/cart')->assertSee('৳800', false)->assertDontSee('৳1,000', false);
        $this->actingAs($retail)->get('/cart')->assertSee('৳1,000', false)->assertDontSee('৳800', false);
    }

    public function test_bundle_can_be_added_and_coupon_feedback_is_shown(): void
    {
        $pan = $this->unitVariant();
        $bundle = Bundle::factory()->withItems([[$pan, 2]])->create();
        Promotion::factory()->coupon('OLD')->create(['ends_at' => now()->subDay()]);

        $this->post('/cart/bundles', ['bundle' => $bundle->id])->assertRedirect();
        $cart = Cart::query()->sole();
        $this->withCookie(CartService::COOKIE, $cart->token)->post('/cart/coupon', ['code' => 'old']);

        $this->withCookie(CartService::COOKIE, $cart->token)->get('/cart')->assertSee($bundle->name)->assertSee('This coupon is not active or has expired.');
    }
}
