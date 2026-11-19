@extends('layouts.admin', ['title' => $bundle->exists ? 'Edit bundle' : 'New bundle'])

@use('App\Support\AdminInput')
@use('App\Support\Taka')
@use('App\Enums\DiscountType')

@php
    $isPercent = ($bundle->discount_type ?? DiscountType::Percentage) === DiscountType::Percentage;
    $valueInput = $bundle->exists ? ($isPercent ? AdminInput::basisPointsToInput($bundle->discount_value) : Taka::toInput($bundle->discount_value)) : '';
    $rows = old('items') ?? $bundle->items->map(fn ($item) => ['sku' => $item->variant?->sku, 'quantity' => $item->quantity])->all();
    for ($blank = 0; $blank < 3; $blank++) { $rows[] = ['sku' => '', 'quantity' => 1]; }
    $audiences = old('audiences', $bundle->audiences ?? []);
@endphp

@section('admin')
    <div class="admin-top"><h1>{{ $bundle->exists ? 'Edit: '.$bundle->name : 'New bundle' }}</h1>
        <span class="spacer"></span>
        @if ($bundle->exists && $bundle->is_active)<a class="btn btn-ghost btn-sm" href="{{ $bundle->url() }}" target="_blank" rel="noopener">View on store ↗</a>@endif</div>
    <form method="POST" action="{{ $bundle->exists ? route('admin.bundles.update', $bundle) : route('admin.bundles.store') }}" class="card card-pad" novalidate>
        @csrf
        @if ($bundle->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">Name (English)</label><input class="input" id="name" name="name" value="{{ old('name', $bundle->name) }}" required></div>
            <div class="field"><label for="name_bn">Name (Bangla)</label><input class="input" id="name_bn" name="name_bn" lang="bn" value="{{ old('name_bn', $bundle->name_bn) }}"></div>
            <div class="field"><label for="slug">URL slug</label><input class="input" id="slug" name="slug" value="{{ old('slug', $bundle->slug) }}" placeholder="auto from name"></div>
            <div class="field"><label for="discount_type">Discount</label>
                <div class="row" style="flex-wrap:nowrap"><select class="input" id="discount_type" name="discount_type" style="width:auto">
                    <option value="percentage" @selected(old('discount_type', $bundle->discount_type?->value) === 'percentage')>%</option>
                    <option value="fixed_amount" @selected(old('discount_type', $bundle->discount_type?->value) === 'fixed_amount')>৳ per bundle</option>
                </select><input class="input tnum" name="discount_value" aria-label="Discount value" value="{{ old('discount_value', $valueInput) }}"></div></div>
            <div class="field full"><label for="description">Description</label><textarea class="input" id="description" name="description" rows="2">{{ old('description', $bundle->description) }}</textarea></div>
            <div class="field"><label for="starts_at">Starts (Dhaka)</label><input class="input" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', AdminInput::utcToDhakaInput($bundle->starts_at)) }}"></div>
            <div class="field"><label for="ends_at">Ends</label><input class="input" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', AdminInput::utcToDhakaInput($bundle->ends_at)) }}"></div>
        </div>
        <h2 style="font-size:var(--bh-fs-md)">Items <span class="muted small">(grams for weight items, units otherwise)</span></h2>
        @error('items')<div class="field-error">{{ $message }}</div>@enderror
        <div class="table-wrap"><table class="table"><thead><tr><th>SKU</th><th>Quantity</th></tr></thead><tbody>
            @foreach ($rows as $i => $row)
                <tr><td><input class="input" name="items[{{ $i }}][sku]" aria-label="SKU" value="{{ $row['sku'] }}" style="min-height:36px">@error("items.$i.sku")<span class="field-error">{{ $message }}</span>@enderror</td>
                    <td><input class="input tnum" name="items[{{ $i }}][quantity]" aria-label="Quantity" type="number" min="1" value="{{ $row['quantity'] }}" style="min-height:36px"></td></tr>
            @endforeach
        </tbody></table></div>
        <fieldset style="border:0;padding:0;margin:12px 0 10px"><legend class="small" style="font-weight:600;color:var(--bh-navy)">Available to</legend>
            @foreach (\App\Enums\CustomerAudience::cases() as $case)
                <label class="check"><input type="checkbox" name="audiences[]" value="{{ $case->value }}" @checked(in_array($case->value, $audiences, true))> {{ $case->label() }}</label>
            @endforeach
        </fieldset>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bundle->is_active))> Active</label>
        <div class="row" style="margin-top:12px"><button class="btn btn-primary" type="submit">Save bundle</button></div>
    </form>
@endsection
