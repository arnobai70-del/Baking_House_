@extends('layouts.app')

@php
    $active = fn (string $key) => (array) ($filters[$key] ?? []);
    $hasFilters = collect($filters)->except(['view', 'sort'])->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->isNotEmpty();
@endphp

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb"><ol>
        <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
        <li><a href="{{ route('catalog.shop') }}">{{ __('Shop') }}</a></li>
        @foreach ($trail as $crumb)
            <li @if ($loop->last) aria-current="page" @endif>
                @if ($loop->last){{ $crumb->localized('name') }}@else<a href="{{ $crumb->url() }}">{{ $crumb->localized('name') }}</a>@endif
            </li>
        @endforeach
    </ol></nav>

    @if ($category)
        <header class="card category-head">
            <span class="category-icon"><x-icon :name="$category->icon" class="icon icon-lg" /></span>
            <div>
                <h1>{{ $category->localized('name') }}</h1>
                @if ($category->localized('summary'))<p class="muted">{{ $category->localized('summary') }}</p>@endif
                @if ($facets['categories']->isNotEmpty())
                    <div class="subcats">
                        @foreach ($facets['categories'] as $child)
                            <a class="fchip" href="{{ $child->url() }}">{{ $child->localized('name') }} <span>({{ $child->listing_count }})</span></a>
                        @endforeach
                    </div>
                @endif
            </div>
        </header>
    @endif

    <div class="shop-layout">
        <aside class="card filters" id="filters" aria-label="Filters">
            <div class="row" style="margin-bottom:12px">
                <h2 style="font-size:var(--bh-fs-lg);margin:0">{{ __('Filter products') }}</h2>
                <span class="spacer"></span>
                <button class="icon-btn filter-toggle" data-filters-close aria-label="Close filters"><x-icon name="x" /></button>
            </div>
            <form method="GET" action="{{ url()->current() }}" id="filter-form">
                @if (! empty($filters['q']))<input type="hidden" name="q" value="{{ $filters['q'] }}">@endif
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'popular' }}">
                <input type="hidden" name="view" value="{{ $view }}">

                @if (! $category && $facets['categories']->isNotEmpty())
                    <fieldset><legend>{{ __('Category') }}</legend>
                        @foreach ($facets['categories'] as $facet)
                            <label class="check"><input type="checkbox" name="category[]" value="{{ $facet->slug }}" @checked(in_array($facet->slug, $active('category'), true))> {{ $facet->localized('name') }}<span class="count">{{ $facet->listing_count }}</span></label>
                        @endforeach
                    </fieldset>
                @endif

                <fieldset><legend>{{ __('Price range (৳)') }}</legend>
                    <div class="range-row">
                        <label class="sr-only" for="f-min">Minimum price</label>
                        <input class="input" id="f-min" name="min" inputmode="numeric" placeholder="0" value="{{ $filters['min'] ?? '' }}">
                        <span>–</span>
                        <label class="sr-only" for="f-max">Maximum price</label>
                        <input class="input" id="f-max" name="max" inputmode="numeric" placeholder="{{ __('Any') }}" value="{{ $filters['max'] ?? '' }}">
                    </div>
                    <p class="hint">{{ __('Weight items compare by price per kg.') }}</p>
                </fieldset>

                @if ($facets['brands']->isNotEmpty())
                    <fieldset><legend>{{ __('Brand') }}</legend>
                        @foreach ($facets['brands'] as $brand)
                            <label class="check"><input type="checkbox" name="brand[]" value="{{ $brand->slug }}" @checked(in_array($brand->slug, $active('brand'), true))> {{ $brand->name }}<span class="count">{{ $brand->listing_count }}</span></label>
                        @endforeach
                    </fieldset>
                @endif

                <fieldset><legend>{{ __('Availability') }}</legend>
                    <label class="check"><input type="checkbox" name="in_stock" value="1" @checked(! empty($filters['in_stock']))> {{ __('In stock only') }}</label>
                    <label class="check"><input type="checkbox" name="weight" value="1" @checked(! empty($filters['weight']))> {{ __('Sold by weight (custom grams)') }}</label>
                </fieldset>

                <button class="btn btn-primary btn-block" type="submit">{{ __('Apply filters') }}</button>
                @if ($hasFilters)
                    <a class="btn btn-ghost btn-block" href="{{ url()->current() }}{{ ! empty($filters['q']) ? '?q='.urlencode($filters['q']) : '' }}">{{ __('Clear all filters') }}</a>
                @endif
            </form>
        </aside>

        <section aria-labelledby="shop-title">
            <div class="card toolbar">
                <button class="btn btn-outline btn-sm filter-toggle" data-filters-open aria-controls="filters"><x-icon name="filter" /> {{ __('Filters') }}</button>
                @if ($category)
                    <h2 id="shop-title" style="font-size:var(--bh-fs-lg);margin:0">{{ __('Products') }}</h2>
                @else
                    <h1 id="shop-title">{{ $heading }}</h1>
                @endif
                <span class="muted small" aria-live="polite">{{ trans_choice(':count product|:count products', $products->total()) }}</span>
                <span class="spacer"></span>
                <form method="GET" action="{{ url()->current() }}" class="row" style="gap:8px" data-autosubmit>
                    @foreach (collect($filters)->except(['sort']) as $key => $value)
                        @foreach ((array) $value as $item)
                            <input type="hidden" name="{{ is_array($value) ? $key.'[]' : $key }}" value="{{ $item }}">
                        @endforeach
                    @endforeach
                    <label class="small" for="sort">{{ __('Sort by') }}</label>
                    <select class="input" id="sort" name="sort" style="width:auto;min-height:36px">
                        @foreach ($sorts as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['sort'] ?? 'popular') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="btn btn-outline btn-sm">Sort</button></noscript>
                </form>
                <div class="row" style="gap:2px" role="group" aria-label="Layout">
                    <a class="icon-btn" href="{{ request()->fullUrlWithQuery(['view' => null, 'page' => null]) }}" aria-label="Grid view" aria-pressed="{{ $view === 'grid' ? 'true' : 'false' }}"><x-icon name="grid" /></a>
                    <a class="icon-btn" href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => null]) }}" aria-label="List view" aria-pressed="{{ $view === 'list' ? 'true' : 'false' }}"><x-icon name="list" /></a>
                </div>
            </div>

            <div class="{{ $view === 'list' ? 'list-view' : '' }}">
                @if ($products->isEmpty())
                    <div class="card empty-state">
                        <x-icon name="search" />
                        <h2 style="font-size:var(--bh-fs-lg)">{{ __('No products match') }}</h2>
                        <p class="muted">{{ __('Try fewer filters or a broader search like “chocolate”, “wok” or “packaging”.') }}</p>
                        <div class="row" style="justify-content:center">
                            <a class="btn btn-outline" href="{{ url()->current() }}">{{ __('Clear filters') }}</a>
                            <a class="btn btn-primary" href="{{ route('organizations.apply', 'business') }}">{{ __('Need it in bulk? Ask for a quote') }}</a>
                        </div>
                    </div>
                @else
                    <div class="grid grid-4">
                        @foreach ($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>
                    <div class="pager">{{ $products->onEachSide(1)->links('storefront.partials.pagination') }}</div>
                @endif
            </div>

            @if ($category?->description && $products->currentPage() === 1)
                <section class="card card-pad seo-copy" aria-label="About {{ $category->name }}">
                    <h2 style="font-size:var(--bh-fs-lg)">{{ __('About :name', ['name' => $category->localized('name')]) }}</h2>
                    <p>{{ $category->localized('description') }}</p>
                </section>
            @endif
        </section>
    </div>
    <div class="drawer-overlay" id="filters-overlay"></div>
@endsection
