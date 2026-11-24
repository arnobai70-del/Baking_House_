<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Exceptions\CheckoutChangedException;
use App\Exceptions\InsufficientStockException;
use App\Models\CakeDesign;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Cakes\CakeSchedule;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Crm\CrmService;
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
        private CrmService $crm,
        private CakeSchedule $cakeSchedule,
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

        $summary = $this->checkout->summarize($cart, $context, $details->district, $details->paymentMethod, $details->fulfilment, $details->pickupLocationId);
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
            if (str_contains($e->getMessage(), 'cake capacity')) {
                throw ValidationException::withMessages(['checkout' => __('The date chosen for your cake has just been fully booked. Please choose another date.')]);
            }
            if (str_contains($e->getMessage(), 'quotation')) {
                throw ValidationException::withMessages(['checkout' => __('A quotation in your cart has already been ordered or is no longer valid.')]);
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
            'fulfilment' => $summary['fulfilment'],
            'pickup_location_id' => $summary['pickup_location']?->id,
            'subtotal_paisa' => (int) $quote['subtotal_paisa'],
            'discount_paisa' => (int) $quote['discount_paisa'],
            'delivery_paisa' => $summary['delivery_paisa'],
            'total_paisa' => $summary['total_paisa'],
            'advance_paisa' => $summary['pay_now_paisa'] < $summary['total_paisa'] ? $summary['pay_now_paisa'] : 0,
            'promotion_snapshot' => $quote['promotions'] === [] ? null : $quote['promotions'],
            'pricing_snapshot' => [
                'quote' => $quote,
                'delivery' => ['zone' => $summary['zone']?->name, 'district' => $details->district, 'charge_paisa' => $summary['delivery_paisa'],
                    'fulfilment' => $summary['fulfilment'], 'pickup_location' => $summary['pickup_location']?->name],
                'advance' => ['percent' => $summary['advance_percent'], 'pay_now_paisa' => $summary['pay_now_paisa'], 'balance_paisa' => $summary['balance_paisa']],
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
            if ($line['type'] === 'cake') {
                $this->createCakeLine($order, $line, $user);

                continue;
            }
            $order->items()->forceCreate([
                'type' => in_array($line['type'], ['bundle', 'quotation'], true) ? $line['type'] : 'item',
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'bundle_id' => $line['bundle_id'] ?? null,
                'sku' => $line['sku'] ?? $line['number'] ?? null,
                'name' => (string) $line['name'],
                'quantity' => (int) ($line['base_quantity'] ?? $line['quantity'] ?? 0),
                'quantity_label' => $line['type'] === 'item' ? (string) $line['quantity_label'] : '× '.$line['quantity'],
                'unit_paisa' => $line['unit_paisa'] ?? null,
                'per_kg_paisa' => $line['per_kg_paisa'] ?? null,
                'rate_source' => $line['rate_source'] ?? ($line['type'] === 'bundle' ? 'bundle' : null),
                'line_paisa' => (int) $line['line_paisa'],
                'standard_line_paisa' => (int) ($line['standard_line_paisa'] ?? $line['normal_paisa'] ?? $line['line_paisa']),
                'components' => $line['type'] === 'item' ? null : $line['items'],
            ]);

            if ($line['type'] === 'quotation') {
                /* Conditional UPDATE: an accepted quotation turns into exactly one order. */
                $claimed = Quotation::query()->whereKey($line['quotation_id'])->where('status', QuotationStatus::Accepted->value)
                    ->where('organization_id', $context->organization?->id)
                    ->update(['status' => QuotationStatus::Ordered->value, 'order_id' => $order->id, 'updated_at' => now()]);
                if ($claimed !== 1) {
                    throw new RuntimeException('quotation already ordered');
                }
                $ordered = Quotation::query()->findOrFail($line['quotation_id']);
                $this->audit->record('quotation.ordered', $ordered, after: ['order' => $number], actorId: $user?->id);
                $this->crm->quotationChanged($ordered, 'ordered');
            }
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
            'amount_paisa' => $summary['pay_now_paisa'], // the advance when one applies, else the total
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
            if ($line['type'] === 'cake') {
                continue; // made to order: no stock reservation
            }
            if (in_array($line['type'], ['bundle', 'quotation'], true)) {
                foreach ($line['items'] as $component) {
                    if ($component['variant_id'] === null) {
                        continue;
                    }
                    $needs[$component['variant_id']] = ($needs[$component['variant_id']] ?? 0) + (int) $component['quantity'];
                }
            } else {
                $needs[$line['product_variant_id']] = ($needs[$line['product_variant_id']] ?? 0) + (int) $line['base_quantity'];
            }
        }
        ksort($needs);

        return array_map(fn (int $variantId, int $quantity) => [$variantId, $quantity], array_keys($needs), $needs);
    }

    /**
     * One order line per custom cake, with the frozen design. The day's capacity is re-checked inside the
     * order transaction so two checkouts cannot overbook the same date.
     *
     * @param  array<string, mixed>  $line
     */
    private function createCakeLine(Order $order, array $line, ?User $user): void
    {
        if ($this->cakeSchedule->remaining((string) $line['scheduled_for']) <= 0) {
            throw new RuntimeException('cake capacity');
        }

        $design = CakeDesign::query()->findOrFail($line['cake_design_id']);
        $order->items()->forceCreate([
            'type' => 'cake',
            'product_variant_id' => $line['product_variant_id'],
            'cake_design_id' => $design->id,
            'sku' => $line['sku'],
            'name' => (string) $line['name'],
            'quantity' => 1,
            'quantity_label' => (string) $line['quantity_label'],
            'unit_paisa' => (int) $line['line_paisa'],
            'rate_source' => 'cake',
            'line_paisa' => (int) $line['line_paisa'],
            'standard_line_paisa' => (int) $line['standard_line_paisa'],
            'scheduled_for' => $line['scheduled_for'],
            'time_slot' => $line['time_slot'],
            'components' => [
                'design' => $design->uuid, 'spec' => $design->spec, 'breakdown' => $line['breakdown'], 'complexity' => $line['complexity'],
                'fulfilment' => $order->fulfilment, 'uses_test_prices' => (bool) ($line['uses_test_prices'] ?? false),
            ],
        ]);

        $design->forceFill(['status' => CakeDesign::ORDERED, 'order_id' => $order->id, 'user_id' => $design->user_id ?? $user?->id, 'fulfilment' => $order->fulfilment,
            'pickup_location_id' => $order->pickup_location_id, 'price_snapshot' => ['line_paisa' => $line['line_paisa'], 'breakdown' => $line['breakdown']]])->save();
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
