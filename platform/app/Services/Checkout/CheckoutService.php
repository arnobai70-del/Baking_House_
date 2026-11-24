<?php

namespace App\Services\Checkout;

use App\Enums\CustomerAudience;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Models\CakeDesign;
use App\Models\CakeProduct;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\PickupLocation;
use App\Services\Cakes\CakeSettings;
use App\Services\Cart\CartService;
use App\Services\Pricing\PricingContext;
use App\Support\Taka;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the authoritative checkout summary: re-priced cart (Phase 4 rules), delivery charge for
 * the address's district, payment methods allowed, and a fingerprint of everything the customer
 * is agreeing to. Order placement recomputes the summary and refuses if the fingerprint differs.
 *
 * Rounding: every line is rounded half-up to the paisa when priced (weight lines) or is exact
 * (unit lines); the order total is the sum of rounded lines − discount + delivery. No floats.
 *
 * Custom cakes (same checkout, no second system): when the cart holds a cake, the order may be collected
 * from an active pickup location, delivery needs a cake-enabled zone (cake charge / minimum / free threshold,
 * tiered cakes only where allowed), and an advance (admin-set %, default 50 %) is due now; the balance is
 * collected later. Without cakes everything behaves exactly as before.
 */
class CheckoutService
{
    public function __construct(private CartService $carts, private CakeSettings $cakeSettings) {}

    /**
     * @return array{quote: array<string, mixed>, zone: ?DeliveryZone, delivery_paisa: int, total_paisa: int, methods: list<PaymentMethod>, errors: list<string>, fingerprint: string, audience: CustomerAudience, has_cakes: bool, fulfilment: string, pickup_location: ?PickupLocation, pickup_locations: Collection<int, PickupLocation>, advance_percent: int, pay_now_paisa: int, balance_paisa: int}
     */
    public function summarize(Cart $cart, PricingContext $context, ?string $district, ?PaymentMethod $method = null, string $fulfilment = 'delivery', ?int $pickupLocationId = null): array
    {
        $quote = $this->carts->quote($cart, $context);
        $errors = [];
        $cakeLines = array_values(array_filter($quote['lines'], fn (array $line) => $line['type'] === 'cake'));
        $hasCakes = $cakeLines !== [];
        $pickupLocations = $hasCakes ? PickupLocation::query()->where('is_active', true)->orderBy('sort_order')->get() : collect();

        if (($quote['item_count'] ?? 0) === 0) {
            $errors[] = 'Your cart is empty.';
        } elseif (! $quote['valid']) {
            $errors[] = 'Some items in your cart need attention (stock, quantity or availability changed).';
        }

        if (! $this->mayOrderFor($context)) {
            $errors[] = __('Your role in :organization is view-only, so you cannot place orders for it. Ask the account owner to change your role.', ['organization' => $context->organization?->name]);
        }

        $fulfilment = $hasCakes && $fulfilment === 'pickup' ? 'pickup' : 'delivery';
        $pickup = null;
        $zone = null;
        if ($fulfilment === 'pickup') {
            $pickup = $pickupLocationId === null ? null : $pickupLocations->firstWhere('id', $pickupLocationId);
            if ($pickup === null) {
                $errors[] = __('Choose a pickup location.');
            }
        } else {
            $zone = $district !== null && in_array($district, DeliveryZone::DISTRICTS, true) ? DeliveryZone::forDistrict($district) : null;
            if ($district !== null && $zone === null) {
                $errors[] = 'We cannot deliver to that district yet.';
            }
        }

        $merchandise = max(0, (int) $quote['subtotal_paisa'] - (int) $quote['discount_paisa']);
        $delivery = $fulfilment === 'pickup' ? 0 : ($zone?->chargeFor($merchandise) ?? 0);

        if ($hasCakes && $fulfilment === 'delivery' && $zone !== null) {
            if (! $zone->cake_delivery_enabled) {
                $errors[] = __('Custom cakes cannot be delivered to this district yet.').($pickupLocations->isNotEmpty() ? ' '.__('Choose store pickup instead.') : '');
            } else {
                if ($zone->cake_min_order_paisa !== null && $merchandise < $zone->cake_min_order_paisa) {
                    $errors[] = __('Cake delivery to this area needs an order of at least :amount.', ['amount' => Taka::format($zone->cake_min_order_paisa)]);
                }
                if (! $zone->cake_tiered_allowed && collect($cakeLines)->contains(fn (array $line) => ($line['complexity'] ?? '') === 'complex')) {
                    $errors[] = __('Tiered or complex cakes cannot be delivered to this area. Choose store pickup.');
                }
                if ($zone->cake_charge_paisa !== null) {
                    $delivery = $zone->cake_free_over_paisa !== null && $merchandise >= $zone->cake_free_over_paisa ? 0 : $zone->cake_charge_paisa;
                }
            }
        }

        $total = $merchandise + $delivery;
        $advancePercent = $hasCakes ? $this->advancePercent($cakeLines, $total) : 0;
        $payNow = $advancePercent > 0 ? Taka::divideRoundHalfUp($total * $advancePercent, 100) : $total;

        $methods = $this->allowedMethods($zone, $total);
        if ($hasCakes) {
            // Cash on delivery means "nothing now": allowed only when no advance applies and the owner allows it.
            $methods = array_values(array_filter($methods, fn (PaymentMethod $m) => $m !== PaymentMethod::CashOnDelivery || ($advancePercent === 0 && $this->cakeSettings->codEnabled())));
        }
        if ($method !== null && ! in_array($method, $methods, true)) {
            $errors[] = $method === PaymentMethod::CashOnDelivery
                ? ($hasCakes ? __('Custom cake orders need an advance payment now, so cash on delivery is only available for the balance.') : 'Cash on delivery is not available for this address or order value.')
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
            'has_cakes' => $hasCakes,
            'fulfilment' => $fulfilment,
            'pickup_location' => $pickup,
            'pickup_locations' => $pickupLocations,
            'advance_percent' => $advancePercent,
            'pay_now_paisa' => $payNow,
            'balance_paisa' => $total - $payNow,
            'fingerprint' => $this->fingerprint($quote, $delivery, $total, $context, [$fulfilment, $pickup?->id, $advancePercent, $payNow]),
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
     * Orders for an organization need a member role that may place orders (owner / buyer).
     */
    public function mayOrderFor(PricingContext $context): bool
    {
        if ($context->organization === null) {
            return true;
        }

        $role = $context->userId === null ? null : DB::table('organization_user')
            ->where('organization_id', $context->organization->id)
            ->where('user_id', $context->userId)
            ->value('role');

        return $role !== null && OrganizationRole::from($role)->canPlaceOrders();
    }

    /**
     * Highest advance any cake in the order needs: full payment if a cake requires it, else the cake's own
     * percentage, else the global rule for this order value.
     *
     * @param  list<array<string, mixed>>  $cakeLines
     */
    private function advancePercent(array $cakeLines, int $totalPaisa): int
    {
        $percent = $this->cakeSettings->advancePercentFor($totalPaisa);
        $designs = CakeDesign::query()->whereIn('id', array_column($cakeLines, 'cake_design_id'))->with('variant.product.cakeProfile')->get();
        foreach ($designs as $design) {
            $cake = $design->variant?->product?->cakeProfile;
            if (! $cake instanceof CakeProduct) {
                continue;
            }
            if ($cake->full_payment_required) {
                return 100;
            }
            if ($cake->advance_percent !== null) {
                $percent = max($percent, min(100, $cake->advance_percent));
            }
        }

        return $percent;
    }

    /**
     * @param  array<string, mixed>  $quote
     * @param  array<int, mixed>  $extra
     */
    private function fingerprint(array $quote, int $delivery, int $total, PricingContext $context, array $extra = []): string
    {
        $lines = array_map(fn (array $line) => [
            $line['type'], $line['sku'] ?? $line['slug'] ?? $line['number'] ?? null, $line['base_quantity'] ?? null, $line['line_paisa'] ?? null, $line['rate_source'] ?? null,
            $line['uuid'] ?? null, $line['scheduled_for'] ?? null, $line['time_slot'] ?? null,
        ], $quote['lines']);

        return hash('sha256', json_encode([
            $lines, $quote['subtotal_paisa'], $quote['discount_paisa'],
            array_column($quote['promotions'] ?? [], 'promotion_id'),
            $delivery, $total, $context->audience->value, $context->organization?->id, $extra,
        ], JSON_THROW_ON_ERROR));
    }
}
