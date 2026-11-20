@extends('portal.layout', ['heading' => __('Delivery addresses'), 'subheading' => __('Branches, kitchens and warehouses you deliver to. Choose one at checkout.')])

@section('portal')
    <div class="portal-grid">
        @forelse ($addresses as $address)
            <div class="card card-pad">
                <div class="row" style="justify-content:space-between"><strong>{{ $address->label }}</strong>@if ($address->is_default)<span class="status info">{{ __('Default') }}</span>@endif</div>
                <p class="small" style="margin:6px 0">{{ $address->recipient_name }} · {{ $address->phone }}<br>{{ $address->line }}, {{ $address->area }}, {{ $address->district }}</p>
                @if ($role->canPlaceOrders())
                    <details><summary class="small" style="cursor:pointer">{{ __('Edit') }}</summary>
                        @include('portal._address-form', ['address' => $address, 'action' => route($portal.'.addresses.update', $address), 'method' => 'PUT'])
                        <form method="POST" action="{{ route($portal.'.addresses.destroy', $address) }}" style="margin-top:6px">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">{{ __('Remove') }}</button></form>
                    </details>
                @endif
            </div>
        @empty
            <div class="card"><div class="empty-state"><x-icon name="truck" /><p><strong>{{ __('No saved addresses yet') }}</strong></p><p class="muted small">{{ __('Save your branches to check out faster.') }}</p></div></div>
        @endforelse
    </div>

    @if ($role->canPlaceOrders())
        <div class="card" style="margin-top:16px">
            <div class="panel-title"><h2>{{ __('Add an address') }}</h2></div>
            <div class="card-pad">@include('portal._address-form', ['address' => null, 'action' => route($portal.'.addresses.store'), 'method' => 'POST'])</div>
        </div>
    @endif
@endsection
