<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutChangedException;
use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\CartItem;
use App\Models\DeliveryZone;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderPlacer;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingContextResolver;
use App\Support\BangladeshPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated customer cart + checkout over the API (orders:write). Prices are re-computed on
 * every response; order placement requires an Idempotency-Key header and the fingerprint from
 * /checkout/preview.
 */
class CartController extends Controller
{
    public function __construct(private CartService $carts, private PricingContextResolver $contexts) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->carts->quote($this->carts->forUser($request->user()), $this->contexts->forApi($request))]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required_without:bundle', 'nullable', 'string', 'max:64'],
            'bundle' => ['required_without:sku', 'nullable', 'string', 'max:220'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
        ]);
        $cart = $this->carts->forUser($request->user());
        $context = $this->contexts->forApi($request);

        if (! empty($validated['sku'])) {
            $variant = ProductVariant::query()->where('sku', strtoupper($validated['sku']))->where('is_active', true)
                ->whereHas('product', fn ($product) => $product->visible())->firstOrFail();
            $this->carts->addVariant($cart, $variant, (int) $validated['quantity'], $context);
        } else {
            $this->carts->addBundle($cart, Bundle::query()->where('slug', $validated['bundle'])->firstOrFail(), (int) $validated['quantity'], $context);
        }

        return response()->json(['data' => $this->carts->quote($cart, $context)], 201);
    }

    public function updateItem(Request $request, int $itemId): JsonResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:100000000']]);
        $cart = $this->carts->forUser($request->user());
        $item = CartItem::query()->where('cart_id', $cart->id)->findOrFail($itemId);

        $this->carts->updateQuantity($item, (int) $validated['quantity'], $this->contexts->forApi($request));

        return response()->json(['data' => $this->carts->quote($cart, $this->contexts->forApi($request))]);
    }

    public function removeItem(Request $request, int $itemId): JsonResponse
    {
        $cart = $this->carts->forUser($request->user());
        CartItem::query()->where('cart_id', $cart->id)->findOrFail($itemId)->delete();

        return response()->json(['data' => $this->carts->quote($cart, $this->contexts->forApi($request))]);
    }

    public function coupon(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['nullable', 'string', 'max:40']]);
        $cart = $this->carts->forUser($request->user());
        $this->carts->setCoupon($cart, $validated['code'] ?? null);

        return response()->json(['data' => $this->carts->quote($cart, $this->contexts->forApi($request))]);
    }

    public function preview(Request $request, CheckoutService $checkout): JsonResponse
    {
        $validated = $request->validate([
            'district' => ['required', Rule::in(DeliveryZone::DISTRICTS)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
        ]);

        $summary = $checkout->summarize(
            $this->carts->forUser($request->user()),
            $this->contexts->forApi($request),
            $validated['district'],
            isset($validated['payment_method']) ? PaymentMethod::from($validated['payment_method']) : null,
        );

        return response()->json(['data' => $this->presentSummary($summary)]);
    }

    public function place(Request $request, OrderPlacer $placer, PaymentService $payments): JsonResponse
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (preg_match('/^[A-Za-z0-9\-_]{16,64}$/', $key) !== 1) {
            throw ValidationException::withMessages(['idempotency_key' => 'Send a unique Idempotency-Key header (16–64 letters, digits, - or _).']);
        }

        $request->merge(['phone' => BangladeshPhone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! BangladeshPhone::isValid((string) $value)) {
                    $fail('Enter a valid Bangladesh mobile number.');
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address_line' => ['required', 'string', 'min:6', 'max:255'],
            'area' => ['required', 'string', 'max:120'],
            'district' => ['required', Rule::in(DeliveryZone::DISTRICTS)],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'fingerprint' => ['required', 'string', 'size:64'],
        ]);

        $details = new CheckoutDetails(
            $validated['name'], $validated['phone'], $validated['email'] ?? null, $validated['address_line'], $validated['area'],
            $validated['district'], $validated['note'] ?? null, PaymentMethod::from($validated['payment_method']),
            'api-'.substr(hash('sha256', $request->user()->id.'|'.$key), 0, 56), $validated['fingerprint'],
        );

        try {
            $placed = $placer->place($this->carts->forUser($request->user()), $this->contexts->forApi($request), $details, $request->user());
        } catch (CheckoutChangedException $e) {
            return response()->json(['message' => $e->getMessage(), 'summary' => $this->presentSummary($e->summary)], 409);
        }

        $order = $placed->order;

        return response()->json([
            'data' => (new OrderController)->present($order->load('items')),
            'payment_url' => $order->payment_method->isOnline() && ! $order->isPaid() && $order->status->value === 'pending_payment' ? $payments->checkoutUrl($order) : null,
            'replayed' => $placed->wasExisting,
        ], $placed->wasExisting ? 200 : 201);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    private function presentSummary(array $summary): array
    {
        return [
            'lines' => $summary['quote']['lines'],
            'subtotal_paisa' => $summary['quote']['subtotal_paisa'],
            'discount_paisa' => $summary['quote']['discount_paisa'],
            'promotions' => $summary['quote']['promotions'],
            'coupon' => $summary['quote']['coupon'],
            'delivery_zone' => $summary['zone']?->name,
            'delivery_paisa' => $summary['delivery_paisa'],
            'total_paisa' => $summary['total_paisa'],
            'payment_methods' => array_map(fn (PaymentMethod $method) => $method->value, $summary['methods']),
            'errors' => $summary['errors'],
            'fingerprint' => $summary['fingerprint'],
        ];
    }
}
