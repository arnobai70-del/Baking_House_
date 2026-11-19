@extends('layouts.app', ['title' => 'Register'])

@section('content')
    <form method="POST" action="{{ route('register') }}" class="card card-pad" style="max-width:480px;margin:0 auto" novalidate>
        @csrf
        <h1 style="font-size:var(--bh-fs-2xl)">Create your account</h1>
        <div class="field">
            <label for="name">Full name</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required @error('name') aria-invalid="true" @enderror>
        </div>
        <div class="field">
            <label for="phone">Mobile number</label>
            <input class="input" id="phone" name="phone" value="{{ old('phone') }}" inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required @error('phone') aria-invalid="true" @enderror>
        </div>
        <div class="field">
            <label for="email">Email (optional)</label>
            <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" @error('email') aria-invalid="true" @enderror>
        </div>
        <div class="form-grid">
            <div class="field">
                <label for="password">Password</label>
                <input class="input" id="password" name="password" type="password" autocomplete="new-password" required>
                <span class="hint">At least 8 characters.</span>
            </div>
            <div class="field">
                <label for="password_confirmation">Confirm password</label>
                <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>
        </div>
        <p class="hint">Restaurants, cafés, bakeries and resellers: register first, then apply for a business or reseller account.</p>
        <button class="btn btn-primary btn-block" type="submit">Create account</button>
    </form>
@endsection
