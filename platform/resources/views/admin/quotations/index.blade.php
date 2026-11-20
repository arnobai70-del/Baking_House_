@extends('layouts.admin', ['title' => 'Quotations'])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>Quotations (RFQ)</h1></div>
    <div class="row" style="margin-bottom:12px;gap:6px">
        <a class="fchip" href="{{ route('admin.quotations.index') }}">All</a>
        @foreach (\App\Enums\QuotationStatus::cases() as $case)
            <a class="fchip" href="{{ route('admin.quotations.index', ['status' => $case->value]) }}" @if ($status === $case) style="background:var(--bh-navy);color:#fff" @endif>{{ $case->label() }} ({{ $counts[$case->value] ?? 0 }})</a>
        @endforeach
    </div>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>Ref</th><th>Received (Dhaka)</th><th>From</th><th>Request</th><th>Quote</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($quotations as $quotation)
            <tr>
                <td><strong class="tnum">{{ $quotation->number }}</strong></td>
                <td class="small">{{ $quotation->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                <td>{{ $quotation->organization?->name ?? 'Lead (no account)' }}<div class="muted small">{{ $quotation->contact_name }} · {{ $quotation->contact_phone }}</div></td>
                <td class="small">{{ \Illuminate\Support\Str::limit($quotation->request_text, 70) }}</td>
                <td class="tnum">{{ $quotation->total_paisa !== null ? Taka::format($quotation->total_paisa) : '—' }}</td>
                <td><span class="status {{ $quotation->status->tone() }}">{{ $quotation->status->label() }}</span></td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.quotations.show', $quotation) }}">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No quotations.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div style="margin-top:12px">{{ $quotations->links() }}</div>
@endsection
