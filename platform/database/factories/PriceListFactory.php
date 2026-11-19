<?php

namespace Database\Factories;

use App\Enums\PriceListAudience;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'audience' => PriceListAudience::Retail,
            'is_active' => true,
        ];
    }

    public function audience(PriceListAudience $audience): static
    {
        return $this->state(fn (array $attributes) => ['audience' => $audience]);
    }

    public function contractFor(Organization $organization): static
    {
        return $this->state(fn (array $attributes) => ['audience' => PriceListAudience::Contract, 'organization_id' => $organization->id]);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $tiers  [min_quantity, rate_paisa] — rate is per kg for weight variants
     */
    public function withTiers(ProductVariant $variant, array $tiers): static
    {
        return $this->afterCreating(function (PriceList $list) use ($variant, $tiers): void {
            foreach ($tiers as [$minQuantity, $rate]) {
                $list->items()->create([
                    'product_variant_id' => $variant->id,
                    'min_quantity' => $minQuantity,
                    $variant->isWeight() ? 'per_kg_paisa' : 'price_paisa' => $rate,
                ]);
            }
        });
    }
}
