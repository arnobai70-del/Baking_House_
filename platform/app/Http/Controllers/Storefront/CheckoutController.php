<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutChangedException;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderPlacer;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingContextResolver;
use App\Support\BangladeshPhone;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Two-step checkout: details → review (server summary + explicit confirmation) → place.
 * Guests can check out as retail customers; trade buyers are always signed in.
 */
class CheckoutController extends Controller
{
    private const DRAFT = 'checkout.draft';

    public function __construct(
        private CartService $carts,
        private CheckoutService $checkout,
        private PricingContextResolver $contexts,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->carts->forRequest($request);
        if ($cart === null || $this->carts->itemCount($cart) === 0) {
            return redirect()->route('cart.show');
        }

        $draft = $request->session()->get(self::DRAFT, []);
        $user = $request->user();
        $district = $request->query('district', $draft['district'] ?? null);
        $summary = $this->checkout->summarize($cart, $this->contexts->forWeb($request), is_string($district) ? $district : null);

        return view('storefront.checkout.details', [
            'seo' => (new Seo(title: __('Checkout')))->noindex(),
            'summary' => $summary,
            'draft' => $draft + ['name' => $user?->name, 'phone' => $user?->phone, 'email' => $user?->email, 'district' => $district],
            'districts' => DeliveryZone::DISTRICTS,
        ]);
    }

    public function review(Request $request): RedirectResponse
    {
        $request->merge(['phone' => BangladeshPhone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! BangladeshPhone::isValid((string) $value)) {
                    $fail(__('Enter a valid Bangladesh mobile number (01XXXXXXXXX).'));
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address_line' => ['required', 'string', 'min:6', 'max:255'],
            'area' => ['required', 'string', 'max:120'],
            'district' => ['required', Rule::in(DeliveryZone::DISTRICTS)],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
        ]);

        $request->session()->put(self::DRAFT, $validated + ['token' => Str::random(40)]);

        return redirect()->route('checkout.review');
    }

    public function showReview(Request $request): View|RedirectResponse
    {
        $draft = $request->session()->get(self::DRAFT);
        $cart = $this->carts->forRequest($request);
        if ($draft === null || $cart === null) {
            return redirect()->route('checkout.show');
        }

        $summary = $this->checkout->summarize($cart, $this->contexts->forWeb($request), $draft['district'], PaymentMethod::from($draft['payment_method']));

        return view('storefront.checkout.review', [
            'seo' => (new Seo(title: __('Review your order')))->noindex(),
            'summary' => $summary,
            'draft' => $draft,
            'method' => PaymentMethod::from($draft['payment_method']),
        ]);
    }

    public function place(Request $request, OrderPlacer $placer, PaymentService $payments): RedirectResponse
    {
        $request->validate([
            'checkout_token' => ['required', 'string', 'size:40'],
            'fingerprint' => ['required', 'string', 'size:64'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => __('Please confirm the order details and terms.')]);

        $draft = $request->session()->get(self::DRAFT);
        $cart = $this->carts->forRequest($request);
        if ($draft === null || $cart === null || ! hash_equals($draft['token'], (string) $request->input('checkout_token'))) {
            return $this->existingOrderRedirect($request) ?? redirect()->route('checkout.show')->withErrors(['checkout' => __('Your checkout session expired. Please review your order again.')]);
        }

        $details = new CheckoutDetails(
            $draft['name'], $draft['phone'], $draft['email'] ?? null, $draft['address_line'], $draft['area'], $draft['district'],
            $draft['note'] ?? null, PaymentMethod::from($draft['payment_method']), $draft['token'], (string) $request->input('fingerprint'),
        );

        try {
            $placed = $placer->place($cart, $this->contexts->forWeb($request), $details, $request->user());
        } catch (CheckoutChangedException $e) {
            return redirect()->route('checkout.review')->withErrors(['checkout' => $e->getMessage()]);
        }

        $request->session()->forget(self::DRAFT);
        $request->session()->put('checkout.last_token', $details->checkoutToken);
        $order = $placed->order;
        if ($placed->guestKey !== null) {
            $request->session()->put('guest_orders.'.$order->number, $placed->guestKey);
        }

        if ($order->payment_method->isOnline() && ! $placed->wasExisting) {
            return redirect()->away($payments->checkoutUrl($order));
        }

        return redirect()->route('orders.show', $order)->with('status', __('Thank you! Your order :number has been placed.', ['number' => $order->number]));
    }

    /**
     * A repeated submit after success goes to the order instead of creating another.
     */
    private function existingOrderRedirect(Request $request): ?RedirectResponse
    {
        $token = $request->session()->get('checkout.last_token');
        if ($token !== null && hash_equals($token, (string) $request->input('checkout_token'))) {
            $order = Order::query()->where('checkout_token', $token)->first();

            return $order === null ? null : redirect()->route('orders.show', $order);
        }

        return null;
    }
}
