@extends('layouts.app')

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol><li><a href="{{ route('home') }}">{{ __('Home') }}</a></li><li aria-current="page">{{ __('Combo & bundle offers') }}</li></ol></nav>
    <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Combo & bundle offers') }}</h1>
    <p class="muted">{{ __('Curated kits for bakeries and restaurants. Normal price and savings are shown openly; bundle discounts do not combine with coupons or trade prices.') }}</p>
    <div class="stack">
        @forelse ($bundles as $entry)
            @include('storefront.bundles._card', ['bundle' => $entry['bundle'], 'quote' => $entry['quote']])
        @empty
            <div class="card empty-state"><x-icon name="gift" /><p><strong>{{ __('No bundles on offer right now') }}</strong></p></div>
        @endforelse
    </div>
@endsection
