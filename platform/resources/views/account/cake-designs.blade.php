@extends('layouts.app', ['title' => __('My cake designs')])

@use('App\Models\CakeDesign')
@use('App\Support\Taka')
@use('App\Support\Weight')

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol>
        <li><a href="{{ route('account') }}">{{ __('My account') }}</a></li>
        <li aria-current="page">{{ __('My cake designs') }}</li>
    </ol></nav>
    <div class="row" style="justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap">
        <div>
            <h1 style="font-size:var(--bh-fs-2xl);margin-bottom:4px">{{ __('My cake designs') }}</h1>
            <p class="muted" style="margin:0">{{ __('Open a design to change it, share a link (your photos stay private), or order a cake you loved again.') }}</p>
        </div>
        <a class="btn btn-primary" href="{{ route('cakes.designer') }}"><x-icon name="cake" /> {{ __('Design a new cake') }}</a>
    </div>

    <div class="design-grid reveal" style="margin-top:16px">
        @forelse ($designs as $design)
            @php
                $status = match ($design->status) { CakeDesign::ORDERED => [__('Ordered'), 'ok'], CakeDesign::IN_CART => [__('In cart'), 'info'], default => [__('Saved'), ''] };
                $spec = (array) $design->spec;
            @endphp
            <article class="card design-card">
                @if ($design->snapshot_path)
                    <img src="{{ route('cakes.design-image', [$design->uuid, 'snapshot']) }}" alt="{{ __('Preview of :name', ['name' => $design->name ?: __('your cake design')]) }}" width="320" height="240" loading="lazy">
                @else
                    <x-photo-placeholder icon="cake" />
                @endif
                <div class="card-pad">
                    <div class="row" style="justify-content:space-between;gap:8px">
                        <h2 style="font-size:var(--bh-fs-md);margin:0">{{ $design->name ?: $design->variant?->product?->localized('name') }}</h2>
                        <span class="status {{ $status[1] }}">{{ $status[0] }}</span>
                    </div>
                    <p class="small muted" style="margin:4px 0 10px">{{ Weight::format((int) ($spec['grams'] ?? 0)) }} · {{ trans_choice(':count tier|:count tiers', (int) ($spec['tiers'] ?? 1)) }}
                        @if ($design->price_snapshot) · <span class="tnum">{{ Taka::format((int) $design->price_snapshot['line_paisa']) }}</span>@endif
                        · {{ $design->updated_at->timezone('Asia/Dhaka')->translatedFormat('j M Y') }}</p>
                    <div class="row" style="gap:6px;flex-wrap:wrap">
                        @if ($design->status !== CakeDesign::ORDERED)
                            <a class="btn btn-navy btn-sm" href="{{ route('cakes.designer', ['design' => $design->uuid]) }}">{{ __('Open') }}</a>
                        @endif
                        <form method="POST" action="{{ route('cakes.duplicate', $design->uuid) }}">@csrf
                            <button class="btn {{ $design->status === CakeDesign::ORDERED ? 'btn-primary' : 'btn-outline' }} btn-sm" type="submit">{{ $design->status === CakeDesign::ORDERED ? __('Order again') : __('Duplicate') }}</button>
                        </form>
                        @if ($design->share_token)
                            <a class="btn btn-ghost btn-sm" href="{{ route('cakes.shared', $design->share_token) }}">{{ __('Share link') }}</a>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="card"><div class="empty-state"><x-icon name="cake" /><p><strong>{{ __('No cake designs yet') }}</strong></p>
                <p class="muted small">{{ __('Designs you save or order appear here.') }}</p></div></div>
        @endforelse
    </div>
@endsection
