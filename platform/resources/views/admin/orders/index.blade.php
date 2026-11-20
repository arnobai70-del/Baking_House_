@extends('layouts.admin', ['title' => 'Orders'])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top">
        <h1>Orders</h1><span class="spacer"></span>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.orders.reconciliation') }}">Payment reconciliation</a>
    </div>
    <div class="row" style="margin-bottom:12px;gap:6px">
        <a class="fchip" href="{{ route('admin.orders.index') }}">All</a>
        @foreach (\App\Enums\OrderStatus::cases() as $case)
            <a class="fchip" href="{{ route('admin.orders.index', ['status' => $case->value]) }}" @if ($status === $case) style="background:var(--bh-navy);color:#fff" @endif>{{ $case->label() }} ({{ $counts[$case->value] ?? 0 }})</a>
        @endforeach
    </div>
    <form method="GET" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end">
        @if ($status)<input type="hidden" name="status" value="{{ $status->value }}">@endif
        <div class="field" style="flex:1;margin:0"><label for="q">Order number or phone</label><input class="input" id="q" name="q" value="{{ $search }}"></div>
        <div class="field" style="margin:0"><label for="payment">Payment</label>
            <select class="input" id="payment" name="payment"><option value="">Any</option>
                @foreach (\App\Enums\PaymentStatus::cases() as $case)<option value="{{ $case->value }}" @selected($paymentStatus === $case)>{{ $case->label() }}</option>@endforeach
            </select></div>
        <button class="btn btn-outline" type="submit">Filter</button>
    </form>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Order</th><th>Placed (Dhaka)</th><th>Customer</th><th>Type</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($orders as $order)
            <tr>
                <td><strong class="tnum">{{ $order->number }}</strong></td>
                <td class="small">{{ $order->placed_at->timezone('Asia/Dhaka')->format('d M H:i') }}</td>
                <td class="small">{{ $order->customer_name }}<div class="muted">{{ $order->customer_phone }}</div></td>
                <td class="small">{{ $order->audience->label() }}</td>
                <td class="tnum">{{ Taka::format($order->total_paisa) }}</td>
                <td class="small">{{ $order->payment_method->label() }}<div><span class="status {{ $order->isPaid() ? 'ok' : 'warn' }}">{{ $order->payment_status->label() }}</span></div></td>
                <td><span class="status {{ $order->status->tone() }}">{{ $order->status->label() }}</span></td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.orders.show', $order) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><p><strong>No orders</strong></p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="pager">{{ $orders->links('storefront.partials.pagination') }}</div>
@endsection
