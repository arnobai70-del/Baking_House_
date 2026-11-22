@extends('layouts.admin', ['title' => $promotion->exists ? 'Edit promotion' : 'New promotion'])

@use('App\Support\AdminInput')
@use('App\Support\Taka')
@use('App\Enums\DiscountType')

@php
    $isPercent = ($promotion->discount_type ?? DiscountType::Percentage) === DiscountType::Percentage;
    $valueInput = $promotion->exists ? ($isPercent ? AdminInput::basisPointsToInput($promotion->discount_value) : Taka::toInput($promotion->discount_value)) : '';
    $skus = $promotion->exists ? $promotion->products->flatMap->variants->pluck('sku')->join(', ') : '';
    $audiences = old('audiences', $promotion->audiences ?? []);
@endphp

@section('admin')
    <div class="admin-top"><h1>{{ $promotion->exists ? 'Edit: '.$promotion->name : 'New promotion' }}</h1></div>
    <form method="POST" action="{{ $promotion->exists ? route('admin.promotions.update', $promotion) : route('admin.promotions.store') }}" class="card card-pad" novalidate>
        @csrf
        @if ($promotion->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" value="{{ old('name', $promotion->name) }}" required></div>
            <div class="field"><label for="code">Coupon code (leave blank for automatic)</label><input class="input" id="code" name="code" value="{{ old('code', $promotion->code) }}" style="text-transform:uppercase"></div>
            <div class="field"><label for="discount_type">Discount type</label>
                <select class="input" id="discount_type" name="discount_type">
                    <option value="percentage" @selected(old('discount_type', $promotion->discount_type?->value) === 'percentage')>Percentage off</option>
                    <option value="fixed_amount" @selected(old('discount_type', $promotion->discount_type?->value) === 'fixed_amount')>Fixed amount off (৳)</option>
                </select></div>
            <div class="field"><label for="discount_value">Value (% or ৳)</label><input class="input tnum" id="discount_value" name="discount_value" value="{{ old('discount_value', $valueInput) }}" required></div>
            <div class="field"><label for="scope">Applies to</label>
                <select class="input" id="scope" name="scope">
                    <option value="order" @selected(old('scope', $promotion->scope) === 'order')>Whole order (eligible items)</option>
                    <option value="products" @selected(old('scope', $promotion->scope) === 'products')>Specific products</option>
                </select></div>
            <div class="field"><label for="product_skus">Product SKUs (comma separated, for “specific products”)</label><input class="input" id="product_skus" name="product_skus" value="{{ old('product_skus', $skus) }}"></div>
            <div class="field"><label for="min_subtotal">Minimum eligible spend (৳)</label><input class="input tnum" id="min_subtotal" name="min_subtotal" value="{{ old('min_subtotal', Taka::toInput($promotion->min_subtotal_paisa ?: null)) }}"></div>
            <div class="field"><label for="max_discount">Maximum discount (৳, optional)</label><input class="input tnum" id="max_discount" name="max_discount" value="{{ old('max_discount', Taka::toInput($promotion->max_discount_paisa)) }}"></div>
            <div class="field"><label for="usage_limit">Total uses (optional)</label><input class="input tnum" id="usage_limit" name="usage_limit" type="number" min="1" value="{{ old('usage_limit', $promotion->usage_limit) }}"></div>
            <div class="field"><label for="per_customer_limit">Uses per customer (optional, requires sign-in)</label><input class="input tnum" id="per_customer_limit" name="per_customer_limit" type="number" min="1" value="{{ old('per_customer_limit', $promotion->per_customer_limit) }}"></div>
            <div class="field"><label for="starts_at">Starts (Dhaka time)</label><input class="input" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', AdminInput::utcToDhakaInput($promotion->starts_at)) }}"></div>
            <div class="field"><label for="ends_at">Ends</label><input class="input" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', AdminInput::utcToDhakaInput($promotion->ends_at)) }}"></div>
        </div>
        <fieldset style="border:0;padding:0;margin:0 0 10px"><legend class="small" style="font-weight:600;color:var(--bh-navy)">Eligible customers</legend>
            @foreach (\App\Enums\CustomerAudience::cases() as $case)
                <label class="check"><input type="checkbox" name="audiences[]" value="{{ $case->value }}" @checked(in_array($case->value, $audiences, true))> {{ $case->label() }}</label>
            @endforeach
        </fieldset>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $promotion->is_active))> Active</label>
        @if ($canStack)
            <label class="check"><input type="hidden" name="stackable" value="0"><input type="checkbox" name="stackable" value="1" @checked(old('stackable', $promotion->stackable))> Stackable — may combine with other promotions and trade / bundle prices (super admin)</label>
        @else
            <p class="hint">Stackable: {{ $promotion->stackable ? 'yes' : 'no' }} (only a super admin can change this).</p>
        @endif
        <div class="row" style="margin-top:12px"><button class="btn btn-navy" type="submit">Save promotion</button></div>
    </form>
@endsection
