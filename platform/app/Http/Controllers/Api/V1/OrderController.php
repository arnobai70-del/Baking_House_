<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The caller's own orders (orders:read). Another customer's order number returns 404.
 */
class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->where('user_id', $request->user()->id)->latest('id')->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => $this->present($order, withItems: false)),
            'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage(), 'total' => $orders->total()],
        ]);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $order = Order::query()->where('number', $number)->where('user_id', $request->user()->id)->with('items')->firstOrFail();

        return response()->json(['data' => $this->present($order)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Order $order, bool $withItems = true): array
    {
        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'payment_method' => $order->payment_method->value,
            'subtotal_paisa' => $order->subtotal_paisa,
            'discount_paisa' => $order->discount_paisa,
            'delivery_paisa' => $order->delivery_paisa,
            'total_paisa' => $order->total_paisa,
            'refunded_paisa' => $order->refunded_paisa,
            'placed_at' => $order->placed_at->toIso8601String(),
            'items' => $withItems ? $order->items->map(fn (OrderItem $item) => [
                'type' => $item->type, 'sku' => $item->sku, 'name' => $item->name, 'quantity' => $item->quantity,
                'quantity_label' => $item->quantity_label, 'rate_source' => $item->rate_source, 'line_paisa' => $item->line_paisa,
            ])->all() : null,
        ];
    }
}
