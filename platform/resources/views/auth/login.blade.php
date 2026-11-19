@extends('layouts.app', ['title' => 'Login'])

@section('content')
    <form method="POST" action="{{ route('login') }}" class="card card-pad" style="max-width:440px;margin:0 auto" novalidate>
        @csrf
        <h1 style="font-size:var(--bh-fs-2xl)">Login</h1>
        <div class="field">
            <label for="identifier">Mobile number or email</label>
            <input class="input" id="identifier" name="identifier" value="{{ old('identifier') }}" autocomplete="username" required
                   @error('identifier') aria-invalid="true" @enderror>
        </div>
        <div class="field">
            <label for="password">Password</label>
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
        <button class="btn btn-primary btn-block" type="submit" style="margin-top:12px">Login</button>
        <p class="small" style="margin-top:12px">New here? <a href="{{ route('register') }}">Create an account</a></p>
    </form>
@endsection
