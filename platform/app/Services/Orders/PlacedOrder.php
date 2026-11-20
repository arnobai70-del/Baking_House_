<?php

namespace App\Services\Orders;

use App\Models\Order;

/**
 * Result of placement. guestKey is shown to a guest exactly once (it unlocks their order page);
 * only its SHA-256 hash is stored.
 */
final readonly class PlacedOrder
{
    public function __construct(public Order $order, public ?string $guestKey, public bool $wasExisting) {}
}
