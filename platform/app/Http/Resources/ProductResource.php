<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public product representation: retail prices only, never business/reseller tiers or exact stock.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => $this->url(),
            'short_description' => $this->short_description,
            'category' => $this->whenLoaded('primaryCategory', fn () => ['name' => $this->primaryCategory->name, 'slug' => $this->primaryCategory->slug]),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand?->name),
            'price_from_paisa' => $this->price_from_paisa,
            'in_stock' => $this->in_stock,
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
