@extends('layouts.app', ['title' => 'Your recovery codes'])

@section('content')
    <div class="card card-pad" style="max-width:560px;margin:0 auto">
        <h1 style="font-size:var(--bh-fs-2xl)">Save your recovery codes</h1>
        <div class="alert alert-info" style="margin-bottom:14px"><x-icon name="info" /><span>These codes are shown <strong>only once</strong>. Store them somewhere safe and offline. Each code can be used a single time if you lose your authenticator device.</span></div>
        <ul class="tnum" style="list-style:none;padding:0;display:grid;grid-template-columns:1fr 1fr;gap:8px;font-family:ui-monospace,monospace;font-size:var(--bh-fs-lg)">
            @foreach ($codes as $code)
                <li class="card" style="padding:8px 12px;text-align:center">{{ $code }}</li>
            @endforeach
        </ul>
        <div class="row" style="margin-top:16px">
            <a class="btn btn-primary" href="{{ route('admin.dashboard') }}">I have saved them — continue</a>
            <a class="btn btn-ghost" href="{{ route('account.security') }}">Security settings</a>
        </div>
    </div>
@endsection
