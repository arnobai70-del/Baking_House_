@extends('layouts.app')

@section('content')
    <div class="admin-layout">
        <nav class="card admin-nav" aria-label="Admin navigation">
            <h4>Overview</h4>
            <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif><x-icon name="chart" /> Dashboard</a>
            @can('catalog.manage')
                <h4>Catalog</h4>
                <a href="{{ route('admin.products.index') }}" @if (request()->routeIs('admin.products.*')) aria-current="page" @endif><x-icon name="tag" /> Products &amp; prices</a>
                <a href="{{ route('admin.categories.index') }}" @if (request()->routeIs('admin.categories.*')) aria-current="page" @endif><x-icon name="grid" /> Categories</a>
                <a href="{{ route('admin.brands.index') }}" @if (request()->routeIs('admin.brands.*')) aria-current="page" @endif><x-icon name="store" /> Brands</a>
            @endcan
            @can('prices.manage')
                <h4>Pricing &amp; offers</h4>
                <a href="{{ route('admin.price-lists.index') }}" @if (request()->routeIs('admin.price-lists.*')) aria-current="page" @endif><x-icon name="tag" /> Price lists</a>
                <a href="{{ route('admin.scheduled-prices.index') }}" @if (request()->routeIs('admin.scheduled-prices.*')) aria-current="page" @endif><x-icon name="chart" /> Scheduled prices</a>
                <a href="{{ route('admin.price-simulator') }}" @if (request()->routeIs('admin.price-simulator')) aria-current="page" @endif><x-icon name="settings" /> Price simulator</a>
                <a href="{{ route('admin.promotions.index') }}" @if (request()->routeIs('admin.promotions.*')) aria-current="page" @endif><x-icon name="quote" /> Coupons &amp; promotions</a>
                <a href="{{ route('admin.bundles.index') }}" @if (request()->routeIs('admin.bundles.*')) aria-current="page" @endif><x-icon name="gift" /> Bundles</a>
            @endcan
            @can('inventory.manage')
                <h4>Inventory</h4>
                <a href="{{ route('admin.inventory.index') }}" @if (request()->routeIs('admin.inventory.*')) aria-current="page" @endif><x-icon name="box" /> Stock &amp; ledger</a>
            @endcan
            @can('cms.manage')
                <h4>Storefront</h4>
                <a href="{{ route('admin.settings.storefront') }}" @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif><x-icon name="settings" /> Languages</a>
            @endcan
            <h4>Account</h4>
            <a href="{{ route('account.security') }}" @if (request()->routeIs('account.security')) aria-current="page" @endif><x-icon name="lock" /> Security &amp; MFA</a>
            @can('audit.view')
                <h4>System</h4>
                <a href="{{ route('admin.audit') }}" @if (request()->routeIs('admin.audit')) aria-current="page" @endif><x-icon name="lock" /> Audit log</a>
            @endcan
        </nav>
        <div style="min-width:0">
            @yield('admin')
        </div>
    </div>
@endsection
