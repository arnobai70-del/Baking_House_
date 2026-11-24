@extends('layouts.admin', ['title' => 'Cake products'])

@use('App\Support\Weight')

@section('admin')
    <div class="admin-top"><h1>Cake products</h1></div>
    @include('admin.cakes._tabs')

    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>A designable cake is a normal catalogue product (create it under <a href="{{ route('admin.products.index') }}">Products &amp; prices</a>) whose variant is “quote only”. Its price comes from the cake options, so it can only be bought through the cake designer.</span></div>

    <div class="card" style="margin-bottom:16px"><div class="table-wrap"><table class="table">
        <thead><tr><th>Cake</th><th>Weights</th><th>Tiers</th><th>Lead time</th><th>Advance</th><th>Diets</th><th></th></tr></thead>
        <tbody>
        @forelse ($cakes as $cake)
            <tr>
                <td><strong>{{ $cake->product->name }}</strong><div class="small muted">{{ $cake->product->status->value }}</div></td>
                <td class="small tnum">{{ Weight::format($cake->min_grams) }}–{{ Weight::format($cake->max_grams) }} (step {{ Weight::format($cake->step_grams) }})</td>
                <td class="tnum">{{ $cake->max_tiers }}</td>
                <td class="small">{{ $cake->lead_time_hours ? $cake->lead_time_hours.' h' : 'Global setting' }}</td>
                <td class="small">{{ $cake->full_payment_required ? 'Full payment' : ($cake->advance_percent !== null ? 'At least '.$cake->advance_percent.'%' : 'Global setting') }}</td>
                <td class="small">{{ collect(['Eggless' => $cake->allow_eggless, 'Sugar-free' => $cake->allow_sugar_free])->filter()->keys()->implode(', ') ?: 'None' }}</td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.cakes.products.edit', $cake) }}">Rules</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><p><strong>No designable cakes yet</strong></p><p class="muted small">Choose a quote-only product below to make it designable.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>

    <form method="POST" action="{{ route('admin.cakes.products.store') }}" class="card card-pad" novalidate>
        @csrf
        <h2 style="font-size:var(--bh-fs-md)">Make a product designable</h2>
        @if ($candidates->isEmpty())
            <p class="muted small">No quote-only products are available. Create a product with a “quote only” variant first.</p>
        @else
            <div class="row" style="gap:8px;align-items:flex-end">
                <div class="field" style="margin:0;min-width:260px"><label for="product_id">Product</label><select class="input" id="product_id" name="product_id">
                    @foreach ($candidates as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></div>
                <button class="btn btn-navy" type="submit">Make designable</button>
            </div>
            @error('product_id')<span class="field-error">{{ $message }}</span>@enderror
        @endif
    </form>
@endsection
