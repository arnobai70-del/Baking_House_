@extends('layouts.app')

{{-- Service facts sit directly under the hero on this page instead of above the footer. --}}
@section('trust-strip-inline', '1')

@section('content')
    <section class="hero hero--photo" aria-labelledby="hero-title">
        @if (is_file(public_path('images/hero/kitchen-line-1600.webp')))
            {{-- Licensed scene (Pexels 4947388, IMAGE_CREDITS.md) on the right ~55%; the LCP image: eager, high priority. --}}
            <div class="hero-media">
                <img src="{{ asset('images/hero/kitchen-line-1600.webp') }}"
                     srcset="{{ asset('images/hero/kitchen-line-800.webp') }} 800w, {{ asset('images/hero/kitchen-line-1200.webp') }} 1200w, {{ asset('images/hero/kitchen-line-1600.webp') }} 1600w, {{ asset('images/hero/kitchen-line-2400.webp') }} 2400w"
                     sizes="(max-width: 960px) 100vw, (min-width: 1400px) 740px, 55vw" width="2400" height="1715" fetchpriority="high" decoding="async"
                     alt="{{ __('Commercial restaurant kitchen line with pans, prep stations and stacked plates') }}">
            </div>
        @endif
        <div class="hero-copy">
            <h1 id="hero-title" class="reveal">{!! __('Complete supply for bakeries, restaurants, cafés and <em>food businesses</em> in Bangladesh') !!}</h1>
            <ul class="hero-points reveal reveal-2">
                <li><x-icon name="check" /> {{ __('Baking ingredients, cake tools and decoration') }}</li>
                <li><x-icon name="check" /> {{ __('Restaurant and Chinese kitchen supplies') }}</li>
                <li><x-icon name="check" /> {{ __('Commercial kitchen equipment') }}</li>
                <li><x-icon name="check" /> {{ __('Packaging, takeaway and catering') }}</li>
                <li><x-icon name="check" /> {{ __('Buy by the gram, the piece or in bulk, with business and reseller pricing') }}</li>
            </ul>
            <div class="row reveal reveal-3">
                <a class="btn btn-primary" href="{{ route('catalog.shop') }}">{{ __('Shop now') }}</a>
                <a class="btn btn-outline" href="{{ route('business.landing') }}">{{ __('Business & bulk buying') }}</a>
            </div>
        </div>
    </section>

    @include('storefront.partials.trust-strip')

    <section class="section" aria-labelledby="cat-title">
        <div class="section-head"><h2 id="cat-title">{{ __('Shop by category') }}</h2><a href="{{ route('catalog.shop') }}">{{ __('View all products →') }}</a></div>
        <div class="cat-grid">
            @foreach ($categories as $category)
                <div class="card cat-tile">
                    @if (is_file(public_path("images/categories/{$category->slug}.webp")))
                        {{-- Licensed photo of the department's typical items (IMAGE_CREDITS.md); decorative next to the title link. --}}
                        <div class="cat-media">
                            <img class="cat-photo" src="{{ asset("images/categories/{$category->slug}.webp") }}"
                                 srcset="{{ asset("images/categories/{$category->slug}-400.webp") }} 400w, {{ asset("images/categories/{$category->slug}-600.webp") }} 600w, {{ asset("images/categories/{$category->slug}.webp") }} 800w"
                                 sizes="(max-width: 640px) 50vw, (max-width: 1180px) 25vw, 180px" width="800" height="600" loading="lazy" decoding="async" alt="">
                            <span class="cat-badge" aria-hidden="true"><x-icon :name="$category->icon" /></span>
                        </div>
                    @else
                        <span class="cat-icon" aria-hidden="true"><x-icon :name="$category->icon" /></span>
                    @endif
                    <h3><a href="{{ $category->url() }}">{{ $category->localized('name') }}</a></h3>
                    <p class="cat-count tnum">{{ trans_choice(':count product|:count products', (int) $category->listing_count) }}</p>
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

    <section class="section" aria-labelledby="pop-title">
        <div class="section-head"><h2 id="pop-title">{{ __('Featured products') }}</h2><a href="{{ route('catalog.shop') }}">{{ __('Shop all →') }}</a></div>
        @if ($departments->isEmpty())
            <div class="card empty-state"><x-icon name="box" /><p><strong>{{ __('No products published yet') }}</strong></p></div>
        @else
            {{-- One tab per department; without JavaScript every panel stays visible under its own heading.
                 The tab that opens first is the department with the most photographed products. --}}
            @php $openSlug = $departments->sortByDesc('photographed')->first()['category']->slug; @endphp
            <div class="dept-tabs" data-tabs>
                <div class="tabs" role="tablist" aria-label="{{ __('Departments') }}" hidden data-tablist>
                    @foreach ($departments as $shelf)
                        <button class="tab" role="tab" type="button" id="dtab-{{ $shelf['category']->slug }}" aria-controls="dpanel-{{ $shelf['category']->slug }}"
                                aria-selected="{{ $shelf['category']->slug === $openSlug ? 'true' : 'false' }}" tabindex="{{ $shelf['category']->slug === $openSlug ? 0 : -1 }}">
                            <x-icon :name="$shelf['category']->icon" /> {{ $shelf['category']->localized('name') }}
                        </button>
                    @endforeach
                </div>
                @foreach ($departments as $shelf)
                    <div class="dept-panel" role="tabpanel" id="dpanel-{{ $shelf['category']->slug }}" aria-labelledby="dtab-{{ $shelf['category']->slug }}">
                        <h3 class="dept-panel-title">{{ $shelf['category']->localized('name') }}</h3>
                        <div class="grid">
                            @foreach ($shelf['products'] as $product)
                                <x-product-card :product="$product" />
                            @endforeach
                        </div>
                        <p class="dept-more"><a href="{{ $shelf['category']->url() }}">{{ __('All :name products →', ['name' => $shelf['category']->localized('name')]) }}</a></p>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="section" aria-label="{{ __('Shop by business type') }}">
        <div class="audience">
            @php
                $restaurant = $categories->firstWhere('slug', 'restaurant-supply');
                $bakery = $categories->firstWhere('slug', 'baking-tools');
                $packaging = $categories->firstWhere('slug', 'packaging');
                $cateringLinks = collect([$restaurant?->children->firstWhere('slug', 'serving-buffet'), $packaging?->children->firstWhere('slug', 'food-containers'), $packaging?->children->firstWhere('slug', 'cake-boxes')])->filter();
            @endphp
            {{-- Licensed photos (IMAGE_CREDITS.md) on the right half, fading into the panel colour; on phones they sit on top. --}}
            <div class="aud-panel aud-restaurant">
                <div class="aud-media"><img src="{{ asset('images/banners/restaurant-wok-1200.webp') }}"
                    srcset="{{ asset('images/banners/restaurant-wok-600.webp') }} 600w, {{ asset('images/banners/restaurant-wok-1200.webp') }} 1200w"
                    sizes="(max-width: 960px) 100vw, 340px" width="1200" height="800" loading="lazy" decoding="async"
                    alt="{{ __('Cook tossing a black wok over a high flame in a commercial kitchen') }}"></div>
                <div class="aud-copy"><h3>{{ __('For Restaurants & Cafés') }}</h3><p>{{ __('Cookware, Chinese kitchen woks, GN pans, serveware and daily operation supplies.') }}</p>
                    <a class="btn btn-outline" href="{{ $restaurant?->url() ?? route('catalog.shop') }}">{{ __('Explore restaurant supply →') }}</a></div>
            </div>
            <div class="aud-panel aud-bakery">
                <div class="aud-media"><img src="{{ asset('images/banners/bakery-cake-1200.webp') }}"
                    srcset="{{ asset('images/banners/bakery-cake-600.webp') }} 600w, {{ asset('images/banners/bakery-cake-1200.webp') }} 1200w"
                    sizes="(max-width: 960px) 100vw, 340px" width="1200" height="800" loading="lazy" decoding="async"
                    alt="{{ __('Pastry chef finishing a chocolate drip cake on a cake turntable') }}"></div>
                <div class="aud-copy"><h3>{{ __('For Bakeries & Home Bakers') }}</h3><p>{{ __('Ingredients, tools, moulds, chocolate and decoration — by the gram or in bulk.') }}</p>
                    <a class="btn btn-outline" href="{{ $bakery?->url() ?? route('catalog.shop') }}">{{ __('Explore baking supply →') }}</a></div>
            </div>
        </div>

        @if ($cateringLinks->isNotEmpty())
            {{-- Café / catering collection: links to the real sub-categories that serve it. --}}
            <div class="catering-band">
                <img src="{{ asset('images/banners/catering-buffet-1200.webp') }}"
                     srcset="{{ asset('images/banners/catering-buffet-600.webp') }} 600w, {{ asset('images/banners/catering-buffet-1200.webp') }} 1200w"
                     sizes="(max-width: 960px) 100vw, 480px" width="1200" height="800" loading="lazy" decoding="async"
                     alt="{{ __('Pastries served in stainless steel buffet trays at a catered event') }}">
                <div class="catering-copy">
                    <h3>{{ __('Café, catering & takeaway') }}</h3>
                    <p>{{ __('Serving and buffet ware, food containers and cake boxes for cafés, caterers and takeaway kitchens.') }}</p>
                    <ul class="chip-links">
                        @foreach ($cateringLinks as $link)
                            <li><a href="{{ $link->url() }}">{{ $link->localized('name') }} →</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </section>

    @if ($combo)
        <section class="section" aria-labelledby="combo-title">
            <div class="section-head"><h2 id="combo-title">{{ __('Combo offers') }}</h2><a href="{{ route('bundles.index') }}">{{ __('All combos →') }}</a></div>
            @include('storefront.bundles._card', ['bundle' => $combo['bundle'], 'quote' => $combo['quote'], 'headingTag' => 'h3'])
        </section>
    @endif

    <section class="section" aria-labelledby="b2b-title">
        <div class="section-head"><h2 id="b2b-title">{{ __('Buying for a business?') }}</h2></div>
        <div class="b2b-band">
            <div class="card"><span class="b2b-icon" aria-hidden="true"><x-icon name="store" /></span><h3>{{ __('Business accounts') }}</h3><p class="muted">{{ __('Verified restaurants, cafés and bakeries get bulk tiers, quotes, repeat orders and team access.') }}</p><a class="btn btn-outline" href="{{ route('organizations.apply', 'business') }}">{{ __('Open a business account') }}</a></div>
            <div class="card"><span class="b2b-icon" aria-hidden="true"><x-icon name="pallet" /></span><h3>{{ __('Reseller program') }}</h3><p class="muted">{{ __('Approved resellers get an assigned rate card, bulk reorder tools and statements.') }}</p><a class="btn btn-outline" href="{{ route('organizations.apply', 'reseller') }}">{{ __('Apply as reseller') }}</a></div>
            <div class="card"><span class="b2b-icon" aria-hidden="true"><x-icon name="quote" /></span><h3>{{ __('Request a quote') }}</h3><p class="muted">{{ __('Large equipment, carton packs or regular supply? Ask for a quotation on any product page.') }}</p><a class="btn btn-outline" href="{{ route('catalog.shop') }}">{{ __('Browse products') }}</a></div>
        </div>
    </section>
@endsection
