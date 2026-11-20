<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\CheckoutChangedException;
use App\Exceptions\InsufficientStockException;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\OrderNotifier;
use App\Services\Pricing\PricingContext;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Turns a cart into an order — once.
 *
 * 1. Idempotent: the checkout token is unique on orders, so a double click / replayed request
 *    returns the existing order instead of creating a second one (also under concurrency).
 * 2. Re-prices on the server and compares with the fingerprint the customer confirmed; any
 *    change (price, stock, promotion, delivery) aborts with CheckoutChangedException.
 * 3. In ONE transaction: reserves stock for every unit / gram (bundles expanded to their items),
 *    writes the immutable order + line snapshot, records promotion redemption (usage limits are
 *    atomic), creates the payment record and empties the cart. Any failure rolls everything back.
 *
 * Online payments keep a 15-minute reservation until a verified callback; cash on delivery and
 * bank transfer commit (allocate) stock immediately.
 */
class OrderPlacer
{
    public function __construct(
        private CheckoutService $checkout,
        private CartService $carts,
        private InventoryService $inventory,
        private PromotionEngine $promotions,
        private AuditLogger $audit,
        private OrderNotifier $notifier,
    ) {}

    /**
     * @throws CheckoutChangedException|ValidationException
     */
    public function place(Cart $cart, PricingContext $context, CheckoutDetails $details, ?User $user): PlacedOrder
    {
        $existing = Order::query()->where('checkout_token', $details->checkoutToken)->first();
        if ($existing !== null) {
            return $this->existingFor($existing, $user);
        }

        $summary = $this->checkout->summarize($cart, $context, $details->district, $details->paymentMethod);
        if ($summary['errors'] !== []) {
            throw ValidationException::withMessages(['checkout' => $summary['errors']]);
        }
        if (! hash_equals($details->expectedFingerprint, $summary['fingerprint'])) {
            throw new CheckoutChangedException($summary);
        }

        $guestKey = $user === null ? Str::random(40) : null;
        $number = $this->newNumber();

        try {
            /* The stock UPDATE is the first statement of the transaction (no reads before it), so
               writers queue on the lock instead of failing; concurrency errors are retried. */
            $order = DB::transaction(fn () => $this->create($cart, $context, $details, $user, $summary, $guestKey, $number), 5);
        } catch (UniqueConstraintViolationException $e) {
            $winner = Order::query()->where('checkout_token', $details->checkoutToken)->first();
            if ($winner === null) {
                throw $e;
            }

            return $this->existingFor($winner, $user);
        } catch (InsufficientStockException $e) {
            $variant = ProductVariant::query()->with('product:id,name')->find($e->variantId);
            throw ValidationException::withMessages(['checkout' => "Not enough stock of {$variant?->product?->name} ({$variant?->sku}) for this order any more. Please update your cart."]);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'promotion')) {
                throw ValidationException::withMessages(['checkout' => 'An offer in your cart is no longer available. Please review your cart.']);
            }
            throw $e;
        }

        $this->notifier->orderPlaced($order, $guestKey);

        return new PlacedOrder($order, $guestKey, wasExisting: false);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function create(Cart $cart, PricingContext $context, CheckoutDetails $details, ?User $user, array $summary, ?string $guestKey, string $number): Order
    {
        $quote = $summary['quote'];
        $isOnline = $details->paymentMethod->isOnline();
        $holdMinutes = (int) config('commerce.reservation_minutes', 15);

        $reservations = $this->inventory->reserveMany($this->stockNeeds($quote), 'order:'.$number, $holdMinutes);

        $status = match (true) {
            $details->paymentMethod === PaymentMethod::CashOnDelivery => OrderStatus::Confirmed,
            default => OrderStatus::PendingPayment,
        };

        $order = new Order;
        $order->forceFill([
            'number' => $number,
            'checkout_token' => $details->checkoutToken,
            'user_id' => $user?->id,
            'organization_id' => $context->organization?->id,
            'audience' => $context->audience,
            'status' => $status,
            'payment_status' => $details->paymentMethod === PaymentMethod::CashOnDelivery ? PaymentStatus::Unpaid : PaymentStatus::Pending,
            'payment_method' => $details->paymentMethod,
            'customer_name' => $details->name,
            'customer_phone' => $details->phone,
            'customer_email' => $details->email,
            'shipping_address' => $details->address(),
            'delivery_zone_id' => $summary['zone']?->id,
            'subtotal_paisa' => (int) $quote['subtotal_paisa'],
            'discount_paisa' => (int) $quote['discount_paisa'],
            'delivery_paisa' => $summary['delivery_paisa'],
            'total_paisa' => $summary['total_paisa'],
            'promotion_snapshot' => $quote['promotions'] === [] ? null : $quote['promotions'],
            'pricing_snapshot' => [
                'quote' => $quote,
                'delivery' => ['zone' => $summary['zone']?->name, 'district' => $details->district, 'charge_paisa' => $summary['delivery_paisa']],
                'fingerprint' => $summary['fingerprint'],
                'rounding' => 'Each line rounded half-up to the paisa; total = sum(lines) - discount + delivery.',
            ],
            'guest_access_hash' => $guestKey === null ? null : hash('sha256', $guestKey),
            'customer_note' => $details->note,
            'placed_at' => now(),
            'confirmed_at' => $status === OrderStatus::Confirmed ? now() : null,
            'reservation_expires_at' => $isOnline ? now()->addMinutes($holdMinutes) : null,
        ])->save();

        foreach ($quote['lines'] as $line) {
            $order->items()->forceCreate([
                'type' => $line['type'] === 'bundle' ? 'bundle' : 'item',
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'bundle_id' => $line['bundle_id'] ?? null,
                'sku' => $line['sku'] ?? null,
                'name' => (string) $line['name'],
                'quantity' => (int) ($line['base_quantity'] ?? $line['quantity'] ?? 0),
                'quantity_label' => $line['type'] === 'bundle' ? '× '.$line['quantity'] : (string) $line['quantity_label'],
                'unit_paisa' => $line['unit_paisa'] ?? null,
                'per_kg_paisa' => $line['per_kg_paisa'] ?? null,
                'rate_source' => $line['rate_source'] ?? ($line['type'] === 'bundle' ? 'bundle' : null),
                'line_paisa' => (int) $line['line_paisa'],
                'standard_line_paisa' => (int) ($line['standard_line_paisa'] ?? $line['normal_paisa'] ?? $line['line_paisa']),
                'components' => $line['type'] === 'bundle' ? $line['items'] : null,
            ]);
        }

        foreach ($quote['promotions'] as $applied) {
            $this->promotions->redeem(Promotion::query()->findOrFail($applied['promotion_id']), $number, (int) $applied['discount_paisa'], $user?->id);
        }

        if (! $isOnline) {
            foreach ($reservations as $reservation) {
                $this->inventory->commit($reservation, $order, $user);
            }
        }

        $payment = new Payment;
        $payment->forceFill([
            'order_id' => $order->id,
            'provider' => $details->paymentMethod,
            'mode' => 'test',
            'status' => PaymentRecordStatus::Initiated,
            'amount_paisa' => $order->total_paisa,
            'reference' => 'PAY-'.Str::upper(Str::random(16)),
        ])->save();

        $order->events()->forceCreate(['kind' => 'fulfillment', 'from_status' => null, 'to_status' => $status->value, 'actor_id' => $user?->id, 'note' => 'Order placed']);
        $order->events()->forceCreate(['kind' => 'payment', 'from_status' => null, 'to_status' => $order->payment_status->value, 'actor_id' => $user?->id, 'note' => $details->paymentMethod->label()]);

        $this->carts->clear($cart);

        $this->audit->record('order.placed', $order, after: [
            'number' => $number, 'total_paisa' => $order->total_paisa, 'method' => $details->paymentMethod->value,
            'audience' => $context->audience->value, 'organization_id' => $context->organization?->id,
        ], actorId: $user?->id);

        return $order;
    }

    /**
     * Per-variant base-unit totals for every line, with bundles expanded to their items.
     *
     * @param  array<string, mixed>  $quote
     * @return list<array{0: int, 1: int}>
     */
    private function stockNeeds(array $quote): array
    {
        $needs = [];
        foreach ($quote['lines'] as $line) {
            if ($line['type'] === 'bundle') {
                foreach ($line['items'] as $component) {
                    $needs[$component['variant_id']] = ($needs[$component['variant_id']] ?? 0) + (int) $component['quantity'];
                }
            } else {
                $needs[$line['product_variant_id']] = ($needs[$line['product_variant_id']] ?? 0) + (int) $line['base_quantity'];
            }
        }
        ksort($needs);

        return array_map(fn (int $variantId, int $quantity) => [$variantId, $quantity], array_keys($needs), $needs);
    }

    private function existingFor(Order $order, ?User $user): PlacedOrder
    {
        if ($order->user_id !== null && $order->user_id !== $user?->id) {
            abort(409, 'This checkout was already used.');
        }

        return new PlacedOrder($order, null, wasExisting: true);
    }

    private function newNumber(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $number = 'BH'.now('Asia/Dhaka')->format('ymd').'-'.$suffix;
            if (! Order::query()->where('number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('Could not allocate an order number.');
    }
}
