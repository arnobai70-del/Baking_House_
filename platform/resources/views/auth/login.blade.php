@extends('layouts.app', ['title' => __('Log in')])

@section('content')
    <form method="POST" action="{{ route('login') }}" class="card card-pad reveal" style="max-width:440px;margin:0 auto" novalidate>
        @csrf
        <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Log in') }}</h1>
        <div class="field">
            <label for="identifier">{{ __('Mobile number or email') }}</label>
            <input class="input" id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" required
                   @error('identifier') aria-invalid="true" aria-describedby="identifier-error" @enderror>
            @error('identifier')<p class="field-error" id="identifier-error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label for="password">{{ __('Password') }}</label>
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <label class="check"><input type="checkbox" name="remember" value="1"> {{ __('Keep me signed in') }}</label>
        <button class="btn btn-navy btn-block" type="submit" style="margin-top:12px">{{ __('Log in') }}</button>
        <p class="small" style="margin-top:12px">{{ __('New here?') }} <a href="{{ route('register') }}">{{ __('Create an account') }}</a></p>
    </form>
@endsection
