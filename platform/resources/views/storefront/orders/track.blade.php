@extends('layouts.app')

@section('content')
    <div class="card card-pad" style="max-width:600px;margin:0 auto">
        <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Track your order') }}</h1>
        <p class="muted small">{{ __('Enter the order number and the mobile number used at checkout.') }}</p>
        <form method="GET" action="{{ route('orders.track') }}" class="form-grid" novalidate>
            <div class="field"><label for="number">{{ __('Order number') }}</label><input class="input tnum" id="number" name="number" value="{{ request('number') }}" placeholder="BH261120-XXXXXX" required></div>
            <div class="field"><label for="phone">{{ __('Mobile number') }}</label><input class="input" id="phone" name="phone" inputmode="tel" value="{{ request('phone') }}" placeholder="01XXXXXXXXX" required></div>
            <div class="full"><button class="btn btn-navy" type="submit">{{ __('Track order') }}</button></div>
        </form>
        @if ($result === false)
            <div class="alert alert-error" style="margin-top:14px"><x-icon name="alert" /><span>{{ __('We could not find an order with those details.') }}</span></div>
        @elseif ($result)
            <div class="card card-pad" style="margin-top:14px">
                <p><strong>{{ $result->number }}</strong> · <span class="status {{ $result->status->tone() }}">{{ __($result->status->label()) }}</span> · <span class="status">{{ __($result->payment_status->label()) }}</span></p>
                <p class="small muted">{{ __('Placed') }} {{ $result->placed_at->timezone('Asia/Dhaka')->format('d M Y H:i') }} · {{ __('Total') }} {{ \App\Support\Taka::format($result->total_paisa) }}</p>
            </div>
        @endif
    </div>
@endsection
