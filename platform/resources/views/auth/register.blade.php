@extends('layouts.app', ['title' => __('Create your account')])

@section('content')
    <form method="POST" action="{{ route('register') }}" class="card card-pad reveal" style="max-width:480px;margin:0 auto" novalidate>
        @csrf
        <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Create your account') }}</h1>
        @foreach ([['name', __('Full name'), 'text', 'name', null], ['phone', __('Mobile number'), 'text', 'tel', '01XXXXXXXXX'], ['email', __('Email (optional)'), 'email', 'email', null]] as [$field, $label, $type, $autocomplete, $placeholder])
            <div class="field">
                <label for="{{ $field }}">{{ $label }}</label>
                <input class="input" id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" autocomplete="{{ $autocomplete }}"
                       @if ($field === 'phone') inputmode="tel" @endif @if ($placeholder) placeholder="{{ $placeholder }}" @endif @if ($field !== 'email') required @endif
                       @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                @error($field)<p class="field-error" id="{{ $field }}-error">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <div class="form-grid">
            <div class="field">
                <label for="password">{{ __('Password') }}</label>
                <input class="input" id="password" name="password" type="password" autocomplete="new-password" required aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                <span class="hint" id="password-hint">{{ __('At least 8 characters.') }}</span>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="password_confirmation">{{ __('Confirm password') }}</label>
                <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
        </div>
        <p class="hint">{{ __('Restaurants, cafés, bakeries and resellers: register first, then apply for a business or reseller account.') }}</p>
        <button class="btn btn-navy btn-block" type="submit">{{ __('Create account') }}</button>
        <p class="small" style="margin-top:12px">{{ __('Already registered?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a></p>
    </form>
@endsection
