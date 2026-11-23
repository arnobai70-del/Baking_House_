@extends('layouts.app')

{{-- Service facts sit directly under the hero on this page instead of above the footer. --}}
@section('trust-strip-inline', '1')

@section('content')
    {{-- 1. Hero: who we are and what we sell, with the departments as the way in (owner reference, 2026-11-22).
         Owner-supplied AI scene (D-074; IMAGE_CREDITS.md). --}}
    <section class="hero-x" aria-labelledby="hero-title">
        <div class="hero-x-stage">
            @if (is_file(public_path('images/hero/supply-scene-1200.webp')))
                {{-- Owner-supplied AI-generated scene (decision D-074; provenance in IMAGE_CREDITS.md): bakery, restaurant,
                     Chinese kitchen, equipment and packaging in one counter. The LCP image: eager, high priority. --}}
                <div class="hero-x-media">
                    <img class="hero-x-photo" src="{{ asset('images/hero/supply-scene-1200.webp') }}"
                         srcset="{{ asset('images/hero/supply-scene-800.webp') }} 800w, {{ asset('images/hero/supply-scene-1200.webp') }} 1200w, {{ asset('images/hero/supply-scene-1312.webp') }} 1312w"
                         sizes="(max-width: 960px) 100vw, 62vw" width="1312" height="664" fetchpriority="high" decoding="async"
                         alt="{{ __('Commercial kitchen counter with a stand mixer, decorated cake, wok over a flame, combi oven and takeaway packaging') }}">
                </div>
            @endif
            <div class="hero-x-copy">
                <p class="hero-eyebrow reveal">{{ __('Bakery · Restaurant · Café · Commercial kitchen') }}</p>
                <h1 id="hero-title" class="reveal">{!! __('Complete supply for bakeries, restaurants, cafés and <em>food businesses</em> in Bangladesh') !!}</h1>
                {{-- Signature mark: an orange stroke that draws itself once under the headline. --}}
                <svg class="hero-swoosh" viewBox="0 0 240 14" aria-hidden="true" focusable="false"><path d="M2 10 C 60 2, 150 2, 238 7" /></svg>
                <ul class="hero-points reveal reveal-2">
                    <li><x-icon name="check" /> {{ __('Baking ingredients, cake tools and decoration') }}</li>
                    <li><x-icon name="check" /> {{ __('Restaurant and Chinese kitchen supplies') }}</li>
                    <li><x-icon name="check" /> {{ __('Commercial kitchen equipment') }}</li>
                    <li><x-icon name="check" /> {{ __('Packaging, takeaway and catering') }}</li>
                    <li><x-icon name="check" /> {{ __('Buy by the gram, the piece or in bulk, with business and reseller pricing') }}</li>
                </ul>
                <div class="row reveal reveal-3">
                    <a class="btn btn-primary" href="{{ route('catalog.shop') }}"><x-icon name="cart" /> {{ __('Shop now') }}</a>
                    <a class="btn btn-ghost-light" href="{{ route('business.landing') }}"><x-icon name="store" /> {{ __('Business & bulk buying') }}</a>
                </div>
            </div>
        </div>

        {{-- 2. Departments: the one block on the page that lists them (sub-categories live in the mega menu and on
             each department page). Photos are decorative next to the link text. --}}
        <nav class="dept-rail" aria-label="{{ __('Shop by category') }}">
            @foreach ($categories as $category)
                <a class="dept-card" href="{{ $category->url() }}" style="--i: {{ $loop->index }}">
                    @if (is_file(public_path("images/categories/{$category->slug}.webp")))
                        <img src="{{ asset("images/categories/{$category->slug}-400.webp") }}"
                             srcset="{{ asset("images/categories/{$category->slug}-400.webp") }} 400w, {{ asset("images/categories/{$category->slug}-600.webp") }} 600w"
                             sizes="(max-width: 640px) 44vw, (max-width: 1180px) 24vw, 170px" width="400" height="300" decoding="async" alt="">
                    @else
                        <span class="dept-card-icon" aria-hidden="true"><x-icon :name="$category->icon" /></span>
                    @endif
                    <span class="dept-card-body">
                        <span class="dept-card-name">{{ $category->localized('name') }}</span>
                        <span class="dept-card-count tnum">{{ trans_choice(':count product|:count products', (int) $category->listing_count) }}</span>
                    </span>
                    <span class="dept-card-go" aria-hidden="true">→</span>
                </a>
            @endforeach
        </nav>
    </section>

    {{-- 3. Service facts (once; claims stay "pending" until verified). --}}
    @include('storefront.partials.trust-strip')

    {{-- 4. Featured products by department (restaurant and equipment have their own block below: no product twice). --}}
    @if ($departments->isNotEmpty())
        <section class="section" aria-labelledby="pop-title">
            <div class="section-head"><h2 id="pop-title">{{ __('Featured products') }}</h2><a href="{{ route('catalog.shop') }}">{{ __('Shop all →') }}</a></div>
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
        </section>
    @endif

    {{-- 5. Restaurant, café & commercial kitchen: its own products, plus the aisles caterers use most. --}}
    @if ($commercialProducts->isNotEmpty())
        @php
            $aisles = $commercialDepartments->flatMap(fn ($department) => $department->children)
                ->merge($categories->firstWhere('slug', 'packaging')?->children->where('slug', 'food-containers') ?? collect());
        @endphp
        <section class="section" aria-labelledby="commercial-title">
            <div class="commercial">
                <div class="commercial-panel">
                    <img src="{{ asset('images/banners/restaurant-wok-1200.webp') }}"
                         srcset="{{ asset('images/banners/restaurant-wok-600.webp') }} 600w, {{ asset('images/banners/restaurant-wok-1200.webp') }} 1200w"
                         sizes="(max-width: 960px) 100vw, 400px" width="1200" height="800" loading="lazy" decoding="async"
                         alt="{{ __('Cook tossing a black wok over a high flame in a commercial kitchen') }}">
                    <div class="commercial-copy">
                        <h2 id="commercial-title">{{ __('For restaurants, cafés & commercial kitchens') }}</h2>
                        <p>{{ __('Woks and Chinese cookware, GN pans, serving and buffet ware, takeaway containers and commercial equipment.') }}</p>
                        @if ($aisles->isNotEmpty())
                            <ul class="chip-links">
                                @foreach ($aisles as $aisle)
                                    <li><a href="{{ $aisle->url() }}">{{ $aisle->localized('name') }}</a></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
                <div class="grid commercial-grid">
                    @foreach ($commercialProducts as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- 6. A genuine combo, priced on the server. --}}
    @if ($combo)
        <section class="section" aria-labelledby="combo-title">
            <div class="section-head"><h2 id="combo-title">{{ __('Combo offers') }}</h2><a href="{{ route('bundles.index') }}">{{ __('All combos →') }}</a></div>
            @include('storefront.bundles._card', ['bundle' => $combo['bundle'], 'quote' => $combo['quote'], 'headingTag' => 'h3'])
        </section>
    @endif

    {{-- 7. Commercial buyers: entry points to the existing business and reseller services. --}}
    <section class="section" aria-labelledby="b2b-title">
        <div class="b2b-x">
            <div class="b2b-x-intro">
                <h2 id="b2b-title">{{ __('Buying for a business?') }}</h2>
                <p>{{ __('Verified restaurants, cafés and bakeries get bulk tiers, quotes, repeat orders and team access.') }}</p>
            </div>
            <a class="b2b-x-card" href="{{ route('organizations.apply', 'business') }}"><x-icon name="store" /><span><strong>{{ __('Open a business account') }}</strong><small>{{ __('Bulk tiers, quotes and team access') }}</small></span></a>
            <a class="b2b-x-card" href="{{ route('organizations.apply', 'reseller') }}"><x-icon name="pallet" /><span><strong>{{ __('Apply as reseller') }}</strong><small>{{ __('Assigned rate card and bulk reorders') }}</small></span></a>
            <a class="b2b-x-card" href="{{ route('business.landing') }}#rfq"><x-icon name="quote" /><span><strong>{{ __('Request a quote') }}</strong><small>{{ __('Large equipment, carton packs or regular supply') }}</small></span></a>
        </div>
    </section>
@endsection
