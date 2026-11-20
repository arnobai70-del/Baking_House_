@extends('layouts.app')

@section('content')
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-grid">
            <div>
                <h1 id="hero-title" class="reveal">{!! __('Complete Supply Solution for Bakery, Restaurant, <em>Café &amp; Food Businesses</em> in Bangladesh') !!}</h1>
                <ul class="reveal reveal-2">
                    <li><x-icon name="check" />{{ __('Baking ingredients & cake supplies') }}</li>
                    <li><x-icon name="check" />{{ __('Restaurant, café & Chinese kitchen supplies') }}</li>
                    <li><x-icon name="check" />{{ __('Commercial kitchen equipment') }}</li>
                    <li><x-icon name="check" />{{ __('Packaging & takeaway solutions') }}</li>
                    <li><x-icon name="check" />{{ __('Bulk & wholesale for businesses') }}</li>
                </ul>
                <div class="row reveal reveal-3">
                    <a class="btn btn-primary" href="{{ route('catalog.shop') }}">{{ __('Shop now') }}</a>
                    <a class="btn btn-outline" href="{{ route('business.landing') }}">{{ __('Business & bulk buying') }}</a>
                </div>
            </div>
            <div class="reveal reveal-2"><x-photo-placeholder :label="__('Licensed hero photo pending')" class="ph-wide" /></div>
        </div>
    </section>

    <section class="section" aria-labelledby="cat-title">
        <div class="section-head"><h2 id="cat-title">{{ __('Shop by category') }}</h2><a href="{{ route('catalog.shop') }}">{{ __('View all products →') }}</a></div>
        <div class="cat-grid">
            @foreach ($categories as $category)
                <a class="card cat-tile" href="{{ $category->url() }}">
                    <x-photo-placeholder :icon="$category->icon" :label="__('Pending')" />
                    <span>{{ $category->localized('name') }}<small>{{ $category->localized('summary') }}</small></span>
                </a>
            @endforeach
        </div>
    </section>

    <section class="section" aria-label="Shop by business type" style="padding-top:8px">
        <div class="audience">
            @php
                $restaurant = $categories->firstWhere('slug', 'restaurant-supply');
                $bakery = $categories->firstWhere('slug', 'baking-tools');
            @endphp
            <div class="aud-panel aud-restaurant">
                <div><h3>{{ __('For Restaurants & Cafés') }}</h3><p>{{ __('Cookware, Chinese kitchen woks, GN pans, serveware and daily operation supplies.') }}</p>
                    <a class="btn btn-primary" href="{{ $restaurant?->url() ?? route('catalog.shop') }}">{{ __('Explore restaurant supply →') }}</a></div>
                <x-photo-placeholder icon="wok" />
            </div>
            <div class="aud-panel aud-bakery">
                <div><h3>{{ __('For Bakeries & Home Bakers') }}</h3><p>{{ __('Ingredients, tools, moulds, chocolate and decoration — by the gram or in bulk.') }}</p>
                    <a class="btn btn-primary" href="{{ $bakery?->url() ?? route('catalog.shop') }}">{{ __('Explore baking supply →') }}</a></div>
                <x-photo-placeholder icon="cake" />
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="pop-title">
        <div class="section-head"><h2 id="pop-title">{{ __('Popular products') }}</h2><a href="{{ route('catalog.shop') }}">{{ __('Shop all →') }}</a></div>
        @if ($featured->isEmpty())
            <div class="card empty-state"><x-icon name="box" /><p><strong>{{ __('No products published yet') }}</strong></p></div>
        @else
            <div class="grid">
                @foreach ($featured as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>

    <section class="section" aria-labelledby="b2b-title">
        <div class="section-head"><h2 id="b2b-title">{{ __('Buying for a business?') }}</h2></div>
        <div class="b2b-band">
            <div class="card"><h3>{{ __('Business accounts') }}</h3><p class="muted">{{ __('Verified restaurants, cafés and bakeries get bulk tiers, quotes, repeat orders and team access.') }}</p><a class="btn btn-teal" href="{{ route('organizations.apply', 'business') }}">{{ __('Open a business account') }}</a></div>
            <div class="card navy"><h3>{{ __('Reseller program') }}</h3><p class="muted">{{ __('Approved resellers get an assigned rate card, bulk reorder tools and statements.') }}</p><a class="btn btn-navy" href="{{ route('organizations.apply', 'reseller') }}">{{ __('Apply as reseller') }}</a></div>
            <div class="card orange"><h3>{{ __('Request a quote') }}</h3><p class="muted">{{ __('Large equipment, carton packs or regular supply? Ask for a quotation on any product page.') }}</p><a class="btn btn-primary" href="{{ route('catalog.shop') }}">{{ __('Browse products') }}</a></div>
        </div>
    </section>
@endsection
