<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Bundle;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use App\Services\Pricing\PricingContextResolver;
use App\Support\Seo;
use App\Support\Weight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Cart pages and actions. Prices shown are always the server's re-priced quote for the buyer's
 * current context; the cart itself never stores a price.
 */
class CartController extends Controller
{
    public function __construct(private CartService $carts, private PricingContextResolver $contexts) {}

    public function show(Request $request): View
    {
        $cart = $this->carts->forRequest($request);
        $context = $this->contexts->forWeb($request);

        return view('storefront.cart', [
            'seo' => (new Seo(title: __('Your cart')))->noindex(),
            'quote' => $cart === null ? null : $this->carts->quote($cart, $context),
            'cart' => $cart,
        ]);
    }

    public function addVariant(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'variant' => ['required', 'integer'],
            'weight_quantity' => ['nullable', 'string', 'max:20'],
            'unit' => ['nullable', Rule::in(['g', 'kg'])],
            'unit_quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $variant = ProductVariant::query()->whereKey($validated['variant'])->where('is_active', true)
            ->whereHas('product', fn ($product) => $product->visible())->firstOrFail();

        $quantity = $this->quantityFrom($variant, $validated);
        $cart = $this->cartFor($request);
        $this->carts->addVariant($cart, $variant, $quantity, $this->contexts->forWeb($request));

        return $this->afterAdd($request, $cart, __('Added to your cart.'));
    }

    public function addBundle(Request $request): RedirectResponse
    {
        $validated = $request->validate(['bundle' => ['required', 'integer'], 'quantity' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $bundle = Bundle::query()->findOrFail($validated['bundle']);

        $cart = $this->cartFor($request);
        $this->carts->addBundle($cart, $bundle, (int) ($validated['quantity'] ?? 1), $this->contexts->forWeb($request));

        return $this->afterAdd($request, $cart, __('Bundle added to your cart.'));
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        $variant = $item->variant()->first();

        $validated = $request->validate([
            'weight_quantity' => ['nullable', 'string', 'max:20'],
            'unit' => ['nullable', Rule::in(['g', 'kg'])],
            'unit_quantity' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $quantity = $variant !== null ? $this->quantityFrom($variant, $validated, allowZero: true) : (int) ($validated['unit_quantity'] ?? 0);
        $this->carts->updateQuantity($item, $quantity, $this->contexts->forWeb($request));

        return redirect()->route('cart.show')->with('status', __('Cart updated.'));
    }

    public function remove(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        $item->delete();

        return redirect()->route('cart.show')->with('status', __('Item removed.'));
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $validated = $request->validate(['code' => ['nullable', 'string', 'max:40']]);
        $cart = $this->cartFor($request);
        $this->carts->setCoupon($cart, $validated['code'] ?? null);

        return redirect()->route('cart.show');
    }

    /**
     * Base units (grams for weight variants, units otherwise) — parsed without floats.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function quantityFrom(ProductVariant $variant, array $input, bool $allowZero = false): int
    {
        if ($variant->isWeight()) {
            try {
                $grams = Weight::parse((string) ($input['weight_quantity'] ?? ''), (string) ($input['unit'] ?? 'g'));
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['quantity' => $e->getMessage()]);
            }

            return $grams;
        }

        $units = (int) ($input['unit_quantity'] ?? 0);
        if ($units < ($allowZero ? 0 : 1)) {
            throw ValidationException::withMessages(['quantity' => __('Enter a quantity.')]);
        }

        return $units;
    }

    private function cartFor(Request $request): Cart
    {
        $cart = $this->carts->forRequest($request, create: true);

        if ($request->user() === null) {
            Cookie::queue(Cookie::make(CartService::COOKIE, $cart->token, CartService::COOKIE_MINUTES, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $cart;
    }

    private function authorizeItem(Request $request, CartItem $item): void
    {
        $cart = $this->carts->forRequest($request);
        abort_if($cart === null || $item->cart_id !== $cart->id, 404);
    }

    private function afterAdd(Request $request, Cart $cart, string $message): RedirectResponse
    {
        return $request->boolean('buy_now')
            ? redirect()->route('checkout.show')
            : back()->with('status', $message.' '.__(':count item(s) in cart.', ['count' => $this->carts->itemCount($cart)]));
    }
}
