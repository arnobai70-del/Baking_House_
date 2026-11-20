@extends('layouts.app')

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol>
        <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
        <li aria-current="page">{{ $page->localized('title') }}</li>
    </ol></nav>
    <article class="card card-pad prose reveal" style="max-width:820px">
        <h1>{{ $page->localized('title') }}</h1>
        {!! $page->html() !!}
        <p class="hint" style="margin-top:24px">{{ __('Last updated :date', ['date' => $page->updated_at->timezone('Asia/Dhaka')->format('d M Y')]) }}</p>
    </article>
@endsection
