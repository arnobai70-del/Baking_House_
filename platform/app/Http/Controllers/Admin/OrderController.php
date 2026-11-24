<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Support\Taka;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff order processing. Every action goes through OrderService / PaymentService, which guard
 * status changes, move stock through the ledger and write the audit log.
 */
class OrderController extends Controller
{
    public function __construct(private OrderService $orders, private PaymentService $payments) {}

    public function index(Request $request): View
    {
        $status = OrderStatus::tryFrom((string) $request->query('status', ''));
        $paymentStatus = PaymentStatus::tryFrom((string) $request->query('payment', ''));
        $search = trim((string) $request->query('q', ''));

        $orders = Order::query()
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($paymentStatus, fn ($query) => $query->where('payment_status', $paymentStatus->value))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->where('number', 'like', '%'.addcslashes(strtoupper($search), '%_\\').'%')->orWhere('customer_phone', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'status' => $status,
            'paymentStatus' => $paymentStatus,
            'search' => $search,
            'counts' => Order::query()->selectRaw('status, count(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load(['items', 'payments.refunds', 'events.actor:id,name', 'organization:id,name,type', 'user:id,name']),
        ]);
    }

    public function transition(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->orders->transition($order, OrderStatus::from($validated['status']), $request->user(), $validated['note'] ?? null);

        return back()->with('status', "Order moved to {$order->status->label()}.");
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->orders->cancel($order, $validated['reason'], $request->user(), byCustomer: false);

        return back()->with('status', 'Order cancelled and stock restored.'.($order->isPaid() ? ' A refund is due.' : ''));
    }

    public function retryAllocation(Request $request, Order $order): RedirectResponse
    {
        $this->orders->retryAllocation($order, $request->user());

        return back()->with('status', 'Stock allocated and order confirmed.');
    }

    public function confirmBankTransfer(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['amount' => ['required', 'string', 'max:20'], 'bank_reference' => ['required', 'string', 'min:3', 'max:100']]);
        $amount = Taka::parse($validated['amount']);
        if ($amount === null) {
            throw ValidationException::withMessages(['amount' => 'Enter the amount received in taka.']);
        }

        $this->payments->confirmBankTransfer($order, $amount, $validated['bank_reference'], $request->user());

        return back()->with('status', 'Bank transfer confirmed; order confirmed.');
    }

    public function collectCash(Request $request, Order $order): RedirectResponse
    {
        $this->payments->markCashCollected($order, $request->user());

        return back()->with('status', 'Cash collection recorded.');
    }

    /**
     * Record the balance of an advance-paid (custom cake) order, received as cash or bank transfer.
     */
    public function collectBalance(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'method' => ['required', Rule::in([PaymentMethod::CashOnDelivery->value, PaymentMethod::BankTransfer->value])],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);
        $payment = $this->payments->collectBalance($order, PaymentMethod::from($validated['method']), $validated['reference'] ?? null, $request->user());

        return back()->with('status', 'Balance of '.Taka::format($payment->amount_paisa).' recorded. The order is fully paid.');
    }

    public function refund(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate(['amount' => ['required', 'string', 'max:20'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $amount = Taka::parse($validated['amount']);
        if ($amount === null) {
            throw ValidationException::withMessages(['amount' => 'Enter the refund amount in taka.']);
        }

        $this->payments->refund($order, $amount, $validated['reason'], $request->user());

        return back()->with('status', 'Refund recorded.');
    }

    public function reconciliation(): View
    {
        return view('admin.orders.reconciliation', [
            'issues' => $this->payments->reconciliationIssues(),
            'events' => PaymentEvent::query()->latest('id')->limit(50)->get(),
        ]);
    }
}
