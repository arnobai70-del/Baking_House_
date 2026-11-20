@extends('layouts.admin', ['title' => 'Payment reconciliation'])

@section('admin')
    <div class="admin-top"><h1>Payment reconciliation</h1></div>
    @if ($issues === [])
        <div class="alert alert-success" style="margin-bottom:16px"><x-icon name="check" /><span>No mismatches between payments, orders and refunds (last 90 days).</span></div>
    @else
        <div class="card" style="margin-bottom:16px"><div class="table-wrap"><table class="table">
            <thead><tr><th>Order</th><th>Payment</th><th>Issue</th></tr></thead>
            <tbody>@foreach ($issues as $issue)
                <tr><td>@if ($issue['order'])<a href="{{ route('admin.orders.show', $issue['order']) }}">{{ $issue['order'] }}</a>@endif</td><td><code>{{ $issue['payment'] }}</code></td><td>{{ $issue['issue'] }}</td></tr>
            @endforeach</tbody></table></div></div>
    @endif
    <div class="card"><div class="panel-title"><h2>Latest provider callbacks</h2><span class="muted small">All payments are in TEST mode</span></div><div class="table-wrap"><table class="table">
        <thead><tr><th>Received</th><th>Provider</th><th>Event</th><th>Type</th><th>Signature</th><th>Result</th></tr></thead>
        <tbody>
        @forelse ($events as $event)
            <tr><td class="small">{{ $event->created_at->timezone('Asia/Dhaka')->format('d M H:i:s') }}</td><td>{{ $event->provider }}</td><td class="small"><code>{{ $event->event_id }}</code></td><td class="small">{{ $event->event_type }}</td>
                <td><span class="status {{ $event->signature_valid ? 'ok' : 'warn' }}">{{ $event->signature_valid ? 'valid' : 'rejected' }}</span></td><td class="small">{{ $event->result }}</td></tr>
        @empty
            <tr><td colspan="6"><div class="empty-state"><p><strong>No callbacks yet</strong></p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endsection
