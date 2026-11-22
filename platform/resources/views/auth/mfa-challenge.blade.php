@extends('layouts.app', ['title' => 'Verify your sign-in'])

@section('content')
    <div class="card card-pad" style="max-width:460px;margin:0 auto">
        <h1 style="font-size:var(--bh-fs-2xl)">Two-step verification</h1>
        <p class="muted">Staff accounts need a code from your authenticator app.</p>

        <form method="POST" action="{{ route('mfa.challenge') }}" novalidate>
            @csrf
            <div class="field">
                <label for="code">6-digit authenticator code</label>
                <input class="input tnum" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus
                       @error('code') aria-invalid="true" @enderror>
            </div>
            <button class="btn btn-navy btn-block" type="submit">Verify and sign in</button>
        </form>

        <details style="margin-top:16px">
            <summary class="small" style="cursor:pointer">Lost your device? Use a recovery code</summary>
            <form method="POST" action="{{ route('mfa.challenge') }}" style="margin-top:10px" novalidate>
                @csrf
                <div class="field">
                    <label for="recovery_code">Recovery code</label>
                    <input class="input tnum" id="recovery_code" name="recovery_code" autocomplete="off" placeholder="XXXXX-XXXXX"
                           @error('recovery_code') aria-invalid="true" @enderror>
                    <span class="hint">Each recovery code works once.</span>
                </div>
                <button class="btn btn-outline btn-block" type="submit">Use recovery code</button>
            </form>
        </details>
    </div>
@endsection
