@extends('layouts.admin', ['title' => 'Scheduled prices'])

@section('admin')
    <div class="admin-top"><h1>Scheduled price changes</h1></div>
    <form method="POST" action="{{ route('admin.scheduled-prices.store') }}" class="card card-pad" style="margin-bottom:16px" novalidate>
        @csrf
        <h2 style="font-size:var(--bh-fs-lg)">Schedule a change</h2>
        <div class="variant-grid">
            <div class="field"><label for="sku">SKU</label><input class="input" id="sku" name="sku" value="{{ old('sku') }}" required></div>
            <div class="field"><label for="field">Price</label>
                <select class="input" id="field" name="field">
                    <option value="price_paisa">Unit price</option><option value="per_kg_paisa">Per-kg price</option><option value="compare_at_paisa">“Was” price</option>
                </select></div>
            <div class="field"><label for="new_price">New price (৳)</label><input class="input tnum" id="new_price" name="new_price" value="{{ old('new_price') }}"></div>
            <div class="field"><label for="effective_at">Effective (Dhaka time)</label><input class="input" id="effective_at" name="effective_at" type="datetime-local" value="{{ old('effective_at') }}" required></div>
        </div>
        <div class="field"><label for="reason">Reason</label><input class="input" id="reason" name="reason" value="{{ old('reason') }}" required></div>
        <button class="btn btn-navy" type="submit">Schedule</button>
        <p class="hint">Applied automatically by the scheduler within a minute of the effective time, with an audit entry.</p>
    </form>
    <div class="card"><div class="table-wrap"><table class="table">
        <thead><tr><th>SKU</th><th>Price</th><th>New value</th><th>Effective (Dhaka)</th><th>Reason</th><th>By</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($changes as $change)
            <tr>
                <td><strong>{{ $change->variant?->sku }}</strong><div class="muted small">{{ $change->variant?->product?->name }}</div></td>
                <td class="small">{{ $change->field }}</td>
                <td class="tnum">{{ $change->new_value_paisa === null ? '—' : \App\Support\Taka::format($change->new_value_paisa) }}</td>
                <td class="small">{{ $change->effective_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td>
                <td class="small">{{ $change->reason }}</td>
                <td class="small">{{ $change->creator?->name }}</td>
                <td><span class="status {{ $change->status === 'applied' ? 'ok' : ($change->status === 'pending' ? 'info' : '') }}">{{ ucfirst($change->status) }}</span></td>
                <td>@if ($change->status === 'pending')
                        <form method="POST" action="{{ route('admin.scheduled-prices.destroy', $change) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm">Cancel</button></form>
                    @endif</td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><p><strong>Nothing scheduled</strong></p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>
@endsection
