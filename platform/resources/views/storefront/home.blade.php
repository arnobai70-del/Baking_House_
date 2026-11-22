@extends('layouts.app')

@section('content')
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-grid">
            <div>
                <h1 id="hero-title" class="reveal">{!! __('Complete supply for bakeries, restaurants, cafés and <em>food businesses</em> in Bangladesh') !!}</h1>
                <p class="hero-lead reveal reveal-2">{{ __('Baking ingredients, cake tools and decorations, restaurant and Chinese kitchen supplies, commercial equipment and food packaging — by the gram, the piece or in bulk.') }}</p>
                <div class="row reveal reveal-3">
                    <a class="btn btn-primary" href="{{ route('catalog.shop') }}">{{ __('Shop now') }}</a>
                    <a class="btn btn-outline" href="{{ route('business.landing') }}">{{ __('Business & bulk buying') }}</a>
                </div>
            </div>
            {{-- Real catalog data instead of an image slot (D2): departments and their live product counts. --}}
            <nav class="dept-panel reveal reveal-2" aria-labelledby="dept-title">
                <div class="dept-head">
                    <h2 id="dept-title">{{ __('Shop by department') }}</h2>
                    <span class="tnum">{{ trans_choice(':count product|:count products', $catalogCount) }}</span>
                </div>
                <ul>
                    @foreach ($categories as $category)
                        <li><a href="{{ $category->url() }}">
                            <span class="dept-icon" aria-hidden="true"><x-icon :name="$category->icon" /></span>
                            <span class="dept-name">{{ $category->localized('name') }}<small>{{ $category->localized('summary') }}</small></span>
                            <span class="dept-count tnum">{{ trans_choice(':count product|:count products', (int) $category->listing_count) }}</span>
                        </a></li>
                    @endforeach
                </ul>
            </nav>
        </div>
    </section>

    <section class="section" aria-labelledby="cat-title">
        <div class="section-head"><h2 id="cat-title">{{ __('Shop by category') }}</h2><a href="{{ route('catalog.shop') }}">{{ __('View all products →') }}</a></div>
        <div class="cat-grid">
            @foreach ($categories as $category)
                <div class="card cat-tile">
                    <span class="cat-icon" aria-hidden="true"><x-icon :name="$category->icon" /></span>
                    <h3><a href="{{ $category->url() }}">{{ $category->localized('name') }}</a></h3>
                    @if ($category->children->isNotEmpty())
                        <ul>
                            @foreach ($category->children->take(3) as $child)
                                <li><a href="{{ $child->url() }}">{{ $child->localized('name') }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="muted small">{{ $category->localized('summary') }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="section" aria-label="{{ __('Shop by business type') }}" style="padding-top:8px">
        <div class="audience">
            @php
                $restaurant = $categories->firstWhere('slug', 'restaurant-supply');
                $bakery = $categories->firstWhere('slug', 'baking-tools');
            @endphp
            <div class="aud-panel aud-restaurant">
                <div><h3>{{ __('For Restaurants & Cafés') }}</h3><p>{{ __('Cookware, Chinese kitchen woks, GN pans, serveware and daily operation supplies.') }}</p>
                    <a class="btn btn-outline" href="{{ $restaurant?->url() ?? route('catalog.shop') }}">{{ __('Explore restaurant supply →') }}</a></div>
            </div>
            <div class="aud-panel aud-bakery">
                <div><h3>{{ __('For Bakeries & Home Bakers') }}</h3><p>{{ __('Ingredients, tools, moulds, chocolate and decoration — by the gram or in bulk.') }}</p>
                    <a class="btn btn-outline" href="{{ $bakery?->url() ?? route('catalog.shop') }}">{{ __('Explore baking supply →') }}</a></div>
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="pop-title">
        <div class="section-head"><h2 id="pop-title">{{ __('Featured products') }}</h2><a href="{{ route('catalog.shop') }}">{{ __('Shop all →') }}</a></div>
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
            <div class="card"><h3>{{ __('Business accounts') }}</h3><p class="muted">{{ __('Verified restaurants, cafés and bakeries get bulk tiers, quotes, repeat orders and team access.') }}</p><a class="btn btn-outline" href="{{ route('organizations.apply', 'business') }}">{{ __('Open a business account') }}</a></div>
            <div class="card"><h3>{{ __('Reseller program') }}</h3><p class="muted">{{ __('Approved resellers get an assigned rate card, bulk reorder tools and statements.') }}</p><a class="btn btn-outline" href="{{ route('organizations.apply', 'reseller') }}">{{ __('Apply as reseller') }}</a></div>
            <div class="card"><h3>{{ __('Request a quote') }}</h3><p class="muted">{{ __('Large equipment, carton packs or regular supply? Ask for a quotation on any product page.') }}</p><a class="btn btn-outline" href="{{ route('catalog.shop') }}">{{ __('Browse products') }}</a></div>
        </div>
    </section>
@endsection
