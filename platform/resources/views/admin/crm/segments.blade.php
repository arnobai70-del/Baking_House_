@extends('layouts.admin', ['title' => 'Customers & segments'])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>Customers &amp; segments</h1><span class="spacer"></span>
        <a class="btn btn-teal btn-sm" href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}"><x-icon name="file" /> Export segment (CSV)</a></div>
    @include('admin.crm._nav')

    <form method="GET" class="card card-pad" style="margin-bottom:16px">
        <div class="form-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))">
            <div class="field"><label for="q">Name, email or phone</label><input class="input" id="q" name="q" value="{{ $filters['q'] }}"></div>
            <div class="field"><label for="type">Customer type</label>
                <select class="input" id="type" name="type"><option value="">Any</option>@foreach (['retail' => 'Retail', 'business' => 'Business', 'reseller' => 'Reseller'] as $value => $label)<option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="field"><label for="tag">Tag</label>
                <select class="input" id="tag" name="tag"><option value="">Any</option>@foreach ($tags as $tag)<option @selected($filters['tag'] === $tag)>{{ $tag }}</option>@endforeach</select></div>
            <div class="field"><label for="min_orders">Min. orders</label><input class="input" id="min_orders" name="min_orders" type="number" min="0" value="{{ $filters['min_orders'] }}"></div>
            @if ($canSeeMoney)<div class="field"><label for="min_spent">Min. spent ৳</label><input class="input" id="min_spent" name="min_spent" value="{{ request('min_spent') }}" inputmode="decimal"></div>@endif
            <div class="field"><label for="inactive_days">No order for (days)</label><input class="input" id="inactive_days" name="inactive_days" type="number" min="0" value="{{ $filters['inactive_days'] }}"></div>
            <div class="field"><label for="district">Delivered to district</label>
                <select class="input" id="district" name="district"><option value="">Any</option>@foreach (\App\Models\DeliveryZone::DISTRICTS as $district)<option @selected($filters['district'] === $district)>{{ $district }}</option>@endforeach</select></div>
        </div>
        <button class="btn btn-outline" type="submit">Apply filters</button>
        <a class="btn btn-ghost" href="{{ route('admin.crm.customers.index') }}">Reset</a>
    </form>

    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Customer</th><th>Type</th><th>Orders</th>@if ($canSeeMoney)<th>Spent</th>@endif<th>Last order</th><th>Tags</th></tr></thead>
        <tbody>
        @forelse ($customers as $customer)
            <tr>
                <td><a href="{{ route('admin.crm.customers.show', $customer) }}"><strong>{{ $customer->name }}</strong></a><div class="muted small">{{ $customer->email }} · {{ $customer->phone }}</div></td>
                <td class="small">{{ \App\Services\Crm\CrmService::customerType($customer) }}</td>
                <td class="tnum">{{ $customer->orders_count }}</td>
                @if ($canSeeMoney)<td class="tnum">{{ Taka::format((int) $customer->orders_sum_total_paisa) }}</td>@endif
                <td class="small">{{ $customer->orders_max_placed_at ? \Illuminate\Support\Carbon::parse($customer->orders_max_placed_at)->timezone('Asia/Dhaka')->format('d M Y') : '—' }}</td>
                <td class="small">{{ $customer->crmTags->pluck('name')->implode(', ') ?: '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No customers match these filters.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div style="margin-top:12px">{{ $customers->links() }}</div>
    <p class="hint">Customers are accounts without staff roles. Orders and spend count non-cancelled orders.@unless ($canSeeMoney) Spend figures need the financial reports permission (super admin or the Finance role).@endunless</p>
@endsection
