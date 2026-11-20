<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use App\Enums\PricingMode;
use App\Enums\SellingUnit;
use App\Services\Pricing\WeightRule;
use App\Support\Taka;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A sellable SKU. stock_quantity is in base units: grams for weight variants, units otherwise.
 */
#[Fillable([
    'sku', 'name', 'pricing_mode', 'selling_unit', 'pack_size', 'min_order_quantity', 'price_paisa', 'compare_at_paisa', 'per_kg_paisa',
    'min_grams', 'max_grams', 'step_grams', 'preset_grams', 'stock_quantity', 'low_stock_threshold', 'is_active', 'sort_order',
])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * Fields whose change counts as a price change (audited with a reason).
     */
    public const PRICE_FIELDS = ['price_paisa', 'compare_at_paisa', 'per_kg_paisa'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pricing_mode' => PricingMode::class,
            'selling_unit' => SellingUnit::class,
            'preset_grams' => 'array',
            'is_active' => 'boolean',
            'price_paisa' => 'integer',
            'compare_at_paisa' => 'integer',
            'per_kg_paisa' => 'integer',
            'min_grams' => 'integer',
            'max_grams' => 'integer',
            'step_grams' => 'integer',
            'pack_size' => 'integer',
            'stock_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'min_order_quantity' => 'integer',
            'low_stock_alerted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        /* Initial stock on a new variant is the opening balance of its ledger. Later changes go
           through App\Services\Inventory\InventoryService only. */
        static::created(function (ProductVariant $variant): void {
            if ($variant->stock_quantity > 0) {
                InventoryMovement::query()->create([
                    'product_variant_id' => $variant->id,
                    'type' => InventoryMovementType::Opening,
                    'quantity' => $variant->stock_quantity,
                    'balance_after' => $variant->stock_quantity,
                    'reason' => 'Opening balance',
                ]);
            }
        });
        static::saved(fn (ProductVariant $variant) => Product::query()->find($variant->product_id)?->refreshSummary());
        static::deleted(fn (ProductVariant $variant) => Product::query()->find($variant->product_id)?->refreshSummary());
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<StockReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function isWeight(): bool
    {
        return $this->pricing_mode === PricingMode::Weight;
    }

    /**
     * Price used for listing, filtering and sorting: per kg for weight, per unit for fixed, none for quotes.
     */
    public function listPricePaisa(): ?int
    {
        return match ($this->pricing_mode) {
            PricingMode::Weight => $this->per_kg_paisa,
            PricingMode::Fixed => $this->price_paisa,
            PricingMode::QuoteOnly => null,
        };
    }

    /**
     * Sellable now: on hand minus stock held by active reservations.
     */
    public function availableQuantity(): int
    {
        return max(0, (int) $this->stock_quantity - (int) $this->reserved_quantity);
    }

    /**
     * Smallest sellable quantity in base units (grams or units).
     */
    public function minimumQuantity(): int
    {
        return $this->isWeight() ? max(1, (int) $this->min_grams) : max(1, (int) $this->min_order_quantity);
    }

    public function isInStock(): bool
    {
        return $this->is_active && $this->availableQuantity() >= $this->minimumQuantity();
    }

    public function isLowStock(): bool
    {
        return $this->isInStock() && $this->low_stock_threshold > 0 && $this->availableQuantity() <= $this->low_stock_threshold;
    }

    public function isDiscounted(): bool
    {
        return $this->pricing_mode === PricingMode::Fixed
            && $this->compare_at_paisa !== null
            && $this->price_paisa !== null
            && $this->compare_at_paisa > $this->price_paisa;
    }

    /**
     * "per kg", "per piece", "per pack of 25".
     */
    public function unitLabel(): string
    {
        if ($this->isWeight()) {
            return 'per kg';
        }

        $unit = strtolower($this->selling_unit->label());

        return $this->selling_unit->hasPackSize() && $this->pack_size
            ? "per {$unit} of {$this->pack_size}"
            : "per {$unit}";
    }

    public function displayPrice(): string
    {
        $price = $this->listPricePaisa();

        return $price === null ? 'Price on request' : Taka::format($price);
    }

    public function weightRule(): WeightRule
    {
        return new WeightRule(
            perKgPaisa: (int) $this->per_kg_paisa,
            minGrams: $this->min_grams ?? 1,
            maxGrams: $this->max_grams ?? PHP_INT_MAX,
            stepGrams: $this->step_grams ?? 1,
        );
    }

    /**
     * Data the product page's client-side preview needs. The server stays authoritative.
     *
     * @return array<string, mixed>
     */
    public function toStorefrontArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'pricingMode' => $this->pricing_mode->value,
            'unitLabel' => $this->unitLabel(),
            'pricePaisa' => $this->price_paisa,
            'compareAtPaisa' => $this->isDiscounted() ? $this->compare_at_paisa : null,
            'inStock' => $this->isInStock(),
            'lowStock' => $this->isLowStock(),
            'weight' => $this->isWeight() ? [
                'perKgPaisa' => $this->per_kg_paisa,
                'minGrams' => $this->min_grams,
                'maxGrams' => $this->max_grams,
                'stepGrams' => $this->step_grams,
                'presets' => $this->preset_grams ?? [],
                'stockGrams' => $this->availableQuantity(),
            ] : null,
            'stock' => $this->isWeight() ? null : $this->availableQuantity(),
            'minQuantity' => $this->minimumQuantity(),
        ];
    }
}
