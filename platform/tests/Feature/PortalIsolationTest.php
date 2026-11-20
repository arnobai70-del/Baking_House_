<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\PriceListAudience;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\User;
use App\Services\OrganizationContext;
use App\Services\Portal\TeamService;
use App\Services\Pricing\PricingContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * Organization isolation and member roles across the business and reseller portals.
 */
class PortalIsolationTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_every_business_and_reseller_page_renders_for_its_own_members(): void
    {
        [, $owner] = $this->businessWith();
        foreach (['dashboard', 'orders', 'bulk', 'quotations', 'prices', 'addresses', 'team', 'statements'] as $page) {
            $this->actingAs($owner)->get("/business/{$page}")->assertOk()->assertSee('Test Restaurant');
        }

        [, $reseller] = $this->resellerWith();
        foreach (['dashboard', 'orders', 'bulk', 'prices', 'addresses', 'team', 'statements'] as $page) {
            $this->actingAs($reseller)->get("/reseller/{$page}")->assertOk()->assertSee('Test Reseller');
        }
    }

    public function test_portals_are_separate_reseller_gets_no_business_access_and_vice_versa(): void
    {
        $this->get('/business/dashboard')->assertRedirect('/login');
        [, $reseller] = $this->resellerWith();
        [, $business] = $this->businessWith();

        foreach (['/business/dashboard', '/business/quotations', '/business/prices', '/business/orders'] as $url) {
            $this->actingAs($reseller)->get($url)->assertForbidden();
        }
        $this->actingAs($business)->get('/reseller/prices')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/business/dashboard')->assertForbidden();
    }

    public function test_pending_or_suspended_organizations_have_no_portal(): void
    {
        $pending = User::factory()->create();
        Organization::factory()->business()->withMember($pending)->create();
        $suspended = User::factory()->create();
        Organization::factory()->business()->suspended()->withMember($suspended)->create();

        $this->actingAs($pending)->get('/business/dashboard')->assertForbidden();
        $this->actingAs($suspended)->get('/business/prices')->assertForbidden();
    }

    public function test_orders_invoices_and_reorder_of_another_organization_are_not_found(): void
    {
        [$mine, $owner] = $this->businessWith();
        [$theirs, $other] = $this->businessWith(name: 'Other Restaurant');
        $context = PricingContext::forOrganization($theirs, $other->id);
        $theirOrder = $this->place($this->cartWith([[$this->unitVariant(), 1]], $other), user: $other, context: $context)->order;

        $this->actingAs($owner)->get(route('business.orders.invoice', $theirOrder))->assertNotFound();
        $this->actingAs($owner)->post(route('business.orders.reorder', $theirOrder))->assertNotFound();
        $this->actingAs($owner)->get(route('orders.show', $theirOrder))->assertNotFound();
        $this->actingAs($owner)->get('/business/orders')->assertDontSee($theirOrder->number);

        $this->assertSame($theirs->id, $theirOrder->organization_id);
        $this->assertNotSame($mine->id, $theirOrder->organization_id);
    }

    public function test_every_member_sees_organization_orders_but_only_buyers_cancel(): void
    {
        [$organization, $owner] = $this->businessWith();
        $viewer = $this->addMember($organization, OrganizationRole::Viewer);
        $buyer = $this->addMember($organization, OrganizationRole::Buyer);
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]], $owner), user: $owner, context: PricingContext::forOrganization($organization, $owner->id))->order;

        $this->actingAs($viewer)->get(route('orders.show', $order))->assertOk();
        $this->actingAs($viewer)->get('/business/orders')->assertSee($order->number);
        $this->actingAs($viewer)->get(route('business.orders.invoice', $order))->assertOk()->assertSee('not a VAT (Mushak) invoice');
        $this->actingAs($viewer)->post(route('orders.cancel', $order), ['reason' => 'Viewer tries'])->assertForbidden();

        $this->actingAs($buyer)->post(route('orders.cancel', $order), ['reason' => 'Buyer cancels'])->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status->value);
    }

    public function test_a_removed_member_immediately_loses_access_to_orders_and_portal(): void
    {
        [$organization, $owner] = $this->businessWith();
        $buyer = $this->addMember($organization, OrganizationRole::Buyer);
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]], $owner), user: $owner, context: PricingContext::forOrganization($organization, $owner->id))->order;
        $this->actingAs($buyer)->get(route('orders.show', $order))->assertOk();

        $organization->members()->detach($buyer->id);

        $this->actingAs($buyer)->get(route('orders.show', $order))->assertNotFound();
        $this->actingAs($buyer)->get('/business/dashboard')->assertForbidden();
    }

    public function test_viewers_cannot_order_for_the_organization(): void
    {
        [$organization] = $this->businessWith();
        $viewer = $this->addMember($organization, OrganizationRole::Viewer);
        $pan = $this->unitVariant(100000, 10);
        $cart = $this->cartWith([[$pan, 1]], $viewer);
        $context = PricingContext::forOrganization($organization, $viewer->id);

        $this->actingAs($viewer)->post('/business/bulk', ['lines' => 'PAN, 1', 'action' => 'add'])->assertForbidden();
        $this->actingAs($viewer)->post('/business/bulk', ['lines' => 'PAN, 1', 'action' => 'check'])->assertOk();

        try {
            $this->place($cart, user: $viewer, context: $context);
            $this->fail('A viewer must not place an order for the organization.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('view-only', collect($e->errors())->flatten()->implode(' '));
        }
        $this->assertSame(0, Order::query()->count());
    }

    public function test_price_list_shows_only_own_contract_and_own_trade_audience(): void
    {
        [$mine, $owner] = $this->businessWith();
        [$theirs] = $this->businessWith(name: 'Other Restaurant');
        [, $reseller] = $this->resellerWith();
        $pan = $this->unitVariant(100000, 10, 'PAN');
        $wok = $this->unitVariant(200000, 10, 'WOK');
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($pan, [[1, 90000]])->create();
        PriceList::factory()->audience(PriceListAudience::Reseller)->withTiers($pan, [[1, 70000]])->create();
        PriceList::factory()->contractFor($theirs)->withTiers($wok, [[1, 111100]])->create();
        PriceList::factory()->contractFor($mine)->withTiers($wok, [[1, 150000]])->create();

        $this->actingAs($owner)->get('/business/prices')->assertOk()
            ->assertSee('৳900', false)->assertSee('৳1,500', false)
            ->assertDontSee('৳700', false)->assertDontSee('৳1,111', false);

        $this->actingAs($reseller)->get('/reseller/prices')->assertOk()
            ->assertSee('৳700', false)->assertDontSee('৳900', false)->assertDontSee('৳1,500', false);

        $csv = $this->actingAs($reseller)->get('/reseller/prices/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('PAN', $csv);
        $this->assertStringContainsString(',1000,700,1,reseller-tier', $csv);
        $this->assertStringNotContainsString('WOK', $csv);
    }

    public function test_opening_a_portal_switches_the_buying_context_to_that_organization(): void
    {
        $user = User::factory()->create();
        $business = Organization::factory()->business()->approved()->withMember($user)->create();
        $reseller = Organization::factory()->reseller()->approved()->withMember($user)->create();

        $this->actingAs($user)->get('/reseller/dashboard')->assertOk();
        $this->assertSame($reseller->id, session(OrganizationContext::SESSION_KEY));
        $this->actingAs($user)->get('/business/dashboard')->assertOk();
        $this->assertSame($business->id, session(OrganizationContext::SESSION_KEY));
    }

    public function test_records_addressed_by_id_are_checked_against_the_organization(): void
    {
        [$mine, $owner] = $this->businessWith();
        [$theirs, $otherOwner] = $this->businessWith(name: 'Other Restaurant');
        $address = $theirs->addresses()->forceCreate(['label' => 'Theirs', 'recipient_name' => 'X', 'phone' => '01711111111', 'line' => 'Road 9', 'area' => 'Uttara', 'district' => 'Dhaka']);
        $invitation = app(TeamService::class)->invite($theirs, 'new@example.com', OrganizationRole::Buyer, $otherOwner)['invitation'];

        $this->actingAs($owner)->delete(route('business.addresses.destroy', $address))->assertNotFound();
        $this->actingAs($owner)->put(route('business.addresses.update', $address), ['label' => 'Mine now', 'recipient_name' => 'Y', 'phone' => '01711111111', 'line' => 'Road 1', 'area' => 'Gulshan', 'district' => 'Dhaka'])->assertNotFound();
        $this->actingAs($owner)->delete(route('business.team.revoke', $invitation))->assertNotFound();
        $this->actingAs($owner)->delete(route('business.team.remove', $otherOwner))->assertNotFound();

        $this->assertSame('Theirs', $address->fresh()->label);
        $this->assertNull($invitation->fresh()->revoked_at);
        $this->assertTrue($theirs->hasMember($otherOwner));
        $this->assertFalse($mine->hasMember($otherOwner));
    }
}
