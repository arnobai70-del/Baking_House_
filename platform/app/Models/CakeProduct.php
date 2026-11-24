<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cake profile of a normal catalogue product: marks it as designable in the cake designer and holds its
 * per-product rules (lead time, advance, weights, tiers, dietary eligibility, ingredients / allergens).
 * The product's own variant stays "quote only", so the normal add-to-cart can never sell it unpriced.
 */
#[Fillable(['product_id', 'lead_time_hours', 'advance_percent', 'full_payment_required', 'min_grams', 'max_grams', 'step_grams', 'max_tiers',
    'allow_eggless', 'allow_sugar_free', 'allow_both_diets', 'ingredients', 'allergens'])]
class CakeProduct extends Model
{
    /** @var array<int, true>|null product ids with a cake profile, memoised per request */
    private static ?array $productIds = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lead_time_hours' => 'integer', 'advance_percent' => 'integer', 'full_payment_required' => 'boolean',
            'min_grams' => 'integer', 'max_grams' => 'integer', 'step_grams' => 'integer', 'max_tiers' => 'integer',
            'allow_eggless' => 'boolean', 'allow_sugar_free' => 'boolean', 'allow_both_diets' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$productIds = null);
        static::deleted(fn () => self::$productIds = null);
    }

    /**
     * Whether a product is designable as a cake (one query per request for listings).
     */
    public static function isCakeProduct(int $productId): bool
    {
        self::$productIds ??= array_fill_keys(self::query()->pluck('product_id')->all(), true);

        return isset(self::$productIds[$productId]);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<CakeOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(CakeOption::class);
    }

    /**
     * Weights the customer may choose, in grams.
     *
     * @return list<int>
     */
    public function weightChoices(): array
    {
        $step = max(100, $this->step_grams);

        return range($this->min_grams, max($this->min_grams, $this->max_grams), $step);
    }
}
