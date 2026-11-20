@extends('layouts.admin', ['title' => 'Reports · inventory'])

@use('App\Services\Pricing\PriceResolver')

@section('admin')
    @include('admin.reports._header')
    <div class="kpis">
        <div class="card kpi"><div class="k-label">Active sellable SKUs</div><div class="k-val tnum">{{ $inventory['active'] }}</div></div>
        <div class="card kpi"><div class="k-label">Out of stock</div><div class="k-val tnum">{{ count($inventory['out']) }}</div></div>
        <div class="card kpi"><div class="k-label">Low stock</div><div class="k-val tnum">{{ count($inventory['low']) }}</div></div>
        <div class="card kpi"><div class="k-label">As of</div><div class="k-val" style="font-size:var(--bh-fs-lg)">{{ now('Asia/Dhaka')->format('d M H:i') }}</div></div>
    </div>
    @foreach (['out' => 'Out of stock', 'low' => 'Low stock (at or below threshold)'] as $key => $title)
        <div class="card" style="margin-bottom:16px">
            <div class="panel-title"><h2>{{ $title }}</h2></div>
            @if ($inventory[$key] === [])
                <div class="empty-state" style="padding:24px"><p class="muted small">None.</p></div>
            @else
                <div class="table-wrap"><table class="table"><thead><tr><th>Product</th><th>Available</th><th>Reserved</th><th>Threshold</th><th></th></tr></thead><tbody>
                    @foreach ($inventory[$key] as $variant)
                        <tr><td>{{ $variant->product->name }}<div class="muted small">{{ $variant->sku }}</div></td>
                            <td class="tnum">{{ PriceResolver::quantityLabel($variant, $variant->availableQuantity()) }}</td>
                            <td class="tnum">{{ $variant->reserved_quantity }}</td>
                            <td class="tnum">{{ $variant->low_stock_threshold ?: '—' }}</td>
                            <td>@can('inventory.manage')<a class="btn btn-ghost btn-sm" href="{{ route('admin.inventory.show', $variant) }}">Stock ledger</a>@endcan</td></tr>
                    @endforeach
                </tbody></table></div>
            @endif
        </div>
    @endforeach
@endsection
