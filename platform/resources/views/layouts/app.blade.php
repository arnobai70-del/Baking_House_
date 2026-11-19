<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ isset($title) ? $title.' — ' : '' }}Baking House</title>
    {{-- Interim brand CSS copied from the Phase 1 prototype; replaced by Tailwind + Vite in Phase 3. --}}
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="masthead">
        <div class="container">
            <a class="logo" href="{{ route('home') }}">
                <span class="logo-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 21v-6M8.5 12.5a3.5 3.5 0 1 1 3.5-6 3.5 3.5 0 1 1 3.5 6 3.5 3.5 0 1 1-3.5 3 3.5 3.5 0 1 1-3.5-3z"/></svg></span>
                <span class="logo-text"><strong>Baking House</strong><span>Bakery · Restaurant · Café · Food Supply</span></span>
            </a>
            <nav class="head-actions" aria-label="Account">
                @auth
                    @can('admin.access')
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.dashboard') }}">Admin</a>
                    @endcan
                    <a class="btn btn-ghost btn-sm" href="{{ route('account') }}">{{ auth()->user()->name }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-outline btn-sm" type="submit">Log out</button>
                    </form>
                @else
                    <a class="btn btn-ghost btn-sm" href="{{ route('login') }}">Login</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('register') }}">Register</a>
                @endauth
            </nav>
        </div>
    </header>

    <main id="main" class="container section">
        @if (session('status'))
            <div class="alert alert-success" role="status" style="margin-bottom:16px"><span>{{ session('status') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert" style="margin-bottom:16px">
                <ul style="margin:0;padding-left:16px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="footer-base"><div class="container" style="display:block">© Baking House · development build</div></div>
    </footer>
</body>
</html>
