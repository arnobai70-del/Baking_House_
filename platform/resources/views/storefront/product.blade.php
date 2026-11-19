@extends('layouts.app')

@use('App\Enums\PricingMode')
@use('App\Support\Taka')
@use('App\Support\Weight')

@php
    $first = $variants->first();
    $icon = $product->primaryCategory->icon;
    $images = $product->approvedImages;
@endphp

@section('content')
    <nav class="crumbs" aria-label="Breadcrumb"><ol>
        <li><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
        @foreach ($trail as $crumb)
            <li><a href="{{ $crumb->url() }}">{{ $crumb->localized('name') }}</a></li>
        @endforeach
        <li aria-current="page">{{ $product->localized('name') }}</li>
    </ol></nav>

    <div class="pdp">
        <section aria-label="Product photos">
            <div class="gallery-main">
                @if ($images->isNotEmpty())
                    <img src="{{ $images->first()->url() }}" alt="{{ $images->first()->alt_text }}" width="800" height="800" style="aspect-ratio:1;object-fit:cover;border-radius:var(--bh-radius-sm)">
                @else
                    <x-photo-placeholder :icon="$icon" :label="__('Real product photo pending')" />
                @endif
            </div>
            @if ($images->isEmpty())
                <p class="hint" style="margin-top:8px"><x-icon name="info" /> {{ __('Approved photography of this exact SKU will replace the placeholder (real-images-only policy).') }}</p>
            @endif
        </section>

        <section aria-labelledby="p-title">
            <div class="muted small">
                {{ $product->primaryCategory->localized('name') }}
                @if ($first) · SKU <span id="p-sku">{{ $first->sku }}</span>@endif
                @if ($product->brand) · {{ $product->brand->name }}@endif
            </div>
            <h1 id="p-title">{{ $product->localized('name') }}</h1>
            @if ($product->localized('short_description'))<p class="muted">{{ $product->localized('short_description') }}</p>@endif

            @if ($variants->isEmpty())
                <div class="alert alert-info"><x-icon name="info" /><span>{{ __('This product is not available to order right now.') }}</span></div>
            @else
                <div id="buy-box" data-product='@json($buyerPayload)'>
                    @if ($variants->count() > 1)
                        <fieldset class="variant-picker">
                            <legend class="small" style="font-weight:600;color:var(--bh-navy)">{{ __('Choose an option') }}</legend>
                            <div class="chips">
                                @foreach ($variants as $variant)
                                    <label class="wchip variant-chip">
                                        <input class="sr-only" type="radio" name="variant" value="{{ $variant->id }}" @checked($loop->first)>
                                        <strong>{{ $variant->name }}</strong>
                                        <span>{{ $variant->displayPrice() }}{{ $variant->listPricePaisa() ? ' '.$variant->unitLabel() : '' }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    {{-- Server-rendered first variant; storefront.js takes over for live previews. --}}
                    <div class="rate tnum" id="rate">
                        @if ($first->pricing_mode === PricingMode::QuoteOnly)
                            <span style="font-size:var(--bh-fs-xl)">{{ __('Price on request') }}</span>
                        @else
                            {{ $first->displayPrice() }} <small>{{ $first->unitLabel() }}</small>
                            @if ($first->isDiscounted())<small><s>{{ Taka::format($first->compare_at_paisa) }}</s></small>@endif
                        @endif
                    </div>
                    @php $firstTiers = $tiersByVariant[$first->id] ?? []; @endphp
                    @if ($firstTiers !== [])
                        <div class="alert alert-info tier-box" style="margin:10px 0">
                            <x-icon name="store" />
                            <div>
                                <strong>{{ $audience === \App\Enums\CustomerAudience::Retail ? __('Buy more, pay less') : __('Your '.strtolower($audience->label()).' price') }}</strong>
                                <ul class="tier-list">
                                    @foreach ($firstTiers as $tier)
                                        <li>{{ $first->isWeight() ? \App\Support\Weight::format($tier['min_quantity']) : $tier['min_quantity'] }}+ : <strong class="tnum">{{ Taka::format($tier['rate_paisa']) }}</strong> {{ $first->unitLabel() }}</li>
                                    @endforeach
                                </ul>
                                <span class="hint">{{ __('Applied automatically on the total quantity.') }}</span>
                            </div>
                        </div>
                    @endif
                    @if (! $first->isWeight() && $first->minimumQuantity() > 1)
                        <p class="small" style="margin:6px 0"><strong>{{ __('Minimum order') }}:</strong> {{ $first->minimumQuantity() }}</p>
                    @endif
                    <p id="stock-line" style="margin:10px 0">
                        @if ($first->isInStock())
                            <span class="stock-ok"><x-icon name="check" /> {{ __('In stock') }}{{ $first->isWeight() ? ' — '.__(':qty available', ['qty' => Weight::format($first->availableQuantity())]) : '' }}</span>
                        @else
                            <span class="stock-low">{{ __('Out of stock') }}</span>
                        @endif
                    </p>

                    <div class="card card-pad stack" id="weight-box" @if (! $first->isWeight()) hidden @endif>
                        <div>
                            <h2 class="small" id="chips-label" style="font-size:var(--bh-fs-sm);margin-bottom:8px">{{ __('Select quantity') }} <span class="muted">{{ __('(weight-based pricing)') }}</span></h2>
                            <div class="chips" role="group" aria-labelledby="chips-label" id="weight-chips"></div>
                        </div>
                        <div>
                            <label class="small" for="custom-qty" style="font-weight:600;color:var(--bh-navy)">{{ __('Or set a custom quantity') }}</label>
                            <div class="custom-qty" style="margin-top:6px">
                                <input class="input tnum" id="custom-qty" inputmode="decimal" placeholder="e.g. 1250" aria-describedby="qty-hint qty-error">
                                <label class="sr-only" for="qty-unit">Unit</label>
                                <select class="input" id="qty-unit"><option value="g">g</option><option value="kg">kg</option></select>
                            </div>
                            <p class="hint" id="qty-hint"></p>
                            <div id="qty-error" aria-live="polite"></div>
                        </div>
                        <div class="calc-box" aria-live="polite">
                            <div class="label">{{ __('Calculated price') }}</div>
                            <div class="total tnum" id="calc-total">—</div>
                            <div class="muted small" id="calc-for">{{ __('Choose a quantity') }}</div>
                        </div>
                    </div>

                    <div class="card card-pad stack" id="fixed-box" @if ($first->pricing_mode !== PricingMode::Fixed) hidden @endif>
                        <div class="row">
                            <div class="stepper" role="group" aria-label="Quantity">
                                <button type="button" data-step="-1" aria-label="Decrease quantity"><x-icon name="minus" /></button>
                                <input id="fixed-qty" class="tnum" value="1" inputmode="numeric" aria-label="Quantity">
                                <button type="button" data-step="1" aria-label="Increase quantity"><x-icon name="plus" /></button>
                            </div>
                            <div class="muted small" aria-live="polite">{{ __('Total') }}: <strong class="tnum" id="fixed-total">—</strong></div>
                        </div>
                        <div id="fixed-error" aria-live="polite"></div>
                    </div>

                    <div class="row" style="margin-top:14px" id="buy-actions" @if ($first->pricing_mode === PricingMode::QuoteOnly) hidden @endif>
                        <button class="btn btn-primary" style="flex:1" disabled title="Cart and checkout open in Phase 5"><x-icon name="cart" /> {{ __('Add to cart') }}</button>
                        <a class="btn btn-outline" href="#quote"><x-icon name="quote" /> {{ __('Bulk quote') }}</a>
                    </div>
                    <p class="hint">{{ __('Cart and checkout open in Phase 5. Prices shown are previews; the server recalculates price and stock at checkout.') }}</p>
                </div>
            @endif

            <details class="card" id="quote" style="margin-top:16px" @if ($product->isQuoteOnly()) open @endif>
                <summary class="card-pad" style="cursor:pointer;font-weight:700;color:var(--bh-navy)"><x-icon name="quote" /> {{ __('Request a quote for :name', ['name' => $product->localized('name')]) }}</summary>
                <div class="card-pad" style="padding-top:0">
                    <p class="muted small">Quotations are handled by our sales team (business portal, Phase 6). Until then, business buyers can
                        <a href="{{ route('organizations.apply', 'business') }}">apply for a business account</a>.</p>
                </div>
            </details>
        </section>
    </div>

    <section class="card" style="margin-top:28px" data-tabs>
        <div class="tabs" role="tablist" aria-label="Product information" style="padding:14px 16px 0">
            <button class="tab" role="tab" id="t-desc" aria-controls="tp-desc" aria-selected="true">{{ __('Description') }}</button>
            <button class="tab" role="tab" id="t-spec" aria-controls="tp-spec" aria-selected="false" tabindex="-1">{{ __('Specifications') }}</button>
            <button class="tab" role="tab" id="t-ship" aria-controls="tp-ship" aria-selected="false" tabindex="-1">{{ __('Delivery & returns') }}</button>
        </div>
        <div class="tabpanel" role="tabpanel" id="tp-desc" aria-labelledby="t-desc">
            <p>{!! nl2br(e($product->localized('description') ?: $product->localized('short_description'))) !!}</p>
        </div>
        <div class="tabpanel" role="tabpanel" id="tp-spec" aria-labelledby="t-spec" hidden>
            <table class="specs"><tbody>
                @foreach ($variants as $variant)
                    <tr><th scope="row">SKU{{ $variants->count() > 1 ? ' — '.$variant->name : '' }}</th><td>{{ $variant->sku }}</td></tr>
                @endforeach
                @if ($product->brand)<tr><th scope="row">Brand</th><td>{{ $product->brand->name }}</td></tr>@endif
                @foreach ($product->specifications ?? [] as $key => $value)
                    <tr><th scope="row">{{ $key }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </tbody></table>
        </div>
        <div class="tabpanel" role="tabpanel" id="tp-ship" aria-labelledby="t-ship" hidden>
            <p>Delivery charges and timelines depend on courier partner and zone — <strong>pending confirmation before launch</strong>.
                Returns follow the store returns policy, which is under owner and legal review.</p>
        </div>
    </section>

    @if ($related->isNotEmpty())
        <section class="section" aria-labelledby="rel-title">
            <div class="section-head"><h2 id="rel-title">{{ __('Related products') }}</h2></div>
            <div class="grid">
                @foreach ($related as $item)
                    <x-product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
@endsection
