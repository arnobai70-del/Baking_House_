@extends('layouts.admin', ['title' => $customer->name])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>{{ $customer->name }}</h1><span class="status">{{ \App\Services\Crm\CrmService::customerType($customer) }}</span><span class="spacer"></span>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.crm.customers.index') }}">← Customers</a></div>
    @include('admin.crm._nav')

    <div class="kpis">
        <div class="card kpi"><div class="k-label">Orders</div><div class="k-val tnum">{{ $stats['active_orders'] }}</div><div class="muted small">{{ $stats['orders'] - $stats['active_orders'] }} cancelled</div></div>
        <div class="card kpi"><div class="k-label">Spent</div><div class="k-val tnum">{{ $stats['spent'] !== null ? Taka::format($stats['spent']) : '—' }}</div>@if ($stats['spent'] === null)<div class="muted small">Needs financial permission</div>@endif</div>
        <div class="card kpi"><div class="k-label">First order</div><div class="k-val" style="font-size:var(--bh-fs-lg)">{{ $stats['first'] ? \Illuminate\Support\Carbon::parse($stats['first'])->timezone('Asia/Dhaka')->format('d M Y') : '—' }}</div></div>
        <div class="card kpi"><div class="k-label">Last order</div><div class="k-val" style="font-size:var(--bh-fs-lg)">{{ $stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->timezone('Asia/Dhaka')->format('d M Y') : '—' }}</div></div>
    </div>

    <div class="admin-grid">
        <section>
            <div class="card card-pad" style="margin-bottom:16px">
                <table class="specs small"><tbody>
                    <tr><th>Email</th><td>{{ $customer->email ?? '—' }}</td></tr>
                    <tr><th>Mobile</th><td>{{ $customer->phone ?? '—' }}</td></tr>
                    <tr><th>Account</th><td>{{ $customer->is_active ? 'Active' : 'Deactivated' }} · joined {{ $customer->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</td></tr>
                    <tr><th>Organizations</th><td>@forelse ($customer->organizations as $organization)<a href="{{ route('admin.crm.organizations.show', $organization) }}">{{ $organization->name }}</a> ({{ $organization->type->label() }}, {{ $organization->status->value }}, {{ $organization->pivot->role }})<br>@empty — @endforelse</td></tr>
                </tbody></table>
            </div>
            @include('admin.crm._orders')
        </section>
        <section>
            @include('admin.crm._records', ['type' => 'customer', 'id' => $customer->id, 'tags' => $customer->crmTags])
        </section>
    </div>
@endsection
