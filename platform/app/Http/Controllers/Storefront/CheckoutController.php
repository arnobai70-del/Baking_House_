<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutChangedException;
use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Services\Addresses\AddressBook;
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
        $context = $this->contexts->forWeb($request);

        /* Pre-fill from saved addresses: the organization's when buying for it, otherwise the customer's own. */
        $addresses = $context->organization?->addresses()->get() ?? $user?->addresses()->get() ?? collect();
        $chosen = $request->filled('address')
            ? $addresses->firstWhere('id', (int) $request->query('address'))
            : ($draft === [] ? $addresses->firstWhere('is_default', true) : null);
        if ($chosen !== null) {
            $draft = ['name' => $chosen->recipient_name, 'phone' => $chosen->phone, 'address_line' => $chosen->line, 'area' => $chosen->area, 'district' => $chosen->district] + $draft;
        }

        $district = $request->query('district', $draft['district'] ?? null);
        $fulfilment = (string) $request->query('fulfilment', $draft['fulfilment'] ?? 'delivery');
        $pickupId = $request->query('pickup_location_id', $draft['pickup_location_id'] ?? null);
        $summary = $this->checkout->summarize($cart, $context, is_string($district) ? $district : null, null, $fulfilment, is_numeric($pickupId) ? (int) $pickupId : null);

        return view('storefront.checkout.details', [
            'seo' => (new Seo(title: __('Checkout')))->noindex(),
            'summary' => $summary,
            'draft' => $draft + ['name' => $user?->name, 'phone' => $user?->phone, 'email' => $user?->email, 'district' => $district, 'fulfilment' => $summary['fulfilment'], 'pickup_location_id' => $pickupId],
            'districts' => DeliveryZone::DISTRICTS,
            'addresses' => $addresses,
            'chosenAddress' => $chosen,
            'buyingFor' => $context->organization,
            'canSaveAddress' => $user !== null && $context->organization === null && $chosen === null && $addresses->count() < AddressBook::MAX_ADDRESSES,
        ]);
    }

    public function review(Request $request): RedirectResponse
    {
        $request->merge(['phone' => BangladeshPhone::normalize($request->input('phone')) ?? $request->input('phone')]);
        $pickup = $request->input('fulfilment') === 'pickup'; // offered only for carts with a custom cake (checked again in the summary)
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! BangladeshPhone::isValid((string) $value)) {
                    $fail(__('Enter a valid Bangladesh mobile number (01XXXXXXXXX).'));
                }
            }],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'fulfilment' => ['nullable', Rule::in(['delivery', 'pickup'])],
            'pickup_location_id' => [$pickup ? 'required' : 'nullable', 'integer'],
            'address_line' => [$pickup ? 'nullable' : 'required', 'string', 'min:6', 'max:255'],
            'area' => [$pickup ? 'nullable' : 'required', 'string', 'max:120'],
            'district' => [$pickup ? 'nullable' : 'required', Rule::in(DeliveryZone::DISTRICTS)],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'save_address' => ['nullable', 'boolean'],
        ]);

        $validated['fulfilment'] = $pickup ? 'pickup' : 'delivery';
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

        $summary = $this->checkout->summarize($cart, $this->contexts->forWeb($request), $draft['district'] ?? null, PaymentMethod::from($draft['payment_method']),
            $draft['fulfilment'] ?? 'delivery', isset($draft['pickup_location_id']) ? (int) $draft['pickup_location_id'] : null);

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
            $draft['name'], $draft['phone'], $draft['email'] ?? null, (string) ($draft['address_line'] ?? ''), (string) ($draft['area'] ?? ''), (string) ($draft['district'] ?? ''),
            $draft['note'] ?? null, PaymentMethod::from($draft['payment_method']), $draft['token'], (string) $request->input('fingerprint'),
            $draft['fulfilment'] ?? 'delivery', isset($draft['pickup_location_id']) ? (int) $draft['pickup_location_id'] : null,
        );

        try {
            $placed = $placer->place($cart, $this->contexts->forWeb($request), $details, $request->user());
        } catch (CheckoutChangedException $e) {
            return redirect()->route('checkout.review')->withErrors(['checkout' => $e->getMessage()]);
        }

        $this->rememberAddress($request, $draft, $placed->wasExisting);
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

    /**
     * "Save this address" at checkout (signed-in retail customers): added once to the address book,
     * never duplicated; a full address book is skipped silently because the order is already placed.
     *
     * @param  array<string, mixed>  $draft
     */
    private function rememberAddress(Request $request, array $draft, bool $wasExisting): void
    {
        $user = $request->user();
        if ($wasExisting || $user === null || empty($draft['save_address']) || ($draft['fulfilment'] ?? 'delivery') === 'pickup'
            || $this->contexts->forWeb($request)->organization !== null) {
            return;
        }

        $duplicate = $user->addresses()->reorder()->where('line', $draft['address_line'])->where('area', $draft['area'])
            ->where('district', $draft['district'])->where('phone', $draft['phone'])->exists();
        if ($duplicate || $user->addresses()->reorder()->count() >= AddressBook::MAX_ADDRESSES) {
            return;
        }

        app(AddressBook::class)->add($user->addresses(), [
            'label' => mb_substr((string) $draft['area'], 0, 60),
            'recipient_name' => (string) $draft['name'],
            'phone' => (string) $draft['phone'],
            'line' => (string) $draft['address_line'],
            'area' => (string) $draft['area'],
            'district' => (string) $draft['district'],
        ], false);
    }
}
