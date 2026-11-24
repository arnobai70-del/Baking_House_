@extends('layouts.app')

@use('App\Support\Taka')
@use('App\Enums\OrderStatus')
@use('App\Enums\PaymentMethod')

@section('content')
    <div class="row" style="margin-bottom:12px">
        <h1 style="font-size:var(--bh-fs-2xl);margin:0">{{ __('Order :number', ['number' => $order->number]) }}</h1>
        <span class="status {{ $order->status->tone() }}">{{ __($order->status->label()) }}</span>
        <span class="status {{ $order->isPaid() ? 'ok' : 'warn' }}">{{ __($order->payment_status->label()) }}</span>
    </div>

    @if ($order->status === OrderStatus::PendingPayment && $order->payment_method->isOnline() && ! $order->isPaid())
        <div class="alert {{ $order->payment_status->value === 'failed' ? 'alert-error' : 'alert-info' }}" style="margin-bottom:16px">
            <x-icon name="{{ $order->payment_status->value === 'failed' ? 'alert' : 'info' }}" />
            <div>{{ $order->payment_status->value === 'failed' ? __('Your payment did not go through.') : __('Waiting for your payment.') }}
                @if ($order->reservation_expires_at?->isFuture()) {{ __('Your items are held until :time.', ['time' => $order->reservation_expires_at->timezone('Asia/Dhaka')->format('H:i')]) }}@endif
                <form method="POST" action="{{ route('orders.pay', $order) }}" style="margin-top:8px">@csrf<button class="btn btn-primary btn-sm" type="submit">{{ __('Pay now with :method', ['method' => __($order->payment_method->label())]) }}</button></form></div>
        </div>
    @elseif ($order->status === OrderStatus::PendingPayment && $order->payment_method === PaymentMethod::BankTransfer)
        <div class="card card-pad" style="margin-bottom:16px;border-left:4px solid var(--bh-teal)">
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Pay by bank transfer') }}</h2>
            <table class="specs"><tbody>
                <tr><th>{{ __('Amount') }}</th><td class="tnum"><strong>{{ Taka::format($order->total_paisa) }}</strong></td></tr>
                <tr><th>{{ __('Reference') }}</th><td><strong>{{ $order->number }}</strong></td></tr>
                <tr><th>{{ __('Account name') }}</th><td>{{ $bank['account_name'] }}</td></tr>
                <tr><th>{{ __('Account number') }}</th><td>{{ $bank['account_number'] }}</td></tr>
                <tr><th>{{ __('Bank / branch') }}</th><td>{{ $bank['bank'] }} — {{ $bank['branch'] }}</td></tr>
            </tbody></table>
            <p class="hint">{{ __('Your order is confirmed when our team sees the transfer. Unpaid transfers are cancelled after :hours hours.', ['hours' => config('commerce.bank_transfer_hours')]) }}</p>
        </div>
    @elseif ($order->status === OrderStatus::PaymentReview)
        <div class="alert alert-error" style="margin-bottom:16px"><x-icon name="alert" /><span>{{ __('We received your payment after the stock hold expired. Our team will confirm availability or refund you in full.') }}</span></div>
    @endif

    <div class="cart-layout">
        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Items') }}</h2>
            @foreach ($order->items as $item)
                <div class="sum-row"><span>{{ $item->name }} <span class="muted">{{ $item->quantity_label }}</span></span><span class="tnum">{{ Taka::format($item->line_paisa) }}</span></div>
                @if ($item->isCake())
                    <div class="order-cake">
                        @if (isset($cakeSnapshots[$item->cake_design_id]))
                            <img src="{{ route('orders.cake-snapshot', [$order, $item]) }}" alt="{{ __('3D preview of your custom cake') }}" width="240" height="240" loading="lazy">
                        @endif
                        <p class="small muted">{{ __('Ready for :date, :slot', ['date' => $item->scheduled_for?->translatedFormat('D j M'), 'slot' => $item->time_slot]) }}<br>{{ __('The real cake is handmade and may vary slightly from the preview.') }}</p>
                    </div>
                @endif
            @endforeach
            <div class="sum-row" style="border-top:1px solid var(--bh-border);margin-top:6px;padding-top:10px"><span>{{ __('Subtotal') }}</span><span class="tnum">{{ Taka::format($order->subtotal_paisa) }}</span></div>
            @if ($order->discount_paisa)<div class="sum-row"><span>{{ __('Discount') }}</span><span class="tnum">−{{ Taka::format($order->discount_paisa) }}</span></div>@endif
            <div class="sum-row"><span>{{ __('Delivery') }}</span><span class="tnum">{{ $order->delivery_paisa === 0 ? __('Free') : Taka::format($order->delivery_paisa) }}</span></div>
            <div class="sum-row total"><span>{{ __('Total') }}</span><span class="tnum">{{ Taka::format($order->total_paisa) }}</span></div>
            @if ($order->refunded_paisa)<div class="sum-row"><span>{{ __('Refunded') }}</span><span class="tnum">{{ Taka::format($order->refunded_paisa) }}</span></div>@endif

            <h2 style="font-size:var(--bh-fs-lg);margin-top:18px">{{ __('Delivery') }}</h2>
            <p>{{ $order->customer_name }} · {{ $order->customer_phone }}<br>{{ $order->shipping_address['line'] }}, {{ $order->shipping_address['area'] }}, {{ $order->shipping_address['district'] }}</p>

            @if ($order->customerCanCancel())
                <details style="margin-top:12px"><summary class="small" style="cursor:pointer">{{ __('Cancel this order') }}</summary>
                    <form method="POST" action="{{ route('orders.cancel', $order) }}" class="row" style="margin-top:8px;align-items:flex-end" novalidate>
                        @csrf
                        <div class="field" style="flex:1;margin:0"><label for="reason">{{ __('Reason') }}</label><input class="input" id="reason" name="reason" required></div>
                        <button class="btn btn-outline" type="submit">{{ __('Cancel order') }}</button>
                    </form></details>
            @endif
        </section>
        <aside class="card card-pad">
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Timeline') }}</h2>
            <ol style="list-style:none;padding:0;margin:0">
                @foreach ($order->events as $event)
                    <li class="small" style="padding:6px 0;border-bottom:1px solid var(--bh-border)">
                        <strong>{{ $event->kind === 'payment' ? __(\App\Enums\PaymentStatus::from($event->to_status)->label()) : __(OrderStatus::from($event->to_status)->label()) }}</strong>
                        <span class="muted">· {{ $event->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="hint" style="margin-top:12px">{{ __('Placed') }} {{ $order->placed_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ __($order->payment_method->label()) }}</p>
        </aside>
    </div>
@endsection
