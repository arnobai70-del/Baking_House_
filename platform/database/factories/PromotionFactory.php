<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => null,
            'discount_type' => DiscountType::Percentage,
            'discount_value' => 1000,
            'scope' => Promotion::SCOPE_ORDER,
            'min_subtotal_paisa' => 0,
            'audiences' => ['retail', 'business', 'reseller'],
            'stackable' => false,
            'is_active' => true,
        ];
    }

    public function coupon(string $code): static
    {
        return $this->state(fn (array $attributes) => ['code' => $code]);
    }

    public function percent(int $basisPoints): static
    {
        return $this->state(fn (array $attributes) => ['discount_type' => DiscountType::Percentage, 'discount_value' => $basisPoints]);
    }

    public function fixed(int $paisa): static
    {
        return $this->state(fn (array $attributes) => ['discount_type' => DiscountType::FixedAmount, 'discount_value' => $paisa]);
    }
}
