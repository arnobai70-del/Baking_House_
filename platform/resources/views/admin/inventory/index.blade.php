@extends('layouts.admin', ['title' => 'Inventory'])

@use('App\Support\Weight')

@section('admin')
    <div class="admin-top">
        <h1>Stock &amp; ledger</h1><span class="spacer"></span>
        <a class="btn btn-outline btn-sm" href="{{ route('admin.inventory.verify') }}">Check ledger integrity</a>
    </div>
    @if ($lowCount > 0)
        <div class="alert alert-error" style="margin-bottom:16px"><x-icon name="alert" /><span><strong>{{ $lowCount }}</strong> variant(s) at or below their low-stock alert level. <a href="{{ route('admin.inventory.index', ['low' => 1]) }}">Show them</a></span></div>
    @endif
    <form method="GET" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end">
        <div class="field" style="flex:1;margin:0"><label for="q">Search SKU or product</label><input class="input" id="q" name="q" value="{{ $search }}"></div>
        <label class="check"><input type="checkbox" name="low" value="1" @checked($lowOnly)> Low stock only</label>
        <button class="btn btn-outline" type="submit">Filter</button>
    </form>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>SKU</th><th>Product</th><th>On hand</th><th>Reserved</th><th>Available</th><th>Alert at</th><th></th></tr></thead>
        <tbody>
        @forelse ($variants as $variant)
            @php $fmt = fn ($q) => $variant->isWeight() ? Weight::format($q) : $q; @endphp
            <tr>
                <td><strong>{{ $variant->sku }}</strong></td>
                <td class="small">{{ $variant->product?->name }} · {{ $variant->name }}</td>
                <td class="tnum">{{ $fmt($variant->stock_quantity) }}</td>
                <td class="tnum">{{ $fmt($variant->reserved_quantity) }}</td>
                <td class="tnum"><strong>{{ $fmt($variant->availableQuantity()) }}</strong>
                    @if (! $variant->isInStock())<span class="status warn">Out</span>@elseif ($variant->isLowStock())<span class="status warn">Low</span>@endif</td>
                <td class="tnum small">{{ $variant->low_stock_threshold ? $fmt($variant->low_stock_threshold) : '—' }}</td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.inventory.show', $variant) }}">Manage</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><p><strong>No variants found</strong></p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="pager">{{ $variants->links('storefront.partials.pagination') }}</div>
@endsection
