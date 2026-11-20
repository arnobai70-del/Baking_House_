@extends('layouts.app')

@use('App\Support\Taka')

@section('content')
    <h1 style="font-size:var(--bh-fs-2xl)">{{ __('Shopping cart') }}</h1>

    @if ($quote === null || ($quote['item_count'] ?? 0) === 0)
        <div class="card empty-state">
            <x-icon name="cart" />
            <h2 style="font-size:var(--bh-fs-lg)">{{ __('Your cart is empty') }}</h2>
            <p class="muted">{{ __('Ingredients, tools, restaurant supplies and equipment — all in one place.') }}</p>
            <div class="row" style="justify-content:center">
                <a class="btn btn-primary" href="{{ route('catalog.shop') }}">{{ __('Start shopping') }}</a>
                <a class="btn btn-outline" href="{{ route('bundles.index') }}">{{ __('Combo & bundle offers') }}</a>
            </div>
        </div>
    @else
        <div class="cart-layout">
            <section class="card card-pad" aria-label="{{ __('Cart items') }}">
                @if ($quote['audience'] !== 'retail')
                    <div class="alert alert-info" style="margin-bottom:12px"><x-icon name="store" /><span>{{ __('Prices shown are your :type prices.', ['type' => __($quote['audience'])]) }}</span></div>
                @endif
                @foreach ($quote['lines'] as $line)
                    <div class="line">
                        <x-photo-placeholder :icon="$line['type'] === 'bundle' ? 'gift' : 'box'" />
                        <div>
                            <h3>{{ $line['name'] ?? $line['sku'] ?? $line['slug'] }}</h3>
                            <div class="small muted">
                                @if ($line['type'] === 'bundle')
                                    {{ __('Bundle') }} · {{ __('Normal price') }} <s>{{ Taka::format($line['normal_paisa'] ?? 0) }}</s>
                                @elseif ($line['ok'])
                                    {{ $line['quantity_label'] }} · {{ Taka::format($line['per_kg_paisa'] ?? $line['unit_paisa']) }} {{ $line['per_kg_paisa'] ? __('per kg') : __('each') }}
                                    @if (! in_array($line['rate_source'], ['standard', null], true))<span class="status info">{{ __($line['rate_source']) }}</span>@endif
                                @endif
                            </div>
                            @if ($line['cart_item_id'])
                                <form method="POST" action="{{ route('cart.items.update', $line['cart_item_id']) }}" class="row" style="margin-top:8px;gap:6px" novalidate>
                                    @csrf
                                    @method('PATCH')
                                    @if ($line['is_weight'] ?? false)
                                        <label class="sr-only" for="q-{{ $line['cart_item_id'] }}">{{ __('Quantity in grams') }}</label>
                                        <input class="input tnum" id="q-{{ $line['cart_item_id'] }}" name="weight_quantity" value="{{ $line['base_quantity'] }}" style="width:110px;min-height:36px" inputmode="numeric">
                                        <input type="hidden" name="unit" value="g"><span class="small muted">g</span>
                                    @else
                                        <label class="sr-only" for="q-{{ $line['cart_item_id'] }}">{{ __('Quantity') }}</label>
                                        <input class="input tnum" id="q-{{ $line['cart_item_id'] }}" name="unit_quantity" type="number" min="0" value="{{ $line['base_quantity'] }}" style="width:90px;min-height:36px">
                                    @endif
                                    <button class="btn btn-outline btn-sm" type="submit">{{ __('Update') }}</button>
                                </form>
                                <form method="POST" action="{{ route('cart.items.destroy', $line['cart_item_id']) }}" style="margin-top:4px">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-ghost btn-sm" type="submit" style="padding-left:0">{{ __('Remove') }}</button>
                                </form>
                            @endif
                            @unless ($line['ok'])
                                <div class="alert alert-error" style="margin-top:6px"><x-icon name="alert" /><div>{{ $line['error'] }}
                                    @if (! empty($line['suggestions']))<div class="small">{{ __('Try') }}: {{ implode(', ', $line['suggestions']) }}</div>@endif</div></div>
                            @endunless
                        </div>
                        <div class="line-total tnum">{{ $line['ok'] ? Taka::format($line['line_paisa']) : '—' }}</div>
                    </div>
                @endforeach
                <div class="row" style="margin-top:12px"><a class="btn btn-ghost" href="{{ route('catalog.shop') }}">← {{ __('Continue shopping') }}</a></div>
            </section>

            <aside class="card summary" aria-label="{{ __('Order summary') }}">
                <h2 style="font-size:var(--bh-fs-lg)">{{ __('Order summary') }}</h2>
                <div class="sum-row"><span>{{ __('Subtotal') }}</span><span class="tnum">{{ Taka::format($quote['subtotal_paisa']) }}</span></div>
                @foreach ($quote['promotions'] as $promotion)
                    <div class="sum-row"><span>{{ $promotion['name'] }}@if ($promotion['code']) ({{ $promotion['code'] }})@endif</span><span class="tnum">−{{ Taka::format($promotion['discount_paisa']) }}</span></div>
                @endforeach
                <div class="sum-row"><span>{{ __('Delivery') }}</span><span class="muted">{{ __('At checkout') }}</span></div>
                <form method="POST" action="{{ route('cart.coupon') }}" class="field" style="margin-top:10px" novalidate>
                    @csrf
                    <label for="coupon">{{ __('Coupon code') }}</label>
                    <div class="row" style="flex-wrap:nowrap">
                        <input class="input" id="coupon" name="code" value="{{ $cart?->coupon_code }}" style="text-transform:uppercase">
                        <button class="btn btn-outline" type="submit">{{ __('Apply') }}</button>
                    </div>
                    @if ($quote['coupon'])
                        <div class="alert {{ $quote['coupon']['applied'] ? 'alert-success' : 'alert-error' }}" style="margin-top:6px"><x-icon name="{{ $quote['coupon']['applied'] ? 'check' : 'alert' }}" /><span>{{ $quote['coupon']['applied'] ? __('Coupon applied.') : $quote['coupon']['message'] }}</span></div>
                    @endif
                </form>
                <div class="sum-row total"><span>{{ __('Total') }}</span><span class="tnum">{{ Taka::format($quote['total_paisa']) }}</span></div>
                @if ($quote['valid'])
                    <a class="btn btn-primary btn-block" href="{{ route('checkout.show') }}">{{ __('Proceed to checkout') }}</a>
                @else
                    <div class="alert alert-error" style="margin:10px 0"><x-icon name="alert" /><span>{{ __('Fix the highlighted items before checkout.') }}</span></div>
                @endif
                <p class="hint" style="margin-top:8px"><x-icon name="lock" /> {{ __('Prices and stock are re-checked by the server before you pay.') }}</p>
            </aside>
        </div>
    @endif
@endsection
