@extends('layouts.admin', ['title' => 'Reports · RFQ'])

@use('App\Support\Taka')

@section('admin')
    @include('admin.reports._header')
    <h2 class="report-h">Quotation funnel (requests received in the period)</h2>
    @php $funnel = [['Requested', $rfq['requested']], ['Quote issued', $rfq['quoted']], ['Accepted', $rfq['accepted']], ['Ordered', $rfq['ordered']]]; $top = max(1, $rfq['requested']); @endphp
    <div class="card" style="margin-bottom:16px"><div class="table-wrap"><table class="table">
        <thead><tr><th>Step</th><th>Quotations</th><th style="width:45%"><span class="sr-only">Relative</span></th><th>Of requests</th></tr></thead>
        <tbody>
        @foreach ($funnel as [$label, $value])
            <tr><td>{{ $label }}</td><td class="tnum"><strong>{{ $value }}</strong></td><td><span class="hbar" style="--w: {{ round($value * 100 / $top, 1) }}%"></span></td><td class="tnum">{{ $rfq['requested'] ? round($value * 100 / $rfq['requested']).'%' : '—' }}</td></tr>
        @endforeach
        <tr><td>Lost (declined / closed / expired)</td><td class="tnum">{{ $rfq['lost'] }}</td><td></td><td class="tnum">{{ $rfq['requested'] ? round($rfq['lost'] * 100 / $rfq['requested']).'%' : '—' }}</td></tr>
        </tbody>
    </table></div></div>
    <div class="kpis">
        <div class="card kpi"><div class="k-label">RFQ → order conversion</div><div class="k-val tnum">{{ $rfq['conversion'] !== null ? $rfq['conversion'].'%' : '—' }}</div></div>
        @if ($money)
            <div class="card kpi"><div class="k-label">Value quoted</div><div class="k-val tnum">{{ Taka::format($rfq['quoted_value']) }}</div></div>
            <div class="card kpi"><div class="k-label">Value ordered</div><div class="k-val tnum">{{ Taka::format($rfq['ordered_value']) }}</div></div>
        @endif
        <div class="card kpi"><div class="k-label">In shared queue now</div><div class="k-val tnum">{{ $rfq['queue'] }}</div><div class="muted small">open, unassigned leads</div></div>
    </div>

    <h2 class="report-h">CRM pipeline (current, all leads)</h2>
    @php $maxStage = max(1, max(array_column($rfq['pipeline'], 'leads'))); @endphp
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Stage</th><th>Leads</th><th style="width:40%"><span class="sr-only">Relative</span></th>@if ($money)<th>Estimated value</th>@endif</tr></thead>
        <tbody>
        @foreach ($rfq['pipeline'] as $row)
            <tr><td><span class="status {{ $row['stage']->tone() }}">{{ $row['stage']->label() }}</span></td><td class="tnum">{{ $row['leads'] }}</td>
                <td><span class="hbar" style="--w: {{ round($row['leads'] * 100 / $maxStage, 1) }}%"></span></td>@if ($money)<td class="tnum">{{ Taka::format($row['value']) }}</td>@endif</tr>
        @endforeach
        </tbody>
    </table></div></div>
@endsection
