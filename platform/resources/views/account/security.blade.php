@extends('layouts.admin', ['title' => 'Security'])

@section('admin')
    <div class="admin-top"><h1>Security</h1></div>

    <div class="card card-pad" style="margin-bottom:16px">
        <h2 style="font-size:var(--bh-fs-lg)">Two-step verification</h2>
        <p><span class="status ok">Enabled</span> since {{ $user->mfa_confirmed_at?->timezone('Asia/Dhaka')->format('d M Y') }} ·
            <strong>{{ $remainingCodes }}</strong> unused recovery codes · {{ $tokenCount }} active API {{ \Illuminate\Support\Str::plural('token', $tokenCount) }}</p>
        <p class="muted small">Two-step verification is mandatory for staff and cannot be switched off. Both actions below need your password and a current authenticator code.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
        <form method="POST" action="{{ route('account.security.recovery-codes') }}" class="card card-pad" novalidate>
            @csrf
            <h2 style="font-size:var(--bh-fs-md)">Generate new recovery codes</h2>
            <p class="muted small">Old codes stop working immediately.</p>
            <div class="field"><label for="rc-password">Current password</label><input class="input" id="rc-password" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="field"><label for="rc-code">Authenticator code</label><input class="input tnum" id="rc-code" name="code" inputmode="numeric" autocomplete="one-time-code" required></div>
            <button class="btn btn-outline" type="submit">Generate new codes</button>
        </form>

        <form method="POST" action="{{ route('account.security.reset') }}" class="card card-pad" novalidate>
            @csrf
            <h2 style="font-size:var(--bh-fs-md)">Move to a new device (reset)</h2>
            <p class="muted small">Removes your current authenticator, revokes all your API tokens and signs you out. You will set up the new device straight away.</p>
            <div class="field"><label for="rs-password">Current password</label><input class="input" id="rs-password" name="current_password" type="password" autocomplete="current-password" required></div>
            <div class="field"><label for="rs-code">Authenticator code</label><input class="input tnum" id="rs-code" name="code" inputmode="numeric" autocomplete="one-time-code" required></div>
            <button class="btn btn-navy" type="submit">Reset two-step verification</button>
        </form>
    </div>
@endsection
