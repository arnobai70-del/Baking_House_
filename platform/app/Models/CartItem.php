<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A variant (quantity in grams or units), a bundle (quantity in bundles) or an accepted quotation
 * (always quantity 1; its lines are fixed by the quote). One row per variant / bundle / quotation
 * per cart, so volume tiers always see the total.
 */
#[Fillable(['product_variant_id', 'bundle_id', 'quotation_id', 'cake_design_id', 'quantity'])]
class CartItem extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<Bundle, $this>
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * Custom cake design line (always quantity 1; priced by CakePricer).
     *
     * @return BelongsTo<CakeDesign, $this>
     */
    public function cakeDesign(): BelongsTo
    {
        return $this->belongsTo(CakeDesign::class);
    }
}
