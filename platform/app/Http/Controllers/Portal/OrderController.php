<?php

namespace App\Http\Controllers\Portal;

use App\Enums\OrderStatus;
use App\Models\Bundle;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\Cart\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Orders of the organization (all members can see them), one-click reorder at TODAY's prices, and
 * a printable invoice.
 */
class OrderController extends PortalController
{
    public function index(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status'));

        return $this->page($request, 'portal.orders', 'orders', [
            'orders' => $this->organization($request)->orders()
                ->when($status !== null, fn ($query) => $query->where('status', $status->value))
                ->with('user:id,name')
                ->paginate(20)->withQueryString(),
            'status' => $status,
        ]);
    }

    /**
     * Add the lines of a past order to the cart again. Prices are recalculated now (never copied
     * from the old order); quotation lines are skipped because each quote can be ordered once.
     */
    public function reorder(Request $request, Order $order, CartService $carts): RedirectResponse
    {
        $this->ownOrder($request, $order);
        $this->requireBuyer($request);

        $context = $this->context($request);
        $cart = $carts->forUser($request->user());
        $added = 0;
        $skipped = [];

        foreach ($order->items()->get() as $item) {
            /** @var OrderItem $item */
            try {
                if ($item->type === 'item' && $item->product_variant_id !== null) {
                    $variant = ProductVariant::query()->find($item->product_variant_id);
                    if ($variant === null) {
                        throw ValidationException::withMessages(['quantity' => __('No longer available.')]);
                    }
                    $carts->addVariant($cart, $variant, $item->quantity, $context);
                    $added++;
                } elseif ($item->type === 'bundle' && $item->bundle_id !== null) {
                    $bundle = Bundle::query()->find($item->bundle_id);
                    if ($bundle === null) {
                        throw ValidationException::withMessages(['quantity' => __('No longer available.')]);
                    }
                    $carts->addBundle($cart, $bundle, $item->quantity, $context);
                    $added++;
                } else {
                    $skipped[] = $item->name.' — '.__('quotations can be ordered once; request a new quote.');
                }
            } catch (ValidationException $e) {
                $skipped[] = $item->name.' — '.collect($e->errors())->flatten()->first();
            }
        }

        return redirect()->route('cart.show')
            ->with('status', __(':count line(s) added to your cart at today\'s prices.', ['count' => $added]))
            ->with('bulk_skipped', $skipped);
    }

    public function invoice(Request $request, Order $order): View
    {
        $this->ownOrder($request, $order);

        return view('portal.invoice', [
            'order' => $order->load('items'),
            'organization' => $this->organization($request),
            'seller' => config('commerce.seller'),
        ]);
    }
}
