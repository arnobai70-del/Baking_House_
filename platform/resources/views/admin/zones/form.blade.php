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
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $zone->is_active))> Active</label>
        <div class="row" style="margin-top:12px"><button class="btn btn-primary" type="submit">Save zone</button></div>
    </form>
@endsection
