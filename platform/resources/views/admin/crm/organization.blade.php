@extends('layouts.admin', ['title' => $organization->name])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>{{ $organization->name }}</h1>
        <span class="status {{ $organization->isApproved() ? 'ok' : 'warn' }}">{{ $organization->type->label() }} · {{ ucfirst($organization->status->value) }}</span><span class="spacer"></span>
        @can('customers.manage')<a class="btn btn-outline btn-sm" href="{{ route('admin.organizations.show', $organization) }}">Account settings</a>@endcan</div>
    @include('admin.crm._nav')

    <div class="kpis">
        <div class="card kpi"><div class="k-label">Orders</div><div class="k-val tnum">{{ $stats['orders'] }}</div></div>
        <div class="card kpi"><div class="k-label">Spent</div><div class="k-val tnum">{{ $stats['spent'] !== null ? Taka::format($stats['spent']) : '—' }}</div>@if ($stats['spent'] === null)<div class="muted small">Needs financial permission</div>@endif</div>
        <div class="card kpi"><div class="k-label">Open quotations</div><div class="k-val tnum">{{ $stats['open_quotations'] }}</div></div>
        <div class="card kpi"><div class="k-label">Last order</div><div class="k-val" style="font-size:var(--bh-fs-lg)">{{ $stats['last'] ? \Illuminate\Support\Carbon::parse($stats['last'])->timezone('Asia/Dhaka')->format('d M Y') : '—' }}</div></div>
    </div>

    <div class="admin-grid">
        <section>
            <div class="card card-pad" style="margin-bottom:16px">
                <table class="specs small"><tbody>
                    <tr><th>Category</th><td>{{ $organization->business_category ?? '—' }}</td></tr>
                    <tr><th>Contact</th><td>{{ $organization->contact_phone ?? '—' }}</td></tr>
                    <tr><th>Team</th><td>@foreach ($organization->members as $member)<a href="{{ route('admin.crm.customers.show', $member) }}">{{ $member->name }}</a> ({{ $member->pivot->role }})<br>@endforeach</td></tr>
                </tbody></table>
                <p class="hint">Negotiated price lists are not shown in the CRM; they are managed under Pricing.</p>
            </div>
            @include('admin.crm._orders')
        </section>
        <section>
            @include('admin.crm._records', ['type' => 'organization', 'id' => $organization->id, 'tags' => $organization->crmTags])
        </section>
    </div>
@endsection
