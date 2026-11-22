@extends('layouts.app')

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol>
        <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
        <li aria-current="page">{{ __('Contact & support') }}</li>
    </ol></nav>
    <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Contact & support') }}</h1>

    <div class="cart-layout">
        <section class="card card-pad reveal">
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Send us a message') }}</h2>
            <form method="POST" action="{{ route('contact.store') }}" novalidate>
                @csrf
                <div class="form-grid">
                    <div class="field"><label for="c-name">{{ __('Name') }}</label><input class="input" id="c-name" name="name" value="{{ old('name', $prefill['name']) }}" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="c-name-error" @enderror>
                        @error('name')<p class="field-error" id="c-name-error">{{ $message }}</p>@enderror</div>
                    <div class="field"><label for="c-phone">{{ __('Mobile') }}</label><input class="input" id="c-phone" name="phone" inputmode="tel" value="{{ old('phone', $prefill['phone']) }}" required placeholder="01XXXXXXXXX" autocomplete="tel" @error('phone') aria-invalid="true" aria-describedby="c-phone-error" @enderror>
                        @error('phone')<p class="field-error" id="c-phone-error">{{ $message }}</p>@enderror</div>
                    <div class="field"><label for="c-email">{{ __('Email (optional)') }}</label><input class="input" id="c-email" name="email" type="email" value="{{ old('email', $prefill['email']) }}" autocomplete="email"></div>
                    <div class="field"><label for="c-topic">{{ __('Topic') }}</label>
                        <select class="input" id="c-topic" name="topic">@foreach ($topics as $value => $label)<option value="{{ $value }}" @selected(old('topic') === $value)>{{ __($label) }}</option>@endforeach</select></div>
                    <div class="field full"><label for="c-order">{{ __('Order number (if about an order)') }}</label><input class="input tnum" id="c-order" name="order_number" value="{{ old('order_number') }}" placeholder="BH261120-XXXXXX"></div>
                    <div class="field full"><label for="c-message">{{ __('Message') }}</label><textarea class="input" id="c-message" name="message" rows="5" required @error('message') aria-invalid="true" aria-describedby="c-message-error" @enderror>{{ old('message') }}</textarea>
                        @error('message')<p class="field-error" id="c-message-error">{{ $message }}</p>@enderror</div>
                </div>
                <div class="sr-only" aria-hidden="true"><label for="c-website">Website</label><input id="c-website" name="website" tabindex="-1" autocomplete="off"></div>
                <button class="btn btn-navy" type="submit">{{ __('Send message') }}</button>
                <p class="hint" style="margin-top:8px">{{ __('We use your details only to answer this message.') }}</p>
            </form>
        </section>
        <aside class="card card-pad reveal reveal-2">
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('How to reach us') }}</h2>
            @if ($information)
                <div class="prose">{!! $information->html() !!}</div>
            @else
                <p class="muted">{{ __('Phone numbers, address and opening hours will be published after the owner verifies them.') }}</p>
            @endif
            <ul class="small" style="padding-left:18px;margin-top:12px">
                <li><a href="{{ route('orders.track') }}">{{ __('Track an order') }}</a></li>
                <li><a href="{{ route('business.landing') }}#rfq">{{ __('Request a quote') }}</a></li>
            </ul>
        </aside>
    </div>
@endsection
