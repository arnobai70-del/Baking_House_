@use('App\Support\Taka')
@use('App\Support\Weight')
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Baker job sheet {{ $order->number }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @include('layouts._fonts')
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/tokens.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/app.css') }}">
    <link rel="stylesheet" href="{{ \App\Support\Asset::url('css/storefront.css') }}">
</head>
<body class="invoice-page">
@php
    $breakdown = (array) ($item->components['breakdown'] ?? []);
    $text = array_values(array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'text'));
    $photos = array_values(array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'image'));
    $placed = array_values(array_filter($layers, fn ($l) => ($l['type'] ?? '') === 'item'));
@endphp
<main class="invoice card card-pad job-sheet">
    <div class="row no-print" style="justify-content:space-between;margin-bottom:12px">
        <a class="btn btn-ghost btn-sm" href="{{ route('admin.orders.show', $order) }}">← Order {{ $order->number }}</a>
        <button class="btn btn-navy btn-sm" type="button" data-print>Print job sheet</button>
    </div>
    <header class="invoice-head">
        <div>
            <h1 style="margin:0;font-size:var(--bh-fs-xl)">Baker job sheet</h1>
            <div class="small">Order <strong class="tnum">{{ $order->number }}</strong> · {{ $item->name }}</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:var(--bh-fs-lg);font-weight:700;color:var(--bh-navy)">{{ $item->scheduled_for?->format('D j M Y') }}</div>
            <div class="small">Slot {{ $item->time_slot }} · {{ $order->isPickup() ? 'Store pickup: '.($order->pickupLocation?->name ?? '—') : 'Delivery: '.($order->shipping_address['district'] ?? '') }}</div>
        </div>
    </header>

    @if ($item->components['uses_test_prices'] ?? false)
        <p class="test-price-note">TEST prices: development order, do not bake.</p>
    @endif

    <section class="job-pics">
        @if ($design?->snapshot_path)<figure><img src="{{ route('cakes.design-image', [$design->uuid, 'snapshot']) }}" alt="3D snapshot" width="300" height="300"><figcaption class="small">3D preview (handmade result may vary)</figcaption></figure>@endif
        @if ($design?->top_path)<figure><img src="{{ route('cakes.design-image', [$design->uuid, 'top']) }}" alt="Top design" width="300" height="300"><figcaption class="small">Top design, ≈ {{ $topCm }} cm across</figcaption></figure>@endif
    </section>

    <table class="specs job-specs"><tbody>
        @php $opt = fn (string $group, ?string $code, string $none) => $code ? ($optionNames[$group.'.'.$code] ?? $code) : $none; @endphp
        <tr><th>Size</th><td>{{ $opt('shape', $spec['shape'] ?? null, '—') }} · {{ Weight::format((int) ($spec['grams'] ?? 0)) }} · {{ (int) ($spec['tiers'] ?? 1) }} tier(s) · bottom tier ≈ {{ $baseCm }} cm, top ≈ {{ $topCm }} cm</td></tr>
        <tr><th>Cake</th><td>Flavour: {{ $opt('flavour', $spec['flavour'] ?? null, '—') }} · Filling: {{ $opt('filling', $spec['filling'] ?? null, 'standard') }} · Cream: {{ $opt('cream', $spec['cream'] ?? null, 'standard') }}
            @if (! empty($spec['diets']))<br><strong>Diet: {{ implode(', ', $spec['diets']) }}</strong>@endif</td></tr>
        <tr><th>Colours</th><td>
            @foreach (['base_colour' => 'Main', 'accent_colour' => 'Accent', 'drip_colour' => 'Drip'] as $key => $label)
                @if (! empty($spec[$key]) && ($key !== 'drip_colour' || ! empty($spec['drip'])))<span class="job-colour"><i style="background:{{ $spec[$key] }}"></i>{{ $label }} {{ $spec[$key] }}</span>@endif
            @endforeach
            @if (! empty($spec['palette'])) · palette {{ $library->get('palette.'.$spec['palette'])?->name ?? $spec['palette'] }}@endif</td></tr>
        <tr><th>Decoration</th><td>
            Drip: {{ $opt('drip', $spec['drip'] ?? null, 'none') }} · Side pattern: {{ $library->get('pattern.'.($spec['pattern'] ?? ''))?->name ?? 'plain' }}
            · Top border: {{ $library->get('border.'.($spec['border_top'] ?? ''))?->name ?? 'none' }} · Bottom border: {{ $library->get('border.'.($spec['border_bottom'] ?? ''))?->name ?? 'none' }}
            @if (! empty($spec['extras']))<br>Extras: {{ collect($spec['extras'])->map(fn ($code) => $opt('extra', $code, ''))->implode(', ') }}@endif</td></tr>
        <tr><th>Writing</th><td>
            @forelse ($text as $layer)
                <div>“<span @if (preg_match('/[\x{0980}-\x{09FF}]/u', $layer['text'])) lang="bn" @endif>{{ $layer['text'] }}</span>” · {{ $library->get('font.'.$layer['font'])?->name ?? 'default font' }} · <span class="job-colour"><i style="background:{{ $layer['fill'] }}"></i>{{ $layer['fill'] }}</span>
                    @if ($layer['stroke']) · outline {{ $layer['stroke'] }}@endif @if (abs($layer['curve']) > 0.02) · curved @endif</div>
            @empty
                @if (! empty($spec['message']))“{{ $spec['message'] }}”@else — @endif
            @endforelse</td></tr>
        <tr><th>Toppers &amp; decorations</th><td>
            @forelse ($placed as $layer)
                @php $libItem = $library->get($layer['kind'].'.'.$layer['item']); @endphp
                <div>{{ $libItem?->name ?? $layer['item'] }}@if ($layer['kind'] === 'sticker_pack') · {{ $libItem?->payload['stickers'][$layer['sticker']]['name'] ?? '' }}@endif · ≈ {{ round($layer['w'] * $topCm, 1) }} cm</div>
            @empty — @endforelse</td></tr>
        <tr><th>Edible prints</th><td>
            @forelse ($photos as $i => $layer)
                @php $upload = $uploads->get($layer['upload']); $q = $quality->get($layer['upload']); @endphp
                <div class="job-print">
                    @if ($upload)<img src="{{ route('admin.cakes.uploads.show', $upload) }}" alt="Photo {{ $i + 1 }}" width="80" height="80">@endif
                    <span>Photo {{ $i + 1 }} · print ≈ {{ round($layer['w'] * $topCm, 1) }} cm wide{{ $layer['mask'] === 'circle' ? ', circle' : '' }}
                        @if ($q) · {{ $q['dpi'] }} dpi ({{ $q['level'] }})@endif
                        · <strong>{{ $upload ? ucfirst($upload->review_status) : 'missing' }}</strong></span>
                </div>
            @empty — @endforelse</td></tr>
        @if (! empty($spec['note']))<tr><th>Customer note</th><td>{{ $spec['note'] }}</td></tr>@endif
        <tr><th>Complexity</th><td>{{ ucfirst($item->components['complexity'] ?? 'standard') }}</td></tr>
    </tbody></table>

    <h2 style="font-size:var(--bh-fs-md);margin-top:16px">Price (as charged)</h2>
    <table class="specs"><tbody>
        @foreach ($breakdown as $part)<tr><th>{{ $part['label'] }}</th><td class="tnum">{{ Taka::format($part['paisa']) }}</td></tr>@endforeach
        <tr><th><strong>Cake</strong></th><td class="tnum"><strong>{{ Taka::format($item->line_paisa) }}</strong></td></tr>
    </tbody></table>
    <p class="small muted">Allergen notice given to the customer: our kitchen handles nuts, gluten, dairy and eggs; allergen-free preparation is not guaranteed.</p>
</main>
<script src="{{ \App\Support\Asset::url('js/print.js') }}"></script>
</body>
</html>
