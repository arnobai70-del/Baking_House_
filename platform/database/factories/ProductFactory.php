<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'primary_category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'specifications' => ['Material' => 'Illustrative'],
            'status' => ProductStatus::Active,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => ProductStatus::Draft]);
    }

    /**
     * Attach the primary category to the category pivot too, as the admin form does.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product): void {
            $product->categories()->syncWithoutDetaching([$product->primary_category_id]);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withFixedVariant(int $pricePaisa = 125000, int $stock = 10, array $attributes = []): static
    {
        return $this->afterCreating(fn (Product $product) => ProductVariant::factory()
            ->for($product)
            ->fixed($pricePaisa, $stock)
            ->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function withWeightVariant(int $perKgPaisa = 65000, int $stockGrams = 10000, array $attributes = []): static
    {
        return $this->afterCreating(fn (Product $product) => ProductVariant::factory()
            ->for($product)
            ->weight($perKgPaisa, $stockGrams)
            ->create($attributes));
    }
}
