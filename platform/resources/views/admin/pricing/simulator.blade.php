@extends('layouts.admin', ['title' => 'Price simulator'])

@use('App\Support\Taka')
@use('App\Support\Weight')

@section('admin')
    <div class="admin-top"><h1>Price simulator</h1></div>
    <form method="GET" class="card card-pad" style="margin-bottom:16px">
        <p class="muted small">Runs the same server pricing engine checkout uses. Stock is ignored here so you can test any quantity.</p>
        <div class="variant-grid">
            <div class="field"><label for="sku">SKU</label><input class="input" id="sku" name="sku" value="{{ $input['sku'] ?? '' }}" required>
                @if ($skuMissing)<span class="field-error">Unknown SKU.</span>@endif</div>
            <div class="field"><label for="quantity">Quantity (grams or units)</label><input class="input tnum" id="quantity" name="quantity" type="number" min="1" value="{{ $input['quantity'] ?? 700 }}"></div>
            <div class="field"><label for="audience">Customer type</label>
                <select class="input" id="audience" name="audience">
                    @foreach (\App\Enums\CustomerAudience::cases() as $case)
                        <option value="{{ $case->value }}" @selected(($input['audience'] ?? 'retail') === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select></div>
            <div class="field"><label for="organization_id">Organization (overrides type)</label>
                <select class="input" id="organization_id" name="organization_id">
                    <option value="">—</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected((int) ($input['organization_id'] ?? 0) === $organization->id)>{{ $organization->name }} ({{ $organization->type->value }}, {{ $organization->status->value }})</option>
                    @endforeach
                </select></div>
            <div class="field"><label for="at">Price at (Dhaka time, optional)</label><input class="input" id="at" name="at" type="datetime-local" value="{{ $input['at'] ?? '' }}"></div>
        </div>
        <button class="btn btn-primary" type="submit">Calculate</button>
    </form>

    @if ($variant && $quote)
        <div class="card card-pad">
            <h2 style="font-size:var(--bh-fs-lg)">{{ $variant->product?->name }} — {{ $variant->sku }}</h2>
            @if ($quote->ok)
                <table class="specs"><tbody>
                    <tr><th>Customer</th><td>{{ $context->audience->label() }}{{ $context->organization ? ' · '.$context->organization->name : '' }}</td></tr>
                    <tr><th>Quantity</th><td class="tnum">{{ $variant->isWeight() ? Weight::format($quote->quantity).' ('.$quote->quantity.' g)' : $quote->quantity }}</td></tr>
                    <tr><th>Rate applied</th><td class="tnum">{{ Taka::format($quote->perKgPaisa ?? $quote->unitPaisa) }} {{ $variant->unitLabel() }}</td></tr>
                    <tr><th>Rate source</th><td><span class="status info">{{ $quote->rateSource }}</span></td></tr>
                    <tr><th>Standard price</th><td class="tnum">{{ Taka::format($quote->standardLinePaisa) }}</td></tr>
                    <tr><th>Line total</th><td class="tnum"><strong style="font-size:var(--bh-fs-lg);color:var(--bh-navy)">{{ Taka::format($quote->linePaisa) }}</strong> @if ($quote->savingsPaisa())<span class="save-pill">saves {{ Taka::format($quote->savingsPaisa()) }}</span>@endif</td></tr>
                </tbody></table>
            @else
                <div class="alert alert-error"><x-icon name="alert" /><span>{{ $quote->error }} @if ($quote->suggestions) Suggested: {{ implode(', ', $quote->suggestions) }} @endif</span></div>
            @endif
            @if ($tiers)
                <h3 style="margin-top:14px">Tiers visible to this customer</h3>
                <ul class="tier-list">@foreach ($tiers as $tier)<li>{{ $tier['min_quantity'] }}+ : {{ Taka::format($tier['rate_paisa']) }} ({{ $tier['source'] }})</li>@endforeach</ul>
            @endif
        </div>
    @endif
@endsection
