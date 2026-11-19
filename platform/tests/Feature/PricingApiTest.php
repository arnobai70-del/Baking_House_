<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /api/v1/pricing/quote — role isolation and server authority over prices.
 */
class PricingApiTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $chocolate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chocolate = Product::factory()->withWeightVariant(65000, 50000, ['sku' => 'BH-ING-0001'])->create()->variants()->sole();
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->chocolate, [[5000, 60000]])->create();
        PriceList::factory()->audience(PriceListAudience::Reseller)->withTiers($this->chocolate, [[1, 61000]])->create();
    }

    private function tokenFor(User $user, array $abilities = []): string
    {
        return $user->createToken('t', $abilities ?: ['account:read', 'business:read', 'reseller:read'])->plainTextToken;
    }

    private function quote(array $payload, ?string $token = null, array $headers = [])
    {
        $this->app['auth']->forgetGuards();
        if ($token) {
            $headers['Authorization'] = "Bearer {$token}";
        }

        return $this->withHeaders($headers)->postJson('/api/v1/pricing/quote', $payload);
    }

    public function test_guest_gets_retail_price_for_700_grams(): void
    {
        $this->quote(['items' => [['sku' => 'bh-ing-0001', 'quantity' => 700]]])
            ->assertOk()
            ->assertJsonPath('audience', 'retail')
            ->assertJsonPath('lines.0.line_paisa', 45500)
            ->assertJsonPath('lines.0.quantity_label', '700 g')
            ->assertJsonPath('total_paisa', 45500)
            ->assertJsonPath('valid', true);
    }

    public function test_client_supplied_prices_are_ignored(): void
    {
        $this->quote(['items' => [['sku' => 'BH-ING-0001', 'quantity' => 1000, 'price_paisa' => 1, 'line_paisa' => 1]], 'total_paisa' => 1])
            ->assertJsonPath('lines.0.line_paisa', 65000)
            ->assertJsonPath('total_paisa', 65000);
    }

    public function test_same_sku_rows_merge_so_tiers_apply_on_total_grams(): void
    {
        $owner = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($owner)->create();

        $this->quote(['items' => [['sku' => 'BH-ING-0001', 'quantity' => 3000], ['sku' => 'BH-ING-0001', 'quantity' => 2000]]], $this->tokenFor($owner))
            ->assertJsonCount(1, 'lines')
            ->assertJsonPath('lines.0.rate_source', 'business-tier')
            ->assertJsonPath('lines.0.line_paisa', 300000);
    }

    public function test_business_member_gets_business_price_retail_does_not(): void
    {
        $owner = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($owner)->create();
        $customer = User::factory()->create();
        $payload = ['items' => [['sku' => 'BH-ING-0001', 'quantity' => 5000]]];

        $this->quote($payload, $this->tokenFor($owner))->assertJsonPath('audience', 'business')->assertJsonPath('lines.0.line_paisa', 300000);
        $this->quote($payload, $this->tokenFor($customer))->assertJsonPath('audience', 'retail')->assertJsonPath('lines.0.line_paisa', 325000);
    }

    public function test_token_without_business_ability_gets_retail_prices(): void
    {
        $owner = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($owner)->create();

        $this->quote(['items' => [['sku' => 'BH-ING-0001', 'quantity' => 5000]]], $this->tokenFor($owner, ['account:read']))
            ->assertJsonPath('audience', 'retail')
            ->assertJsonPath('lines.0.line_paisa', 325000);
    }

    public function test_reseller_never_gets_business_prices(): void
    {
        $reseller = User::factory()->create();
        Organization::factory()->reseller()->approved()->withMember($reseller)->create();

        $this->quote(['items' => [['sku' => 'BH-ING-0001', 'quantity' => 5000]]], $this->tokenFor($reseller))
            ->assertJsonPath('audience', 'reseller')
            ->assertJsonPath('lines.0.rate_source', 'reseller-tier')
            ->assertJsonPath('lines.0.line_paisa', 305000);
    }

    public function test_organization_header_cannot_borrow_another_organizations_prices(): void
    {
        $customer = User::factory()->create();
        $foreign = Organization::factory()->business()->approved()->create();

        $this->quote(['items' => [['sku' => 'BH-ING-0001', 'quantity' => 5000]]], $this->tokenFor($customer), ['X-Organization-Id' => (string) $foreign->id])
            ->assertForbidden();
    }

    public function test_pending_or_suspended_organizations_get_retail_prices(): void
    {
        $applicant = User::factory()->create();
        Organization::factory()->business()->withMember($applicant)->create();

        $this->quote(['items' => [['sku' => 'BH-ING-0001', 'quantity' => 5000]]], $this->tokenFor($applicant))
            ->assertJsonPath('audience', 'retail');
    }

    public function test_invalid_quantities_are_rejected_with_suggestions_not_changed(): void
    {
        $this->quote(['items' => [['sku' => 'BH-ING-0001', 'quantity' => 725]]])
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('lines.0.error_code', 'step')
            ->assertJsonPath('lines.0.suggestions', [700, 750])
            ->assertJsonPath('total_paisa', 0);
    }

    public function test_unknown_and_draft_products_are_not_priced(): void
    {
        Product::factory()->draft()->withFixedVariant(1000, 5, ['sku' => 'DRAFT-1'])->create();

        $this->quote(['items' => [['sku' => 'DRAFT-1', 'quantity' => 1], ['sku' => 'NOPE', 'quantity' => 1]]])
            ->assertJsonPath('valid', false)
            ->assertJsonPath('lines.0.ok', false)
            ->assertJsonPath('lines.1.ok', false);
    }

    public function test_empty_basket_is_a_validation_error(): void
    {
        $this->quote([])->assertUnprocessable()->assertJsonValidationErrors('items');
    }
}
