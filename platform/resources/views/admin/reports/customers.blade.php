@extends('layouts.admin', ['title' => 'Reports · customers'])

@section('admin')
    @include('admin.reports._header')
    <div class="kpis">
        <div class="card kpi"><div class="k-label">New customer accounts</div><div class="k-val tnum">{{ $customers['new_accounts'] }}</div></div>
        <div class="card kpi"><div class="k-label">Customers who ordered</div><div class="k-val tnum">{{ $customers['buyers'] }}</div><div class="muted small">signed-in buyers</div></div>
        <div class="card kpi"><div class="k-label">First-time buyers</div><div class="k-val tnum">{{ $customers['first_time'] }}</div></div>
        <div class="card kpi"><div class="k-label">Repeat buyers</div><div class="k-val tnum">{{ $customers['repeat'] }}</div><div class="muted small">{{ $customers['repeat_rate'] !== null ? $customers['repeat_rate'].'% of buyers' : '—' }}</div></div>
    </div>
    <div class="card card-pad">
        <p class="small" style="margin:0"><strong>{{ $customers['guest_orders'] }}</strong> guest order(s) in the period are not linked to an account and are not counted as customers.</p>
        <p class="hint">A repeat buyer has two or more non-cancelled orders in total (one of them in this period). Accounts with staff roles are excluded.
            @can('crm.access')<a href="{{ route('admin.crm.customers.index', ['min_orders' => 2]) }}">See repeat customers in the CRM →</a>@endcan</p>
    </div>
@endsection
