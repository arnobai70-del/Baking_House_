@extends('layouts.app', ['title' => __('My addresses')])

@section('content')
    <nav class="crumbs" aria-label="{{ __('Breadcrumb') }}"><ol>
        <li><a href="{{ route('account') }}">{{ __('My account') }}</a></li>
        <li aria-current="page">{{ __('My addresses') }}</li>
    </ol></nav>
    <h1 style="font-size:var(--bh-fs-2xl)">{{ __('My addresses') }}</h1>
    <p class="muted">{{ __('Save the places you order to; choose one at checkout.') }}</p>

    <div class="portal-grid reveal">
        @forelse ($addresses as $address)
            <div class="card card-pad">
                <div class="row" style="justify-content:space-between"><strong>{{ $address->label }}</strong>@if ($address->is_default)<span class="status info">{{ __('Default') }}</span>@endif</div>
                <p class="small" style="margin:6px 0">{{ $address->recipient_name }} · {{ $address->phone }}<br>{{ $address->line }}, {{ $address->area }}, {{ $address->district }}</p>
                <details><summary class="small" style="cursor:pointer">{{ __('Edit') }}</summary>
                    @include('portal._address-form', ['address' => $address, 'action' => route('account.addresses.update', $address), 'method' => 'PUT'])
                    <form method="POST" action="{{ route('account.addresses.destroy', $address) }}" style="margin-top:6px">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" type="submit">{{ __('Remove') }}</button></form>
                </details>
            </div>
        @empty
            <div class="card"><div class="empty-state"><x-icon name="truck" /><p><strong>{{ __('No saved addresses yet') }}</strong></p><p class="muted small">{{ __('Add one below, or tick “Save this address” at checkout.') }}</p></div></div>
        @endforelse
    </div>

    <div class="card" style="margin-top:16px">
        <div class="panel-title"><h2>{{ __('Add an address') }}</h2></div>
        <div class="card-pad">@include('portal._address-form', ['address' => null, 'action' => route('account.addresses.store'), 'method' => 'POST'])</div>
    </div>
@endsection
