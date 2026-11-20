@extends('layouts.app')

@section('content')
    @php $isBusiness = $type === \App\Enums\OrganizationType::Business; @endphp
    <section class="hero" aria-labelledby="landing-title">
        <div class="hero-grid">
            <div>
                <span class="eyebrow" style="color:#9fd6db">{{ $isBusiness ? __('For restaurants, cafés, bakeries & caterers') : __('For shops and distributors') }}</span>
                <h1 id="landing-title" class="reveal" style="margin-top:8px">{{ $isBusiness ? __('Business pricing, bulk orders & quotes') : __('Reseller rate card & bulk reorders') }}</h1>
                <ul class="reveal reveal-2">
                    @foreach ($isBusiness
                        ? [__('Verified organization account with protected pricing'), __('Bulk tiers on total weight, e.g. chocolate from 5 kg'), __('Request quotations for equipment and carton packs'), __('Repeat orders, invoices and team members')]
                        : [__('Your own assigned rate card, visible only to your account'), __('Bulk upload orders by SKU or CSV'), __('Monthly statements and price list export'), __('Team members with view-only or buyer roles')] as $point)
                        <li><x-icon name="check" />{{ $point }}</li>
                    @endforeach
                </ul>
                <div class="row reveal reveal-3">
                    @if ($membership)
                        <a class="btn btn-primary" href="{{ route($type->value.'.dashboard') }}">{{ __('Open your dashboard') }}</a>
                    @else
                        <a class="btn btn-primary" href="{{ route('organizations.apply', $type->value) }}">{{ $isBusiness ? __('Apply for a business account') : __('Apply as reseller') }}</a>
                    @endif
                    @if ($isBusiness)<a class="btn btn-outline" href="#rfq">{{ __('Request a quote') }}</a>@endif
                </div>
            </div>
            <div class="reveal reveal-2"><x-photo-placeholder :icon="$isBusiness ? 'store' : 'pallet'" :label="__('Licensed photo pending')" class="ph-wide" /></div>
        </div>
    </section>

    <section class="section portal-steps">
        @foreach ([
            ['user', __('Apply'), __('Tell us about your business. Our team reviews every application manually.')],
            ['check', __('Get approved'), __('Pricing is shown only after approval, never to other accounts.')],
            [$isBusiness ? 'quote' : 'tag', $isBusiness ? __('Order or request a quote') : __('Order at your rates'), $isBusiness ? __('Buy at your business prices or send an RFQ for special items.') : __('Bulk upload SKUs; your rate card applies automatically.')],
        ] as $index => [$icon, $title, $text])
            <div class="card card-pad reveal" style="animation-delay:{{ $index * 80 }}ms"><x-icon :name="$icon" /><h2 style="font-size:var(--bh-fs-lg);margin:8px 0 4px">{{ $title }}</h2><p class="muted small" style="margin:0">{{ $text }}</p></div>
        @endforeach
    </section>

    @if ($isBusiness)
        <section class="section" id="rfq" style="max-width:720px">
            <div class="card card-pad">
                <h2 style="font-size:var(--bh-fs-lg)">{{ __('Request a quote (RFQ)') }}</h2>
                <p class="muted small">{{ __('No account needed. Our sales team checks price and stock and contacts you. Approved business accounts can accept quotes online.') }}</p>
                @include('storefront.partials.rfq-form', ['prefill' => $prefill])
            </div>
        </section>
    @else
        <div class="alert alert-info" style="margin-bottom:24px"><x-icon name="info" /><span>{{ __('Reseller accounts do not get business permissions or quotations automatically, and no commission or affiliate scheme is offered.') }}</span></div>
    @endif
@endsection
