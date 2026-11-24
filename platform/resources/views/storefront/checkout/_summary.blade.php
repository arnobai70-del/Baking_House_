@use('App\Support\Taka')
<aside class="card summary" aria-label="{{ __('Order summary') }}">
    <h2 style="font-size:var(--bh-fs-lg)">{{ __('Order summary') }}</h2>
    @foreach ($summary['quote']['lines'] as $line)
        <div class="sum-row"><span>{{ $line['name'] ?? $line['sku'] ?? $line['slug'] }} <span class="muted">{{ in_array($line['type'], ['item', 'cake'], true) ? ($line['quantity_label'] ?? '') : '× '.$line['quantity'] }}</span></span>
            <span class="tnum">{{ $line['ok'] ? Taka::format($line['line_paisa']) : '—' }}</span></div>
        @if ($line['type'] === 'cake')
            <div class="sum-cake">
                @if ($line['scheduled_for'])<span>{{ __('Date') }}: {{ \Carbon\Carbon::parse($line['scheduled_for'])->translatedFormat('D j M') }} · {{ $line['time_slot'] }}</span>@endif
                @foreach ($line['breakdown'] ?? [] as $part)<span class="sum-cake-part"><span>{{ $part['label'] }}</span><span class="tnum">{{ Taka::format($part['paisa']) }}</span></span>@endforeach
                @if (! $line['ok'] && $line['error'])<span class="field-error">{{ $line['error'] }}</span>@endif
            </div>
        @endif
    @endforeach
    <div class="sum-row" style="border-top:1px solid var(--bh-border);margin-top:6px;padding-top:10px"><span>{{ __('Subtotal') }}</span><span class="tnum">{{ Taka::format($summary['quote']['subtotal_paisa']) }}</span></div>
    @foreach ($summary['quote']['promotions'] as $promotion)
        <div class="sum-row"><span>{{ $promotion['name'] }}</span><span class="tnum">−{{ Taka::format($promotion['discount_paisa']) }}</span></div>
    @endforeach
    @if (($summary['fulfilment'] ?? 'delivery') === 'pickup')
        <div class="sum-row"><span>{{ __('Store pickup') }}@if ($summary['pickup_location']) ({{ $summary['pickup_location']->localized('name') }})@endif</span><span class="tnum">{{ __('Free') }}</span></div>
    @else
        <div class="sum-row"><span>{{ __('Delivery') }}@if ($summary['zone']) ({{ $summary['zone']->localized('name') }})@endif</span>
            <span class="tnum">{{ $summary['zone'] ? ($summary['delivery_paisa'] === 0 ? __('Free') : Taka::format($summary['delivery_paisa'])) : __('Choose district') }}</span></div>
    @endif
    <div class="sum-row total"><span>{{ __('Total') }}</span><span class="tnum">{{ Taka::format($summary['total_paisa']) }}</span></div>
    @if (($summary['advance_percent'] ?? 0) > 0)
        {{-- Custom cakes: an advance is paid now; the balance is collected later (admin-set rule). --}}
        <div class="sum-row sum-advance"><span>{{ __('Pay now (:percent% advance)', ['percent' => $summary['advance_percent']]) }}</span><span class="tnum">{{ Taka::format($summary['pay_now_paisa']) }}</span></div>
        @if ($summary['balance_paisa'] > 0)
            <div class="sum-row"><span>{{ __('Balance due later') }}</span><span class="tnum">{{ Taka::format($summary['balance_paisa']) }}</span></div>
        @endif
    @endif
    @if ($summary['zone']?->estimate)<p class="hint">{{ __('Estimated delivery') }}: {{ $summary['zone']->estimate }}</p>@endif
    <p class="hint">{{ __('Delivery charges are provisional until courier rates are confirmed.') }}</p>
</aside>
