@props(['product'])
@use('App\Enums\PricingMode')
@use('App\Support\Taka')
@php
    $variant = $product->defaultVariant();
    $quoteOnly = $product->isQuoteOnly();
    /* One badge per card, highest priority first (Phase 9.5). "Featured" is a staff choice, not measured popularity. */
    $badge = match (true) {
        (bool) $variant?->isDiscounted() => [__('Sale'), 'orange'],
        (bool) $variant?->isLowStock() => [__('Low stock'), 'warn'],
        $quoteOnly => [__('Quote only'), 'navy'],
        (bool) $product->is_featured => [__('Featured'), ''],
        default => null,
    };
@endphp
<article class="card pcard">
    @if ($badge)
        <div class="pcard-badges"><span class="chip-badge {{ $badge[1] }}">{{ $badge[0] }}</span></div>
    @endif
    <div class="media">
        @if ($product->relationLoaded('primaryImage') && $product->primaryImage)
            <a href="{{ $product->url() }}" tabindex="-1" aria-hidden="true"><x-product-photo :image="$product->primaryImage" sizes="(max-width: 640px) 50vw, 260px" :width="400" /></a>
        @else
            <x-photo-placeholder :icon="$product->primaryCategory?->icon ?? 'photo'" />
        @endif
    </div>
    <div class="body">
        <div class="meta">{{ $product->primaryCategory?->localized('name') }}</div>
        <h3><a href="{{ $product->url() }}">{{ $product->localized('name') }}</a></h3>
        <div class="price tnum">
            @if ($quoteOnly || $variant === null)
                <span class="muted small">{{ __('Price on request') }}</span>
            @elseif ($variant->pricing_mode === PricingMode::Weight)
                {{ Taka::format($variant->per_kg_paisa) }} <span class="muted small">/ kg</span>
            @else
                {{ Taka::format($variant->price_paisa) }}
                @if ($variant->isDiscounted())<s>{{ Taka::format($variant->compare_at_paisa) }}</s>@endif
                @if ($variant->selling_unit->hasPackSize() && $variant->pack_size)<span class="muted small">/ {{ strtolower($variant->selling_unit->label()) }} of {{ $variant->pack_size }}</span>@endif
            @endif
        </div>
        @if ($quoteOnly)
            <a class="btn btn-outline btn-sm btn-block" href="{{ $product->url() }}#quote"><x-icon name="quote" /> {{ __('Request quote') }}</a>
        @elseif (! $product->in_stock)
            <span class="btn btn-outline btn-sm btn-block" aria-disabled="true" style="opacity:.6">{{ __('Out of stock') }}</span>
        @elseif ($variant?->pricing_mode === PricingMode::Weight)
            <a class="btn btn-outline btn-sm btn-block" href="{{ $product->url() }}">{{ __('Choose weight') }}</a>
        @else
            <a class="btn btn-outline btn-sm btn-block" href="{{ $product->url() }}">{{ __('View product') }}</a>
        @endif
    </div>
</article>
