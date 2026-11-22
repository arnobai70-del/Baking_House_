@extends('portal.layout', ['heading' => $portal === 'business' ? __('Business dashboard') : __('Reseller dashboard')])

@use('App\Support\Taka')

@section('portal')
    <div class="kpis">
        @foreach ($kpis as $kpi)
            <div class="card kpi"><div class="k-label">{{ $kpi['label'] }}</div><div class="k-val tnum">{{ $kpi['money'] ? Taka::format($kpi['value']) : $kpi['value'] }}</div></div>
        @endforeach
    </div>

    @foreach ($quotesAwaiting as $quotation)
        <div class="alert alert-info" style="margin-bottom:12px"><x-icon name="quote" />
            <span>{{ __('Quote :number is ready: :total, valid until :date.', ['number' => $quotation->number, 'total' => Taka::format($quotation->total_paisa), 'date' => $quotation->valid_until->timezone('Asia/Dhaka')->format('d M Y')]) }}
                <a href="{{ route('business.quotations.show', $quotation) }}">{{ __('Review quote') }}</a></span></div>
    @endforeach

    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>{{ __('Recent orders') }}</h2><a class="btn btn-outline btn-sm" href="{{ route($portal.'.orders') }}">{{ __('All orders') }}</a></div>
        @include('portal._orders-table', ['orders' => $recentOrders])
    </div>
@endsection
