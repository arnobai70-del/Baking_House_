@extends('layouts.admin', ['title' => 'Order '.$order->number])

@use('App\Support\Taka')
@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')
@use('App\Enums\PaymentStatus')

@section('admin')
    <div class="admin-top">
        <h1>{{ $order->number }}</h1>
        <span class="status {{ $order->status->tone() }}">{{ $order->status->label() }}</span>
        <span class="status {{ $order->isPaid() ? 'ok' : 'warn' }}">{{ $order->payment_status->label() }}</span>
        <span class="spacer"></span>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.orders.index') }}">← All orders</a>
    </div>

    @if ($order->status === OrderStatus::Cancelled && $order->isPaid() && $order->refunded_paisa < $order->total_paisa)
        <div class="alert alert-error" style="margin-bottom:16px"><x-icon name="alert" /><span><strong>Refund due:</strong> this cancelled order was paid. Refund {{ Taka::format($order->total_paisa - $order->refunded_paisa) }} below.</span></div>
    @endif
    @if ($order->status === OrderStatus::PaymentReview)
        <div class="alert alert-error" style="margin-bottom:16px"><x-icon name="alert" /><span>Paid after the stock hold expired and stock was not available. Allocate stock now (if restocked) or cancel and refund.</span></div>
    @endif

    <div class="admin-grid">
        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-lg)">Items (as charged)</h2>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Item</th><th>SKU</th><th>Qty</th><th>Rate</th><th>Source</th><th>Line</th></tr></thead>
                <tbody>@foreach ($order->items as $item)
                    <tr><td>{{ $item->name }}
                            @if ($item->isCake())
                                <div class="small"><strong>{{ $item->scheduled_for?->format('D j M Y') }} · {{ $item->time_slot }}</strong>
                                    @if ($item->components['uses_test_prices'] ?? false) <span class="status warn">TEST prices</span>@endif</div>
                                <ul class="small muted" style="margin:4px 0 0;padding-left:16px">@foreach ($item->components['breakdown'] ?? [] as $part)<li>{{ $part['label'] }} — {{ Taka::format($part['paisa']) }}</li>@endforeach</ul>
                                @if (! empty($item->components['spec']['message']))<div class="small">Message: “{{ $item->components['spec']['message'] }}”</div>@endif
                                @if (! empty($item->components['spec']['note']))<div class="small">Baker note: {{ $item->components['spec']['note'] }}</div>@endif
                            @endif</td><td class="small">{{ $item->sku ?? 'bundle' }}</td><td class="tnum">{{ $item->quantity_label }}</td>
                        <td class="tnum small">{{ $item->per_kg_paisa ? Taka::format($item->per_kg_paisa).'/kg' : ($item->unit_paisa ? Taka::format($item->unit_paisa) : '—') }}</td>
                        <td><span class="status info">{{ $item->rate_source }}</span></td><td class="tnum">{{ Taka::format($item->line_paisa) }}</td></tr>
                @endforeach</tbody></table></div>
            @foreach ($order->items->filter->isCake() as $item)
                @include('admin.orders._cake', ['item' => $item])
            @endforeach
            <div style="max-width:340px;margin-left:auto">
                <div class="sum-row"><span>Subtotal</span><span class="tnum">{{ Taka::format($order->subtotal_paisa) }}</span></div>
                @foreach ($order->promotion_snapshot ?? [] as $promotion)<div class="sum-row"><span>{{ $promotion['name'] }}@if ($promotion['code']) ({{ $promotion['code'] }})@endif</span><span class="tnum">−{{ Taka::format($promotion['discount_paisa']) }}</span></div>@endforeach
                <div class="sum-row"><span>{{ $order->isPickup() ? 'Store pickup' : 'Delivery ('.($order->pricing_snapshot['delivery']['zone'] ?? '—').')' }}</span><span class="tnum">{{ Taka::format($order->delivery_paisa) }}</span></div>
                <div class="sum-row total"><span>Total</span><span class="tnum">{{ Taka::format($order->total_paisa) }}</span></div>
                @if ($order->advance_paisa > 0)
                    <div class="sum-row"><span>Advance due at checkout</span><span class="tnum">{{ Taka::format($order->advance_paisa) }}</span></div>
                    <div class="sum-row"><span>Paid so far</span><span class="tnum">{{ Taka::format($order->paidPaisa()) }}</span></div>
                    <div class="sum-row"><strong>Balance due</strong><strong class="tnum">{{ Taka::format($order->balancePaisa()) }}</strong></div>
                @endif
                @if ($order->refunded_paisa)<div class="sum-row"><span>Refunded</span><span class="tnum">{{ Taka::format($order->refunded_paisa) }}</span></div>@endif
            </div>
            <h3 style="margin-top:14px">Customer</h3>
            <p class="small">{{ $order->customer_name }} · {{ $order->customer_phone }}@if ($order->customer_email) · {{ $order->customer_email }}@endif<br>
                @if ($order->isPickup())
                    Collects from: <strong>{{ $order->pickupLocation?->name ?? 'pickup location removed' }}</strong>@if ($order->pickupLocation) — {{ $order->pickupLocation->address }}@endif<br>
                @else
                    {{ $order->shipping_address['line'] }}, {{ $order->shipping_address['area'] }}, {{ $order->shipping_address['district'] }}<br>
                @endif
                {{ $order->audience->label() }}@if ($order->organization) · {{ $order->organization->name }}@endif @if ($order->user) · account: {{ $order->user->name }}@else · guest @endif</p>
            @if ($order->customer_note)<p class="small"><strong>Note:</strong> {{ $order->customer_note }}</p>@endif
            @if ($order->cancel_reason)<p class="small"><strong>Cancel reason:</strong> {{ $order->cancel_reason }}</p>@endif
        </section>

        <section class="stack">
            <div class="card card-pad">
                <h2 style="font-size:var(--bh-fs-md)">Fulfillment</h2>
                @php $next = array_values(array_filter($order->status->allowedNext(), fn ($s) => ! in_array($s, [OrderStatus::Cancelled, OrderStatus::Confirmed], true))); @endphp
                @if ($next)
                    <form method="POST" action="{{ route('admin.orders.transition', $order) }}" class="row" style="gap:6px" novalidate>
                        @csrf
                        @foreach ($next as $status)<button class="btn btn-navy btn-sm" name="status" value="{{ $status->value }}">Mark {{ strtolower($status->label()) }}</button>@endforeach
                        <input class="input" name="note" placeholder="Note (optional)" aria-label="Note" style="min-height:36px">
                    </form>
                @endif
                @if ($order->status === OrderStatus::PaymentReview)
                    <form method="POST" action="{{ route('admin.orders.allocate', $order) }}" style="margin-top:8px">@csrf<button class="btn btn-teal btn-sm">Allocate stock &amp; confirm</button></form>
                @endif
                @if ($order->status->canTransitionTo(OrderStatus::Cancelled))
                    <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="row" style="margin-top:10px;gap:6px" novalidate>
                        @csrf
                        <input class="input" name="reason" placeholder="Cancellation reason" aria-label="Cancellation reason" required style="min-height:36px">
                        <button class="btn btn-outline btn-sm">Cancel &amp; restock</button>
                    </form>
                @endif
                @if (! $next && ! $order->status->canTransitionTo(OrderStatus::Cancelled) && $order->status !== OrderStatus::PaymentReview)<p class="muted small">No further fulfillment steps.</p>@endif
            </div>

            <div class="card card-pad">
                <h2 style="font-size:var(--bh-fs-md)">Payment — {{ $order->payment_method->label() }}</h2>
                @foreach ($order->payments as $payment)
                    <p class="small"><code>{{ $payment->reference }}</code> · {{ $payment->status->value }} · {{ Taka::format($payment->amount_paisa) }} · {{ $payment->mode }}
                        @if ($payment->provider_transaction_id)<br>Txn {{ $payment->provider_transaction_id }}@endif
                        @if ($payment->refunded_paisa)<br>Refunded {{ Taka::format($payment->refunded_paisa) }}@endif</p>
                @endforeach
                @if ($order->payment_method === PaymentMethod::BankTransfer && ! $order->isPaid() && $order->status === OrderStatus::PendingPayment)
                    <form method="POST" action="{{ route('admin.orders.bank-transfer', $order) }}" novalidate>
                        @csrf
                        <div class="form-grid">
                            <div class="field"><label for="amount">Amount received (৳)</label><input class="input tnum" id="amount" name="amount" value="{{ Taka::toInput($order->advance_paisa > 0 ? $order->advance_paisa : $order->total_paisa) }}"></div>
                            <div class="field"><label for="bank_reference">Bank reference</label><input class="input" id="bank_reference" name="bank_reference" required></div>
                        </div>
                        <button class="btn btn-teal btn-sm">Confirm transfer received</button>
                    </form>
                @endif
                @if ($order->payment_method === PaymentMethod::CashOnDelivery && ! $order->isPaid() && in_array($order->status, [OrderStatus::Shipped, OrderStatus::Delivered], true))
                    <form method="POST" action="{{ route('admin.orders.cash-collected', $order) }}">@csrf<button class="btn btn-teal btn-sm">Record cash collected</button></form>
                @endif
                @if ($order->payment_status === PaymentStatus::PartiallyPaid && $order->status !== OrderStatus::Cancelled)
                    <form method="POST" action="{{ route('admin.orders.balance', $order) }}" style="margin-top:10px" novalidate>
                        @csrf
                        <p class="small"><strong>Advance received.</strong> Balance {{ Taka::format($order->balancePaisa()) }} is due on {{ $order->isPickup() ? 'pickup' : 'delivery' }}.</p>
                        <div class="form-grid">
                            <div class="field"><label for="balance_method">Received as</label>
                                <select class="input" id="balance_method" name="method"><option value="cod">Cash</option><option value="bank_transfer">Bank transfer</option></select></div>
                            <div class="field"><label for="balance_reference">Reference (optional)</label><input class="input" id="balance_reference" name="reference"></div>
                        </div>
                        <button class="btn btn-teal btn-sm">Record balance received</button>
                    </form>
                @endif
                @if ($order->isPaid() || $order->payment_status === PaymentStatus::PartiallyPaid)
                    <form method="POST" action="{{ route('admin.orders.refund', $order) }}" style="margin-top:10px" novalidate>
                        @csrf
                        <div class="form-grid">
                            <div class="field"><label for="refund_amount">Refund (৳)</label><input class="input tnum" id="refund_amount" name="amount" value="{{ Taka::toInput(min($order->total_paisa, $order->paidPaisa()) - $order->refunded_paisa) }}"></div>
                            <div class="field"><label for="refund_reason">Reason</label><input class="input" id="refund_reason" name="reason" required></div>
                        </div>
                        <button class="btn btn-outline btn-sm">Record refund</button>
                    </form>
                @endif
            </div>

            <div class="card card-pad">
                <h2 style="font-size:var(--bh-fs-md)">Timeline</h2>
                <ol style="list-style:none;padding:0;margin:0">
                    @foreach ($order->events as $event)
                        <li class="small" style="padding:5px 0;border-bottom:1px solid var(--bh-border)">
                            <span class="status {{ $event->kind === 'payment' ? 'info' : '' }}">{{ $event->kind }}</span>
                            {{ $event->from_status ? $event->from_status.' → ' : '' }}<strong>{{ $event->to_status }}</strong>
                            <span class="muted">· {{ $event->created_at->timezone('Asia/Dhaka')->format('d M H:i') }} · {{ $event->actor?->name ?? 'system' }}</span>
                            @if ($event->note)<div class="muted">{{ $event->note }}</div>@endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    </div>
@endsection
