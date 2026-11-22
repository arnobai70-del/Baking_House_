@extends('layouts.admin', ['title' => 'Quotation '.$quotation->number])

@use('App\Support\Taka')
@use('App\Enums\QuotationStatus')

@section('admin')
    <div class="admin-top">
        <h1>Quotation {{ $quotation->number }}</h1>
        <span class="status {{ $quotation->status->tone() }}">{{ $quotation->status->label() }}</span>
        <span class="spacer"></span>
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.quotations.index') }}">← All quotations</a>
    </div>

    <div class="admin-grid">
        <section class="card card-pad">
            <h2 style="font-size:var(--bh-fs-lg)">Request</h2>
            <p style="white-space:pre-line">{{ $quotation->request_text }}</p>
            @if ($quotation->customer_note)<p class="muted small">Note: {{ $quotation->customer_note }}</p>@endif
            <table class="specs small"><tbody>
                <tr><th>Account</th><td>
                    @if ($quotation->organization)
                        @can('customers.manage')<a href="{{ route('admin.organizations.show', $quotation->organization) }}">{{ $quotation->organization->name }}</a>@else{{ $quotation->organization->name }}@endcan
                        ({{ $quotation->organization->type->label() }}, {{ $quotation->organization->status->value }})
                    @else
                        Lead without account — follow up by phone, then close.
                    @endif</td></tr>
                <tr><th>Contact</th><td>{{ $quotation->contact_name }} · {{ $quotation->contact_phone }}{{ $quotation->contact_email ? ' · '.$quotation->contact_email : '' }}</td></tr>
                <tr><th>Received</th><td>{{ $quotation->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td></tr>
                @if ($quotation->order)<tr><th>Order</th><td><a href="{{ route('admin.orders.show', $quotation->order) }}">{{ $quotation->order->number }}</a></td></tr>@endif
            </tbody></table>

            @if ($quotation->items->isNotEmpty())
                <h3 style="font-size:var(--bh-fs-md);margin-top:16px">Issued quote</h3>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>Line</th><th>Qty</th><th>Available now</th><th>Price</th></tr></thead>
                    <tbody>@foreach ($quotation->items as $item)
                        @php $available = $item->variant?->availableQuantity(); @endphp
                        <tr><td>{{ $item->description }} <span class="muted small">{{ $item->sku }}</span></td><td class="tnum">{{ $item->quantity_label }}</td>
                            <td class="tnum">@if ($available === null)<span class="muted">—</span>@else<span class="status {{ $available >= $item->quantity ? 'ok' : 'warn' }}">{{ \App\Services\Pricing\PriceResolver::quantityLabel($item->variant, $available) }}</span>@endif</td>
                            <td class="tnum">{{ Taka::format($item->line_paisa) }}</td></tr>
                    @endforeach</tbody>
                    <tfoot><tr><th colspan="3">Total · valid until {{ $quotation->valid_until?->timezone('Asia/Dhaka')->format('d M Y') }}</th><th class="tnum">{{ Taka::format($quotation->total_paisa) }}</th></tr></tfoot>
                </table></div>
                <p class="hint">Stock is not reserved by a quote; it is reserved when the customer checks out. A line short of stock blocks checkout until it is restocked or the quote is revised.</p>
            @endif
        </section>

        <section>
            @if ($quotation->organization_id !== null && in_array($quotation->status, [QuotationStatus::Requested, QuotationStatus::Quoted], true))
                <form method="POST" action="{{ route('admin.quotations.issue', $quotation) }}" class="card card-pad" style="margin-bottom:16px" novalidate>
                    @csrf
                    <h2 style="font-size:var(--bh-fs-lg)">{{ $quotation->status === QuotationStatus::Quoted ? 'Revise quote' : 'Issue quote' }}</h2>
                    <p class="hint">Quantity and price are fixed per line. SKU lines reserve stock at checkout; weight quantities accept 500g / 6kg (bare number = grams). Leave SKU empty for a custom line (e.g. installation). Price = line total in ৳.</p>
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>SKU</th><th>Description</th><th>Quantity</th><th>Line price ৳</th></tr></thead>
                        <tbody>
                        @foreach ($rows as $index => $row)
                            <tr>
                                <td><input class="input" name="lines[{{ $index }}][sku]" value="{{ $row['sku'] }}" aria-label="SKU line {{ $index + 1 }}" style="min-height:34px;width:130px"></td>
                                <td><input class="input" name="lines[{{ $index }}][description]" value="{{ $row['description'] }}" aria-label="Description line {{ $index + 1 }}" style="min-height:34px"></td>
                                <td><input class="input tnum" name="lines[{{ $index }}][quantity]" value="{{ $row['quantity'] }}" aria-label="Quantity line {{ $index + 1 }}" style="min-height:34px;width:90px"></td>
                                <td><input class="input tnum" name="lines[{{ $index }}][price]" value="{{ $row['price'] }}" inputmode="decimal" aria-label="Price line {{ $index + 1 }}" style="min-height:34px;width:110px"></td>
                            </tr>
                            @foreach (['sku', 'description', 'quantity', 'price'] as $field)
                                @error("lines.{$index}.{$field}")<tr><td colspan="4" class="field-error">Line {{ $index + 1 }}: {{ $message }}</td></tr>@enderror
                            @endforeach
                        @endforeach
                        </tbody>
                    </table></div>
                    <div class="form-grid" style="margin-top:12px">
                        <div class="field"><label for="valid_until">Valid until (Dhaka date)</label><input class="input" id="valid_until" name="valid_until" type="date" value="{{ $validUntil }}" required></div>
                        <div class="field full"><label for="terms">Terms shown to the customer</label><textarea class="input" id="terms" name="terms" rows="2" placeholder="e.g. delivery in 5 working days; installation included">{{ old('terms', $quotation->terms) }}</textarea></div>
                        <div class="field full"><label for="internal_note">Internal note (staff only)</label><textarea class="input" id="internal_note" name="internal_note" rows="2">{{ old('internal_note', $quotation->internal_note) }}</textarea></div>
                    </div>
                    <button class="btn btn-navy" type="submit">{{ $quotation->status === QuotationStatus::Quoted ? 'Send revised quote' : 'Send quote' }}</button>
                </form>
            @elseif ($quotation->internal_note)
                <div class="card card-pad" style="margin-bottom:16px"><strong class="small">Internal note</strong><p class="small">{{ $quotation->internal_note }}</p></div>
            @endif

            @if ($quotation->status->isOpen())
                <form method="POST" action="{{ route('admin.quotations.close', $quotation) }}" class="card card-pad row" style="align-items:flex-end" novalidate>
                    @csrf
                    <div class="field" style="flex:1;margin:0"><label for="reason">Close {{ $quotation->organization_id ? 'quotation' : 'lead' }} — reason</label><input class="input" id="reason" name="reason" required></div>
                    <button class="btn btn-outline" type="submit">Close</button>
                </form>
            @endif
        </section>
    </div>
@endsection
