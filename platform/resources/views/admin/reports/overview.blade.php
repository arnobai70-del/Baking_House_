@extends('layouts.admin', ['title' => 'Reports'])

@use('App\Support\Taka')

@section('admin')
    @include('admin.reports._header')

    @if ($financial)
        {{-- Financial statement: each figure is its own labelled tile; the arithmetic is spelled out. --}}
        <h2 class="report-h">Sales &amp; revenue</h2>
        <div class="kpis report-kpis">
            <div class="card kpi"><div class="k-label">Gross sales</div><div class="k-val tnum">{{ Taka::format($financial['gross']) }}</div><div class="muted small">{{ $financial['orders'] }} orders, line amounts</div></div>
            <div class="card kpi"><div class="k-label">− Discounts</div><div class="k-val tnum">{{ Taka::format($financial['discounts']) }}</div><div class="muted small">coupons &amp; promotions</div></div>
            <div class="card kpi"><div class="k-label">= Net sales</div><div class="k-val tnum">{{ Taka::format($financial['net_sales']) }}</div></div>
            <div class="card kpi"><div class="k-label">+ Delivery charges</div><div class="k-val tnum">{{ Taka::format($financial['delivery']) }}</div></div>
            <div class="card kpi"><div class="k-label">= Billed</div><div class="k-val tnum">{{ Taka::format($financial['billed']) }}</div><div class="muted small">avg {{ Taka::format($financial['average_order']) }} / order</div></div>
            <div class="card kpi"><div class="k-label">− Refunds</div><div class="k-val tnum">{{ Taka::format($financial['refunds']) }}</div><div class="muted small">recorded in period</div></div>
            <div class="card kpi kpi-hero"><div class="k-label">= Net revenue</div><div class="k-val tnum">{{ Taka::format($financial['net_revenue']) }}</div></div>
        </div>
        <div class="kpis">
            <div class="card kpi"><div class="k-label">Paid</div><div class="k-val tnum">{{ Taka::format($financial['paid']) }}</div></div>
            <div class="card kpi"><div class="k-label">Unpaid / pending</div><div class="k-val tnum">{{ Taka::format($financial['unpaid']) }}</div></div>
            <div class="card kpi"><div class="k-label">Cancelled (not sales)</div><div class="k-val tnum">{{ Taka::format($financial['cancelled_amount']) }}</div><div class="muted small">{{ $financial['cancelled_orders'] }} orders</div></div>
            <div class="card kpi"><div class="k-label">Refunded</div><div class="k-val tnum">{{ Taka::format($financial['refunds']) }}</div></div>
        </div>
        <p class="hint" style="margin-bottom:16px">Orders count by the date they were placed; cancelled orders are excluded from sales. VAT is not included or reported (VAT invoicing is disabled until legal details are supplied).</p>
    @endif

    @php
        $values = array_column($daily, $money ? 'billed' : 'orders');
        $max = max(1, max($values ?: [0]));
        $count = max(1, count($daily));
        $width = 900; $height = 220; $pad = 4;
        $slot = $width / $count; $bar = max(1, $slot - 2);
    @endphp
    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>{{ $money ? 'Billed per day' : 'Orders per day' }}</h2><span class="muted small">peak {{ $money ? Taka::format($max) : $max }}</span></div>
        <div class="card-pad chart-wrap">
            <svg class="bar-chart" viewBox="0 0 {{ $width }} {{ $height + 24 }}" role="img" aria-label="{{ $money ? 'Billed amount' : 'Orders' }} per day, {{ $from->format('d M') }} to {{ $to->format('d M Y') }}. Table below.">
                @foreach ([0, 0.5, 1] as $tick)
                    <line class="grid" x1="0" x2="{{ $width }}" y1="{{ $pad + ($height - $pad) * (1 - $tick) }}" y2="{{ $pad + ($height - $pad) * (1 - $tick) }}"></line>
                @endforeach
                @foreach ($daily as $index => $day)
                    @php $value = $money ? $day['billed'] : $day['orders']; $h = $value > 0 ? max(2, ($height - $pad) * $value / $max) : 0; $x = $index * $slot + 1; @endphp
                    <g class="bar" tabindex="0" data-tip="{{ \Carbon\CarbonImmutable::parse($day['date'])->format('D d M') }} · {{ $day['orders'] }} order(s){{ $money ? ' · '.Taka::format($day['billed']) : '' }}">
                        <rect class="hit" x="{{ $index * $slot }}" y="0" width="{{ $slot }}" height="{{ $height }}"></rect>
                        @if ($h > 0)<path class="mark" d="M{{ $x }},{{ $height }} V{{ $height - $h + min(4, $h) }} q0,-{{ min(4, $h) }} {{ min(4, $bar / 2) }},-{{ min(4, $h) }} H{{ $x + $bar - min(4, $bar / 2) }} q{{ min(4, $bar / 2) }},0 {{ min(4, $bar / 2) }},{{ min(4, $h) }} V{{ $height }} Z"></path>@endif
                    </g>
                @endforeach
                <line class="axis" x1="0" x2="{{ $width }}" y1="{{ $height }}" y2="{{ $height }}"></line>
                <text class="tick" x="0" y="{{ $height + 18 }}">{{ \Carbon\CarbonImmutable::parse($daily[0]['date'])->format('d M') }}</text>
                <text class="tick" x="{{ $width }}" y="{{ $height + 18 }}" text-anchor="end">{{ \Carbon\CarbonImmutable::parse(end($daily)['date'])->format('d M') }}</text>
            </svg>
            <div class="chart-tip" role="status" aria-live="polite" hidden></div>
        </div>
        <details class="card-pad" style="padding-top:0"><summary class="small" style="cursor:pointer">Show as table</summary>
            <div class="table-wrap"><table class="table"><thead><tr><th>Date</th><th>Orders</th>@if ($money)<th>Billed</th>@endif</tr></thead><tbody>
                @foreach ($daily as $day)<tr><td>{{ $day['date'] }}</td><td class="tnum">{{ $day['orders'] }}</td>@if ($money)<td class="tnum">{{ Taka::format($day['billed']) }}</td>@endif</tr>@endforeach
            </tbody></table></div></details>
    </div>

    <div class="admin-grid">
        <div class="card">
            <div class="panel-title"><h2>Orders by status</h2></div>
            @php $maxStatus = max(1, max(array_column($statuses, 'orders'))); @endphp
            <div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th>Orders</th><th style="width:35%"><span class="sr-only">Relative</span></th>@if ($money)<th>Total</th>@endif</tr></thead><tbody>
                @foreach ($statuses as $row)
                    <tr><td><span class="status {{ $row['status']->tone() }}">{{ $row['status']->label() }}</span></td><td class="tnum">{{ $row['orders'] }}</td>
                        <td><span class="hbar" style="--w: {{ round($row['orders'] * 100 / $maxStatus, 1) }}%"></span></td>@if ($money)<td class="tnum">{{ Taka::format($row['total']) }}</td>@endif</tr>
                @endforeach
            </tbody></table></div>
        </div>
        <div>
            <div class="card" style="margin-bottom:16px">
                <div class="panel-title"><h2>Retail, business &amp; reseller</h2></div>
                <div class="table-wrap"><table class="table"><thead><tr><th>Customer type</th><th>Orders</th>@if ($money)<th>Gross</th><th>Discounts</th><th>Billed</th>@endif</tr></thead><tbody>
                    @foreach ($audiences as $row)
                        <tr><td>{{ ucfirst($row['audience']) }}</td><td class="tnum">{{ $row['orders'] }}</td>@if ($money)<td class="tnum">{{ Taka::format($row['gross']) }}</td><td class="tnum">{{ Taka::format($row['discounts']) }}</td><td class="tnum">{{ Taka::format($row['billed']) }}</td>@endif</tr>
                    @endforeach
                </tbody></table></div>
            </div>
            <div class="card">
                <div class="panel-title"><h2>Payment status (non-cancelled)</h2></div>
                <div class="table-wrap"><table class="table"><thead><tr><th>Payment</th><th>Orders</th>@if ($money)<th>Total</th>@endif</tr></thead><tbody>
                    @foreach ($payments as $row)
                        <tr><td>{{ $row['status']->label() }}</td><td class="tnum">{{ $row['orders'] }}</td>@if ($money)<td class="tnum">{{ Taka::format($row['total']) }}</td>@endif</tr>
                    @endforeach
                </tbody></table></div>
            </div>
        </div>
    </div>

    <script src="{{ \App\Support\Asset::url('js/admin-charts.js') }}" defer></script>
@endsection
