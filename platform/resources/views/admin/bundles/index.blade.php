@extends('layouts.admin', ['title' => 'Bundles'])

@section('admin')
    <div class="admin-top">
        <h1>Bundles &amp; combos</h1><span class="spacer"></span>
        <a class="btn btn-navy btn-sm" href="{{ route('admin.bundles.create') }}"><x-icon name="plus" /> New bundle</a>
    </div>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Discount</th><th>Items</th><th>For</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($bundles as $bundle)
            <tr>
                <td><strong>{{ $bundle->name }}</strong><div class="muted small">/combo/{{ $bundle->slug }}</div></td>
                <td>{{ $bundle->discount_type->describe($bundle->discount_value) }}</td>
                <td class="tnum">{{ $bundle->items_count }}</td>
                <td class="small">{{ implode(', ', $bundle->audiences ?? []) }}</td>
                <td><span class="status {{ $bundle->is_active ? 'ok' : '' }}">{{ $bundle->is_active ? 'Active' : 'Off' }}</span></td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.bundles.edit', $bundle) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty-state"><p><strong>No bundles yet</strong></p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endsection
