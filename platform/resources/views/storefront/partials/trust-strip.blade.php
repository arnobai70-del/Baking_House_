{{-- Service facts (each sub-line stays "pending" until verified; no new claims). The homepage shows it under the hero. --}}
<section class="trust-strip" aria-label="{{ __('Why :brand', ['brand' => config('brand.name')]) }}"><div class="container">
    <div class="trust"><x-icon name="truck" /><div><strong>{{ __('Nationwide delivery') }}</strong><span>{{ __('Courier partners pending') }}</span></div></div>
    <div class="trust"><x-icon name="card" /><div><strong>{{ __('bKash, Nagad, card & bank') }}</strong><span>{{ __('Provider integration pending') }}</span></div></div>
    <div class="trust"><x-icon name="store" /><div><strong>{{ __('Wholesale & business pricing') }}</strong><span>{{ __('For verified accounts') }}</span></div></div>
    <div class="trust"><x-icon name="quote" /><div><strong>{{ __('Request a quote') }}</strong><span>{{ __('Large & regular orders') }}</span></div></div>
</div></section>
