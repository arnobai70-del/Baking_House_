@extends('layouts.app')

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol>
        <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
        <li><a href="{{ route('bundles.index') }}">{{ __('Combo & bundle offers') }}</a></li>
        <li aria-current="page">{{ $bundle->localized('name') }}</li>
    </ol></nav>
    @include('storefront.bundles._card', ['bundle' => $bundle, 'quote' => $quote])
    <p class="hint" style="margin-top:12px">{{ __('Prices are re-checked on the server when the order is placed.') }}</p>
@endsection
