@extends('layouts.admin', ['title' => 'Price lists'])

@section('admin')
    <div class="admin-top">
        <h1>Price lists</h1><span class="spacer"></span>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.price-lists.create') }}"><x-icon name="plus" /> New price list</a>
    </div>
    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>Precedence: <strong>Contract</strong> → <strong>Business / Reseller</strong> → <strong>Campaign</strong> → <strong>Retail volume</strong> → standard price. Tiers apply on the total quantity (grams or units).</span></div>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Audience</th><th>Organization</th><th>Tiers</th><th>Window (Dhaka)</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($lists as $list)
            <tr>
                <td><strong>{{ $list->name }}</strong></td>
                <td><span class="status info">{{ $list->audience->label() }}</span></td>
                <td class="small">{{ $list->organization?->name ?? '—' }}</td>
                <td class="tnum">{{ $list->items_count }}</td>
                <td class="small">{{ $list->starts_at?->timezone('Asia/Dhaka')->format('d M Y H:i') ?? 'Now' }} → {{ $list->ends_at?->timezone('Asia/Dhaka')->format('d M Y H:i') ?? 'open' }}</td>
                <td><span class="status {{ $list->is_active ? 'ok' : 'warn' }}">{{ $list->is_active ? 'Active' : 'Off' }}</span></td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.price-lists.edit', $list) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><p><strong>No price lists yet</strong></p><p class="muted small">Everyone pays the standard product price.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endsection
