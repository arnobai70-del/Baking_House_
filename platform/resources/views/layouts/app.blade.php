@php
    /* Storefront pages pass $seo; every other page (auth, account, admin) is noindex. */
    if (! isset($seo)) {
        $seo = (new \App\Support\Seo(title: $title ?? config('seo.site_name')))->noindex();
    }
    $navigation = \App\Models\Category::navigation();
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $seo->fullTitle() }}</title>
    <meta name="description" content="{{ $seo->metaDescription() }}">
    <meta name="robots" content="{{ $seo->robots() }}">
    @if ($seo->canonical)
        <link rel="canonical" href="{{ $seo->canonical }}">
        <meta property="og:url" content="{{ $seo->canonical }}">
    @endif
    <meta property="og:type" content="{{ $seo->ogType }}">
    <meta property="og:title" content="{{ $seo->fullTitle() }}">
    <meta property="og:description" content="{{ $seo->metaDescription() }}">
    <meta property="og:site_name" content="{{ config('seo.site_name') }}">
    <meta name="theme-color" content="#103B56">
    @if (app()->getLocale() === 'bn')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+Bengali:wght@400;600;700&display=swap">
    @endif
    @foreach ($seo->jsonLdScripts() as $jsonLd)
        <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endforeach
    {{-- Approved prototype design system (tokens + components). Bundling/minification is Phase 8. --}}
    <link rel="stylesheet" href="{{ asset('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/storefront.css') }}">
    <script type="module" src="{{ asset('js/storefront.js') }}"></script>
</head>
<body class="has-tabbar">
    <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

    @unless (app()->isProduction())
        <div class="proto-bar" role="note"><div class="container">
            <span><x-icon name="info" /> <strong>{{ __('Development build') }}</strong> — {{ __('catalog, prices and stock are illustrative test data, not real products. Photos pending licensing.') }}</span>
        </div></div>
    @endunless

    <div class="topbar"><div class="container">
        <span>{{ __('Need help? Contact details pending owner verification') }}</span>
        <div class="links">
            @php $languages = \App\Models\Setting::enabledLanguages(); @endphp
            @if (count($languages) > 1)
                <form method="POST" action="{{ route('language') }}" class="lang-switch" aria-label="{{ __('Language') }}">
                    @csrf
                    @foreach ($languages as $code)
                        <button name="locale" value="{{ $code }}" lang="{{ $code }}" aria-pressed="{{ app()->getLocale() === $code ? 'true' : 'false' }}">{{ \App\Models\Setting::SUPPORTED_LANGUAGES[$code] }}</button>
                    @endforeach
                </form>
            @endif
            <a href="{{ route('organizations.apply', 'reseller') }}">{{ __('Become a reseller') }}</a>
            <a href="{{ route('organizations.apply', 'business') }}">{{ __('For business (bulk / wholesale)') }}</a>
        </div>
    </div></div>

    <div class="header-wrap">
        <header class="masthead"><div class="container">
            <button class="icon-btn menu-toggle" data-drawer-open="nav-drawer" aria-label="Open menu" aria-controls="nav-drawer" aria-expanded="false"><x-icon name="menu" /></button>
            <a class="logo" href="{{ route('home') }}" aria-label="Baking House home">
                <span class="logo-mark"><x-icon name="logo" class="" /></span>
                <span class="logo-text"><strong>Baking House</strong><span>Bakery · Restaurant · Café · Food Supply</span></span>
            </a>
            <div class="search" id="search" data-suggest-url="{{ route('api.v1.search') }}">
                <form action="{{ route('catalog.search') }}" role="search" autocomplete="off">
                    <label class="sr-only" for="q">Search products</label>
                    <input id="q" name="q" type="search" value="{{ request()->routeIs('catalog.search', 'catalog.shop', 'catalog.category') ? request('q') : '' }}"
                           placeholder="{{ __('Search ingredients, equipment, packaging, tools…') }}"
                           role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="suggest-list">
                    <button type="submit" aria-label="Search"><x-icon name="search" /></button>
                </form>
                <div class="suggest" id="suggest-list" role="listbox" aria-label="Suggestions"></div>
            </div>
            <nav class="head-actions" aria-label="Account">
                @auth
                    @can('admin.access')
                        <a class="btn btn-ghost btn-sm" href="{{ route('admin.dashboard') }}">Admin</a>
                    @endcan
                    <a class="icon-btn" href="{{ route('account') }}" aria-label="My account"><x-icon name="user" /></a>
                    <span class="label">{{ \Illuminate\Support\Str::limit(auth()->user()->name, 18) }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-ghost btn-sm" type="submit">{{ __('Log out') }}</button>
                    </form>
                @else
                    <a class="icon-btn" href="{{ route('login') }}" aria-label="Login or register"><x-icon name="user" /></a>
                    <span class="label"><a href="{{ route('login') }}">{{ __('Login') }}</a> /<br><a href="{{ route('register') }}">{{ __('Register') }}</a></span>
                @endauth
                <span class="icon-btn" aria-label="Cart (opens in Phase 5)" title="Cart opens in Phase 5"><x-icon name="cart" /></span>
            </nav>
        </div></header>

        <nav class="catnav" aria-label="Categories"><div class="container">
            <button class="all" id="mega-toggle" aria-expanded="false" aria-controls="mega"><x-icon name="menu" /> {{ __('All Categories') }}</button>
            @foreach ($navigation->take(7) as $navCategory)
                <a href="{{ $navCategory->url() }}" @if (request()->is('category/'.$navCategory->slug)) aria-current="page" @endif>{{ $navCategory->localized('name') }}</a>
            @endforeach
        </div></nav>

        <div class="mega" id="mega" role="region" aria-label="All categories"><div class="container">
            @forelse ($navigation as $navCategory)
                <div>
                    <h3><x-icon :name="$navCategory->icon" /><a href="{{ $navCategory->url() }}">{{ $navCategory->localized('name') }}</a></h3>
                    <ul>
                        @foreach ($navCategory->children as $child)
                            <li><a href="{{ $child->url() }}">{{ $child->localized('name') }}</a></li>
                        @endforeach
                    </ul>
                </div>
            @empty
                <p class="muted">No categories yet.</p>
            @endforelse
        </div></div>
    </div>

    <div class="drawer-overlay" id="overlay"></div>
    <aside class="drawer left" id="nav-drawer" aria-label="Menu" aria-hidden="true" inert>
        <div class="drawer-head"><strong>Menu</strong><button class="icon-btn" data-drawer-close aria-label="Close menu"><x-icon name="x" /></button></div>
        <div class="drawer-body"><nav>
            <a href="{{ route('catalog.shop') }}"><x-icon name="grid" /> {{ __('All products') }}</a>
            @foreach ($navigation as $navCategory)
                <a href="{{ $navCategory->url() }}"><x-icon :name="$navCategory->icon" /> {{ $navCategory->localized('name') }}</a>
            @endforeach
            <a href="{{ route('organizations.apply', 'business') }}"><x-icon name="store" /> {{ __('For business') }}</a>
            <a href="{{ route('organizations.apply', 'reseller') }}"><x-icon name="users" /> {{ __('Reseller program') }}</a>
        </nav></div>
    </aside>

    <main id="main" class="container" style="padding-top:20px;padding-bottom:40px">
        @if (session('status'))
            <div class="alert alert-success" role="status" style="margin-bottom:16px"><x-icon name="check" /><span>{{ session('status') }}</span></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-error" role="alert" style="margin-bottom:16px">
                <x-icon name="alert" />
                <ul style="margin:0;padding-left:16px">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <section class="trust-strip" aria-label="Why Baking House"><div class="container">
        <div class="trust"><x-icon name="truck" /><div><strong>{{ __('Nationwide delivery') }}</strong><span>{{ __('Courier partners pending') }}</span></div></div>
        <div class="trust"><x-icon name="card" /><div><strong>{{ __('bKash, Nagad, card & bank') }}</strong><span>{{ __('Provider integration pending') }}</span></div></div>
        <div class="trust"><x-icon name="store" /><div><strong>{{ __('Wholesale & business pricing') }}</strong><span>{{ __('For verified accounts') }}</span></div></div>
        <div class="trust"><x-icon name="quote" /><div><strong>{{ __('Request a quote') }}</strong><span>{{ __('Large & regular orders') }}</span></div></div>
    </div></section>
    <footer class="site-footer">
        <div class="container">
            <div>
                <a class="logo" href="{{ route('home') }}"><span class="logo-mark"><x-icon name="logo" class="" /></span><span class="logo-text"><strong>Baking House</strong><span>Bakery · Restaurant · Café · Food Supply</span></span></a>
                <p>{{ __('Complete supply marketplace for bakery, restaurant, café, Chinese kitchen and food businesses in Bangladesh.') }}</p>
            </div>
            <div><h3>{{ __('Shop') }}</h3><ul>
                @foreach ($navigation->take(6) as $navCategory)
                    <li><a href="{{ $navCategory->url() }}">{{ $navCategory->localized('name') }}</a></li>
                @endforeach
            </ul></div>
            <div><h3>{{ __('Business') }}</h3><ul>
                <li><a href="{{ route('organizations.apply', 'business') }}">{{ __('Business accounts') }}</a></li>
                <li><a href="{{ route('organizations.apply', 'reseller') }}">Reseller program</a></li>
            </ul></div>
            <div><h3>{{ __('Help') }}</h3><ul>
                <li><span>{{ __('Delivery policy — pending') }}</span></li>
                <li><span>{{ __('Returns policy — pending') }}</span></li>
                <li><span>{{ __('Privacy policy — pending') }}</span></li>
            </ul></div>
        </div>
        <div class="footer-base"><div class="container" style="display:block">© {{ date('Y') }} Baking House{{ app()->isProduction() ? '' : ' · development build' }}</div></div>
    </footer>

    <nav class="mobile-tabbar" aria-label="Quick navigation">
        <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif><x-icon name="home" />{{ __('Home') }}</a>
        <a href="{{ route('catalog.shop') }}" @if (request()->routeIs('catalog.*')) aria-current="page" @endif><x-icon name="grid" />{{ __('Shop') }}</a>
        <a href="{{ route('catalog.search') }}"><x-icon name="search" />{{ __('Search') }}</a>
        <a href="{{ route('account') }}" @if (request()->routeIs('account')) aria-current="page" @endif><x-icon name="user" />{{ __('Account') }}</a>
    </nav>
</body>
</html>
