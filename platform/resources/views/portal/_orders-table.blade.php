@use('App\Support\Taka')

@if ($orders->isEmpty())
    <div class="empty-state"><x-icon name="cart" /><p><strong>{{ __('No orders yet') }}</strong></p><p class="muted small">{{ __('Orders placed for your organization will appear here.') }}</p></div>
@else
    <div class="table-wrap"><table class="table">
        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Date') }}</th><th>{{ __('Placed by') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th><span class="sr-only">{{ __('Actions') }}</span></th></tr></thead>
        <tbody>
        @foreach ($orders as $order)
            <tr>
                <td class="tnum"><a href="{{ route('orders.show', $order) }}"><strong>{{ $order->number }}</strong></a></td>
                <td class="small">{{ $order->placed_at->timezone('Asia/Dhaka')->format('d M Y') }}</td>
                <td class="small">{{ $order->relationLoaded('user') ? ($order->user?->name ?? '—') : $order->customer_name }}</td>
                <td class="tnum">{{ Taka::format($order->total_paisa) }}</td>
                <td><span class="status {{ $order->status->tone() }}">{{ __($order->status->label()) }}</span>
                    <span class="status {{ $order->isPaid() ? 'ok' : 'warn' }}">{{ __($order->payment_status->label()) }}</span></td>
                <td class="row" style="gap:6px;flex-wrap:nowrap">
                    <a class="btn btn-ghost btn-sm" href="{{ route($portal.'.orders.invoice', $order) }}">{{ __('Invoice') }}</a>
                    @if ($role->canPlaceOrders())
                        <form method="POST" action="{{ route($portal.'.orders.reorder', $order) }}">@csrf<button class="btn btn-outline btn-sm" type="submit"><x-icon name="cart" /> {{ __('Reorder') }}</button></form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
@endif
