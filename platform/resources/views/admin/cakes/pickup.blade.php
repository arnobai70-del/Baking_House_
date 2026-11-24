@extends('layouts.admin', ['title' => 'Pickup locations'])

@section('admin')
    <div class="admin-top"><h1>Pickup locations</h1></div>
    @include('admin.cakes._tabs')

    <div class="alert alert-info" style="margin-bottom:16px"><x-icon name="info" /><span>Customers with a custom cake in the cart can collect it from an active location instead of delivery. Pickup is not offered until a location is active.</span></div>

    <div class="card" style="margin-bottom:16px"><div class="table-wrap"><table class="table">
        <thead><tr><th>Location</th><th>Address</th><th>Hours</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($locations as $location)
            <tr>
                <td><strong>{{ $location->name }}</strong>@if ($location->phone)<div class="small muted">{{ $location->phone }}</div>@endif</td>
                <td class="small">{{ $location->address }}</td>
                <td class="small">{{ $location->hours ?? '—' }}</td>
                <td><span class="status {{ $location->is_active ? 'ok' : '' }}">{{ $location->is_active ? 'Active' : 'Off' }}</span></td>
                <td><a class="btn btn-outline btn-sm" href="{{ route('admin.cakes.pickup', ['edit' => $location->id]) }}#pickup-form">Edit</a></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty-state"><p><strong>No pickup locations</strong></p><p class="muted small">Add one to offer store pickup for cakes.</p></div></td></tr>
        @endforelse
        </tbody>
    </table></div></div>

    @php $location = $editing ?? new \App\Models\PickupLocation(['is_active' => true]); @endphp
    <form method="POST" action="{{ $location->exists ? route('admin.cakes.pickup.update', $location) : route('admin.cakes.pickup.store') }}" class="card card-pad" id="pickup-form" novalidate>
        @csrf @if ($location->exists) @method('PUT') @endif
        <h2 style="font-size:var(--bh-fs-md)">{{ $location->exists ? 'Edit: '.$location->name : 'Add a location' }}</h2>
        <div class="form-grid">
            <div class="field"><label for="name">Name</label><input class="input" id="name" name="name" value="{{ old('name', $location->name) }}" required>@error('name')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="name_bn">Name (Bangla)</label><input class="input" id="name_bn" name="name_bn" lang="bn" value="{{ old('name_bn', $location->name_bn) }}"></div>
            <div class="field full"><label for="address">Address</label><input class="input" id="address" name="address" value="{{ old('address', $location->address) }}" required>@error('address')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="field"><label for="phone">Phone</label><input class="input" id="phone" name="phone" value="{{ old('phone', $location->phone) }}"></div>
            <div class="field"><label for="hours">Opening hours</label><input class="input" id="hours" name="hours" value="{{ old('hours', $location->hours) }}" placeholder="e.g. Sat–Thu 10:00–20:00"></div>
            <div class="field"><label for="sort_order">Order</label><input class="input tnum" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $location->sort_order ?? 0) }}"></div>
        </div>
        <label class="check"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $location->is_active))> Active</label>
        <div class="row" style="margin-top:12px;gap:8px"><button class="btn btn-navy" type="submit">Save location</button>
            @if ($location->exists)<a class="btn btn-outline" href="{{ route('admin.cakes.pickup') }}">Cancel</a>@endif</div>
    </form>
@endsection
