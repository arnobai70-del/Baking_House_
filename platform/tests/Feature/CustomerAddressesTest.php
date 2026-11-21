<?php

namespace Tests\Feature;

use App\Models\CustomerAddress;
use App\Models\User;
use App\Services\Addresses\AddressBook;
use App\Services\Cart\CartService;
use App\Services\Pricing\PricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * Retail saved addresses (Phase 9): owner-only, one default, checkout pre-fill and save-at-checkout.
 */
class CustomerAddressesTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function address(array $overrides = []): array
    {
        return $overrides + ['label' => 'Home', 'recipient_name' => 'Rina Akter', 'phone' => '+880 1711-111111', 'line' => 'House 12, Road 4', 'area' => 'Dhanmondi', 'district' => 'Dhaka'];
    }

    public function test_customers_manage_their_own_addresses_with_one_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/account/addresses', $this->address(['phone' => '123']))->assertSessionHasErrors('phone');
        $this->actingAs($user)->post('/account/addresses', $this->address())->assertSessionHasNoErrors();
        $this->actingAs($user)->post('/account/addresses', $this->address(['label' => 'Office', 'line' => 'Level 5, Gulshan Avenue', 'area' => 'Gulshan', 'is_default' => 1]))->assertSessionHasNoErrors();

        $home = $user->addresses()->where('label', 'Home')->sole();
        $office = $user->addresses()->where('label', 'Office')->sole();
        $this->assertSame('01711111111', $home->phone);
        $this->assertFalse($home->is_default);
        $this->assertTrue($office->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());

        $this->actingAs($user)->delete(route('account.addresses.destroy', $office))->assertSessionHasNoErrors();
        $this->assertTrue($home->fresh()->is_default, 'The remaining address becomes the default.');

        $this->actingAs($user)->get('/account/addresses')->assertOk()->assertSee('House 12, Road 4');
    }

    public function test_another_customers_address_is_not_found(): void
    {
        $this->get('/account/addresses')->assertRedirect('/login');
        $owner = User::factory()->create();
        $address = app(AddressBook::class)->add($owner->addresses(), ['label' => 'Home', 'recipient_name' => 'X', 'phone' => '01711111111', 'line' => 'Road 1', 'area' => 'Mirpur', 'district' => 'Dhaka'], false);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)->put(route('account.addresses.update', $address), $this->address())->assertNotFound();
        $this->actingAs($intruder)->delete(route('account.addresses.destroy', $address))->assertNotFound();
        $this->actingAs($intruder)->get('/account/addresses')->assertDontSee('Road 1');
        $this->assertSame('Road 1', $address->fresh()->line);
    }

    public function test_address_book_is_capped(): void
    {
        $user = User::factory()->create();
        foreach (range(1, AddressBook::MAX_ADDRESSES) as $i) {
            CustomerAddress::query()->forceCreate(['user_id' => $user->id, 'label' => "A{$i}", 'recipient_name' => 'X', 'phone' => '01711111111', 'line' => "Road {$i}", 'area' => 'Mirpur', 'district' => 'Dhaka']);
        }

        $this->actingAs($user)->post('/account/addresses', $this->address())->assertSessionHasErrors('label');
    }

    public function test_checkout_prefills_the_default_address_and_offers_to_save_new_ones(): void
    {
        $this->zones();
        $user = User::factory()->create();
        $this->cartWith([[$this->unitVariant(), 1]], $user);

        $this->actingAs($user)->get('/checkout')->assertOk()->assertSee('Save this address to my account')->assertDontSee('Use a saved address');

        app(AddressBook::class)->add($user->addresses(), ['label' => 'Home', 'recipient_name' => 'Rina Akter', 'phone' => '01711111111', 'line' => 'House 12, Road 4', 'area' => 'Dhanmondi', 'district' => 'Dhaka'], true);
        $this->actingAs($user)->get('/checkout')->assertOk()
            ->assertSee('Use a saved address')->assertSee('value="House 12, Road 4"', false)->assertDontSee('Save this address to my account');
    }

    public function test_save_at_checkout_adds_the_address_once(): void
    {
        $this->zones();
        $user = User::factory()->create();
        $pan = $this->unitVariant(100000, 20);
        $form = ['name' => 'Rina Akter', 'phone' => '01711111111', 'address_line' => 'House 12, Road 4', 'area' => 'Dhanmondi', 'district' => 'Dhaka', 'payment_method' => 'cod', 'save_address' => 1];

        foreach ([1, 2] as $attempt) {
            $this->cartWith([[$pan, 1]], $user);
            $this->actingAs($user)->post('/checkout/review', $form)->assertRedirect(route('checkout.review'));
            $review = $this->actingAs($user)->get('/checkout/review')->getContent();
            preg_match('/name="checkout_token" value="([^"]+)"/', $review, $token);
            preg_match('/name="fingerprint" value="([^"]+)"/', $review, $fingerprint);
            $this->actingAs($user)->post('/checkout/place', ['checkout_token' => $token[1], 'fingerprint' => $fingerprint[1], 'confirm' => 1])->assertRedirect();
        }

        $this->assertSame(1, $user->addresses()->count(), 'Saved once, not duplicated on the second order.');
        $this->assertSame(['Dhanmondi', true], [$user->addresses()->sole()->label, $user->addresses()->sole()->is_default]);
    }

    public function test_guests_and_organization_buyers_never_write_a_personal_address(): void
    {
        $this->zones();
        [$organization, $owner] = $this->businessWith();
        $cart = app(CartService::class)->forUser($owner);
        app(CartService::class)->addVariant($cart, $this->unitVariant(100000, 20), 1, PricingContext::forOrganization($organization, $owner->id));

        $this->actingAs($owner)->get('/business/dashboard');
        $this->actingAs($owner)->get('/checkout')->assertOk()->assertDontSee('Save this address to my account');
        $this->actingAs($owner)->post('/checkout/review', ['name' => 'X', 'phone' => '01711111111', 'address_line' => 'Kitchen, Road 9', 'area' => 'Uttara', 'district' => 'Dhaka', 'payment_method' => 'cod', 'save_address' => 1]);
        $review = $this->actingAs($owner)->get('/checkout/review')->getContent();
        preg_match('/name="checkout_token" value="([^"]+)"/', $review, $token);
        preg_match('/name="fingerprint" value="([^"]+)"/', $review, $fingerprint);
        $this->actingAs($owner)->post('/checkout/place', ['checkout_token' => $token[1], 'fingerprint' => $fingerprint[1], 'confirm' => 1])->assertRedirect();

        $this->assertSame(0, CustomerAddress::query()->count());
    }
}
