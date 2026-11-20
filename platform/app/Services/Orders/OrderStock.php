<?php

namespace App\Services\Orders;

use App\Enums\InventoryMovementType;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Stock side of an order, on top of the Phase 4 InventoryService.
 */
class OrderStock
{
    public function __construct(private InventoryService $inventory) {}

    /**
     * Allocate the order's stock for good. Uses the live reservations when they still cover the
     * order; otherwise (e.g. a payment confirmed after the 15-minute hold expired) it tries to
     * reserve again atomically. Returns false — changing nothing — if stock is no longer there.
     * An order is never confirmed against unavailable stock.
     */
    public function commitOrReallocate(Order $order, ?User $actor = null): bool
    {
        try {
            return DB::transaction(function () use ($order, $actor): bool {
                $active = $this->reservations($order, ReservationStatus::Active);
                $covered = $this->totals($active->all()) == $this->needs($order);

                if (! $covered) {
                    foreach ($active as $reservation) {
                        $this->inventory->release($reservation);
                    }
                    $active = collect($this->inventory->reserveMany($this->needLines($order), $order->reservationReference(), 5));
                }

                foreach ($active as $reservation) {
                    $this->inventory->commit($reservation, $order, $actor);
                }

                return true;
            });
        } catch (InsufficientStockException) {
            return false;
        }
    }

    /**
     * Return the order's stock: release held reservations and put committed (sold) quantities
     * back on hand through the ledger.
     */
    public function restock(Order $order, ?User $actor = null): void
    {
        foreach ($this->reservations($order, ReservationStatus::Active) as $reservation) {
            $this->inventory->release($reservation);
        }

        foreach ($this->reservations($order, ReservationStatus::Committed) as $reservation) {
            $this->inventory->record(
                $reservation->product_variant_id,
                InventoryMovementType::Restock,
                (int) $reservation->quantity,
                "Order {$order->number} cancelled",
                $order,
                $actor,
            );
        }
    }

    public function hasActiveReservations(Order $order): bool
    {
        return $this->reservations($order, ReservationStatus::Active)->isNotEmpty();
    }

    /**
     * @return Collection<int, StockReservation>
     */
    private function reservations(Order $order, ReservationStatus $status)
    {
        return StockReservation::query()->where('reference', $order->reservationReference())->where('status', $status->value)->orderBy('id')->get();
    }

    /**
     * @return array<int, int> variant id => base units
     */
    private function needs(Order $order): array
    {
        $needs = [];
        foreach ($order->items()->get() as $item) {
            if (in_array($item->type, ['bundle', 'quotation'], true)) {
                foreach ($item->components ?? [] as $component) {
                    if (($component['variant_id'] ?? null) === null) {
                        continue;
                    }
                    $needs[$component['variant_id']] = ($needs[$component['variant_id']] ?? 0) + (int) $component['quantity'];
                }
            } elseif ($item->product_variant_id !== null) {
                $needs[$item->product_variant_id] = ($needs[$item->product_variant_id] ?? 0) + $item->quantity;
            }
        }
        ksort($needs);

        return $needs;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function needLines(Order $order): array
    {
        $needs = $this->needs($order);

        return array_map(fn (int $variantId, int $quantity) => [$variantId, $quantity], array_keys($needs), $needs);
    }

    /**
     * @param  list<StockReservation>  $reservations
     * @return array<int, int>
     */
    private function totals(array $reservations): array
    {
        $totals = [];
        foreach ($reservations as $reservation) {
            $totals[$reservation->product_variant_id] = ($totals[$reservation->product_variant_id] ?? 0) + (int) $reservation->quantity;
        }
        ksort($totals);

        return $totals;
    }
}
