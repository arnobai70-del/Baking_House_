@extends('layouts.app', ['title' => 'Set up two-step verification'])

@section('content')
    <div class="card card-pad" style="max-width:560px;margin:0 auto">
        <h1 style="font-size:var(--bh-fs-2xl)">Set up two-step verification</h1>
        <p class="muted">Two-step verification is required for all staff accounts. Use an authenticator app such as Google Authenticator, Microsoft Authenticator or 1Password.</p>

        <ol class="small" style="padding-left:18px">
            <li>Open your authenticator app and scan this QR code.</li>
            <li>Enter the 6-digit code it shows to finish.</li>
        </ol>

        <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap;margin:16px 0">
            <div style="background:#fff;padding:8px;border:1px solid var(--bh-border);border-radius:var(--bh-radius-sm);width:216px" aria-label="QR code for your authenticator app" role="img">{!! $qrSvg !!}</div>
            <div class="small">
                <p style="margin-bottom:4px"><strong>Can't scan?</strong> Enter this key manually:</p>
                <code class="tnum" style="font-size:var(--bh-fs-md);word-break:break-all">{{ $secret }}</code>
                <p class="hint" style="margin-top:6px">Keep this private. It is never shown again after setup.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('mfa.setup') }}" novalidate>
            @csrf
            <div class="field">
                <label for="code">6-digit code from the app</label>
                <input class="input tnum" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" autofocus
                       @error('code') aria-invalid="true" @enderror>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Confirm and continue</button>
        </form>
    </div>
@endsection
