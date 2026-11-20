<?php

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\ReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of stock. Quantities are base units (grams for weight variants, units otherwise).
 *
 * Overselling protection: every change to stock_quantity / reserved_quantity is ONE conditional
 * UPDATE whose WHERE clause re-checks availability (stock_quantity - reserved_quantity >= qty).
 * The database evaluates and applies it atomically, so concurrent requests can never both take
 * the last unit — on SQLite and MySQL alike, without relying on SELECT ... FOR UPDATE.
 */
class InventoryService
{
    public const DEFAULT_HOLD_MINUTES = 15;

    public function __construct(private AuditLogger $audit) {}

    public function available(ProductVariant|int $variant): int
    {
        $row = DB::table('product_variants')->where('id', $this->id($variant))->first(['stock_quantity', 'reserved_quantity']);

        return $row === null ? 0 : max(0, (int) $row->stock_quantity - (int) $row->reserved_quantity);
    }

    /**
     * Record a stock change (receipt, adjustment, damage, return...). A decrease may never take
     * on-hand stock below what is already reserved.
     *
     * @throws InsufficientStockException
     */
    public function record(ProductVariant|int $variant, InventoryMovementType $type, int $delta, ?string $reason = null, ?Model $reference = null, ?User $actor = null): InventoryMovement
    {
        $variantId = $this->id($variant);

        $movement = DB::transaction(function () use ($variantId, $type, $delta, $reason, $reference, $actor): InventoryMovement {
            $query = DB::table('product_variants')->where('id', $variantId);
            if ($delta < 0) {
                $query->whereRaw('stock_quantity - reserved_quantity >= ?', [-$delta]);
            }

            if ($query->update(['stock_quantity' => DB::raw('stock_quantity + ('.(int) $delta.')'), 'updated_at' => now()]) !== 1) {
                throw new InsufficientStockException($variantId, $this->available($variantId), 'Stock cannot go below the quantity already reserved or on hand.');
            }

            return $this->writeMovement($variantId, $type, $delta, $reason, $reference, $actor);
        });

        $this->afterStockChange($variantId);

        return $movement;
    }

    /**
     * Physical count: set on-hand to the counted quantity, logging the difference.
     *
     * @throws InsufficientStockException when the count is below stock currently reserved
     */
    public function reconcile(ProductVariant|int $variant, int $countedQuantity, string $reason, ?User $actor = null): InventoryMovement
    {
        if ($countedQuantity < 0) {
            throw new InvalidArgumentException('Counted quantity cannot be negative.');
        }
        $variantId = $this->id($variant);

        $movement = DB::transaction(function () use ($variantId, $countedQuantity, $reason, $actor): InventoryMovement {
            $current = (int) DB::table('product_variants')->where('id', $variantId)->lockForUpdate()->value('stock_quantity');
            $delta = $countedQuantity - $current;

            $updated = DB::table('product_variants')
                ->where('id', $variantId)
                ->where('stock_quantity', $current)
                ->where('reserved_quantity', '<=', $countedQuantity)
                ->update(['stock_quantity' => $countedQuantity, 'updated_at' => now()]);

            if ($updated !== 1) {
                throw new InsufficientStockException($variantId, $this->available($variantId), 'Counted stock is below the quantity reserved for open orders, or stock changed during the count. Recount and try again.');
            }

            return $this->writeMovement($variantId, InventoryMovementType::Reconciliation, $delta, $reason, null, $actor);
        });

        $this->afterStockChange($variantId);

        return $movement;
    }

    /**
     * Hold stock for a checkout/order. Atomic: succeeds only if enough is available right now.
     *
     * @throws InsufficientStockException
     */
    public function reserve(ProductVariant|int $variant, int $quantity, string $reference, int $holdMinutes = self::DEFAULT_HOLD_MINUTES): StockReservation
    {
        return $this->reserveMany([[$variant, $quantity]], $reference, $holdMinutes)[0];
    }

    /**
     * All-or-nothing reservation of several variants (e.g. a cart or a bundle). If any line is
     * short, the transaction rolls back and nothing stays reserved.
     *
     * @param  list<array{0: ProductVariant|int, 1: int}>  $lines
     * @return list<StockReservation>
     *
     * @throws InsufficientStockException
     */
    public function reserveMany(array $lines, string $reference, int $holdMinutes = self::DEFAULT_HOLD_MINUTES): array
    {
        $reservations = DB::transaction(function () use ($lines, $reference, $holdMinutes): array {
            $created = [];
            foreach ($lines as [$variant, $quantity]) {
                if ($quantity <= 0) {
                    throw new InvalidArgumentException('Reservation quantity must be positive.');
                }
                $variantId = $this->id($variant);

                $updated = DB::table('product_variants')
                    ->where('id', $variantId)
                    ->where('is_active', true)
                    ->whereRaw('stock_quantity - reserved_quantity >= ?', [$quantity])
                    ->update(['reserved_quantity' => DB::raw('reserved_quantity + '.(int) $quantity), 'updated_at' => now()]);

                if ($updated !== 1) {
                    throw new InsufficientStockException($variantId, $this->available($variantId));
                }

                $reservation = new StockReservation([
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                    'reference' => $reference,
                    'expires_at' => now()->addMinutes($holdMinutes),
                ]);
                $reservation->status = ReservationStatus::Active;
                $reservation->save();
                $created[] = $reservation;
            }

            return $created;
        });

        foreach ($reservations as $reservation) {
            $this->afterStockChange($reservation->product_variant_id);
        }

        return $reservations;
    }

    /**
     * Give reserved stock back. Returns false if the reservation was no longer active
     * (already released, expired or committed) — it can never be released twice.
     */
    public function release(StockReservation $reservation, ReservationStatus $as = ReservationStatus::Released): bool
    {
        $released = DB::transaction(function () use ($reservation, $as): bool {
            if (! $this->transition($reservation, $as)) {
                return false;
            }

            DB::table('product_variants')
                ->where('id', $reservation->product_variant_id)
                ->where('reserved_quantity', '>=', $reservation->quantity)
                ->update(['reserved_quantity' => DB::raw('reserved_quantity - '.(int) $reservation->quantity), 'updated_at' => now()]);

            return true;
        });

        if ($released) {
            $this->afterStockChange($reservation->product_variant_id);
        }

        return $released;
    }

    /**
     * Convert a reservation into a sale: on-hand and reserved both drop; a ledger row is written.
     */
    public function commit(StockReservation $reservation, ?Model $reference = null, ?User $actor = null): bool
    {
        $committed = DB::transaction(function () use ($reservation, $reference, $actor): bool {
            if (! $this->transition($reservation, ReservationStatus::Committed)) {
                return false;
            }

            $quantity = (int) $reservation->quantity;
            DB::table('product_variants')
                ->where('id', $reservation->product_variant_id)
                ->update([
                    'stock_quantity' => DB::raw('stock_quantity - '.$quantity),
                    'reserved_quantity' => DB::raw('reserved_quantity - '.$quantity),
                    'updated_at' => now(),
                ]);

            $this->writeMovement($reservation->product_variant_id, InventoryMovementType::Sale, -$quantity, "Reservation {$reservation->reference}", $reference, $actor);

            return true;
        });

        if ($committed) {
            $this->afterStockChange($reservation->product_variant_id);
        }

        return $committed;
    }

    /**
     * Release every active reservation past its hold time. Returns the number released.
     */
    public function releaseExpired(): int
    {
        $count = 0;
        StockReservation::query()
            ->where('status', ReservationStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (StockReservation $reservation) use (&$count): void {
                $count += $this->release($reservation, ReservationStatus::Expired) ? 1 : 0;
            });

        return $count;
    }

    /**
     * Ledger integrity: on-hand must equal the sum of movements and reserved must equal the sum
     * of active reservations, for every variant.
     *
     * @return list<array{variant_id: int, sku: string, stock_quantity: int, ledger_total: int, reserved_quantity: int, active_reservations: int}>
     */
    public function discrepancies(): array
    {
        $ledger = DB::table('inventory_movements')->selectRaw('product_variant_id, sum(quantity) as total')->groupBy('product_variant_id')->pluck('total', 'product_variant_id');
        $reserved = DB::table('stock_reservations')->where('status', ReservationStatus::Active->value)
            ->selectRaw('product_variant_id, sum(quantity) as total')->groupBy('product_variant_id')->pluck('total', 'product_variant_id');

        $problems = [];
        foreach (DB::table('product_variants')->orderBy('id')->get(['id', 'sku', 'stock_quantity', 'reserved_quantity']) as $variant) {
            $ledgerTotal = (int) ($ledger[$variant->id] ?? 0);
            $activeReserved = (int) ($reserved[$variant->id] ?? 0);
            if ($ledgerTotal !== (int) $variant->stock_quantity || $activeReserved !== (int) $variant->reserved_quantity) {
                $problems[] = [
                    'variant_id' => (int) $variant->id,
                    'sku' => $variant->sku,
                    'stock_quantity' => (int) $variant->stock_quantity,
                    'ledger_total' => $ledgerTotal,
                    'reserved_quantity' => (int) $variant->reserved_quantity,
                    'active_reservations' => $activeReserved,
                ];
            }
        }

        return $problems;
    }

    private function transition(StockReservation $reservation, ReservationStatus $to): bool
    {
        $updated = StockReservation::query()
            ->whereKey($reservation->id)
            ->where('status', ReservationStatus::Active->value)
            ->update(['status' => $to->value, 'updated_at' => now()]);

        if ($updated === 1) {
            $reservation->status = $to;
        }

        return $updated === 1;
    }

    private function writeMovement(int $variantId, InventoryMovementType $type, int $delta, ?string $reason, ?Model $reference, ?User $actor): InventoryMovement
    {
        return InventoryMovement::query()->create([
            'product_variant_id' => $variantId,
            'type' => $type,
            'quantity' => $delta,
            'balance_after' => (int) DB::table('product_variants')->where('id', $variantId)->value('stock_quantity'),
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'actor_id' => $actor?->getKey(),
        ]);
    }

    /**
     * Refresh listing flags and raise / clear the low-stock alert.
     */
    private function afterStockChange(int $variantId): void
    {
        $variant = ProductVariant::query()->find($variantId);
        if ($variant === null) {
            return;
        }

        $available = $variant->availableQuantity();
        $isLow = $variant->low_stock_threshold > 0 && $available <= $variant->low_stock_threshold;

        if ($isLow && $variant->low_stock_alerted_at === null) {
            $variant->forceFill(['low_stock_alerted_at' => now()])->saveQuietly();
            $this->audit->record('inventory.low_stock', $variant, after: ['sku' => $variant->sku, 'available' => $available, 'threshold' => $variant->low_stock_threshold]);
        } elseif (! $isLow && $variant->low_stock_alerted_at !== null) {
            $variant->forceFill(['low_stock_alerted_at' => null])->saveQuietly();
        }

        Product::query()->find($variant->product_id)?->refreshSummary();
    }

    private function id(ProductVariant|int $variant): int
    {
        return $variant instanceof ProductVariant ? (int) $variant->id : $variant;
    }
}
