<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Bundle;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bundle>
 */
class BundleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 1000,
            'audiences' => ['retail', 'business', 'reseller'],
            'is_active' => true,
        ];
    }

    /**
     * @param  list<array{0: ProductVariant, 1: int}>  $items  [variant, quantity in base units]
     */
    public function withItems(array $items): static
    {
        return $this->afterCreating(function (Bundle $bundle) use ($items): void {
            foreach ($items as [$variant, $quantity]) {
                $bundle->items()->create(['product_variant_id' => $variant->id, 'quantity' => $quantity]);
            }
        });
    }
}
