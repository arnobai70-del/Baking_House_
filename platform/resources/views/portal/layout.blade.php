@extends('layouts.app', ['title' => $heading.' · '.$organization->name, 'workspace' => $portal])

@php
    $isBusiness = $portal === 'business';
    $nav = array_values(array_filter([
        ['overview', 'dashboard', 'chart', __('Overview')],
        ['orders', 'orders', 'list', __('Orders')],
        ['bulk', 'bulk', 'pallet', __('Bulk order')],
        $isBusiness ? ['quotations', 'quotations', 'quote', __('Quotations')] : null,
        ['prices', 'prices', 'tag', $isBusiness ? __('Business pricing') : __('My rate card')],
        ['addresses', 'addresses', 'truck', __('Delivery addresses')],
        ['team', 'team', 'users', __('Team & access')],
        ['statements', 'statements', 'file', __('Invoices & statements')],
    ]));
    $initials = collect(preg_split('/\s+/', trim($organization->name)))->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
@endphp

@section('content')
    <div class="dash portal portal-{{ $portal }}">
        <aside class="card dash-side" aria-label="{{ __('Dashboard navigation') }}">
            <div class="dash-id">
                <span class="avatar" aria-hidden="true" @if ($isBusiness) style="background:var(--bh-teal)" @endif>{{ $initials }}</span>
                <div><strong>{{ $organization->name }}</strong>
                    <div class="muted small">{{ $isBusiness ? __('Business account') : __('Reseller account') }} · {{ __($role->label()) }}</div></div>
            </div>
            <nav>
                @foreach ($nav as [$id, $routeName, $icon, $label])
                    <a href="{{ route($portal.'.'.$routeName) }}" aria-current="{{ $section === $id ? 'true' : 'false' }}"><x-icon :name="$icon" /> {{ $label }}</a>
                @endforeach
                <a href="{{ route('cart.show') }}"><x-icon name="cart" /> {{ __('Cart') }}</a>
            </nav>
        </aside>
        <section class="portal-main">
            <div class="admin-top page-head reveal">
                <div><h1>{{ $heading }}</h1><p class="muted small">{{ $subheading ?? ($isBusiness
                    ? __('Bulk orders, quotations, prices and team for :organization', ['organization' => $organization->name])
                    : __('Your assigned rate card, bulk reorders and statements. Not visible to any other account.')) }}</p></div>
            </div>
            @if ($role === \App\Enums\OrganizationRole::Viewer)
                <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="lock" /><span>{{ __('You have view-only access to :organization: you can see orders, prices and statements but cannot order.', ['organization' => $organization->name]) }}</span></div>
            @endif
            <div class="portal-body reveal reveal-2">
                @yield('portal')
            </div>
        </section>
    </div>
@endsection
