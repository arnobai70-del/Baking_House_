@use('App\Support\Taka')
@use('App\Enums\LeadStage')
{{-- Pipeline by stage: counts are the headline, a thin single-hue bar shows relative size. --}}
@php $maxLeads = max(1, max(array_column($pipeline, 'count'))); @endphp
<div class="card" style="margin-bottom:16px">
    <div class="panel-title"><h2>Sales pipeline</h2><a class="btn btn-ghost btn-sm" href="{{ route('admin.crm.leads.index') }}">All leads</a></div>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Stage</th><th>Leads</th><th style="width:40%"><span class="sr-only">Relative size</span></th><th>Estimated value</th></tr></thead>
        <tbody>
        @foreach (LeadStage::cases() as $stage)
            @php $row = $pipeline[$stage->value]; @endphp
            <tr>
                <td><a href="{{ route('admin.crm.leads.index', ['stage' => $stage->value]) }}"><span class="status {{ $stage->tone() }}">{{ $stage->label() }}</span></a></td>
                <td class="tnum"><strong>{{ $row['count'] }}</strong></td>
                <td><span class="hbar" style="--w: {{ round($row['count'] * 100 / $maxLeads, 1) }}%" title="{{ $stage->label() }}: {{ $row['count'] }} lead(s)"></span></td>
                <td class="tnum">{{ $row['value'] ? Taka::format($row['value']) : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>
