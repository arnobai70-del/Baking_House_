@extends('layouts.admin', ['title' => 'Ledger integrity'])

@section('admin')
    <div class="admin-top"><h1>Ledger integrity check</h1></div>
    @if ($problems === [])
        <div class="alert alert-success"><x-icon name="check" /><span>Every variant reconciles: on-hand stock equals the sum of ledger movements, and reserved stock equals active reservations.</span></div>
    @else
        <div class="alert alert-error" style="margin-bottom:16px"><x-icon name="alert" /><span>{{ count($problems) }} variant(s) do not reconcile. Investigate before trading; do not edit stock directly.</span></div>
        <div class="card"><div class="table-wrap"><table class="table">
            <thead><tr><th>SKU</th><th>On hand</th><th>Ledger total</th><th>Reserved</th><th>Active reservations</th></tr></thead>
            <tbody>@foreach ($problems as $problem)
                <tr><td><strong>{{ $problem['sku'] }}</strong></td><td class="tnum">{{ $problem['stock_quantity'] }}</td><td class="tnum">{{ $problem['ledger_total'] }}</td><td class="tnum">{{ $problem['reserved_quantity'] }}</td><td class="tnum">{{ $problem['active_reservations'] }}</td></tr>
            @endforeach</tbody></table></div></div>
    @endif
@endsection
