@extends('layouts.admin', ['title' => $list->exists ? 'Edit price list' : 'New price list'])

@use('App\Support\AdminInput')
@use('App\Support\Taka')

@php
    $rows = old('items') ?? $list->items->map(fn ($item) => [
        'sku' => $item->variant?->sku, 'min_quantity' => $item->min_quantity,
        'price' => Taka::toInput($item->per_kg_paisa ?? $item->price_paisa),
    ])->all();
    for ($blank = 0; $blank < 4; $blank++) { $rows[] = ['sku' => '', 'min_quantity' => 1, 'price' => '']; }
@endphp

@section('admin')
    <div class="admin-top"><h1>{{ $list->exists ? 'Edit: '.$list->name : 'New price list' }}</h1></div>
    <form method="POST" action="{{ $list->exists ? route('admin.price-lists.update', $list) : route('admin.price-lists.store') }}" class="card card-pad" novalidate>
        @csrf
        @if ($list->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" value="{{ old('name', $list->name) }}" required></div>
            <div class="field"><label for="audience">Applies to</label>
                <select class="input" id="audience" name="audience">
                    @foreach ($audiences as $audience)
                        <option value="{{ $audience->value }}" @selected(old('audience', $list->audience?->value) === $audience->value)>{{ $audience->label() }}</option>
                    @endforeach
                </select></div>
            <div class="field"><label for="organization_id">Organization (contract lists only)</label>
                <select class="input" id="organization_id" name="organization_id">
                    <option value="">—</option>
                    @foreach ($organizations as $organization)
                        <option value="{{ $organization->id }}" @selected((int) old('organization_id', $list->organization_id) === $organization->id)>{{ $organization->name }} ({{ $organization->type->value }})</option>
                    @endforeach
                </select></div>
            <div class="field" style="justify-content:flex-end"><label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $list->is_active))> Active</label></div>
            <div class="field"><label for="starts_at">Starts (Dhaka time, optional)</label><input class="input" id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', AdminInput::utcToDhakaInput($list->starts_at)) }}"></div>
            <div class="field"><label for="ends_at">Ends (optional)</label><input class="input" id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', AdminInput::utcToDhakaInput($list->ends_at)) }}"></div>
        </div>

        <h2 style="font-size:var(--bh-fs-lg);margin-top:8px">Tiers</h2>
        <p class="hint">One row per tier. Weight items: minimum in grams and price per kg. Others: minimum units and price per unit. Leave SKU blank to skip a row.</p>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>SKU</th><th>From quantity</th><th>Price (৳)</th></tr></thead>
            <tbody>
            @foreach ($rows as $i => $row)
                <tr>
                    <td><label class="sr-only" for="sku-{{ $i }}">SKU</label><input class="input" id="sku-{{ $i }}" name="items[{{ $i }}][sku]" value="{{ $row['sku'] }}" style="min-height:36px">
                        @error("items.$i.sku")<span class="field-error">{{ $message }}</span>@enderror</td>
                    <td><label class="sr-only" for="min-{{ $i }}">From quantity</label><input class="input tnum" id="min-{{ $i }}" name="items[{{ $i }}][min_quantity]" type="number" min="1" value="{{ $row['min_quantity'] }}" style="min-height:36px">
                        @error("items.$i.min_quantity")<span class="field-error">{{ $message }}</span>@enderror</td>
                    <td><label class="sr-only" for="price-{{ $i }}">Price</label><input class="input tnum" id="price-{{ $i }}" name="items[{{ $i }}][price]" value="{{ $row['price'] }}" style="min-height:36px">
                        @error("items.$i.price")<span class="field-error">{{ $message }}</span>@enderror</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>

        <div class="field" style="margin-top:12px"><label for="notes">Internal notes</label><textarea class="input" id="notes" name="notes" rows="2">{{ old('notes', $list->notes) }}</textarea></div>
        @if ($list->exists)
            <div class="reason-box"><label for="reason" style="font-weight:700;color:var(--bh-navy)">Reason for this change</label>
                <input class="input" id="reason" name="reason" value="{{ old('reason') }}" required>
                <span class="hint">Saved in the audit log with the before/after tiers.</span></div>
        @endif
        <div class="row"><button class="btn btn-navy" type="submit">Save price list</button>
            <a class="btn btn-ghost" href="{{ route('admin.price-simulator') }}">Open price simulator</a></div>
    </form>
@endsection
