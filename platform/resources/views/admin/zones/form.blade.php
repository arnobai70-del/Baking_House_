@extends('layouts.admin', ['title' => $zone->exists ? 'Edit zone' : 'New zone'])

@use('App\Support\Taka')

@section('admin')
    <div class="admin-top"><h1>{{ $zone->exists ? 'Edit: '.$zone->name : 'New delivery zone' }}</h1></div>
    <form method="POST" action="{{ $zone->exists ? route('admin.zones.update', $zone) : route('admin.zones.store') }}" class="card card-pad" novalidate>
        @csrf
        @if ($zone->exists) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" value="{{ old('name', $zone->name) }}" required></div>
            <div class="field"><label for="name_bn">Name (Bangla)</label><input class="input" id="name_bn" name="name_bn" lang="bn" value="{{ old('name_bn', $zone->name_bn) }}"></div>
            <div class="field"><label for="charge">Delivery charge (৳)</label><input class="input tnum" id="charge" name="charge" value="{{ old('charge', Taka::toInput($zone->charge_paisa)) }}" required></div>
            <div class="field"><label for="free_over">Free delivery over (৳, optional)</label><input class="input tnum" id="free_over" name="free_over" value="{{ old('free_over', Taka::toInput($zone->free_over_paisa)) }}"></div>
            <div class="field"><label for="estimate">Delivery estimate</label><input class="input" id="estimate" name="estimate" value="{{ old('estimate', $zone->estimate) }}" placeholder="e.g. 1–2 days (provisional)"></div>
            <div class="field"><label for="sort_order">Order</label><input class="input tnum" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $zone->sort_order ?? 0) }}"></div>
        </div>
        <fieldset style="border:0;padding:0;margin:0 0 12px"><legend style="font-weight:700;color:var(--bh-navy)">Districts</legend>
            @error('districts')<div class="field-error">{{ $message }}</div>@enderror
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:2px 12px">
                @foreach ($districts as $district)
                    <label class="check"><input type="checkbox" name="districts[]" value="{{ $district }}" @checked(in_array($district, old('districts', $zone->districts ?? []), true))> {{ $district }}</label>
                @endforeach
            </div>
        </fieldset>
        <label class="check"><input type="hidden" name="is_default" value="0"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $zone->is_default))> Default zone (covers districts not listed in any zone)</label>
        <label class="check"><input type="hidden" name="cod_available" value="0"><input type="checkbox" name="cod_available" value="1" @checked(old('cod_available', $zone->cod_available))> Cash on delivery available</label>
        <fieldset style="border:0;padding:0;margin:12px 0"><legend style="font-weight:700;color:var(--bh-navy)">Custom cakes</legend>
            <label class="check"><input type="hidden" name="cake_delivery_enabled" value="0"><input type="checkbox" name="cake_delivery_enabled" value="1" @checked(old('cake_delivery_enabled', $zone->cake_delivery_enabled))> Deliver custom cakes to this zone</label>
            <div class="form-grid" style="margin-top:8px">
                <div class="field"><label for="cake_charge">Cake delivery charge (৳, empty = normal charge)</label><input class="input tnum" id="cake_charge" name="cake_charge" value="{{ old('cake_charge', Taka::toInput($zone->cake_charge_paisa)) }}">@error('cake_charge')<span class="field-error">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="cake_free_over">Free cake delivery over (৳, optional)</label><input class="input tnum" id="cake_free_over" name="cake_free_over" value="{{ old('cake_free_over', Taka::toInput($zone->cake_free_over_paisa)) }}"></div>
                <div class="field"><label for="cake_min_order">Minimum order for cake delivery (৳, optional)</label><input class="input tnum" id="cake_min_order" name="cake_min_order" value="{{ old('cake_min_order', Taka::toInput($zone->cake_min_order_paisa)) }}"></div>
            </div>
            <label class="check"><input type="hidden" name="cake_tiered_allowed" value="0"><input type="checkbox" name="cake_tiered_allowed" value="1" @checked(old('cake_tiered_allowed', $zone->cake_tiered_allowed))> Tiered / complex (fragile) cakes can be delivered here</label>
            <p class="hint">Turn this on only where a courier or own rider has been verified for cakes.</p>
        </fieldset>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $zone->is_active))> Active</label>
        <div class="row" style="margin-top:12px"><button class="btn btn-navy" type="submit">Save zone</button></div>
    </form>
@endsection
