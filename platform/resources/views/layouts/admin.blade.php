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
