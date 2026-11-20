@extends('portal.layout', ['heading' => __('Quotations'), 'subheading' => __('Request prices for equipment, cartons or large volumes. Accepted quotes go to your cart at the quoted price.')])

@use('App\Support\Taka')

@section('portal')
    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>{{ __('Your quotations') }}</h2></div>
        @if ($quotations->isEmpty())
            <div class="empty-state"><x-icon name="quote" /><p><strong>{{ __('No quotations yet') }}</strong></p><p class="muted small">{{ __('Send a request below; our sales team replies with a quote.') }}</p></div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Ref') }}</th><th>{{ __('Requested') }}</th><th>{{ __('Items') }}</th><th>{{ __('Quote') }}</th><th>{{ __('Valid until') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                @foreach ($quotations as $quotation)
                    <tr>
                        <td class="tnum"><a href="{{ route('business.quotations.show', $quotation) }}"><strong>{{ $quotation->number }}</strong></a></td>
                        <td class="small">{{ $quotation->created_at->timezone('Asia/Dhaka')->format('d M Y') }}</td>
                        <td class="small">{{ \Illuminate\Support\Str::limit($quotation->request_text, 60) }}</td>
                        <td class="tnum">{{ $quotation->total_paisa !== null ? Taka::format($quotation->total_paisa) : '—' }}</td>
                        <td class="small">{{ $quotation->valid_until?->timezone('Asia/Dhaka')->format('d M Y') ?? '—' }}</td>
                        <td><span class="status {{ $quotation->status->tone() }}">{{ __($quotation->status->label()) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    {{ $quotations->links() }}

    @if ($role->canPlaceOrders())
        <div class="card">
            <div class="panel-title"><h2>{{ __('New quotation request') }}</h2></div>
            <div class="card-pad">@include('storefront.partials.rfq-form', ['prefill' => $prefill, 'requestText' => ''])</div>
        </div>
    @endif
@endsection
