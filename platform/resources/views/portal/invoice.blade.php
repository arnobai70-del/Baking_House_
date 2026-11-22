@use('App\Support\Taka')
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Invoice') }} {{ $order->number }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('layouts._fonts')
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/storefront.css') }}">
</head>
<body class="invoice-page">
<main class="invoice card card-pad">
    <div class="row no-print" style="justify-content:space-between;margin-bottom:12px">
        <a class="btn btn-ghost btn-sm" href="{{ route($organization->type->value.'.statements') }}">← {{ __('Back') }}</a>
        <button class="btn btn-navy btn-sm" type="button" data-print>{{ __('Print / save as PDF') }}</button>
    </div>
    <header class="invoice-head">
        <div>
            <strong style="font-size:var(--bh-fs-xl);color:var(--bh-navy)">{{ $seller['name'] }}</strong>
            <div class="small">{{ $seller['address'] }}<br>{{ $seller['phone'] }}</div>
        </div>
        <div style="text-align:right">
            <h1 style="margin:0;font-size:var(--bh-fs-xl)">{{ __('Invoice') }}</h1>
            <div class="small tnum">{{ $order->number }}<br>{{ $order->placed_at->timezone('Asia/Dhaka')->format('d M Y') }}</div>
        </div>
    </header>

    <section class="invoice-parties">
        <div><div class="k-label small muted">{{ __('Bill to') }}</div><strong>{{ $organization->name }}</strong>
            @if ($organization->trade_licence_no)<div class="small">{{ __('Trade licence') }}: {{ $organization->trade_licence_no }}</div>@endif
            <div class="small">{{ $order->customer_name }} · {{ $order->customer_phone }}</div></div>
        <div><div class="k-label small muted">{{ __('Deliver to') }}</div>
            <div class="small">{{ $order->shipping_address['line'] }}, {{ $order->shipping_address['area'] }}, {{ $order->shipping_address['district'] }}</div></div>
        <div><div class="k-label small muted">{{ __('Payment') }}</div>
            <div class="small">{{ __($order->payment_method->label()) }} · {{ __($order->payment_status->label()) }}</div></div>
    </section>

    <table class="table">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Rate') }}</th><th style="text-align:right">{{ __('Amount') }}</th></tr></thead>
        <tbody>
        @foreach ($order->items as $item)
            <tr>
                <td>{{ $item->name }}@if ($item->sku)<div class="muted small">{{ $item->sku }}</div>@endif
                    @if ($item->components && $item->type === 'quotation')
                        <ul class="small muted" style="margin:4px 0 0;padding-left:16px">@foreach ($item->components as $component)<li>{{ $component['name'] }} — {{ $component['quantity_label'] }} — {{ Taka::format($component['line_paisa']) }}</li>@endforeach</ul>
                    @endif</td>
                <td class="tnum">{{ $item->quantity_label }}</td>
                <td class="tnum">{{ $item->per_kg_paisa ? Taka::format($item->per_kg_paisa).'/kg' : ($item->unit_paisa ? Taka::format($item->unit_paisa) : '—') }}</td>
                <td class="tnum" style="text-align:right">{{ Taka::format($item->line_paisa) }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="3">{{ __('Subtotal') }}</td><td class="tnum" style="text-align:right">{{ Taka::format($order->subtotal_paisa) }}</td></tr>
            @if ($order->discount_paisa)<tr><td colspan="3">{{ __('Discount') }}</td><td class="tnum" style="text-align:right">−{{ Taka::format($order->discount_paisa) }}</td></tr>@endif
            <tr><td colspan="3">{{ __('Delivery') }}</td><td class="tnum" style="text-align:right">{{ Taka::format($order->delivery_paisa) }}</td></tr>
            <tr><th colspan="3">{{ __('Total') }}</th><th class="tnum" style="text-align:right">{{ Taka::format($order->total_paisa) }}</th></tr>
            @if ($order->refunded_paisa)<tr><td colspan="3">{{ __('Refunded') }}</td><td class="tnum" style="text-align:right">{{ Taka::format($order->refunded_paisa) }}</td></tr>@endif
        </tfoot>
    </table>

    <p class="hint" style="margin-top:16px">
        @if ($seller['vat_registration'])
            {{ __('VAT registration (BIN)') }}: {{ $seller['vat_registration'] }}
        @else
            {{ __('This is an order invoice, not a VAT (Mushak) invoice. VAT registration details are pending.') }}
        @endif
        {{ __('Amounts in Bangladeshi taka; each line rounded half-up to the paisa.') }}
    </p>
</main>
<script src="{{ \App\Support\Asset::url('js/print.js') }}" defer></script>
</body>
</html>
