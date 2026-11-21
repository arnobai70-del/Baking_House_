@extends('layouts.app')

@section('content')
    <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Review your order') }}</h1>
    <ol class="steps" aria-label="{{ __('Checkout progress') }}">
        <li class="done">{{ __('Cart') }}</li><li class="done">{{ __('Delivery & payment') }}</li><li class="current" aria-current="step">{{ __('Review') }}</li><li>{{ __('Confirmation') }}</li>
    </ol>
    @error('checkout')<div class="alert alert-error" role="alert" style="margin-bottom:12px"><x-icon name="alert" /><span>{{ $message }}</span></div>@enderror
    @foreach ($summary['errors'] as $problem)
        <div class="alert alert-error" role="alert" style="margin-bottom:12px"><x-icon name="alert" /><span>{{ $problem }} <a href="{{ route('cart.show') }}">{{ __('Review cart') }}</a></span></div>
    @endforeach

    <div class="cart-layout">
        <form method="POST" action="{{ route('checkout.place') }}" class="card card-pad" id="place-form" novalidate>
            @csrf
            <input type="hidden" name="checkout_token" value="{{ $draft['token'] }}">
            <input type="hidden" name="fingerprint" value="{{ $summary['fingerprint'] }}">
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Deliver to') }}</h2>
            <p>{{ $draft['name'] }} · {{ $draft['phone'] }}@if (! empty($draft['email'])) · {{ $draft['email'] }}@endif<br>
                {{ $draft['address_line'] }}, {{ $draft['area'] }}, {{ $draft['district'] }}</p>
            @if (! empty($draft['note']))<p class="muted small">{{ __('Note') }}: {{ $draft['note'] }}</p>@endif
            <p><a href="{{ route('checkout.show') }}">{{ __('Change details') }}</a></p>

            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Payment') }}</h2>
            <p><strong>{{ __($method->label()) }}</strong>
                @if ($method->isOnline())<span class="status warn">{{ __('Test mode') }}</span><br><span class="small muted">{{ __('You will be taken to a simulated payment page. Stock is held for :minutes minutes.', ['minutes' => config('commerce.reservation_minutes')]) }}</span>
                @elseif ($method->value === 'bank_transfer')<br><span class="small muted">{{ __('Bank details are shown after you place the order. Stock is reserved for you while we wait for the transfer.') }}</span>
                @else<br><span class="small muted">{{ __('Pay in cash when your order arrives.') }}</span>@endif</p>

            <label class="check" style="margin:12px 0"><input type="checkbox" name="confirm" value="1" required> {{ __('I confirm the items, quantities, address and total, and agree to the terms, delivery and returns policies.') }}</label>
            @error('confirm')<div class="field-error">{{ $message }}</div>@enderror
            <button class="btn btn-primary btn-block" type="submit" id="place-button" @disabled($summary['errors'] !== [])>{{ __('Place order') }} · {{ \App\Support\Taka::format($summary['total_paisa']) }}</button>
            <p class="hint" style="margin-top:8px"><x-icon name="lock" /> {{ __('Your order is created once, even if you press the button twice.') }}</p>
        </form>
        @include('storefront.checkout._summary', ['summary' => $summary])
    </div>
    {{-- Double-submit protection and the busy spinner come from storefront.js (initBusyForms). --}}
@endsection
