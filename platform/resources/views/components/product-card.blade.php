@props(['product'])
@use('App\Enums\PricingMode')
@use('App\Support\Taka')
@php
    $variant = $product->defaultVariant();
    $quoteOnly = $product->isQuoteOnly();
    $badges = [];
    if ($variant?->isDiscounted()) { $badges[] = ['Sale', 'orange']; }
    if ($product->is_featured) { $badges[] = ['Popular', '']; }
    if ($quoteOnly) { $badges[] = ['Quote only', 'navy']; }
    if ($variant?->isLowStock()) { $badges[] = ['Low stock', '']; }
@endphp
<article class="card pcard">
    @if ($badges)
        <div class="pcard-badges">
            @foreach ($badges as [$label, $tone])
                <span class="chip-badge {{ $tone }}">{{ $label }}</span>
            @endforeach
        </div>
    @endif
    <div class="media"><x-photo-placeholder :icon="$product->primaryCategory?->icon ?? 'photo'" /></div>
    <div class="body">
        <div class="meta">{{ $product->primaryCategory?->name }}</div>
        <h3><a href="{{ $product->url() }}">{{ $product->name }}</a></h3>
        <div class="price tnum">
            @if ($quoteOnly || $variant === null)
                <span class="muted small">Price on request</span>
            @elseif ($variant->pricing_mode === PricingMode::Weight)
                {{ Taka::format($variant->per_kg_paisa) }} <span class="muted small">/ kg</span>
            @else
                {{ Taka::format($variant->price_paisa) }}
                @if ($variant->isDiscounted())<s>{{ Taka::format($variant->compare_at_paisa) }}</s>@endif
                @if ($variant->selling_unit->hasPackSize() && $variant->pack_size)<span class="muted small">/ {{ strtolower($variant->selling_unit->label()) }} of {{ $variant->pack_size }}</span>@endif
            @endif
        </div>
        @if ($quoteOnly)
            <a class="btn btn-outline btn-sm btn-block" href="{{ $product->url() }}#quote"><x-icon name="quote" /> Request quote</a>
        @elseif (! $product->in_stock)
            <span class="btn btn-outline btn-sm btn-block" aria-disabled="true" style="opacity:.6">Out of stock</span>
        @elseif ($variant?->pricing_mode === PricingMode::Weight)
            <a class="btn btn-primary btn-sm btn-block" href="{{ $product->url() }}">Choose weight</a>
        @else
            <a class="btn btn-primary btn-sm btn-block" href="{{ $product->url() }}"><x-icon name="cart" /> View &amp; buy</a>
        @endif
    </div>
</article>
