@props(['product', 'eager' => false])
@use('App\Enums\PricingMode')
@use('App\Support\Taka')
@php
    $variant = $product->defaultVariant();
    $activeVariants = $product->variants->where('is_active', true);
    $isCake = \App\Models\CakeProduct::isCakeProduct($product->id);
    $quoteOnly = ! $isCake && $product->isQuoteOnly();
    $isWeight = $variant?->pricing_mode === PricingMode::Weight;
    /* One badge per card, highest priority first (Phase 9.5). "Featured" is a staff choice, not measured popularity. */
    $badge = match (true) {
        (bool) $variant?->isDiscounted() => [__('Sale'), 'orange'],
        (bool) $variant?->isLowStock() => [__('Low stock'), 'warn'],
        $isCake => [__('Made to order'), 'navy'],
        $quoteOnly => [__('Quote only'), 'navy'],
        (bool) $product->is_featured => [__('Featured'), ''],
        default => null,
    };
    /* The only direct "Add to cart": one fixed-price variant, in stock. Weight / options / quotes need a choice first. */
    $directAdd = ! $quoteOnly && $product->in_stock && $variant !== null && ! $isWeight && $activeVariants->count() === 1;
@endphp
<article class="card pcard">
    @if ($badge)
        <div class="pcard-badges"><span class="chip-badge {{ $badge[1] }}">{{ $badge[0] }}</span></div>
    @endif
    <div class="media">
        @if ($product->relationLoaded('primaryImage') && $product->primaryImage)
            <a href="{{ $product->url() }}" tabindex="-1" aria-hidden="true"><x-product-photo :image="$product->primaryImage" sizes="(max-width: 640px) 50vw, 260px" :width="400" /></a>
        @elseif ($representative = \App\Support\RepresentativeImage::forSlug($product->slug))
            <a href="{{ $product->url() }}" tabindex="-1" aria-hidden="true"><x-representative-photo :image="$representative" :eager="$eager" /></a>
        @else
            <x-photo-placeholder :icon="$product->primaryCategory?->icon ?? 'photo'" />
        @endif
    </div>
    <div class="body">
        <div class="meta">{{ $product->primaryCategory?->localized('name') }}</div>
        <h3><a href="{{ $product->url() }}">{{ $product->localized('name') }}</a></h3>
        <div class="price tnum">
            @if ($isCake)
                <span class="muted small">{{ __('Priced by your design') }}</span>
            @elseif ($quoteOnly || $variant === null)
                <span class="muted small">{{ __('Price on request') }}</span>
            @elseif ($isWeight)
                {{ Taka::format($variant->per_kg_paisa) }} <span class="muted small">/ kg</span>
            @else
                {{ Taka::format($variant->price_paisa) }}
                @if ($variant->isDiscounted())<s>{{ Taka::format($variant->compare_at_paisa) }}</s>@endif
                @if ($variant->selling_unit->hasPackSize() && $variant->pack_size)
                    <span class="muted small">/ {{ __(strtolower($variant->selling_unit->label())) }} of {{ $variant->pack_size }}</span>
                @else
                    <span class="muted small">/ {{ __(strtolower($variant->selling_unit->label())) }}</span>
                @endif
            @endif
        </div>
        <p class="pcard-stock small {{ $isCake || $product->in_stock ? 'is-in' : 'is-out' }}">
            @if ($isCake)
                {{ __('Baked to order') }}
            @elseif ($quoteOnly)
                {{ __('Quoted on request') }}
            @elseif ($product->in_stock)
                {{ $isWeight ? __('In stock · sold by weight') : __('In stock') }}
            @else
                {{ __('Out of stock') }}
            @endif
        </p>
        @if ($isCake)
            <a class="btn btn-primary btn-sm btn-block" href="{{ route('cakes.designer', ['product' => $product->slug]) }}"><x-icon name="cake" /> {{ __('Design this cake') }}</a>
        @elseif ($quoteOnly)
            <a class="btn btn-outline btn-sm btn-block" href="{{ $product->url() }}#quote"><x-icon name="quote" /> {{ __('Request quote') }}</a>
        @elseif (! $product->in_stock)
            <span class="btn btn-outline btn-sm btn-block is-disabled" aria-disabled="true">{{ __('Out of stock') }}</span>
        @elseif ($directAdd)
            <form method="POST" action="{{ route('cart.items.store') }}">
                @csrf
                <input type="hidden" name="variant" value="{{ $variant->id }}">
                <input type="hidden" name="unit_quantity" value="1">
                <button class="btn btn-primary btn-sm btn-block" type="submit" aria-label="{{ __('Add :name to cart', ['name' => $product->localized('name')]) }}"><x-icon name="cart" /> {{ __('Add to cart') }}</button>
            </form>
        @elseif ($isWeight)
            <a class="btn btn-select btn-sm btn-block" href="{{ $product->url() }}">{{ __('Choose weight') }}</a>
        @else
            <a class="btn btn-select btn-sm btn-block" href="{{ $product->url() }}">{{ __('Choose options') }}</a>
        @endif
    </div>
</article>
