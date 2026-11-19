<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One price tier for a variant in a list. min_quantity is in base units (grams for weight
 * variants, units otherwise); the highest tier not above the requested quantity wins.
 */
#[Fillable(['product_variant_id', 'min_quantity', 'price_paisa', 'per_kg_paisa'])]
class PriceListItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_quantity' => 'integer',
            'price_paisa' => 'integer',
            'per_kg_paisa' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PriceList, $this>
     */
    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
