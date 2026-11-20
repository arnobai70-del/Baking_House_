<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\PriceListAudience;
use App\Enums\QuotationStatus;
use App\Models\Cart;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Pricing\PricingContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * /api/v1/business/* and /api/v1/reseller/*: abilities, organization header and roles.
 */
class PortalApiTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @param  list<string>  $abilities
     */
    private function api(User $user, array $abilities, ?Organization $organization = null): static
    {
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $request = $this->withHeader('Authorization', 'Bearer '.$user->createToken('t', $abilities)->plainTextToken);

        return $organization === null ? $request : $request->withHeader('X-Organization-Id', (string) $organization->id);
    }

    public function test_orders_and_prices_are_scoped_to_the_named_organization(): void
    {
        [$mine, $owner] = $this->businessWith();
        [$theirs, $other] = $this->businessWith(name: 'Other Restaurant');
        $pan = $this->unitVariant(100000, 10, 'PAN');
        PriceList::factory()->contractFor($mine)->withTiers($pan, [[1, 85000]])->create();
        $mineOrder = $this->place($this->cartWith([[$pan, 1]], $owner), user: $owner, context: PricingContext::forOrganization($mine, $owner->id))->order;
        $theirOrder = $this->place($this->cartWith([[$this->unitVariant(5000, 10, 'POT'), 1]], $other), user: $other, context: PricingContext::forOrganization($theirs, $other->id))->order;

        $this->api($owner, ['business:read'], $mine)->getJson('/api/v1/business/orders')->assertOk()
            ->assertJsonPath('data.0.number', $mineOrder->number)->assertJsonMissing(['number' => $theirOrder->number]);
        $this->api($owner, ['business:read'], $mine)->getJson('/api/v1/business/prices')->assertOk()
            ->assertJsonPath('data.0.sku', 'PAN')->assertJsonPath('data.0.tiers.0.rate_paisa', 85000);

        $this->api($owner, ['business:read'], $theirs)->getJson('/api/v1/business/orders')->assertForbidden();
        $this->api($other, ['business:read'], $theirs)->getJson('/api/v1/business/prices')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_abilities_gate_reads_writes_and_the_other_portal(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->unitVariant(100000, 10, 'PAN');

        $this->api($owner, ['orders:read'])->getJson('/api/v1/business/orders')->assertForbidden();
        $this->api($owner, ['business:read'])->postJson('/api/v1/business/bulk', ['lines' => [['sku' => 'PAN', 'quantity' => '1']]])->assertForbidden();
        $this->api($owner, ['business:read', 'reseller:read'])->getJson('/api/v1/reseller/orders')->assertForbidden();
        $this->api($owner, ['business:read'])->getJson('/api/v1/business/orders')->assertOk();
    }

    public function test_bulk_check_and_add_over_the_api(): void
    {
        [$organization, $owner] = $this->businessWith();
        $choc = $this->weightVariant(65000, 50000, 'CHOC');
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($choc, [[5000, 55000]])->create();

        $this->api($owner, ['business:read'], $organization)->postJson('/api/v1/business/bulk/check', ['text' => "CHOC, 6kg\nNOPE, 1"])->assertOk()
            ->assertJsonPath('data.0.ok', true)->assertJsonPath('data.0.line_paisa', 330000)->assertJsonPath('data.1.error', 'Unknown SKU.');

        $this->api($owner, ['business:read', 'business:write'], $organization)->postJson('/api/v1/business/bulk', ['lines' => [['sku' => 'CHOC', 'quantity' => '6kg'], ['sku' => 'CHOC', 'quantity' => '500']]])
            ->assertOk()->assertJsonPath('added', 1);
        $this->assertSame(6500, Cart::query()->where('user_id', $owner->id)->sole()->items()->sole()->quantity);
    }

    public function test_viewer_tokens_cannot_write_even_with_the_write_ability(): void
    {
        [$organization] = $this->businessWith();
        $viewer = $this->addMember($organization, OrganizationRole::Viewer);
        $this->unitVariant(100000, 10, 'PAN');

        $this->api($viewer, ['business:read', 'business:write'], $organization)->postJson('/api/v1/business/bulk', ['text' => 'PAN, 1'])->assertForbidden();
        $this->api($viewer, ['business:read', 'business:write'], $organization)->postJson('/api/v1/business/quotations', ['request_text' => 'Oven'])->assertForbidden();
        $this->api($viewer, ['business:read'], $organization)->getJson('/api/v1/business/orders')->assertOk();
    }

    public function test_quotations_api_request_read_and_accept(): void
    {
        [$organization, $owner] = $this->businessWith();
        [$other, $otherOwner] = $this->businessWith(name: 'Other Restaurant');
        $this->unitVariant(100000, 10, 'OVEN');

        $number = $this->api($owner, ['business:read', 'business:write'], $organization)
            ->postJson('/api/v1/business/quotations', ['request_text' => 'Deck oven × 1'])
            ->assertCreated()->assertJsonPath('data.status', 'requested')->json('data.number');
        $quotation = Quotation::query()->where('number', $number)->sole();
        $this->assertSame($organization->id, $quotation->organization_id);

        $issued = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '1', 'price' => '90000']]);

        $this->api($otherOwner, ['business:read'], $other)->getJson('/api/v1/business/quotations/'.$issued->number)->assertNotFound();
        $this->api($owner, ['business:read'], $organization)->getJson('/api/v1/business/quotations/'.$issued->number)->assertOk()
            ->assertJsonPath('data.total_paisa', 9000000)->assertJsonPath('data.items.0.quantity', 1);
        $this->api($owner, ['business:read', 'business:write'], $organization)->postJson('/api/v1/business/quotations/'.$issued->number.'/accept')
            ->assertOk()->assertJsonPath('in_cart', true);

        $this->assertSame(QuotationStatus::Accepted, $issued->fresh()->status);
        $this->api($owner, ['orders:read', 'business:read'], $organization)->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.subtotal_paisa', 9000000)->assertJsonPath('data.lines.0.type', 'quotation');
    }
}
