<?php

namespace Database\Factories;

use App\Enums\PricingMode;
use App\Enums\SellingUnit;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'BH-TST-'.fake()->unique()->numerify('#####'),
            'name' => 'Standard',
            'pricing_mode' => PricingMode::Fixed,
            'selling_unit' => SellingUnit::Piece,
            'price_paisa' => 125000,
            'stock_quantity' => 10,
            'is_active' => true,
        ];
    }

    public function fixed(int $pricePaisa = 125000, int $stock = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingMode::Fixed,
            'selling_unit' => SellingUnit::Piece,
            'price_paisa' => $pricePaisa,
            'per_kg_paisa' => null,
            'stock_quantity' => $stock,
        ]);
    }

    public function weight(int $perKgPaisa = 65000, int $stockGrams = 10000): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingMode::Weight,
            'selling_unit' => SellingUnit::Gram,
            'price_paisa' => null,
            'per_kg_paisa' => $perKgPaisa,
            'min_grams' => 100,
            'max_grams' => 25000,
            'step_grams' => 50,
            'preset_grams' => [100, 250, 500, 700, 900, 1000],
            'stock_quantity' => $stockGrams,
        ]);
    }

    public function quoteOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_mode' => PricingMode::QuoteOnly,
            'price_paisa' => null,
            'per_kg_paisa' => null,
        ]);
    }
}
