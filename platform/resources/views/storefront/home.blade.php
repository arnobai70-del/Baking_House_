@extends('layouts.app')

@section('content')
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-grid">
            <div>
                <h1 id="hero-title" class="reveal">Complete Supply Solution for Bakery, Restaurant, <em>Café &amp; Food Businesses</em> in Bangladesh</h1>
                <ul class="reveal reveal-2">
                    <li><x-icon name="check" />Baking ingredients &amp; cake supplies</li>
                    <li><x-icon name="check" />Restaurant, café &amp; Chinese kitchen supplies</li>
                    <li><x-icon name="check" />Commercial kitchen equipment</li>
                    <li><x-icon name="check" />Packaging &amp; takeaway solutions</li>
                    <li><x-icon name="check" />Bulk &amp; wholesale for businesses</li>
                </ul>
                <div class="row reveal reveal-3">
                    <a class="btn btn-primary" href="{{ route('catalog.shop') }}">Shop now</a>
                    <a class="btn btn-outline" href="{{ route('organizations.apply', 'business') }}">Business &amp; bulk buying</a>
                </div>
            </div>
            <div class="reveal reveal-2"><x-photo-placeholder label="Licensed hero photo pending" class="ph-wide" /></div>
        </div>
    </section>

    <section class="section" aria-labelledby="cat-title">
        <div class="section-head"><h2 id="cat-title">Shop by category</h2><a href="{{ route('catalog.shop') }}">View all products →</a></div>
        <div class="cat-grid">
            @foreach ($categories as $category)
                <a class="card cat-tile" href="{{ $category->url() }}">
                    <x-photo-placeholder :icon="$category->icon" label="Pending" />
                    <span>{{ $category->name }}<small>{{ $category->summary }}</small></span>
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
                <div><h3>For Restaurants &amp; Cafés</h3><p>Cookware, Chinese kitchen woks, GN pans, serveware and daily operation supplies.</p>
                    <a class="btn btn-primary" href="{{ $restaurant?->url() ?? route('catalog.shop') }}">Explore restaurant supply →</a></div>
                <x-photo-placeholder icon="wok" />
            </div>
            <div class="aud-panel aud-bakery">
                <div><h3>For Bakeries &amp; Home Bakers</h3><p>Ingredients, tools, moulds, chocolate and decoration — by the gram or in bulk.</p>
                    <a class="btn btn-primary" href="{{ $bakery?->url() ?? route('catalog.shop') }}">Explore baking supply →</a></div>
                <x-photo-placeholder icon="cake" />
            </div>
        </div>
    </section>

    <section class="section" aria-labelledby="pop-title">
        <div class="section-head"><h2 id="pop-title">Popular products</h2><a href="{{ route('catalog.shop') }}">Shop all →</a></div>
        @if ($featured->isEmpty())
            <div class="card empty-state"><x-icon name="box" /><p><strong>No products published yet</strong></p></div>
        @else
            <div class="grid">
                @foreach ($featured as $product)
                    <x-product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>

    <section class="section" aria-labelledby="b2b-title">
        <div class="section-head"><h2 id="b2b-title">Buying for a business?</h2></div>
        <div class="b2b-band">
            <div class="card"><h3>Business accounts</h3><p class="muted">Verified restaurants, cafés and bakeries get bulk tiers, quotes, repeat orders and team access.</p><a class="btn btn-teal" href="{{ route('organizations.apply', 'business') }}">Open a business account</a></div>
            <div class="card navy"><h3>Reseller program</h3><p class="muted">Approved resellers get an assigned rate card, bulk reorder tools and statements.</p><a class="btn btn-navy" href="{{ route('organizations.apply', 'reseller') }}">Apply as reseller</a></div>
            <div class="card orange"><h3>Request a quote</h3><p class="muted">Large equipment, carton packs or regular supply? Ask for a quotation on any product page.</p><a class="btn btn-primary" href="{{ route('catalog.shop') }}">Browse products</a></div>
        </div>
    </section>
@endsection
