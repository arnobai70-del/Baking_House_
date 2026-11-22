@extends('layouts.admin', ['title' => 'Products'])

@use('App\Support\Weight')

@section('admin')
    <div class="admin-top">
        <h1>Products &amp; prices</h1><span class="spacer"></span>
        <a class="btn btn-navy btn-sm" href="{{ route('admin.products.create') }}"><x-icon name="plus" /> New product</a>
    </div>

    <form method="GET" class="card card-pad row" style="margin-bottom:16px;align-items:flex-end">
        <div class="field" style="flex:1;margin:0"><label for="q">Search name or SKU</label><input class="input" id="q" name="q" value="{{ $search }}"></div>
        <div class="field" style="margin:0"><label for="status">Status</label>
            <select class="input" id="status" name="status">
                <option value="">Any</option>
                @foreach (\App\Enums\ProductStatus::cases() as $case)
                    <option value="{{ $case->value }}" @selected($status === $case)>{{ ucfirst($case->value) }}</option>
                @endforeach
            </select></div>
        <button class="btn btn-outline" type="submit">Filter</button>
    </form>

    <div class="card">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Product</th><th>Category</th><th>Variants / price</th><th>Stock</th><th>Status</th><th>Photos</th><th><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                @forelse ($products as $product)
                    <tr>
                        <td><strong>{{ $product->name }}</strong><div class="muted small">{{ $product->variants->pluck('sku')->join(', ') }}</div></td>
                        <td class="small">{{ $product->primaryCategory?->name }}</td>
                        <td class="small tnum">
                            @foreach ($product->variants as $variant)
                                <div>{{ $variant->displayPrice() }} @if ($variant->listPricePaisa())<span class="muted">{{ $variant->unitLabel() }}</span>@endif</div>
                            @endforeach
                        </td>
                        <td class="small tnum">
                            @foreach ($product->variants as $variant)
                                <div>{{ $variant->isWeight() ? Weight::format($variant->stock_quantity) : $variant->stock_quantity }}
                                    @if (! $variant->isInStock())<span class="status warn">Out</span>@elseif ($variant->isLowStock())<span class="status warn">Low</span>@endif</div>
                            @endforeach
                        </td>
                        <td><span class="status {{ $product->status === \App\Enums\ProductStatus::Active ? 'ok' : '' }}">{{ ucfirst($product->status->value) }}</span></td>
                        <td><span class="status warn">Pending</span></td>
                        <td class="row" style="gap:4px;flex-wrap:nowrap"><a class="btn btn-outline btn-sm" href="{{ route('admin.products.edit', $product) }}">Edit</a>
                            @can('media.manage')<a class="btn btn-ghost btn-sm" href="{{ route('admin.media.product', $product) }}">Photos</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><div class="empty-state"><p><strong>No products found</strong></p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="pager">{{ $products->links('storefront.partials.pagination') }}</div>
@endsection
