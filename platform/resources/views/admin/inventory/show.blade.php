@extends('layouts.admin', ['title' => 'Stock: '.$variant->sku])

@use('App\Support\Weight')

@php $fmt = fn ($q) => $variant->isWeight() ? Weight::format(abs($q)) : abs($q); $unit = $variant->isWeight() ? 'grams' : 'units'; @endphp

@section('admin')
    <div class="admin-top"><h1>{{ $variant->sku }} — {{ $variant->product?->name }}</h1></div>
    <div class="kpis">
        <div class="card kpi"><div class="k-label">On hand</div><div class="k-val tnum">{{ $fmt($variant->stock_quantity) }}</div></div>
        <div class="card kpi"><div class="k-label">Reserved</div><div class="k-val tnum">{{ $fmt($variant->reserved_quantity) }}</div></div>
        <div class="card kpi"><div class="k-label">Available</div><div class="k-val tnum">{{ $fmt($variant->availableQuantity()) }}</div></div>
        <div class="card kpi"><div class="k-label">Alert at</div><div class="k-val tnum">{{ $variant->low_stock_threshold ? $fmt($variant->low_stock_threshold) : '—' }}</div></div>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:16px">
        <form method="POST" action="{{ route('admin.inventory.movements.store', $variant) }}" class="card card-pad" novalidate>
            @csrf
            <h2 style="font-size:var(--bh-fs-md)">Record a stock movement</h2>
            <div class="form-grid">
                <div class="field"><label for="type">Type</label><select class="input" id="type" name="type">
                    @foreach ($types as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div>
                <div class="field"><label for="direction">Direction</label><select class="input" id="direction" name="direction"><option value="in">Add to stock</option><option value="out">Remove from stock</option></select></div>
            </div>
            <div class="field"><label for="quantity">Quantity ({{ $unit }})</label><input class="input tnum" id="quantity" name="quantity" type="number" min="1" required></div>
            <div class="field"><label for="m-reason">Reason / reference</label><input class="input" id="m-reason" name="reason" placeholder="e.g. PO-1042, damaged in transit" required></div>
            <button class="btn btn-primary" type="submit">Record</button>
        </form>
        <form method="POST" action="{{ route('admin.inventory.reconcile', $variant) }}" class="card card-pad" novalidate>
            @csrf
            <h2 style="font-size:var(--bh-fs-md)">Physical count (reconcile)</h2>
            <p class="muted small">Enter what is physically on the shelf. The difference is logged as a correction.</p>
            <div class="field"><label for="counted_quantity">Counted quantity ({{ $unit }})</label><input class="input tnum" id="counted_quantity" name="counted_quantity" type="number" min="0" required></div>
            <div class="field"><label for="c-reason">Reason</label><input class="input" id="c-reason" name="reason" placeholder="Monthly stock count" required></div>
            <button class="btn btn-navy" type="submit">Save count</button>
        </form>
    </div>

    @if ($reservations->isNotEmpty())
        <div class="card" style="margin-bottom:16px"><div class="panel-title"><h2>Active reservations</h2></div><div class="table-wrap"><table class="table">
            <thead><tr><th>Reference</th><th>Quantity</th><th>Expires (Dhaka)</th></tr></thead>
            <tbody>@foreach ($reservations as $reservation)
                <tr><td>{{ $reservation->reference }}</td><td class="tnum">{{ $fmt($reservation->quantity) }}</td><td class="small">{{ $reservation->expires_at->timezone('Asia/Dhaka')->format('d M H:i') }}</td></tr>
            @endforeach</tbody></table></div></div>
    @endif

    <div class="card"><div class="panel-title"><h2>Ledger</h2></div><div class="table-wrap"><table class="table">
        <thead><tr><th>When (Dhaka)</th><th>Type</th><th>Change</th><th>Balance</th><th>Reason</th><th>By</th></tr></thead>
        <tbody>
        @foreach ($movements as $movement)
            <tr>
                <td class="small tnum">{{ $movement->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                <td>{{ $movement->type->label() }}</td>
                <td class="tnum"><strong>{{ $movement->quantity >= 0 ? '+' : '−' }}{{ $fmt($movement->quantity) }}</strong></td>
                <td class="tnum">{{ $fmt($movement->balance_after) }}</td>
                <td class="small">{{ $movement->reason }}</td>
                <td class="small">{{ $movement->actor?->name ?? 'System' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>
    <div class="pager">{{ $movements->links('storefront.partials.pagination') }}</div>
@endsection
