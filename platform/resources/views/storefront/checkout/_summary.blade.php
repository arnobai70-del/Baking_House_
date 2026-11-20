@use('App\Support\Taka')
<aside class="card summary" aria-label="{{ __('Order summary') }}">
    <h2 style="font-size:var(--bh-fs-lg)">{{ __('Order summary') }}</h2>
    @foreach ($summary['quote']['lines'] as $line)
        <div class="sum-row"><span>{{ $line['name'] ?? $line['sku'] ?? $line['slug'] }} <span class="muted">{{ $line['type'] === 'bundle' ? '× '.$line['quantity'] : ($line['quantity_label'] ?? '') }}</span></span>
            <span class="tnum">{{ $line['ok'] ? Taka::format($line['line_paisa']) : '—' }}</span></div>
    @endforeach
    <div class="sum-row" style="border-top:1px solid var(--bh-border);margin-top:6px;padding-top:10px"><span>{{ __('Subtotal') }}</span><span class="tnum">{{ Taka::format($summary['quote']['subtotal_paisa']) }}</span></div>
    @foreach ($summary['quote']['promotions'] as $promotion)
        <div class="sum-row"><span>{{ $promotion['name'] }}</span><span class="tnum">−{{ Taka::format($promotion['discount_paisa']) }}</span></div>
    @endforeach
    <div class="sum-row"><span>{{ __('Delivery') }}@if ($summary['zone']) ({{ $summary['zone']->localized('name') }})@endif</span>
        <span class="tnum">{{ $summary['zone'] ? ($summary['delivery_paisa'] === 0 ? __('Free') : Taka::format($summary['delivery_paisa'])) : __('Choose district') }}</span></div>
    <div class="sum-row total"><span>{{ __('Total') }}</span><span class="tnum">{{ Taka::format($summary['total_paisa']) }}</span></div>
    @if ($summary['zone']?->estimate)<p class="hint">{{ __('Estimated delivery') }}: {{ $summary['zone']->estimate }}</p>@endif
    <p class="hint">{{ __('Delivery charges are provisional until courier rates are confirmed.') }}</p>
</aside>
