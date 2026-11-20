@use('App\Support\Taka')
@use('App\Services\Pricing\PriceResolver')
<article class="card combo">
    <x-photo-placeholder icon="gift" :label="__('Bundle photo pending')" />
    <div>
        <span class="chip-badge orange">{{ __('Combo offer') }}</span>
        <h2 style="font-size:var(--bh-fs-xl);margin-top:8px"><a href="{{ $bundle->url() }}" style="color:inherit;text-decoration:none">{{ $bundle->localized('name') }}</a></h2>
        @if ($bundle->description)<p class="muted small">{{ $bundle->description }}</p>@endif
        <ul class="bundle-items">
            @foreach ($bundle->items as $item)
                <li><x-icon name="check" /> {{ $item->variant?->product?->localized('name') }} <span class="muted">({{ PriceResolver::quantityLabel($item->variant, $item->quantity) }})</span></li>
            @endforeach
        </ul>
    </div>
    <div class="price-box">
        @if ($quote['ok'])
            <div class="muted small">{{ __('Normal price') }} <s>{{ Taka::format($quote['normal_paisa']) }}</s></div>
            <div class="big tnum">{{ Taka::format($quote['line_paisa']) }}</div>
            <p><span class="save-pill">{{ __('Save') }} {{ Taka::format($quote['discount_paisa']) }}</span></p>
            <button class="btn btn-primary btn-block" disabled title="{{ __('Cart and checkout open in Phase 5') }}"><x-icon name="cart" /> {{ __('Add combo to cart') }}</button>
        @else
            <div class="alert alert-error"><x-icon name="alert" /><span>{{ $quote['error'] }}</span></div>
        @endif
    </div>
</article>
