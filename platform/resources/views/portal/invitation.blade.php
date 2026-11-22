@extends('layouts.app')

@section('content')
    <div class="card card-pad" style="max-width:520px;margin:32px auto">
        <h1 style="font-size:var(--bh-fs-xl)"><x-icon name="users" /> {{ __('Team invitation') }}</h1>
        @if (! $invitation->isPending())
            <div class="alert alert-error"><x-icon name="alert" /><span>{{ __('This invitation has expired or was already used.') }}</span></div>
        @else
            <p>{{ __('You are invited to join :organization as :role.', ['organization' => $invitation->organization->name, 'role' => __($invitation->role->label())]) }}</p>
            <p class="muted small">{{ __('Sent to :email. Sign in with that email address to accept.', ['email' => $invitation->email]) }}</p>
            @error('invitation')<div class="alert alert-error" role="alert"><x-icon name="alert" /><span>{{ $message }}</span></div>@enderror
            @auth
                <form method="POST" action="{{ route('invitations.accept', $token) }}">@csrf<button class="btn btn-navy" type="submit"><x-icon name="check" /> {{ __('Accept invitation') }}</button></form>
            @else
                <div class="row">
                    <a class="btn btn-navy" href="{{ route('login') }}">{{ __('Sign in') }}</a>
                    <a class="btn btn-outline" href="{{ route('register') }}">{{ __('Create an account') }}</a>
                </div>
                <p class="hint" style="margin-top:8px">{{ __('After signing in you come back to this page.') }}</p>
            @endauth
        @endif
    </div>
@endsection
