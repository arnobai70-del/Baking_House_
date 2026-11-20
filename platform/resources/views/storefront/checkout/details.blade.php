@extends('layouts.app')

@section('content')
    <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Checkout') }}</h1>
    <ol class="steps" aria-label="{{ __('Checkout progress') }}">
        <li class="done">{{ __('Cart') }}</li><li class="current" aria-current="step">{{ __('Delivery & payment') }}</li><li>{{ __('Review') }}</li><li>{{ __('Confirmation') }}</li>
    </ol>
    @error('checkout')<div class="alert alert-error" role="alert" style="margin-bottom:12px"><x-icon name="alert" /><span>{{ $message }}</span></div>@enderror

    <div class="cart-layout">
        <form method="POST" action="{{ route('checkout.review.store') }}" class="card card-pad" novalidate>
            @csrf
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Delivery details') }}</h2>
            @guest
                <div class="alert alert-info" style="margin-bottom:14px"><x-icon name="info" /><span>{{ __('Checking out as a guest.') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a> {{ __('to save addresses and see business prices.') }}</span></div>
            @endguest
            <div class="form-grid">
                <div class="field"><label for="name">{{ __('Full name') }}</label><input class="input" id="name" name="name" autocomplete="name" value="{{ old('name', $draft['name'] ?? '') }}" required></div>
                <div class="field"><label for="phone">{{ __('Mobile number') }}</label><input class="input" id="phone" name="phone" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" value="{{ old('phone', $draft['phone'] ?? '') }}" required></div>
                <div class="field full"><label for="email">{{ __('Email (optional, for receipts)') }}</label><input class="input" id="email" name="email" type="email" autocomplete="email" value="{{ old('email', $draft['email'] ?? '') }}"></div>
                <div class="field full"><label for="address_line">{{ __('Address') }}</label><input class="input" id="address_line" name="address_line" autocomplete="street-address" placeholder="{{ __('House, road, block') }}" value="{{ old('address_line', $draft['address_line'] ?? '') }}" required></div>
                <div class="field"><label for="area">{{ __('Area / thana') }}</label><input class="input" id="area" name="area" value="{{ old('area', $draft['area'] ?? '') }}" required></div>
                <div class="field"><label for="district">{{ __('District') }}</label>
                    <select class="input" id="district" name="district" required>
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($districts as $district)
                            <option value="{{ $district }}" @selected(old('district', $draft['district'] ?? '') === $district)>{{ $district }}</option>
                        @endforeach
                    </select></div>
                <div class="field full"><label for="note">{{ __('Delivery note (optional)') }}</label><input class="input" id="note" name="note" value="{{ old('note', $draft['note'] ?? '') }}"></div>
            </div>

            <h2 style="font-size:var(--bh-fs-lg);margin-top:8px">{{ __('Payment method') }}</h2>
            <fieldset style="border:0;padding:0;margin:0 0 12px"><legend class="sr-only">{{ __('Payment method') }}</legend>
                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                    @php $available = in_array($method, $summary['methods'], true); @endphp
                    <label class="pay-opt" @if (! $available) style="opacity:.55" @endif>
                        <input type="radio" name="payment_method" value="{{ $method->value }}" @checked(old('payment_method', $draft['payment_method'] ?? '') === $method->value) @disabled(! $available)>
                        <strong>{{ __($method->label()) }}</strong>
                        <span class="tag">{{ $method->isOnline() ? __('Test mode — no real money moves') : ($method->value === 'cod' ? ($available ? __('Pay when you receive') : __('Not available for this address or amount')) : __('Pay to our bank account')) }}</span>
                    </label>
                @endforeach
            </fieldset>
            <div class="row"><a class="btn btn-ghost" href="{{ route('cart.show') }}">← {{ __('Back to cart') }}</a><span class="spacer"></span>
                <button class="btn btn-primary" type="submit">{{ __('Review order') }}</button></div>
        </form>
        @include('storefront.checkout._summary', ['summary' => $summary])
    </div>
@endsection
