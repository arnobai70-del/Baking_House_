@extends('layouts.admin', ['title' => 'Admin'])

@section('admin')
    <div class="admin-top">
        <h1>Admin dashboard</h1>
        <span class="spacer"></span>
        @can('audit.view')
            <a class="btn btn-outline btn-sm" href="{{ route('admin.audit') }}">Audit log</a>
        @endcan
    </div>

    @if ($counts !== [])
        <div class="kpis ops-kpis">
            @foreach ($counts as $count)
                <a class="card kpi kpi-link" href="{{ $count['url'] }}">
                    <div class="k-label">{{ $count['label'] }}</div>
                    <div class="k-val tnum">{{ $count['value'] }}</div>
                    <div class="muted small">{{ $count['hint'] }}</div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="dash-row">
        @if ($orderTrend !== null)
            @php
                $max = max(1, max(array_column($orderTrend, 'count')));
                $total = array_sum(array_column($orderTrend, 'count'));
                $barW = 28; $gap = 12; $chartH = 140; $left = 28;
            @endphp
            {{-- Real order records, counts only (money figures live in Reports). One series: no legend; the title names it. --}}
            <section class="card chart-card" aria-labelledby="trend-title">
                <div class="panel-title"><h2 id="trend-title">Orders placed, last {{ count($orderTrend) }} days</h2><span class="muted small tnum">{{ $total }} in total</span></div>
                <svg class="trend" viewBox="0 0 {{ $left + count($orderTrend) * ($barW + $gap) }} {{ $chartH + 28 }}" role="img" aria-labelledby="trend-title">
                    @foreach ([0, 0.5, 1] as $tick)
                        @php $y = $chartH - $tick * $chartH; @endphp
                        <line class="grid" x1="{{ $left }}" x2="100%" y1="{{ $y }}" y2="{{ $y }}" />
                        <text class="axis" x="{{ $left - 6 }}" y="{{ $y + 4 }}" text-anchor="end">{{ (int) round($tick * $max) }}</text>
                    @endforeach
                    @foreach ($orderTrend as $i => $day)
                        @php $h = $day['count'] / $max * $chartH; $x = $left + $i * ($barW + $gap) + $gap / 2; @endphp
                        <g class="bar-hit">
                            <rect class="hit" x="{{ $x - $gap / 2 }}" y="0" width="{{ $barW + $gap }}" height="{{ $chartH }}" />
                            @if ($day['count'] > 0)
                                <path class="bar" d="M{{ $x }},{{ $chartH }} V{{ $chartH - $h + 4 }} q0,-4 4,-4 h{{ $barW - 8 }} q4,0 4,4 V{{ $chartH }} Z" />
                            @endif
                            <title>{{ $day['date']->format('D j M') }}: {{ $day['count'] }} {{ \Illuminate\Support\Str::plural('order', $day['count']) }}</title>
                        </g>
                        @if ($i % 2 === 0 || $loop->last)
                            <text class="axis" x="{{ $x + $barW / 2 }}" y="{{ $chartH + 18 }}" text-anchor="middle">{{ $day['date']->format('j M') }}</text>
                        @endif
                    @endforeach
                </svg>
                <table class="sr-only"><caption>Orders placed per day</caption><thead><tr><th>Day</th><th>Orders</th></tr></thead><tbody>
                    @foreach ($orderTrend as $day)<tr><td>{{ $day['date']->format('D j M Y') }}</td><td>{{ $day['count'] }}</td></tr>@endforeach
                </tbody></table>
            </section>
        @endif

        <section class="card shortcuts-card" aria-labelledby="shortcut-title">
            <div class="panel-title"><h2 id="shortcut-title">Quick actions</h2></div>
            <div class="shortcuts">
                @can('catalog.manage')<a href="{{ route('admin.products.create') }}"><x-icon name="plus" /> Add a product</a>@endcan
                @can('orders.manage')<a href="{{ route('admin.orders.index') }}"><x-icon name="cart" /> Orders</a>@endcan
                @can('media.approve')<a href="{{ route('admin.media.queue') }}"><x-icon name="photo" /> Photo review</a>@endcan
                @can('inventory.manage')<a href="{{ route('admin.inventory.index') }}"><x-icon name="box" /> Stock &amp; ledger</a>@endcan
                @can('quotations.manage')<a href="{{ route('admin.quotations.index') }}"><x-icon name="quote" /> Quotations</a>@endcan
                @can('crm.access')<a href="{{ route('admin.crm.dashboard') }}"><x-icon name="users" /> CRM</a>@endcan
                @can('reports.view')<a href="{{ route('admin.reports') }}"><x-icon name="chart" /> Reports</a>@endcan
            </div>
        </section>
    </div>

    <div class="card">
        <div class="panel-title"><h2>Pending business &amp; reseller applications ({{ $pendingOrganizations->count() }})</h2></div>
        @if ($pendingOrganizations->isEmpty())
            <div class="empty-state"><p><strong>Nothing to review</strong></p></div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Organization</th><th>Type</th><th>Applicant</th><th>Applied</th><th>Decision</th></tr></thead>
                    <tbody>
                    @foreach ($pendingOrganizations as $organization)
                        <tr>
                            <td><strong>{{ $organization->name }}</strong><div class="muted small">{{ $organization->business_category }}</div></td>
                            <td>{{ $organization->type->label() }}</td>
                            <td>{{ $organization->members->first()?->name }}<div class="muted small">{{ $organization->contact_phone }}</div></td>
                            <td class="small">{{ $organization->created_at->diffForHumans() }}</td>
                            <td>
                                @can('decide', $organization)
                                    <form method="POST" action="{{ route('admin.organizations.status', $organization) }}" class="row" style="flex-wrap:nowrap">
                                        @csrf
                                        @method('PATCH')
                                        <label class="sr-only" for="reason-{{ $organization->id }}">Reason</label>
                                        <input class="input" id="reason-{{ $organization->id }}" name="reason" placeholder="Reason (required)" required style="min-height:36px">
                                        <button class="btn btn-navy btn-sm" name="status" value="approved">Approve</button>
                                        <button class="btn btn-ghost btn-sm" name="status" value="rejected">Reject</button>
                                    </form>
                                @else
                                    <span class="muted small">No permission</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
