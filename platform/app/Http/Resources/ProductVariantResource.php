<?php

namespace App\Http\Resources;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sku' => $this->sku,
            'name' => $this->name,
            'pricing_mode' => $this->pricing_mode->value,
            'selling_unit' => $this->selling_unit->value,
            'pack_size' => $this->pack_size,
            'price_paisa' => $this->price_paisa,
            'compare_at_paisa' => $this->isDiscounted() ? $this->compare_at_paisa : null,
            'per_kg_paisa' => $this->per_kg_paisa,
            'weight_rules' => $this->isWeight() ? [
                'min_grams' => $this->min_grams,
                'max_grams' => $this->max_grams,
                'step_grams' => $this->step_grams,
                'presets' => $this->preset_grams ?? [],
            ] : null,
            'in_stock' => $this->isInStock(),
        ];
    }
}
