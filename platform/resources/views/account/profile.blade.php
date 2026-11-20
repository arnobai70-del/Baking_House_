@extends('layouts.app', ['title' => __('Profile & password')])

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol>
        <li><a href="{{ route('account') }}">{{ __('My account') }}</a></li>
        <li aria-current="page">{{ __('Profile & password') }}</li>
    </ol></nav>
    <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Profile & password') }}</h1>

    <div class="portal-grid">
        <form method="POST" action="{{ route('account.profile.update') }}" class="card card-pad reveal" novalidate>
            @csrf @method('PUT')
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Profile') }}</h2>
            <div class="field"><label for="name">{{ __('Full name') }}</label><input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                @error('name')<p class="field-error" id="name-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="phone">{{ __('Mobile number') }}</label><input class="input tnum" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" required inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
                @error('phone')<p class="field-error" id="phone-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="email">{{ __('Email (optional)') }}</label><input class="input" id="email" name="email" type="email" value="{{ old('email', $user->email) }}" autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror</div>
            <button class="btn btn-primary" type="submit">{{ __('Save profile') }}</button>
        </form>

        <form method="POST" action="{{ route('account.password') }}" class="card card-pad reveal reveal-2" novalidate>
            @csrf @method('PUT')
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Change password') }}</h2>
            <div class="field"><label for="current_password">{{ __('Current password') }}</label><input class="input" id="current_password" name="current_password" type="password" required autocomplete="current-password" @error('current_password') aria-invalid="true" aria-describedby="current-error" @enderror>
                @error('current_password')<p class="field-error" id="current-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password">{{ __('New password (at least 8 characters)') }}</label><input class="input" id="password" name="password" type="password" required autocomplete="new-password" minlength="8" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror</div>
            <div class="field"><label for="password_confirmation">{{ __('Repeat new password') }}</label><input class="input" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
            <p class="hint">{{ __('Other devices and apps will be signed out.') }}</p>
            <button class="btn btn-outline" type="submit">{{ __('Change password') }}</button>
        </form>
    </div>
@endsection
