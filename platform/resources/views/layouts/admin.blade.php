@extends('layouts.app', ['workspace' => 'admin'])

@section('content')
    <div class="admin-layout">
        <nav class="card admin-nav" aria-label="Admin navigation">
            <h4>Overview</h4>
            <a href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif><x-icon name="chart" /> Dashboard</a>
            @can('orders.manage')
                <h4>Orders</h4>
                <a href="{{ route('admin.orders.index') }}" @if (request()->routeIs('admin.orders.index', 'admin.orders.show')) aria-current="page" @endif><x-icon name="cart" /> Orders</a>
                <a href="{{ route('admin.orders.reconciliation') }}" @if (request()->routeIs('admin.orders.reconciliation')) aria-current="page" @endif><x-icon name="card" /> Payment reconciliation</a>
                <a href="{{ route('admin.zones.index') }}" @if (request()->routeIs('admin.zones.*')) aria-current="page" @endif><x-icon name="truck" /> Delivery zones</a>
                <a href="{{ route('admin.cakes.calendar') }}" @if (request()->routeIs('admin.cakes.calendar', 'admin.cakes.pickup')) aria-current="page" @endif><x-icon name="cake" /> Cake calendar &amp; pickup</a>
            @endcan
            @canany(['crm.access', 'reports.view'])
                <h4>Sales &amp; insight</h4>
                @can('crm.access')
                    <a href="{{ route('admin.crm.dashboard') }}" @if (request()->routeIs('admin.crm.*')) aria-current="page" @endif><x-icon name="users" /> CRM</a>
                @endcan
                @can('reports.view')
                    <a href="{{ route('admin.reports') }}" @if (request()->routeIs('admin.reports')) aria-current="page" @endif><x-icon name="chart" /> Reports</a>
                @endcan
            @endcanany
            @can('media.approve')
                <h4>Photography</h4>
                <a href="{{ route('admin.media.queue') }}" @if (request()->routeIs('admin.media.queue')) aria-current="page" @endif><x-icon name="photo" /> Photo review</a>
            @endcan
            @canany(['quotations.manage', 'customers.manage'])
                <h4>Business &amp; resellers</h4>
                @can('quotations.manage')
                    <a href="{{ route('admin.quotations.index') }}" @if (request()->routeIs('admin.quotations.*')) aria-current="page" @endif><x-icon name="quote" /> Quotations (RFQ)</a>
                @endcan
                @can('customers.manage')
                    <a href="{{ route('admin.organizations.index') }}" @if (request()->routeIs('admin.organizations.index', 'admin.organizations.show')) aria-current="page" @endif><x-icon name="users" /> Organizations</a>
                @endcan
            @endcanany
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
                <a href="{{ route('admin.cakes.settings') }}" @if (request()->routeIs('admin.cakes.settings*', 'admin.cakes.options*', 'admin.cakes.products*', 'admin.cakes.simulator', 'admin.cakes.library*')) aria-current="page" @endif><x-icon name="cake" /> Custom cakes</a>
            @endcan
            @can('inventory.manage')
                <h4>Inventory</h4>
                <a href="{{ route('admin.inventory.index') }}" @if (request()->routeIs('admin.inventory.*')) aria-current="page" @endif><x-icon name="box" /> Stock &amp; ledger</a>
            @endcan
            @can('cms.manage')
                <h4>Storefront</h4>
                <a href="{{ route('admin.settings.storefront') }}" @if (request()->routeIs('admin.settings.*')) aria-current="page" @endif><x-icon name="settings" /> Languages</a>
                <a href="{{ route('admin.pages.index') }}" @if (request()->routeIs('admin.pages.*')) aria-current="page" @endif><x-icon name="file" /> Information pages</a>
            @endcan
            <h4>Account</h4>
            <a href="{{ route('account.security') }}" @if (request()->routeIs('account.security')) aria-current="page" @endif><x-icon name="lock" /> Security &amp; MFA</a>
            @can('audit.view')
                <h4>System</h4>
                <a href="{{ route('admin.audit') }}" @if (request()->routeIs('admin.audit')) aria-current="page" @endif><x-icon name="lock" /> Audit log</a>
            @endcan
            @can('roles.manage')
                <a href="{{ route('admin.staff') }}" @if (request()->routeIs('admin.staff')) aria-current="page" @endif><x-icon name="users" /> Staff &amp; roles</a>
            @endcan
        </nav>
        <div class="admin-main reveal" style="min-width:0">
            @yield('admin')
        </div>
    </div>
@endsection
