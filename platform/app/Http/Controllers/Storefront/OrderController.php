<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Support\BangladeshPhone;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer order pages. Access: the signed-in customer who placed it, or a guest holding the
 * order's access key (shown once at checkout / in the SMS link). Staff use the admin screens.
 */
class OrderController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $this->authorizeView($request, $order);

        return view('storefront.orders.show', [
            'seo' => (new Seo(title: __('Order :number', ['number' => $order->number])))->noindex(),
            'order' => $order->load(['items', 'events', 'payments']),
            'bank' => config('commerce.bank_transfer'),
        ]);
    }

    public function pay(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $this->authorizeView($request, $order);

        return redirect()->away($payments->checkoutUrl($order));
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeView($request, $order);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);

        $orders->cancel($order, $validated['reason'], $request->user(), byCustomer: true);

        return redirect()->route('orders.show', $order)->with('status', __('Your order has been cancelled.'));
    }

    public function track(Request $request): View
    {
        $result = null;
        if ($request->filled('number')) {
            $request->validate(['number' => ['required', 'string', 'max:32'], 'phone' => ['required', 'string', 'max:20']]);
            $phone = BangladeshPhone::normalize($request->input('phone'));
            $order = Order::query()->where('number', strtoupper(trim((string) $request->input('number'))))->first();
            $result = $order !== null && $phone !== null && hash_equals($order->customer_phone, $phone) ? $order : false;
        }

        return view('storefront.orders.track', ['seo' => (new Seo(title: __('Track your order')))->noindex(), 'result' => $result]);
    }

    private function authorizeView(Request $request, Order $order): void
    {
        $sessionKey = $request->session()->get('guest_orders.'.$order->number);
        $key = $request->query('key', $sessionKey);

        if ($order->belongsToUser($request->user())) {
            return;
        }

        abort_unless($order->user_id === null && $order->matchesGuestKey(is_string($key) ? $key : null), 404);

        if ($request->query('key') !== null) {
            $request->session()->put('guest_orders.'.$order->number, (string) $request->query('key'));
        }
    }
}
