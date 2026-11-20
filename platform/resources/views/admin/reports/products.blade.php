@extends('layouts.admin', ['title' => 'Reports · products'])

@use('App\Support\Taka')

@section('admin')
    @include('admin.reports._header')
    @php $maxProduct = max(1, max(array_column($products, $money ? 'revenue' : 'orders') ?: [0])); $maxCategory = max(1, max(array_column($categories, $money ? 'revenue' : 'orders') ?: [0])); @endphp
    <div class="admin-grid">
        <div class="card">
            <div class="panel-title"><h2>Top-selling products</h2></div>
            @if ($products === [])
                <div class="empty-state" style="padding:24px"><p class="muted small">No sales in this period.</p></div>
            @else
                <div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Product</th><th>Sold</th><th>Orders</th>@if ($money)<th>Revenue</th>@endif<th style="width:22%"><span class="sr-only">Relative</span></th></tr></thead><tbody>
                    @foreach ($products as $rank => $row)
                        <tr><td class="tnum">{{ $rank + 1 }}</td><td>{{ $row['name'] }}<div class="muted small">{{ $row['sku'] }}</div></td><td class="tnum">{{ $row['quantity_label'] }}</td><td class="tnum">{{ $row['orders'] }}</td>
                            @if ($money)<td class="tnum">{{ Taka::format($row['revenue']) }}</td>@endif
                            <td><span class="hbar" style="--w: {{ round(($money ? $row['revenue'] : $row['orders']) * 100 / $maxProduct, 1) }}%"></span></td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </div>
        <div class="card">
            <div class="panel-title"><h2>Categories</h2></div>
            @if ($categories === [])
                <div class="empty-state" style="padding:24px"><p class="muted small">No sales in this period.</p></div>
            @else
                <div class="table-wrap"><table class="table"><thead><tr><th>Category</th><th>Orders</th>@if ($money)<th>Revenue</th>@endif<th style="width:30%"><span class="sr-only">Relative</span></th></tr></thead><tbody>
                    @foreach ($categories as $row)
                        <tr><td>{{ $row['category'] }}</td><td class="tnum">{{ $row['orders'] }}</td>@if ($money)<td class="tnum">{{ Taka::format($row['revenue']) }}</td>@endif
                            <td><span class="hbar" style="--w: {{ round(($money ? $row['revenue'] : $row['orders']) * 100 / $maxCategory, 1) }}%"></span></td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </div>
    </div>
    <p class="hint">Revenue is line value before order-level discounts, from non-cancelled orders. Items inside accepted quotations count under their product; bundles are listed as bundles because their price is not split across items.</p>
@endsection
