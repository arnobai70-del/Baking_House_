@extends('layouts.admin', ['title' => 'Delivery zones'])

@section('admin')
    <div class="admin-top"><h1>Delivery zones</h1><span class="spacer"></span><a class="btn btn-primary btn-sm" href="{{ route('admin.zones.create') }}"><x-icon name="plus" /> New zone</a></div>
    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>Charges are placeholders until courier rates are agreed. Each district belongs to at most one zone; the default zone covers all others.</span></div>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Zone</th><th>Districts</th><th>Charge</th><th>Free over</th><th>COD</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($zones as $zone)
            <tr>
                <td><strong>{{ $zone->name }}</strong>@if ($zone->is_default) <span class="status info">default</span>@endif</td>
                <td class="small">{{ $zone->districts ? implode(', ', $zone->districts) : '—' }}</td>
                <td class="tnum">{{ \App\Support\Taka::format($zone->charge_paisa) }}</td>
                <td class="tnum">{{ $zone->free_over_paisa ? \App\Support\Taka::format($zone->free_over_paisa) : '—' }}</td>
                <td>{{ $zone->cod_available ? 'Yes' : 'No' }}</td>
                <td><span class="status {{ $zone->is_active ? 'ok' : '' }}">{{ $zone->is_active ? 'Active' : 'Off' }}</span></td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.zones.edit', $zone) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><p><strong>No delivery zones</strong></p><p class="muted small">Checkout needs at least a default zone.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endsection
