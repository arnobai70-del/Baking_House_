@extends('layouts.app', ['title' => 'My account'])

@section('content')
    <div class="role-head role-retail">
        <div>
            <h1>Hello, {{ $user->name }}</h1>
            <p>Customer dashboard · {{ $user->phone ?? $user->email }}</p>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="panel-title"><h2>Business &amp; reseller accounts</h2></div>
        @if ($memberships->isEmpty())
            <div class="card-pad">
                <p class="muted">Buying for a restaurant, café, bakery or shop? Apply for protected business or reseller pricing.</p>
                <div class="row">
                    <a class="btn btn-teal" href="{{ route('organizations.apply', 'business') }}">Apply for a business account</a>
                    <a class="btn btn-navy" href="{{ route('organizations.apply', 'reseller') }}">Apply as reseller</a>
                </div>
            </div>
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Organization</th><th>Type</th><th>Your role</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody>
                    @foreach ($memberships as $organization)
                        <tr>
                            <td><strong>{{ $organization->name }}</strong></td>
                            <td>{{ $organization->type->label() }}</td>
                            <td>{{ ucfirst($organization->pivot->role) }}</td>
                            <td><span class="status {{ $organization->isApproved() ? 'ok' : 'warn' }}">{{ ucfirst($organization->status->value) }}</span></td>
                            <td>
                                @if ($organization->isApproved())
                                    <form method="POST" action="{{ route('account.context') }}" style="display:inline">
                                        @csrf
                                        <input type="hidden" name="organization_id" value="{{ $organization->id }}">
                                        <button class="btn btn-outline btn-sm" type="submit">Use this account</button>
                                    </form>
                                    <a class="btn btn-primary btn-sm" href="{{ route($organization->type->value.'.dashboard') }}">Open dashboard</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="panel-title"><h2>{{ __('My orders') }}</h2></div>
        @if ($orders->isEmpty())
            <div class="empty-state"><p><strong>{{ __('No orders yet') }}</strong></p><a class="btn btn-primary" href="{{ route('catalog.shop') }}">{{ __('Start shopping') }}</a></div>
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Placed') }}</th><th>{{ __('Total') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>@foreach ($orders as $order)
                    <tr><td class="tnum"><strong>{{ $order->number }}</strong></td><td class="small">{{ $order->placed_at->timezone('Asia/Dhaka')->format('d M Y') }}</td>
                        <td class="tnum">{{ \App\Support\Taka::format($order->total_paisa) }}</td>
                        <td><span class="status {{ $order->isPaid() ? 'ok' : 'warn' }}">{{ __($order->payment_status->label()) }}</span></td>
                        <td><span class="status {{ $order->status->tone() }}">{{ __($order->status->label()) }}</span></td>
                        <td><a class="btn btn-outline btn-sm" href="{{ route('orders.show', $order) }}">{{ __('View') }}</a></td></tr>
                @endforeach</tbody></table></div>
        @endif
    </div>
@endsection
