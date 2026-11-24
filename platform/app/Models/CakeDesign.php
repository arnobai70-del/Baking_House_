<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer's cake design: the chosen cake product variant, the specification (shape, weight, tiers,
 * flavour, filling, cream, diet, message …), delivery or pickup and the requested date / slot.
 * It is priced on every read by CakePricer; `price_snapshot` is only a record of the last quote shown.
 *
 * spec keys: shape, grams, tiers, flavour, filling, cream, diets[], extras[], note, palette, base_colour, accent_colour,
 * drip, drip_colour, pattern, border_top, border_bottom, template, canvas{layers[]} (Part 2). Legacy Part 1 keys
 * message / print still price as a message / print when no canvas text / photo is present.
 */
#[Fillable(['uuid', 'name', 'user_id', 'owner_token_hash', 'product_variant_id', 'spec', 'fulfilment', 'scheduled_for', 'time_slot', 'pickup_location_id', 'status', 'order_id', 'price_snapshot', 'copied_from_id'])]
class CakeDesign extends Model
{
    public const DRAFT = 'draft';

    public const IN_CART = 'in_cart';

    public const ORDERED = 'ordered';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['spec' => 'array', 'price_snapshot' => 'array', 'scheduled_for' => 'date:Y-m-d'];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<PickupLocation, $this>
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(PickupLocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Photos used by this design's top (owner's private uploads), in layer order.
     *
     * @return list<string>
     */
    public function uploadUuids(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($layer) => is_array($layer) && ($layer['type'] ?? '') === 'image' ? (string) ($layer['upload'] ?? '') : '',
            (array) ($this->spec['canvas']['layers'] ?? []),
        ))));
    }

    public function isPickup(): bool
    {
        return $this->fulfilment === 'pickup';
    }
}
