@extends('layouts.admin', ['title' => 'Promotions'])

@section('admin')
    <div class="admin-top">
        <h1>Coupons &amp; promotions</h1><span class="spacer"></span>
        <a class="btn btn-navy btn-sm" href="{{ route('admin.promotions.create') }}"><x-icon name="plus" /> New promotion</a>
    </div>
    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>One non-stackable promotion per order (the best one for the customer). Trade-priced and bundle lines are excluded unless a super admin marks a promotion <strong>stackable</strong>.</span></div>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Name</th><th>Code</th><th>Discount</th><th>For</th><th>Used</th><th>Window (Dhaka)</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($promotions as $promotion)
            <tr>
                <td><strong>{{ $promotion->name }}</strong>@if ($promotion->stackable) <span class="status warn">Stackable</span>@endif</td>
                <td><code>{{ $promotion->code ?? 'automatic' }}</code></td>
                <td>{{ $promotion->describe() }}</td>
                <td class="small">{{ implode(', ', $promotion->audiences ?? []) }}</td>
                <td class="tnum">{{ $promotion->used_count }}{{ $promotion->usage_limit ? ' / '.$promotion->usage_limit : '' }}</td>
                <td class="small">{{ $promotion->starts_at?->timezone('Asia/Dhaka')->format('d M Y') ?? 'Now' }} → {{ $promotion->ends_at?->timezone('Asia/Dhaka')->format('d M Y') ?? 'open' }}</td>
                <td><span class="status {{ $promotion->is_active ? 'ok' : '' }}">{{ $promotion->is_active ? 'Active' : 'Off' }}</span></td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.promotions.edit', $promotion) }}">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><p><strong>No promotions yet</strong></p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endsection
