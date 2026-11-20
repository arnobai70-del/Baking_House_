@extends('portal.layout', ['heading' => __('Quotation :number', ['number' => $quotation->number])])

@use('App\Support\Taka')
@use('App\Enums\QuotationStatus')

@section('portal')
    <div class="cart-layout">
        <section class="card card-pad">
            <div class="row" style="justify-content:space-between;margin-bottom:8px">
                <h2 style="font-size:var(--bh-fs-lg);margin:0">{{ __('Quote') }}</h2>
                <span class="status {{ $quotation->status->tone() }}">{{ __($quotation->status->label()) }}</span>
            </div>

            @if ($quotation->items->isEmpty())
                <div class="alert alert-info"><x-icon name="info" /><span>{{ __('Our sales team is preparing your quote. You will be notified when it is ready.') }}</span></div>
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Quantity') }}</th><th>{{ __('Price') }}</th></tr></thead>
                    <tbody>
                    @foreach ($quotation->items as $item)
                        <tr><td>{{ $item->description }}@if ($item->sku)<div class="muted small">{{ $item->sku }}</div>@endif</td>
                            <td class="tnum">{{ $item->quantity_label }}</td><td class="tnum">{{ Taka::format($item->line_paisa) }}</td></tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><th colspan="2">{{ __('Quoted total') }}</th><th class="tnum">{{ Taka::format($quotation->total_paisa) }}</th></tr></tfoot>
                </table></div>
                <p class="hint">{{ __('Quantities and prices are fixed by this quote. Delivery is calculated at checkout. Coupons do not apply to quoted prices.') }}</p>
                @if ($quotation->terms)<div class="card card-pad" style="margin-top:10px;background:var(--bh-bg)"><strong class="small">{{ __('Terms') }}</strong><p class="small" style="margin:4px 0 0">{!! nl2br(e($quotation->terms)) !!}</p></div>@endif
            @endif

            @if ($role->canPlaceOrders())
                <div class="row" style="margin-top:16px">
                    @if ($quotation->status === QuotationStatus::Quoted && $quotation->isCurrent())
                        <form method="POST" action="{{ route('business.quotations.accept', $quotation) }}">@csrf<button class="btn btn-primary" type="submit"><x-icon name="check" /> {{ __('Accept quote & add to cart') }}</button></form>
                    @elseif ($quotation->status === QuotationStatus::Accepted && $quotation->isCurrent())
                        <form method="POST" action="{{ route('business.quotations.accept', $quotation) }}">@csrf<button class="btn btn-primary" type="submit"><x-icon name="cart" /> {{ __('Add to cart') }}</button></form>
                    @endif
                    @if (in_array($quotation->status, [QuotationStatus::Requested, QuotationStatus::Quoted, QuotationStatus::Accepted], true))
                        <details><summary class="btn btn-ghost">{{ __('Decline') }}</summary>
                            <form method="POST" action="{{ route('business.quotations.decline', $quotation) }}" class="row" style="margin-top:8px;align-items:flex-end">
                                @csrf
                                <div class="field" style="margin:0"><label for="decline-reason">{{ __('Reason (optional)') }}</label><input class="input" id="decline-reason" name="reason" maxlength="500"></div>
                                <button class="btn btn-outline btn-sm" type="submit">{{ __('Decline quote') }}</button>
                            </form></details>
                    @endif
                </div>
            @endif
            @if ($quotation->order_id)
                <p style="margin-top:12px">{{ __('Ordered as') }} <a href="{{ route('orders.show', $quotation->order()->first()) }}">{{ $quotation->order()->value('number') }}</a></p>
            @endif
        </section>

        <aside class="card card-pad">
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Request') }}</h2>
            <p class="small" style="white-space:pre-line">{{ $quotation->request_text }}</p>
            @if ($quotation->customer_note)<p class="small muted">{{ $quotation->customer_note }}</p>@endif
            <table class="specs small"><tbody>
                <tr><th>{{ __('Requested') }}</th><td>{{ $quotation->created_at->timezone('Asia/Dhaka')->format('d M Y H:i') }}</td></tr>
                <tr><th>{{ __('Contact') }}</th><td>{{ $quotation->contact_name }} · {{ $quotation->contact_phone }}</td></tr>
                @if ($quotation->valid_until)<tr><th>{{ __('Valid until') }}</th><td>{{ $quotation->valid_until->timezone('Asia/Dhaka')->format('d M Y') }}</td></tr>@endif
            </tbody></table>
            <a class="btn btn-ghost btn-sm" href="{{ route('business.quotations') }}">← {{ __('All quotations') }}</a>
        </aside>
    </div>
@endsection
