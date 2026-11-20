<?php

namespace App\Services\Checkout;

use App\Enums\CustomerAudience;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Services\Cart\CartService;
use App\Services\Pricing\PricingContext;

/**
 * Builds the authoritative checkout summary: re-priced cart (Phase 4 rules), delivery charge for
 * the address's district, payment methods allowed, and a fingerprint of everything the customer
 * is agreeing to. Order placement recomputes the summary and refuses if the fingerprint differs.
 *
 * Rounding: every line is rounded half-up to the paisa when priced (weight lines) or is exact
 * (unit lines); the order total is the sum of rounded lines − discount + delivery. No floats.
 */
class CheckoutService
{
    public function __construct(private CartService $carts) {}

    /**
     * @return array{quote: array<string, mixed>, zone: ?DeliveryZone, delivery_paisa: int, total_paisa: int, methods: list<PaymentMethod>, errors: list<string>, fingerprint: string, audience: CustomerAudience}
     */
    public function summarize(Cart $cart, PricingContext $context, ?string $district, ?PaymentMethod $method = null): array
    {
        $quote = $this->carts->quote($cart, $context);
        $errors = [];

        if (($quote['item_count'] ?? 0) === 0) {
            $errors[] = 'Your cart is empty.';
        } elseif (! $quote['valid']) {
            $errors[] = 'Some items in your cart need attention (stock, quantity or availability changed).';
        }

        $zone = $district !== null && in_array($district, DeliveryZone::DISTRICTS, true) ? DeliveryZone::forDistrict($district) : null;
        if ($district !== null && $zone === null) {
            $errors[] = 'We cannot deliver to that district yet.';
        }

        $merchandise = max(0, (int) $quote['subtotal_paisa'] - (int) $quote['discount_paisa']);
        $delivery = $zone?->chargeFor($merchandise) ?? 0;
        $total = $merchandise + $delivery;

        $methods = $this->allowedMethods($zone, $total);
        if ($method !== null && ! in_array($method, $methods, true)) {
            $errors[] = $method === PaymentMethod::CashOnDelivery
                ? 'Cash on delivery is not available for this address or order value.'
                : 'That payment method is not available.';
        }

        return [
            'quote' => $quote,
            'zone' => $zone,
            'delivery_paisa' => $delivery,
            'total_paisa' => $total,
            'methods' => $methods,
            'errors' => $errors,
            'audience' => $context->audience,
            'fingerprint' => $this->fingerprint($quote, $delivery, $total, $context),
        ];
    }

    /**
     * @return list<PaymentMethod>
     */
    public function allowedMethods(?DeliveryZone $zone, int $totalPaisa): array
    {
        $configured = array_values(array_filter(array_map(
            fn (string $value) => PaymentMethod::tryFrom(trim($value)),
            (array) config('commerce.payment_methods', []),
        )));

        return array_values(array_filter($configured, function (PaymentMethod $method) use ($zone, $totalPaisa): bool {
            if ($method !== PaymentMethod::CashOnDelivery) {
                return true;
            }

            return ($zone === null || $zone->cod_available) && $totalPaisa <= (int) config('commerce.cod_max_paisa');
        }));
    }

    /**
     * @param  array<string, mixed>  $quote
     */
    private function fingerprint(array $quote, int $delivery, int $total, PricingContext $context): string
    {
        $lines = array_map(fn (array $line) => [
            $line['type'], $line['sku'] ?? $line['slug'] ?? null, $line['base_quantity'] ?? null, $line['line_paisa'] ?? null, $line['rate_source'] ?? null,
        ], $quote['lines']);

        return hash('sha256', json_encode([
            $lines, $quote['subtotal_paisa'], $quote['discount_paisa'],
            array_column($quote['promotions'] ?? [], 'promotion_id'),
            $delivery, $total, $context->audience->value, $context->organization?->id,
        ], JSON_THROW_ON_ERROR));
    }
}
