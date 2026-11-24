<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CakeDesign;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Support\BangladeshPhone;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

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
            'cakeSnapshots' => CakeDesign::query()->whereIn('id', $order->items->pluck('cake_design_id')->filter())->whereNotNull('snapshot_path')->pluck('id')->flip()->all(),
        ]);
    }

    /**
     * The 3D snapshot of a custom cake in this order, for whoever may see the order.
     */
    public function cakeSnapshot(Request $request, Order $order, OrderItem $item): Response
    {
        $this->authorizeView($request, $order);
        abort_unless($item->order_id === $order->id && $item->isCake() && $item->cake_design_id !== null, 404);
        $path = CakeDesign::query()->whereKey($item->cake_design_id)->value('snapshot_path');
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function pay(Request $request, Order $order, PaymentService $payments): RedirectResponse
    {
        $this->authorizeView($request, $order);

        return redirect()->away($payments->checkoutUrl($order));
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $this->authorizeView($request, $order);
        abort_unless($this->mayCancel($request, $order), 403);
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

        if ($order->belongsToUser($request->user()) || $order->isVisibleToMember($request->user())) {
            return;
        }

        abort_unless($order->user_id === null && $order->matchesGuestKey(is_string($key) ? $key : null), 404);

        if ($request->query('key') !== null) {
            $request->session()->put('guest_orders.'.$order->number, (string) $request->query('key'));
        }
    }

    /**
     * The customer who placed it, or a member of its organization who may place orders (not viewers).
     */
    private function mayCancel(Request $request, Order $order): bool
    {
        $user = $request->user();
        if ($order->belongsToUser($user) || ($user === null && $order->user_id === null)) {
            return true;
        }

        $organization = $order->organization_id === null ? null : $order->organization()->first();

        return $organization !== null && ($user?->roleIn($organization)?->canPlaceOrders() ?? false);
    }
}
